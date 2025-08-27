SELECT uid, name, email, hashed_pw
FROM users
WHERE email = :email;