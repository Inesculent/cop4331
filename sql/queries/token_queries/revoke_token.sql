INSERT INTO revoked_tokens (jti, expires_at) 
VALUES (:jti, FROM_UNIXTIME(:expires_at))
ON DUPLICATE KEY UPDATE revoked_at = CURRENT_TIMESTAMP;
