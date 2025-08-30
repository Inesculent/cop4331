SELECT uid
FROM users
WHERE email = :email
    LIMIT 1;
