<?php

require __DIR__ . '/../vendor/autoload.php';

use SecureLite\Cipher;

$db = new Cipher('database.sqlite', 'root');

$db->exec("CREATE TABLE IF NOT EXISTS users(id INTEGER, name TEXT)");

$result = $db->query("INSERT INTO users(id, name) VALUES ('1', 'test 1')");

//$data = $result->fetchOne();

var_dump($result);
