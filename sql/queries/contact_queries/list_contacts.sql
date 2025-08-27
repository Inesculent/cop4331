SELECT uid, cid, name, phone, email
FROM contacts
WHERE uid = :uid
ORDER BY name DESC;