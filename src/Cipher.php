<?php

declare(strict_types=1);

namespace SecureLite;

use FFI\CData;
use FFI\Exception;

final class Cipher
{
    private const SQLITE_OK = 0;

    private const SQLITE_ROW = 100;

    private const SQLITE_DONE = 101;

    private const T_INTEGER = 1;

    private const T_FLOAT = 2;

    private const T_TEXT = 3;

    private const T_BLOB = 4;

    private const T_NULL = 5;

    private const CDEF = <<<'C'
        typedef struct sqlite3 sqlite3;
        typedef struct sqlite3_stmt sqlite3_stmt;
        typedef long long sqlite3_int64;

        int sqlite3_open(const char *filename, sqlite3 **ppDb);
        int sqlite3_close_v2(sqlite3 *db);
        int sqlite3_key(sqlite3 *db, const void *pKey, int nKey);
        int sqlite3_rekey(sqlite3 *db, const void *pKey, int nKey);
        int sqlite3_exec(sqlite3 *db, const char *sql, void *cb, void *arg, char **errmsg);
        const char *sqlite3_errmsg(sqlite3 *db);

        int sqlite3_prepare_v2(sqlite3 *db, const char *sql, int nByte, sqlite3_stmt **ppStmt, const char **pzTail);
        int sqlite3_step(sqlite3_stmt *stmt);
        int sqlite3_finalize(sqlite3_stmt *stmt);

        int sqlite3_bind_parameter_index(sqlite3_stmt *stmt, const char *name);
        int sqlite3_bind_null(sqlite3_stmt *stmt, int i);
        int sqlite3_bind_int64(sqlite3_stmt *stmt, int i, sqlite3_int64 v);
        int sqlite3_bind_double(sqlite3_stmt *stmt, int i, double v);
        int sqlite3_bind_text(sqlite3_stmt *stmt, int i, const char *v, int n, intptr_t destructor);
        int sqlite3_bind_blob(sqlite3_stmt *stmt, int i, const void *v, int n, intptr_t destructor);

        int sqlite3_column_count(sqlite3_stmt *stmt);
        const char *sqlite3_column_name(sqlite3_stmt *stmt, int i);
        int sqlite3_column_type(sqlite3_stmt *stmt, int i);
        sqlite3_int64 sqlite3_column_int64(sqlite3_stmt *stmt, int i);
        double sqlite3_column_double(sqlite3_stmt *stmt, int i);
        const unsigned char *sqlite3_column_text(sqlite3_stmt *stmt, int i);
        const void *sqlite3_column_blob(sqlite3_stmt *stmt, int i);
        int sqlite3_column_bytes(sqlite3_stmt *stmt, int i);

        sqlite3_int64 sqlite3_last_insert_rowid(sqlite3 *db);
        int sqlite3_changes(sqlite3 *db);
    C;

    private \FFI $ffi;

    /** @var CData sqlite3* */
    private $db = null;

    public function __construct(string $path, ?string $key, ?string $libPath = null)
    {
        if (! extension_loaded('ffi')) {
            throw new RuntimeException('The FFI extension is not enabled.');
        }
        if ($key === null || $key === '' || $key === false) {
            throw new InvalidArgumentException('Encryption key is missing (check your environment variable).');
        }

        $this->ffi = \FFI::cdef(self::CDEF, $libPath ?? self::findLibrary());

        $db = $this->ffi->new('sqlite3*');
        $rc = $this->ffi->sqlite3_open($path, \FFI::addr($db));
        if ($rc !== self::SQLITE_OK) {
            throw new RuntimeException('Cannot open database (code '.$rc.').');
        }
        $this->db = $db;

        // Must be the first operation after opening
        if ($this->ffi->sqlite3_key($this->db, $key, strlen($key)) !== self::SQLITE_OK) {
            $this->fail('Cannot set key');
        }

        // Verify SQLCipher is active (plain SQLite returns no rows for this pragma)
        if (! $this->query('PRAGMA cipher_version')) {
            $this->close();
            throw new RuntimeException('Loaded library is not SQLCipher; database would NOT be encrypted.');
        }

        // Verify the key is correct (a wrong key fails on the first real read)
        try {
            $this->query('SELECT count(*) FROM sqlite_master');
        } catch (RuntimeException $e) {
            $this->close();
            throw new RuntimeException('Wrong encryption key or not a SQLCipher database.');
        }
    }

    private static function findLibrary(): string
    {
        $candidates = match (PHP_OS_FAMILY) {
            'Windows' => ['sqlcipher.dll'],
            'Darwin' => ['libsqlcipher.dylib', '/opt/homebrew/lib/libsqlcipher.dylib', '/usr/local/lib/libsqlcipher.dylib'],
            default => ['libsqlcipher.so', 'libsqlcipher.so.0', 'libsqlcipher.so.1'],
        };

        foreach ($candidates as $lib) {
            try {
                \FFI::cdef('int sqlite3_libversion_number(void);', $lib);

                return $lib;
            } catch (Exception $e) {
                // try next
            }
        }
        throw new RuntimeException('SQLCipher library not found. Pass its path as the 3rd constructor argument.');
    }

    /** Run SQL without parameters or results (supports multiple statements). */
    public function exec(string $sql): void
    {
        $this->assertOpen();
        if ($this->ffi->sqlite3_exec($this->db, $sql, null, null, null) !== self::SQLITE_OK) {
            $this->fail('exec failed');
        }
    }

