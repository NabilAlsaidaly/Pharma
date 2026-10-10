<?php

namespace App\Domains\Identity\Interfaces\Http;

use Illuminate\Contracts\Config\Repository;
use LogicException;

class FrontendRedirect
{
    public function __construct(private readonly Repository $config) {}

    public function safePath(mixed $path): string
    {
        if (! is_string($path) || strlen($path) > 2048) {
            return '/';
        }

        $decoded = $path;
        for ($i = 0; $i < 5; $i++) {
            if (! str_starts_with($decoded, '/') || str_starts_with($decoded, '//')
                || str_contains($decoded, '\\') || preg_match('/[\x00-\x20\x7f]/', $decoded)) {
                return '/';
            }
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                return $path;
            }
            $decoded = $next;
        }

        return '/';
    }

    public function destination(mixed $path): string
    {
        $origin = rtrim((string) ($this->config->get('identity.frontend_url') ?: $this->config->get('app.url')), '/');
        $parts = parse_url($origin);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || ! empty($parts['path'])) {
            throw new LogicException('A valid frontend origin must be configured.');
        }

        return $origin.$this->safePath($path);
    }
}
