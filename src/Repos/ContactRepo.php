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

        // Error handling for improperly formatted data
        if (mb_strlen($phone) >=32){
            return Result::err('INVALID_PHONE', 'Phone number is invalid', ['field' => 'phone']);
        }
        if (mb_strlen($email) >=255){
            return Result::err('INVALID_EMAIL', 'Email address is invalid', ['field' => 'email']);
        }
        if (mb_strlen($name) >=255){
            return Result::err('INVALID_NAME', 'Name is invalid', ['field' => 'name']);
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

        }




        return Result::err('NOT_IMPLEMENTED','Pending');
    }


    public function readContact() : Result{
        return Result::err('NOT_IMPLEMENTED','Pending');
    }

    public function listContacts(): Result{
        return Result::err('NOT_IMPLEMENTED','Pending');
    }

    public function updateContact(): Result{
        return Result::err('NOT_IMPLEMENTED','Pending');
    }

    public function deleteContact(): Result{
        return Result::err('NOT_IMPLEMENTED','Pending');
    }
}