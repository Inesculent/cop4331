DELETE FROM revoked_tokens 
WHERE expires_at <= NOW();
