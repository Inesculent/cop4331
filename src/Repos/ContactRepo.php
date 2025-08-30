<?php


namespace App\Repos;

use App\Infrastructure\DBManager;
use App\Infrastructure\LoadSql;
use App\Infrastructure\Result;
class ContactRepo{

    public function __construct(
        private readonly DBManager $db,
        private readonly LoadSql $sql
    ) {}


    // Need to rewrite this to handle dynamic information in the email
    public function createContact(int $uid, string $name, string $phone, string $email): Result{

        if ($name === '' || mb_strlen($name) >= 255){
            return Result::err('INVALID_NAME', "Name is invalid.");
        }

        if ($phone === '' && $email === '') {
            return Result::err('NOT_ENOUGH_ARGUMENTS', 'Include a phone number OR an email address');
        }
        // Error handling for improperly formatted data
        if (mb_strlen($phone) >=32){
            return Result::err('INVALID_PHONE', 'Phone number is invalid', ['field' => 'phone']);
        }

        // We can easily add the same constraints as UserRepo to force email to have @ and whatnot, but probably not necessary for contacts.
        if (mb_strlen($email) >=255){
            return Result::err('INVALID_EMAIL', 'Email address is invalid', ['field' => 'email']);
        }

        $path = 'queries/contact_queries/create_contact';
        $sql = $this->sql->load($path);

        $params = [
            'uid' => $uid,
            'name' => $name,
            'phone' => $phone,
            'email' => $email
        ];

        // Try to create the contact
        try{
            $cid = $this->db->insert($sql, $params);
            return Result::ok(['cid' => (int)$cid]);
        }
        catch (\PDOException $e){
            $sqlState = $e->errorInfo[0] ?? null;
            $driverCode = $e->errorInfo[1] ?? null;
            $msg = strtolower($e->errorInfo[2] ?? $e->getMessage());

            error_log('createContact DB error: ' . $e->getMessage());
            return Result::err('DB_ERROR', 'Database failure');
        }
    }


    public function readContact(int $cid, int $uid) : Result{


        $path = 'queries/contact_queries/read_contact';
        $sql = $this->sql->load($path);

        $params = [
            'cid' => $cid,
            'uid' => $uid
        ];

        try{

            $rows = $this->db->rows($sql, $params);

            $contact = $rows[0];
            return Result::ok($contact);
        }
        catch (\PDOException $e){
            return Result::err('DB_ERROR', 'Database failure');
        }

    }

    public function listContacts(): Result{

        $path = 'queries/contact_queries/list_contacts';
        return Result::err('NOT_IMPLEMENTED','Pending');
    }

    public function updateContact(): Result{
        return Result::err('NOT_IMPLEMENTED','Pending');
    }

    public function deleteContact(): Result{
        return Result::err('NOT_IMPLEMENTED','Pending');
    }
}