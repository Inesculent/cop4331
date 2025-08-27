CREATE TABLE IF NOT EXISTS contacts (
    cid INT NOT NULL AUTO_INCREMENT,
    uid INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(32),
    email VARCHAR(255),
    PRIMARY KEY (cid),

    FOREIGN KEY (uid) REFERENCES users(uid),

    -- If a user is deleted, or their uid changes somehow, then update contacts to match
    CONSTRAINT alter_user
        FOREIGN KEY (uid) REFERENCES users(uid)
            ON DELETE CASCADE
            ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;