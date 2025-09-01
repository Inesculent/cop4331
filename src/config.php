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

    'auth' => [
        'jwt_secret' => $_ENV['JWT_SECRET'] ?? '9i9aGvLxhdxYplGf6ZkyaMH8M8U+q0fhH6C3zMebn0Bl7nRZP3YBljQ2QdoZkA7l',
        'issuer' => 'https://contacts.local',   // your app or domain
        'audience' => 'https://contacts.api',     // the intended consumer (your API)
        'access_ttl' => 3600, // 1h
    ],

    // Development settings
    'dev' => [
        'show_cookies' => $_ENV['DEV_MODE'] ?? true, // Set to false in production
        'secure_cookies' => $_ENV['SECURE_COOKIES'] ?? false, // Set to true in production with HTTPS
    ],

    'sql_root' => __DIR__ . '/../sql',
];

