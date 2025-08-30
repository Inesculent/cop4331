<?php
declare(strict_types=1);

namespace App\Infrastructure;

use Firebase\JWT\JWT;

final class AuthService {
    public function __construct(private array $cfg) {}

    public function issueAccessToken(int $uid): string {
        $now = time();
        $exp = $now + (int)$this->cfg['auth']['access_ttl'];

        $payload = [
            'iss' => $this->cfg['auth']['issuer'],
            'aud' => $this->cfg['auth']['audience'],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $exp,
            'sub' => (string)$uid,
            'sid' => bin2hex(random_bytes(8)),
        ];

        return JWT::encode($payload, $this->cfg['auth']['jwt_secret'], 'HS256');
    }
}
