SELECT COUNT(*) as is_revoked 
FROM revoked_tokens 
WHERE jti = :jti AND expires_at > NOW();
