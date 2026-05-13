<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsfeedController extends Controller
{
    // ── Supabase helpers ─────────────────────────────────────────

    private function supabaseUrl(): string
    {
        return rtrim(env('SUPABASE_URL'), '/');
    }

    private function supabaseServiceKey(): string
    {
        return env('SUPABASE_SERVICE_KEY');
    }

    /**
     * Query Supabase REST API using the SERVICE KEY (bypasses RLS).
     */
    private function supabaseQuery(string $table, array $params = []): array
    {
        $url = $this->supabaseUrl() . '/rest/v1/' . $table;

        $response = Http::withoutVerifying()
            ->withHeaders([
                'apikey'        => $this->supabaseServiceKey(),
                'Authorization' => 'Bearer ' . $this->supabaseServiceKey(),
                'Content-Type'  => 'application/json',
            ])->get($url, $params);

        if ($response->failed()) {
            Log::error('[Supabase] Query failed', [
                'table'  => $table,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    // ── Newsfeed page ─────────────────────────────────────────────

    public function index(Request $request)
    {
        // Friends tab removed — no need to resolve friend IDs server-side.
        // The following tab still works via client-side followingSet (anon key, open SELECT).
        return view('home.newsfeed');
    }

    // ── OG Preview ────────────────────────────────────────────────

    public function ogPreview(Request $request)
    {
        $url = $request->query('url');

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Invalid URL'], 422);
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; StudyHubBot/1.0)',
                ])
                ->get($url);

            $html = $response->body();

            $title = $this->extractMeta($html, 'og:title')
                  ?: $this->extractMeta($html, 'twitter:title')
                  ?: $this->extractTitle($html)
                  ?: $url;

            $image = $this->extractMeta($html, 'og:image')
                  ?: $this->extractMeta($html, 'twitter:image')
                  ?: null;

            $description = $this->extractMeta($html, 'og:description')
                        ?: $this->extractMeta($html, 'description')
                        ?: null;

            if ($image && !preg_match('/^https?:\/\//', $image)) {
                $parsed = parse_url($url);
                $base   = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
                $image  = $base . '/' . ltrim($image, '/');
            }

            return response()->json([
                'url'         => $url,
                'title'       => $title,
                'image'       => $image,
                'description' => $description,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── OG helpers ────────────────────────────────────────────────

    private function extractMeta(string $html, string $property): ?string
    {
        if (preg_match(
            '/<meta[^>]+(?:property|name)=["\']'
            . preg_quote($property, '/')
            . '["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            $html, $m
        )) {
            return trim($m[1]);
        }

        if (preg_match(
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']'
            . preg_quote($property, '/')
            . '["\'][^>]*>/i',
            $html, $m
        )) {
            return trim($m[1]);
        }

        return null;
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}