    /** Run a SELECT and return all rows as associative arrays. */
    public function query(string $sql, array $params = []): array
    {
        $this->assertOpen();
        $stmt = $this->prepare($sql, $params);
        $rows = [];

        try {
            $cols = $this->ffi->sqlite3_column_count($stmt);
            while (($rc = $this->ffi->sqlite3_step($stmt)) === self::SQLITE_ROW) {
                $row = [];
                for ($i = 0; $i < $cols; $i++) {
                    $row[$this->ffi->sqlite3_column_name($stmt, $i)] = $this->readColumn($stmt, $i);
                }
                $rows[] = $row;
            }
            if ($rc !== self::SQLITE_DONE) {
                $this->fail('query failed');
            }
        } finally {
            $this->ffi->sqlite3_finalize($stmt);
        }

        return $rows;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        return $this->query($sql, $params)[0] ?? null;
    }

    /** Run INSERT/UPDATE/DELETE with parameters. Returns affected rows. */
    public function execute(string $sql, array $params = []): int
    {
        $this->assertOpen();
        $stmt = $this->prepare($sql, $params);

        try {
            $rc = $this->ffi->sqlite3_step($stmt);
            while ($rc === self::SQLITE_ROW) {
                $rc = $this->ffi->sqlite3_step($stmt);
            }
            if ($rc !== self::SQLITE_DONE) {
                $this->fail('execute failed');
            }
        } finally {
            $this->ffi->sqlite3_finalize($stmt);
        }

        return $this->ffi->sqlite3_changes($this->db);
    }

    public function lastInsertId(): int
    {
        return (int) $this->ffi->sqlite3_last_insert_rowid($this->db);
    }

    public function begin(): void
    {
        $this->exec('BEGIN');
    }

    public function commit(): void
    {
        $this->exec('COMMIT');
    }

    public function rollback(): void
    {
        $this->exec('ROLLBACK');
    }

    /** Run a callback in a transaction. */
    public function transaction(callable $fn): mixed
    {
        $this->begin();
        try {
            $result = $fn($this);
            $this->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /** Change the encryption key of the open database. */
    public function rekey(string $newKey): void
    {
        $this->assertOpen();
        if ($this->ffi->sqlite3_rekey($this->db, $newKey, strlen($newKey)) !== self::SQLITE_OK) {
            $this->fail('rekey failed');
        }
    }

    public function close(): void
    {
        if ($this->db !== null) {
            $this->ffi->sqlite3_close_v2($this->db);
            $this->db = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    // ---------- internals ----------

    public function prepare(string $sql, array $params)
    {
        $stmt = $this->ffi->new('sqlite3_stmt*');
        if ($this->ffi->sqlite3_prepare_v2($this->db, $sql, -1, \FFI::addr($stmt), null) !== self::SQLITE_OK) {
            $this->fail('prepare failed');
        }

        try {
            $this->bindAll($stmt, $params);
        } catch (\Throwable $e) {
            $this->ffi->sqlite3_finalize($stmt);
            throw $e;
        }

        return $stmt;
    }

    private function bindAll($stmt, array $params): void
    {
        $position = 1;
        foreach ($params as $name => $value) {
            if (is_string($name)) {
                $index = $this->ffi->sqlite3_bind_parameter_index($stmt, $name[0] === ':' ? $name : ':'.$name);
                if ($index === 0) {
                    throw new InvalidArgumentException("Unknown parameter: $name");
                }
            } else {
                $index = $position++;
            }

            // -1 = SQLITE_TRANSIENT: SQLite makes its own copy of the data
            $rc = match (true) {
                $value === null => $this->ffi->sqlite3_bind_null($stmt, $index),
                is_bool($value) => $this->ffi->sqlite3_bind_int64($stmt, $index, (int) $value),
                is_int($value) => $this->ffi->sqlite3_bind_int64($stmt, $index, $value),
                is_float($value) => $this->ffi->sqlite3_bind_double($stmt, $index, $value),
                default => $this->ffi->sqlite3_bind_text($stmt, $index, (string) $value, strlen((string) $value), -1),
            };

            if ($rc !== self::SQLITE_OK) {
                $this->fail('bind failed');
            }
        }
    }

    private function readColumn($stmt, int $i): mixed
    {
        switch ($this->ffi->sqlite3_column_type($stmt, $i)) {
            case self::T_INTEGER:
                return (int) $this->ffi->sqlite3_column_int64($stmt, $i);
            case self::T_FLOAT:
                return (float) $this->ffi->sqlite3_column_double($stmt, $i);
            case self::T_TEXT:
                $ptr = $this->ffi->sqlite3_column_text($stmt, $i);
                $len = $this->ffi->sqlite3_column_bytes($stmt, $i);

                return $len > 0 ? \FFI::string($ptr, $len) : '';
            case self::T_BLOB:
                $ptr = $this->ffi->sqlite3_column_blob($stmt, $i);
                $len = $this->ffi->sqlite3_column_bytes($stmt, $i);

                return $len > 0 ? \FFI::string($ptr, $len) : '';
            default:
                return null;
        }
    }

    private function assertOpen(): void
    {
        if ($this->db === null) {
            throw new RuntimeException('Database is closed.');
        }
    }

    private function fail(string $message): never
    {
        throw new RuntimeException($message.': '.$this->ffi->sqlite3_errmsg($this->db));
    }
}
