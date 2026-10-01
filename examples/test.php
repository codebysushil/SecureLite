<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use SecureLite\SqlCipher;

$db = new SqlCipher();

echo 'SQLCipher SQLite version: ';
echo $db->version();
echo PHP_EOL;

$db->open(
    __DIR__ . '/test.db',
    'root1234'
);

echo "Database opened successfully." . PHP_EOL;

$db->exec(
    'CREATE TABLE IF NOT EXISTS test (
        id INTEGER PRIMARY KEY,
        message TEXT NOT NULL
    )'
);

echo "SQL executed successfully." . PHP_EOL;

$db->close();

echo "Database closed." . PHP_EOL;
