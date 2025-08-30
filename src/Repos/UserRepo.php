<?php

namespace App\Repos;
use App\Infrastructure\LoadSql;
use App\Infrastructure\Result;
use App\Infrastructure\DBManager;
class UserRepo{
    public function __construct(
        private readonly DBManager $db,
        private readonly LoadSql $sql
    ) {}

    public function createUser(string $name, string $email, string $password): Result
    {
        // Normalize
        $name = trim($name);
        $email = strtolower(trim($email));

        // Data validation

        // Name less than 255 chars
        if ($name === '' || mb_strlen($name, 'UTF-8') >= 255) {
            return Result::err('INVALID_NAME', 'Name is required and must be < 255 chars', ['field' => 'name']);
        }

        // Email less than 255 chars or does not contain @
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') >= 255) {
            return Result::err('INVALID_EMAIL', 'Email format invalid or too long', ['field' => 'email']);
        }

        // Password between 8 and 255 chars
        if ($password === '' || mb_strlen($password, 'UTF-8') < 8) {
            return Result::err('INVALID_PASSWORD', 'Password must be at least 8 characters', ['field' => 'password']);
        }
        if (mb_strlen($password, 'UTF-8') >= 255) {
            return Result::err('INVALID_PASSWORD', 'Password must be < 255 characters', ['field' => 'password']);
        }

        $path = 'queries/user_queries/create_user.sql';
        $sql = $this->sql->load($path);
        $params = [
            'name' => $name,
            'email' => $email,
            'hashed_pw' => password_hash($password, PASSWORD_BCRYPT),
        ];

