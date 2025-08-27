SELECT uid, cid, name, phone, email
FROM contacts
WHERE cid = :cid AND uid = :uid;