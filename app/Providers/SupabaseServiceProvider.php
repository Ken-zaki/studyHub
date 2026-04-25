<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;

/**
 * SupabaseService Provider
 *
 * This class handles all Supabase API interactions for StudyHub
 * Place this file in: studyhub/app/Providers/SupabaseServiceProvider.php
 */

class SupabaseServiceProvider
{
    private $supabaseUrl;
    private $supabaseAnonKey;
    private $supabaseServiceKey;

    public function __construct()
    {
        // Load from .env file
        $this->supabaseUrl = env('SUPABASE_URL');
        $this->supabaseAnonKey = env('SUPABASE_ANON_KEY');
        $this->supabaseServiceKey = env('SUPABASE_SERVICE_KEY');
    }

    /**
     * Sign up a new user
     *
     * @param string $email
     * @param string $password
     * @param array $metadata Additional user data (username, first_name, last_name, birthday)
     * @return array Response from Supabase
     */
    public function signUp($email, $password, $metadata = [])
    {
        $url = $this->supabaseUrl . '/auth/v1/signup';

        $data = [
            'email' => $email,
            'password' => $password,
            'data' => $metadata // User metadata
        ];

        return $this->makeRequest($url, 'POST', $this->supabaseAnonKey, $data);
    }

    /**
     * Sign in existing user
     *
     * @param string $email
     * @param string $password
     * @return array Response with access_token and user data
     */
    public function signIn($email, $password)
    {
        $url = $this->supabaseUrl . '/auth/v1/token?grant_type=password';

        $data = [
            'email' => $email,
            'password' => $password
        ];

        return $this->makeRequest($url, 'POST', $this->supabaseAnonKey, $data);
    }

    /**
     * Sign in with username (requires custom implementation)
     * First, look up email by username, then sign in
     *
     * @param string $username
     * @param string $password
     * @return array Response with access_token and user data
     */
    public function signInWithUsername($username, $password)
    {
        // First, get email from username
        $profile = $this->getProfileByUsername($username);

        if (!$profile) {
            return ['error' => 'Username not found'];
        }

        $email = $profile['email'];
        return $this->signIn($email, $password);
    }

    /**
     * Get user profile by username
     *
     * @param string $username
     * @return array|null User profile or null
     */
    public function getProfileByUsername($username)
    {
        $url = $this->supabaseUrl . '/rest/v1/profiles?username=eq.' . urlencode($username);

        $result = $this->makeRequest($url, 'GET', $this->supabaseAnonKey);

        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get all user profiles
     *
     * @return array List of profiles
     */
    public function getAllProfiles()
    {
        $url = $this->supabaseUrl . '/rest/v1/profiles?select=id,username,first_name,last_name,profile_photo_url&order=username.asc';

        $result = $this->makeRequest($url, 'GET', $this->supabaseServiceKey, null, $this->supabaseServiceKey);

        return is_array($result) && array_is_list($result) ? $result : [];
    }

    /**
     * Get users from Supabase Auth admin API
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllAuthUsers(): array
    {
        $url = $this->supabaseUrl . '/auth/v1/admin/users?page=1&per_page=1000';

        $result = $this->makeRequest($url, 'GET', $this->supabaseServiceKey, null, $this->supabaseServiceKey);

        if (!is_array($result)) {
            return [];
        }

        $users = $result['users'] ?? [];

        return is_array($users) && array_is_list($users) ? $users : [];
    }

    /**
     * Create user profile (after signup)
     *
     * @param string $userId UUID from auth.users
     * @param array $profileData username, first_name, last_name, birthday, email
     * @return array Response from Supabase
     */
    public function createProfile($userId, $profileData)
    {
        $url = $this->supabaseUrl . '/rest/v1/profiles';

        $data = array_merge(
            ['id' => $userId],
            $profileData
        );

        return $this->makeRequest($url, 'POST', $this->supabaseServiceKey, $data);
    }

    /**
     * Send password reset email
     *
     * @param string $email
     * @return array Response from Supabase
     */
    public function sendPasswordResetEmail($email)
    {
        $url = $this->supabaseUrl . '/auth/v1/recover';

        $data = ['email' => $email];

        return $this->makeRequest($url, 'POST', $this->supabaseAnonKey, $data);
    }

    /**
     * Update user password
     *
     * @param string $accessToken User's JWT token
     * @param string $newPassword
     * @return array Response from Supabase
     */
    public function updatePassword($accessToken, $newPassword)
    {
        $url = $this->supabaseUrl . '/auth/v1/user';

        $data = ['password' => $newPassword];

        return $this->makeRequest($url, 'PUT', $this->supabaseAnonKey, $data, $accessToken);
    }

    /**
     * Get current user from access token
     *
     * @param string $accessToken JWT token
     * @return array User data
     */
    public function getUser($accessToken)
    {
        $url = $this->supabaseUrl . '/auth/v1/user';

        return $this->makeRequest($url, 'GET', $this->supabaseAnonKey, null, $accessToken);
    }

    /**
     * Sign out user
     *
     * @param string $accessToken JWT token
     * @return array Response
     */
    public function signOut($accessToken)
    {
        $url = $this->supabaseUrl . '/auth/v1/logout';

        return $this->makeRequest($url, 'POST', $this->supabaseAnonKey, null, $accessToken);
    }

    /**
     * Make HTTP request to Supabase API
     *
     * @param string $url API endpoint
     * @param string $method HTTP method
     * @param string $apiKey Supabase API key (REQUIRED)
     * @param array|null $data Request body (optional)
     * @param string|null $accessToken User JWT token (optional)
     * @return array Response data
     */
    private function makeRequest($url, $method, $apiKey, $data = null, $accessToken = null)
    {
        $headers = [
            'apikey' => $apiKey,
            'Content-Type' => 'application/json',
        ];

        if ($accessToken) {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }

        try {
            $request = Http::withHeaders($headers)->timeout(20);

            $response = match (strtoupper((string) $method)) {
                'GET' => $request->get($url),
                'POST' => $request->post($url, $data ?? []),
                'PUT' => $request->put($url, $data ?? []),
                'PATCH' => $request->patch($url, $data ?? []),
                'DELETE' => $request->delete($url, $data ?? []),
                default => $request->send($method, $url, [
                    'json' => $data ?? [],
                ]),
            };

            $result = $response->json();

            if (!$response->successful()) {
                return [
                    'error' => true,
                    'status' => $response->status(),
                    'message' => $result['message'] ?? 'Request failed',
                ];
            }

            return $result;
        } catch (\Throwable $exception) {
            return [
                'error' => true,
                'status' => 0,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Upload file to Supabase Storage
     *
     * @param string $bucket Bucket name (profile-photos, study-resources, group-files)
     * @param string $filePath Local file path
     * @param string $fileName Name to save as in storage
     * @return array Response with file URL
     */
    public function uploadFile($bucket, $filePath, $fileName)
    {
        $url = $this->supabaseUrl . '/storage/v1/object/' . $bucket . '/' . $fileName;

        $fileData = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);

        $ch = curl_init();

        $headers = [
            'apikey: ' . $this->supabaseServiceKey,
            'Content-Type: ' . $mimeType,
            'Authorization: Bearer ' . $this->supabaseServiceKey
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * Get public URL for uploaded file
     *
     * @param string $bucket Bucket name
     * @param string $fileName File name in storage
     * @return string Public URL
     */
    public function getPublicUrl($bucket, $fileName)
    {
        return $this->supabaseUrl . '/storage/v1/object/public/' . $bucket . '/' . $fileName;
    }
}
