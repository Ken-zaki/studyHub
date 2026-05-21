<?php

namespace App\Providers;

class SupabaseServiceProvider
{
    private string $supabaseUrl;
    private string $supabaseAnonKey;
    private string $supabaseServiceKey;

    public function __construct()
    {
        $this->supabaseUrl        = (string) env('SUPABASE_URL', '');
        $this->supabaseAnonKey    = (string) env('SUPABASE_ANON_KEY', '');
        $this->supabaseServiceKey = (string) env('SUPABASE_SERVICE_KEY', '');
    }

    // ──────────────────────────────────────────────────────────────
    // AUTH  (anon key, no service key needed)
    // ──────────────────────────────────────────────────────────────

    public function signUp(string $email, string $password, array $metadata = []): array
    {
        return $this->request('POST', '/auth/v1/signup', [
            'email'    => $email,
            'password' => $password,
            'data'     => $metadata,
        ], useServiceKey: false);
    }

    public function signIn(string $email, string $password): array
    {
        return $this->request('POST', '/auth/v1/token?grant_type=password', [
            'email'    => $email,
            'password' => $password,
        ], useServiceKey: false);
    }

    public function signInWithUsername(string $username, string $password): array
    {
        $profile = $this->getProfileByUsername($username);
        if (!$profile || empty($profile['email'])) {
            return ['error' => 'Username not found'];
        }
        return $this->signIn((string) $profile['email'], $password);
    }

    public function sendPasswordResetEmail(string $email): array
    {
        return $this->request('POST', '/auth/v1/recover', ['email' => $email], useServiceKey: false);
    }

    public function updatePassword(string $accessToken, string $newPassword): array
    {
        return $this->request('PUT', '/auth/v1/user', ['password' => $newPassword],
            useServiceKey: false, bearerOverride: $accessToken);
    }

    public function getUser(string $accessToken): array
    {
        return $this->request('GET', '/auth/v1/user', null,
            useServiceKey: false, bearerOverride: $accessToken);
    }

    public function signOut(string $accessToken): array
    {
        return $this->request('POST', '/auth/v1/logout', null,
            useServiceKey: false, bearerOverride: $accessToken);
    }

    // ──────────────────────────────────────────────────────────────
    // PROFILE READS  (service key — bypasses RLS completely)
    // ──────────────────────────────────────────────────────────────

    public function getAllProfiles(): array
    {
        $result = $this->request('GET', '/rest/v1/profiles?select=*', null, useServiceKey: true);
        return isset($result['error']) ? [] : $result;
    }

    public function getProfileById(string $userId): ?array
    {
        $result = $this->request(
            'GET',
            '/rest/v1/profiles?id=eq.' . urlencode($userId) . '&limit=1',
            null,
            useServiceKey: true
        );
        return !empty($result) && !isset($result['error']) ? $result[0] : null;
    }

    public function getProfilesByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $encodedIds = array_map('urlencode', $ids);

        $result = $this->request(
            'GET',
            '/rest/v1/profiles?id=in.(' . implode(',', $encodedIds) . ')',
            null,
            useServiceKey: true
        );

