<?php

declare(strict_types=1);

use SecureLite\Connection;

require_once __DIR__ . '/vendor/autoload.php';

$db = new Connection('test.db', 'root1234');

$db->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        mobile TEXT NOT NULL,
        city TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
    SQL);

/*
$user = [
    'name' => 'sushil',
    'email' => 'sushil@example.com',
    'mobile' => '1234567890',
    'city' => 'bargarh'
];

$db->transaction(function (Connection $db) use ($user) {

    $stmt = $db->prepare(
        'INSERT INTO users
            (name, email, mobile, city)
         VALUES
            (:name, :email, :mobile, :city)'
    );

    $stmt
        ->bind(':name', $user['name'])
        ->bind(':email', $user['email'])
        ->bind(':mobile', $user['mobile'])
        ->bind(':city', $user['city'])
        ->execute();
});
 */
$res = $db->query('SELECT * FROM users');

$data = $res->fetchArray(SQLITE3_ASSOC);

echo '<pre>';
print_r($data);
