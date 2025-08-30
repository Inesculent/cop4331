<?php
declare(strict_types=1);

namespace App\Infrastructure;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class AuthMiddleware {
    public function __construct(private array $cfg) {}

    public function authenticate(): ?int {
        $token = $this->extractToken();
        if (!$token) return null;

        try {
            $decoded = JWT::decode($token, new Key($this->cfg['auth']['jwt_secret'], 'HS256'));
            if (($decoded->iss ?? null) !== $this->cfg['auth']['issuer']) return null;
            if (($decoded->aud ?? null) !== $this->cfg['auth']['audience']) return null;
            return (int)($decoded->sub ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractToken(): ?string {
        if (!empty($_COOKIE['auth'])) return $_COOKIE['auth'];
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $hdr, $m)) return $m[1];
        return null;
    }
}
