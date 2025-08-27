<?php

class UserRepo{
    public function __construct(private DB $db, private loadSql $sql){}

    public function createUser(string $username, string $hashed_pw, string $email): int{

        $path = 'queries/user_queries/create_user.sql';
        $sql = $this->sql->load(rpath: $path);
        $id = $this->db->insert($sql, [

            'name' => $username,
            'email' => $email,
            'hashed_pw' => $hashed_pw
        ]);

        return (int)$id;

    }

    public function readUser(int $uid): ?array{
        $path = 'queries/user_queries/read_user.sql';
        $rows = $this->db->rows(

            $this->sql->load(rpath: $path),
            ['id' => $uid]
        );
        return $rows[0] ?? null;
    }

    /*
     (Example of method for custom updateUser)
        curl -X PATCH http://localhost/users/1 \
        -H "Content-Type: application/json" \
        -d '{"email":"new@example.com"}'
     */
    public function updateUser(int $uid, array $patch) :int{
        $path = 'queries/user_queries/update_user.sql';

        $set = [];
        $params = [':uid' => $uid];

        if (array_key_exists('name', $patch)){
            $set [] = 'name = :name';
            $params ['name'] = $patch['name'];
        }
        if (array_key_exists('email', $patch)){
            $set [] = 'email = :email';
            $params ['email'] = $patch['email'];
        }

        //Change password hashing alg dynamically in config later
        if (array_key_exists('password', $patch)){
            $set [] = 'hashed_pw = :hashed_pw';
            $params ['hashed_pw'] = password_hash(['hashed_pw'], PASSWORD_BCRYPT);
        }

        if (empty($set)){
            return 0;
        }

        $sql = $this->sql->load(rpath: $path);

        $sql = str_replace('/*SET_CLAUSE*/', implode(', ', $set), $sql);
        return $this->db->exec($sql, $params);

    }

    public function deleteUser(int $uid) :int{

        $path = 'queries/user_queries/delete_user.sql';
        return $this->db->exec(
            $this->sql->load(rpath: $path),
            ['uid' => $uid]
        );
    }
}