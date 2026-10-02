# SecureLite

A lightweight, SQLCipher-backed SQLite3 database library for PHP.

SecureLite provides a simple and developer-friendly way to work with encrypted SQLite databases in PHP. It is designed for applications that want the portability and simplicity of SQLite while protecting sensitive data stored on disk.

## Why SecureLite?

SQLite is an excellent choice for small applications, APIs, desktop applications, embedded systems, development environments, and applications that do not require a separate database server.

However, a standard SQLite database is stored as a regular database file. If someone gains access to that file, its contents can potentially be inspected or copied.

This is where SQLCipher can help.

SQLCipher provides transparent database encryption for SQLite. SecureLite builds a lightweight PHP API on top of SQLite3 and SQLCipher, making encrypted database access easier to integrate into PHP applications.

What problem does it solve?

Without database encryption, an SQLite database may look like:

```bash
database/
└── database.sqlite3
```

Anyone who obtains the database file may be able to open and inspect its contents using SQLite-compatible tools.

With SecureLite and SQLCipher, the database is encrypted at rest:

```bash
database/
└── database.sqlite3  ← encrypted database
```

The application provides the encryption key when opening the database, and SQLCipher handles the encryption and decryption of database pages transparently.

### Where is SecureLite useful?

SecureLite can be useful when you need a local, portable, encrypted database without deploying a separate database server.

1. Small PHP applications

For small applications, SQLite can be much simpler than setting up MySQL or PostgreSQL.

SecureLite adds encryption while keeping the database file-based.

2. APIs and backend applications

If your PHP API stores sensitive application data in SQLite, SecureLite can provide encryption at rest through SQLCipher.

For example:
```bash
PHP API
   │
   ▼
SecureLite
   │
   ▼
SQLCipher
   │
   ▼
Encrypted SQLite database
```

3. Local and embedded applications

SQLite is commonly useful for applications that need a self-contained database.

SecureLite can be useful when that local database contains information that should not be stored as plain SQLite data.

4. Development and testing

SQLite is convenient for development because it does not require a database server.

SecureLite allows developers to use the same simple database-file approach while also testing encrypted database workflows.

5. Portable applications

An SQLite database is easy to move between systems because it is stored in a single file.

SecureLite keeps this portability while using SQLCipher to protect the database contents.

## Key Features

- Lightweight PHP API
- SQLCipher-backed SQLite database
- Database encryption at rest
- Simple connection API
- Composer installation
- PHP 8.3+
- Uses environment variables for encryption keys
- No external database server required
- Suitable for small and embedded applications
- Keeps SQLite's simple file-based workflow

### Requirements

- [x] PHP 8.3+
- [x] Composer 2.10+
- [x] SQLite3 extension
- [x] SQLCipher

### PHP Extensions
```ini
; Linux/macOS
extension=sqlite3.so

; Windows
; extension=sqlite3.dll

SQLCipher Extension

; Linux/macOS
extension=sqlcipher.so

; Windows
; extension=sqlcipher.dll
```

«Make sure the SQLCipher extension is installed and enabled in your PHP configuration before using SecureLite.»

### Installation

Install SecureLite using Composer:

```bash
composer require sushilk/securelite
```

### Configuration

Load Composer's autoloader and create a database connection:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use SecureLite\Connection;

$db = new Connection(
    'database/database.sqlite3',
    $_ENV['DATABASE_ENCRYPTION_KEY']
);

$result = $db->query('SELECT * FROM users');

$data = $result->fetchArray();

var_dump($data);
```

### Encryption Key

Store the database encryption key in an environment variable instead of hard-coding it in your PHP source code:

```env
DATABASE_ENCRYPTION_KEY=your-secure-encryption-key
```

Then provide the key when creating the database connection:

```php
$db = new Connection(
    'database/database.sqlite3',
    $_ENV['DATABASE_ENCRYPTION_KEY']
);
```

#### How SecureLite Works

SecureLite sits between your PHP application and the encrypted SQLite database:

```bash
┌──────────────────────┐
│    PHP Application   │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│      SecureLite      │
│   PHP Database API   │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│       SQLCipher      │
│ Database Encryption  │
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│   SQLite Database    │
│     Encrypted File   │
└──────────────────────┘
```

Your PHP application works with SQL queries normally, while SQLCipher handles encryption and decryption underneath.

### Example

```php
$result = $db->query('SELECT * FROM users');

while ($user = $result->fetchArray()) {
    var_dump($user);
}
```

The application does not need to manually encrypt every database value. SQLCipher operates at the database level.

### Security Considerations

SecureLite protects the SQLite database at rest through SQLCipher. However, database encryption does not automatically protect your application from every security threat.

#### In particular:

- Keep the encryption key outside your source code.
- Do not commit ".env" files containing production keys.
- Use strong, randomly generated encryption keys.
- Protect server filesystem access and application credentials.
- Do not expose the database file through a public web directory.
- Keep PHP, SQLite, and SQLCipher components updated.
- Use appropriate access controls for the environment where the database is stored.
- Back up encrypted databases securely.

«Important: SecureLite does not replace application-level authentication, authorization, input validation, or general server security.»

#### When Should You Use SecureLite?

SecureLite is a good fit when you want:

```bash
SQLite
   +
SQLCipher encryption
   +
Simple PHP API
   =
Portable encrypted database
```

It is especially useful for applications where deploying a full database server would add unnecessary complexity, but storing sensitive information in an unencrypted SQLite file is not acceptable.

# License

SecureLite is open-source software under **MIT License** and is intended to provide a simple foundation for encrypted **SQLite** database access in **PHP**.
