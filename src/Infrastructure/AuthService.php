<?php
declare(strict_types=1);

namespace App\Infrastructure;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class AuthService {
    public function __construct(
        private array $cfg,
        private DBManager $db,
        private LoadSql $loadSql
    ) {}

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

    // Validates a token
    public function validateToken(string $token): ?object {
        try {
            $payload = JWT::decode($token, new Key($this->cfg['auth']['jwt_secret'], 'HS256'));
            
            // Validate issuer and audience
            if (($payload->iss ?? null) !== $this->cfg['auth']['issuer']) {
                return null;
            }
            if (($payload->aud ?? null) !== $this->cfg['auth']['audience']) {
                return null;
            }
            
            // Check if token is revoked
            if ($this->isTokenRevoked($payload->sid ?? '')) {
                return null;
            }
            
            return $payload;
        } 
        catch (\Throwable $e) {
            return null;
        }
    }

    // Add token to blacklist table
    public function revokeAccessToken(string $token): bool {
        try {
            $payload = JWT::decode($token, new Key($this->cfg['auth']['jwt_secret'], 'HS256'));
            
            // Validate issuer
            if (($payload->iss ?? null) !== $this->cfg['auth']['issuer']) {
                return false;
            }
            
            // Extract token ID and expiration
            $jti = $payload->sid ?? null;
            $exp = $payload->exp ?? null;
            
            if (!$jti || !$exp) {
                return false;
            }
            
            // Add to revoked tokens table
            $sql = $this->loadSql->load('queries/token_queries/revoke_token.sql');
            $this->db->exec($sql, [
                'jti' => $jti,
                'expires_at' => $exp
            ]);
            
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // Checks for tokens in the blacklist
    private function isTokenRevoked(string $jti): bool {
        if (empty($jti)) {
            return true;
        }
        
        try {
            $sql = $this->loadSql->load('queries/token_queries/check_revoked_token.sql');
            $result = $this->db->rows($sql, ['jti' => $jti]);
            
            return !empty($result) && ($result[0]['is_revoked'] ?? 0) > 0;
        } 
        catch (\Throwable $e) {
            // If we can't check, assume it's revoked for security
            return true;
        }
    }

   // Clean up expired tokens (I'll need to figure out a better solution for this later)
    public function cleanupExpiredTokens(): int {
        try {
            $sql = $this->loadSql->load('queries/token_queries/cleanup_expired_tokens.sql');
            return $this->db->exec($sql);
        } catch (\Throwable $e) {
            return 0;
        }
    }

       
}