        // Try to execute
        try {
            $id = $this->db->insert($sql, $params);
            return Result::ok(['id' => (int)$id]);  // controller will send 201
        }
        catch (\PDOException $e) {
            // MySQL duplicate key -> SQLSTATE 23000 + error 1062
            $sqlState   = $e->errorInfo[0] ?? null;
            $driverCode = $e->errorInfo[1] ?? null;

            if ($sqlState === '23000' && $driverCode === 1062) {
                return Result::err('DUPLICATE_EMAIL', 'Email already registered', ['field' => 'email']);
            }

            // Log server-side; return generic error to client
            error_log('createUser DB error: ' . $e->getMessage());
            return Result::err('DB_ERROR', 'Database failure');
        }
    }

    public function readUser(int $uid): Result{
        $path = 'queries/user_queries/read_user.sql';
        $sql = $this->sql->load($path);

        try {
            $rows = $this->db->rows($sql, ['id' => $uid]);
            if (empty($rows)) {
                return Result::err('USER_NOT_FOUND', 'User not found', ['field' => 'uid']);
            }

            $user = $rows[0];

            return Result::ok(['user' => $user]);
        }
        catch (\PDOException $e) {
            return Result::err('DB_ERROR', 'Database failure');
        }

    }

    private function readPassword(int $uid): Result{
        $path = 'queries/user_queries/read_password.sql';
        $sql = $this->sql->load($path);

        try {
            $rows = $this->db->rows($sql, ['id' => $uid]);
            if (empty($rows)) {
                return Result::err('USER_NOT_FOUND', 'User not found', ['field' => 'uid']);
            }

            $user = $rows[0];

            return Result::ok(['user' => $user]);
        }
        catch (\PDOException $e) {
            return Result::err('DB_ERROR', 'Database failure');
        }

    }

    /*
     (Example of method for custom updateUser)
        curl -X PATCH http://localhost/users/1 \
        -H "Content-Type: application/json" \
        -d '{"email":"new@example.com"}'
     */
    public function updateUser(int $uid, array $patch): Result{

        // Validate data from the frontend
        $allowed = ['name', 'email', 'password'];
        $patch = array_intersect_key($patch, array_flip($allowed));

        if (isset($patch['name']) && mb_strlen($patch['name']) >= 255) {
            return Result::err('INVALID_NAME', 'Name is too long', ['field' => 'name']);
        }
        if (isset($patch['email'])) {
            if (mb_strlen($patch['email']) >= 255) {
                return Result::err('INVALID_EMAIL', 'Email address is too long', ['field' => 'email']);
            }
            if (!filter_var($patch['email'], FILTER_VALIDATE_EMAIL)) {
                return Result::err('INVALID_EMAIL', 'Email address is not valid', ['field' => 'email']);
            }
        }

        // Password requirements (same as add user)
        if (isset($patch['password']) && $patch['password'] === '' || mb_strlen($patch['password'], 'UTF-8') < 8) {
            return Result::err('INVALID_PASSWORD', 'Password must be at least 8 characters', ['field' => 'password']);
        }
        if (mb_strlen($patch['password'], 'UTF-8') >= 255) {
            return Result::err('INVALID_PASSWORD', 'Password must be < 255 characters', ['field' => 'password']);
        }


        // Build set based on our params
        $set = [];
        $params = ['uid' => $uid];

        if (array_key_exists('name', $patch)) {
            $set[] = 'name = :name';
            $params['name'] = $patch['name'];
        }
        if (array_key_exists('email', $patch)) {
            $set[] = 'email = :email';
            $params['email'] = $patch['email'];
        }
        if (array_key_exists('password', $patch)) {
            $set[] = 'hashed_pw = :hashed_pw';
            $params['hashed_pw'] = password_hash($patch['password'], PASSWORD_BCRYPT);
        }

        // If there's nothing to update
        if (empty($set)) {
            return Result::err('INVALID_PARAMETERS', 'No parameters passed.', ['field' => 'parameters']);
        }

        $path = 'queries/user_queries/update_user.sql';
        $sql = $this->sql->load($path); // expects: UPDATE users SET /*SET_CLAUSE*/ WHERE id = :uid;
        $sql = str_replace('/*SET_CLAUSE*/', implode(', ', $set), $sql);

        //Try to update it
        try {
            $rows = $this->db->exec($sql, $params);
            if ($rows === 0) {
                return Result::err('FAILED_TO_SAVE', 'Database error', ['field' => 'error']);
            }
            return Result::ok(['id' => $uid]);
        }
        catch (\PDOException $e) {
            error_log("updateUser failed: " . $e->getMessage());
            // If the email already exists elsewhere (conflict!)
            if ($this->isSQLDupe($e, 'uq_users_email') || $this->isSQLDupe($e, 'email')) {
                return Result::err('DUPLICATE_EMAIL', 'Email already registered', ['field' => 'email']);
            }
            return Result::err('DB_ERROR', 'Database failure');
        }
    }


    // Function to delete a user
    public function deleteUser(int $uid) : Result{

        // Define our path
        $path = 'queries/user_queries/delete_user.sql';
        $sql = $this->sql->load($path);

        // Try to delete based on UID
        try {
            $rows = $this->db->exec($sql, ['id' => $uid]);
            // If unable to find the ID
            if ($rows === 0) {
                return Result::err('FAILED_TO_DELETE', 'No user found!', ['field' => 'error']);
            }

            // Otherwise we're good
            return Result::ok(['id' => $uid, 'deleted' => 1]);
        }
        catch (\PDOException $e) {

            // TO DO: add more robust error handling here (have to look at cascade dependents)
            return Result::err('DB_ERROR', 'Database failure');
        }

    }


    // Takes in raw email and raw password and authenticates a user
    public function verifyUser(String $email, String $password) : Result{

        $email = trim(strtolower($email));

        if ($email === '' || $password === '') {
            return Result::err('INVALID_PARAMETERS', 'No parameters passed.', ['field' => 'parameters']);
        }

        $uid = $this->getUID($email);

        if ($uid <= 0){
            return Result::err('INVALID_CREDENTIALS', 'Invalid email or password', ['field' => 'uid']);
        }

        $res = $this->readPassword($uid);

        //If we had an issue with finding the user
        if (!$res->ok) {
            if ($res->code === 'USER_NOT_FOUND'){
                return Result::err('INVALID_CREDENTIALS', 'Invalid email or password', ['field' => 'uid']);
            }
            return $res;
        }

        //Create the user object from res
        $user = $res->data['user'] ?? [];

        // Use built in php password_verify to verify the passwords
        if (!password_verify($password, $user['hashed_pw'])) {
            return Result::err('INVALID_PASSWORD', 'Invalid email or password', ['field' => 'uid']);
        }

        unset($user['hashed_pw']);

        return Result::ok(['user' => $user]);

    }

    private function getUID(string $email): int
    {


        $path = 'queries/user_queries/get_uid_by_email.sql';
        $sql  = $this->sql->load($path);

        try {
            // Prefer a single-row helper if you have it; otherwise take first row.
            $rowset = $this->db->rows($sql, ['email' => $email]);
            if (empty($rowset)) {
                return 0;
            }
            // Expect column name 'uid'
            return (int)($rowset[0]['uid'] ?? 0);
        } catch (\PDOException $e) {
            error_log('getUID DB error: ' . $e->getMessage());
            return 0;
        }
    }

    // See if the email already exists (can't make duplicate users)
    private function isSQLDupe(\PDOException $e, ?string $keyName = null): bool
    {
        // errorInfo = [sqlstate, driver_error_code, driver_message]
        if (($e->errorInfo[0] ?? '') !== '23000') return false;
        if ($keyName === null) return true;

        $msg = strtolower($e->errorInfo[2] ?? $e->getMessage());
        return str_contains($msg, strtolower($keyName));
    }
}