        return isset($result['error']) ? [] : $result;
    }

    public function getProfileByUsername(string $username): ?array
    {
        $result = $this->request(
            'GET',
            '/rest/v1/profiles?username=eq.' . urlencode($username) . '&limit=1',
            null,
            useServiceKey: true
        );
        return !empty($result) && !isset($result['error']) ? $result[0] : null;
    }

    public function getProfileByEmail(string $email): ?array
    {
        $result = $this->request(
            'GET',
            '/rest/v1/profiles?email=eq.' . urlencode($email) . '&limit=1',
            null,
            useServiceKey: true
        );
        return !empty($result) && !isset($result['error']) ? $result[0] : null;
    }

    // ──────────────────────────────────────────────────────────────
    // PROFILE WRITES  (service key)
    // ──────────────────────────────────────────────────────────────

    public function createProfile(string $userId, array $profileData): array
    {
        return $this->request('POST', '/rest/v1/profiles',
            array_merge(['id' => $userId], $profileData), useServiceKey: true);
    }

    public function updateProfilePhoto(string $userId, string $profilePhotoUrl): array
    {
        return $this->request(
            'PATCH',
            '/rest/v1/profiles?id=eq.' . urlencode($userId),
            ['profile_photo_url' => $profilePhotoUrl],
            useServiceKey: true
        );
    }

    // ──────────────────────────────────────────────────────────────
    // GENERIC TABLE HELPERS
    // ──────────────────────────────────────────────────────────────

    public function queryTable(string $table, array $queryParams = [], bool $useServiceKey = false): array
    {
        $qs     = http_build_query($queryParams);
        $path   = '/rest/v1/' . $table . ($qs !== '' ? '?' . $qs : '');
        $result = $this->request('GET', $path, null, useServiceKey: $useServiceKey);
        return !isset($result['error']) ? $result : [];
    }

    public function countTableRows(string $table, array $queryParams = [], bool $useServiceKey = false): int
    {
        return count($this->queryTable($table, array_merge(['select' => 'id'], $queryParams), $useServiceKey));
    }

    // ──────────────────────────────────────────────────────────────
    // STORAGE
    // ──────────────────────────────────────────────────────────────

    public function uploadFile(string $bucket, string $filePath, string $fileName): array
    {
        $url      = $this->supabaseUrl . '/storage/v1/object/' . $bucket . '/' . $fileName;
        $fileData = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $fileData,
            CURLOPT_HTTPHEADER     => [
                'apikey: '               . $this->supabaseServiceKey,
                'Authorization: Bearer ' . $this->supabaseServiceKey,
                'Content-Type: '         . $mimeType,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return (array) json_decode($response, true);
    }

    public function getPublicUrl(string $bucket, string $fileName): string
    {
        return $this->supabaseUrl . '/storage/v1/object/public/' . $bucket . '/' . $fileName;
    }

    // ──────────────────────────────────────────────────────────────
    // CORE HTTP  — single place where all headers are assembled
    // ──────────────────────────────────────────────────────────────

    /**
     * @param string      $method         GET | POST | PUT | PATCH | DELETE
     * @param string      $path           URL path + query string (no base URL)
     * @param array|null  $body           JSON body for POST/PUT/PATCH
     * @param bool        $useServiceKey  true  → service key as apikey AND bearer (bypasses RLS)
     *                                   false → anon key as apikey, no bearer unless $bearerOverride
     * @param string|null $bearerOverride Explicit bearer token (e.g. user JWT for auth endpoints)
     */
    private function request(
        string  $method,
        string  $path,
        ?array  $body          = null,
        bool    $useServiceKey = false,
        ?string $bearerOverride = null
    ): array {
        $url    = $this->supabaseUrl . $path;
        $apiKey = $useServiceKey ? $this->supabaseServiceKey : $this->supabaseAnonKey;

        // Bearer token priority:
        //   1. explicit override (user JWT passed by caller)
        //   2. service key when useServiceKey = true  ← THIS is what was missing before
        //   3. nothing (anon / public endpoints)
        $bearer = $bearerOverride ?? ($useServiceKey ? $this->supabaseServiceKey : null);

        $headers = [
            'apikey: '        . $apiKey,
            'Content-Type: application/json',
        ];

        if ($bearer !== null && $bearer !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 10,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            \Log::error('Supabase cURL error', ['path' => $path, 'error' => $curlError]);
            return ['error' => true, 'message' => 'Network error: ' . $curlError];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            \Log::warning('Supabase HTTP ' . $httpCode, [
                'path'    => $path,
                'response'=> $response,
            ]);
            return [
                'error'   => true,
                'status'  => $httpCode,
                'message' => is_array($decoded)
                    ? ($decoded['message'] ?? $decoded['error_description'] ?? 'Request failed')
                    : 'Request failed',
            ];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
