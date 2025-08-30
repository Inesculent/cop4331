SELECT
    uid AS id,
    name,
    email,
    hashed_pw
FROM users
WHERE uid = :id;
