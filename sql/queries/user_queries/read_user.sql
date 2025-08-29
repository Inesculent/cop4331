SELECT
    uid AS id,
    name,
    email,
    hashed_pw,
    created_at
FROM users
WHERE uid = :id;
