<?php
declare(strict_types=1);

namespace App\Repos;

use App\Infrastructure\DBManager;
use App\Infrastructure\LoadSql;
use App\Infrastructure\Result;

class ContactRepo {

    public function __construct(
        private readonly DBManager $db,
        private readonly LoadSql $sql
    ) {}


   
    public function createContact(int $uid, string $name, string $phone, string $email): Result {
        if ($name === '' || mb_strlen($name) >= 255) {
            return Result::err('INVALID_NAME', "Name is invalid.");
        }

        if ($phone === '' && $email === '') {
            return Result::err('NOT_ENOUGH_ARGUMENTS', 'Include a phone number OR an email address');
        }
        // Error handling for improperly formatted data
        if (mb_strlen($phone) >= 32) {
            return Result::err('INVALID_PHONE', 'Phone number is invalid', ['field' => 'phone']);
        }

        // We can easily add the same constraints as UserRepo to force email to have @ and whatnot, but probably not necessary for contacts.
        if (mb_strlen($email) >= 255) {
            return Result::err('INVALID_EMAIL', 'Email address is invalid', ['field' => 'email']);
        }

        $path = 'queries/contact_queries/create_contact.sql';
        $sql = $this->sql->load($path);

        $params = [
            'uid' => $uid,
            'name' => $name,
            'phone' => $phone,
            'email' => $email
        ];

        // Try to create the contact
        try {
            $cid = $this->db->insert($sql, $params);
            return Result::ok(['cid' => (int)$cid]);
        } catch (\PDOException $e) {
            $sqlState = $e->errorInfo[0] ?? null;
            $driverCode = $e->errorInfo[1] ?? null;
            $msg = strtolower($e->errorInfo[2] ?? $e->getMessage());

            error_log('createContact DB error: ' . $e->getMessage());
            return Result::err('DB_ERROR', 'Database failure');
        }
    }


    public function readContact(int $cid, int $uid): Result {
        $path = 'queries/contact_queries/read_contact.sql';
        $sql = $this->sql->load($path);

        $params = [
            'cid' => $cid,
            'uid' => $uid
        ];

        try {
            $rows = $this->db->rows($sql, $params);

            if (empty($rows)) {
                return Result::err('NOT_FOUND', 'Contact not found');
            }

            $contact = $rows[0];
            return Result::ok($contact);
        } catch (\PDOException $e) {
            return Result::err('DB_ERROR', 'Database failure');
        }
    }

    public function listContacts(int $uid): Result {
        $path = 'queries/contact_queries/list_contacts.sql';
        $sql = $this->sql->load($path);

        $params = [
            'uid' => $uid
        ];

        try {
            $rows = $this->db->rows($sql, $params);
            return Result::ok($rows);
        } catch (\PDOException $e) {
            return Result::err('DB_ERROR', 'Database failure');
        }
    }

    public function updateContact(int $cid, int $uid, array $patch): Result {
        // Validate allowed fields
        $allowed = ['name', 'phone', 'email'];
        $patch = array_intersect_key($patch, array_flip($allowed));

        if (empty($patch)) {
            return Result::err('INVALID_PARAMETERS', 'No valid parameters provided');
        }

        // Validate name if provided
        if (isset($patch['name'])) {
            if ($patch['name'] === '' || mb_strlen($patch['name']) >= 255) {
                return Result::err('INVALID_NAME', 'Name is invalid');
            }
        }

        // Validate phone if provided
        if (isset($patch['phone']) && mb_strlen($patch['phone']) >= 32) {
            return Result::err('INVALID_PHONE', 'Phone number is invalid', ['field' => 'phone']);
        }

        // Validate email if provided
        if (isset($patch['email']) && mb_strlen($patch['email']) >= 255) {
            return Result::err('INVALID_EMAIL', 'Email address is invalid', ['field' => 'email']);
        }

        // Ensure at least phone or email exists after update
        // First get current contact to check existing values
        $currentContact = $this->readContact($cid, $uid);
        if (!$currentContact->ok) {
            return $currentContact; // Return the error (likely NOT_FOUND)
        }

        $current = $currentContact->data;
        $newPhone = $patch['phone'] ?? $current['phone'];
        $newEmail = $patch['email'] ?? $current['email'];

        if (($newPhone === '' || $newPhone === null) && ($newEmail === '' || $newEmail === null)) {
            return Result::err('NOT_ENOUGH_ARGUMENTS', 'Contact must have either a phone number or email address');
        }

        // Build the SET clause dynamically
        $set = [];
        $params = ['cid' => $cid, 'uid' => $uid];

        if (array_key_exists('name', $patch)) {
            $set[] = 'name = :name';
            $params['name'] = $patch['name'];
        }
        if (array_key_exists('phone', $patch)) {
            $set[] = 'phone = :phone';
            $params['phone'] = $patch['phone'];
        }
        if (array_key_exists('email', $patch)) {
            $set[] = 'email = :email';
            $params['email'] = $patch['email'];
        }

        $path = 'queries/contact_queries/update_contact.sql';
        $sql = $this->sql->load($path);
        
        // Replace the SET clause placeholder
        $sql = str_replace('SET name = :name, phone = :phone, email = :email', 'SET ' . implode(', ', $set), $sql);

        try {
            $rowsAffected = $this->db->exec($sql, $params);
            if ($rowsAffected === 0) {
                return Result::err('NOT_FOUND', 'Contact not found or no changes made');
            }
            return Result::ok(['cid' => $cid, 'updated' => 1]);
        } catch (\PDOException $e) {
            error_log('updateContact DB error: ' . $e->getMessage());
            return Result::err('DB_ERROR', 'Database failure');
        }
    }

    public function deleteContact(int $cid, int $uid): Result {
        $path = 'queries/contact_queries/delete_contact.sql';
        $sql = $this->sql->load($path);

        $params = [
            'cid' => $cid,
            'uid' => $uid
        ];

        try {
            $rowsAffected = $this->db->exec($sql, $params);
            if ($rowsAffected === 0) {
                return Result::err('NOT_FOUND', 'Contact not found');
            }
            return Result::ok(['cid' => $cid, 'deleted' => 1]);
        } catch (\PDOException $e) {
            error_log('deleteContact DB error: ' . $e->getMessage());
            return Result::err('DB_ERROR', 'Database failure');
        }
    }
}