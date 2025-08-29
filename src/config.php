<?php
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'contacts_app',
        'user' => 'root', // Using root for now for testing — will change for deployment
        'pass' => 'meow',  // Can be defined as anything
        'charset' => 'utf8mb4',
    ],
    'sql_root' => __DIR__ . '/../sql',
];