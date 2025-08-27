UPDATE contacts
SET name = :name, phone = :phone, email = :email
WHERE cid = :cid AND uid = :uid;