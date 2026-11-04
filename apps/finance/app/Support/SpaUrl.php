<?php

namespace App\Support;

class SpaUrl
{
    public function to(string $path = '', array $query = []): string
    {
        $baseUrl = rtrim((string) config('app.spa_url'), '/');
        $normalizedPath = trim($path, '/');
        $url = $normalizedPath === ''
            ? $baseUrl
            : $baseUrl.'/'.$normalizedPath;

        if ($query === []) {
            return $url;
        }

        return $url.'?'.http_build_query($query);
    }
}
