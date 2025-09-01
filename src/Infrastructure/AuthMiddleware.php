<?php
declare(strict_types=1);

namespace App\Infrastructure;

// Middleware for JWT authentication
final class AuthMiddleware {
    public function __construct(
        private array $cfg,
        private AuthService $authService
    ) {}

    public function authenticate(): ?int {
        $token = $this->extractToken();
        if (!$token) return null;

        $payload = $this->authService->validateToken($token);
        if (!$payload) return null;
        
        return (int)($payload->sub ?? 0);
    }

    private function extractToken(): ?string {
        if (!empty($_COOKIE['auth'])) return $_COOKIE['auth'];
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $hdr, $m)) return $m[1];
        return null;
    }
}
