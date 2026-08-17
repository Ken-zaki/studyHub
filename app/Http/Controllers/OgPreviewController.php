<?php
// app/Http/Controllers/OgPreviewController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class OgPreviewController extends Controller
{
    public function fetch(Request $request)
    {
        $url = $request->query('url');

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Invalid URL'], 422);
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; StudyHubBot/1.0)'])
                ->get($url);

            $html  = $response->body();
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

            // Make relative image URLs absolute
            if ($image && !preg_match('/^https?:\/\//', $image)) {
                $parsed = parse_url($url);
                $base   = $parsed['scheme'] . '://' . $parsed['host'];
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

    private function extractMeta(string $html, string $property): ?string
    {
        // og: / twitter: properties
        if (preg_match(
            '/<meta[^>]+(?:property|name)=["\']' . preg_quote($property, '/') . '["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i',
            $html, $m
        )) return trim($m[1]);

        // reversed attribute order
        if (preg_match(
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\']' . preg_quote($property, '/') . '["\'][^>]*>/i',
            $html, $m
        )) return trim($m[1]);

        return null;
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m))
            return trim($m[1]);
        return null;
    }
}
