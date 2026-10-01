<?php

declare(strict_types=1);

namespace SecureLite;

use FFI;
use RuntimeException;

final class SqlCipher
{
    private FFI $ffi;

    private mixed $db = null;

    public function __construct(
        string $library = '/data/data/com.termux/files/usr/lib/libsqlcipher.so'
    ) {
        if (!is_file($library)) {
            throw new RuntimeException(
                "SQLCipher library not found: {$library}"
            );
        }

        $definitions = <<<'C'
typedef struct sqlite3 sqlite3;

int sqlite3_open_v2(
    const char *filename,
    sqlite3 **ppDb,
    int flags,
    const char *zVfs
);

int sqlite3_close(sqlite3 *db);

const char *sqlite3_errmsg(sqlite3 *db);

int sqlite3_key(
    sqlite3 *db,
    const void *pKey,
    int nKey
);

int sqlite3_exec(
    sqlite3 *db,
    const char *sql,
    void *callback,
    void *arg,
    char **errmsg
);

const char *sqlite3_libversion(void);
C;

        $this->ffi = FFI::cdef($definitions, $library);
    }

    public function open(string $filename, string $key): void
    {
        if ($filename === '') {
            throw new RuntimeException(
                'Database filename cannot be empty.'
            );
        }

        if ($key === '') {
            throw new RuntimeException(
                'SQLCipher key cannot be empty.'
            );
        }

        if ($this->db !== null) {
            throw new RuntimeException(
                'Database is already open.'
            );
        }

        $database = $this->ffi->new('sqlite3 *');

        /*
         * SQLITE_OPEN_READWRITE = 0x00000002
         * SQLITE_OPEN_CREATE    = 0x00000004
         */
        $flags = 0x00000002 | 0x00000004;

        $result = $this->ffi->sqlite3_open_v2(
            $filename,
            FFI::addr($database),
            $flags,
            null
        );

        if ($result !== 0) {
            $message = 'Unable to open database.';

            if ($database->cdata !== null) {
                $message = FFI::string(
                    $this->ffi->sqlite3_errmsg($database->cdata)
                );

                $this->ffi->sqlite3_close($database->cdata);
            }

            throw new RuntimeException($message);
        }

        $this->db = $database->cdata;

        /*
         * Apply the SQLCipher key immediately after opening.
         */
        $result = $this->ffi->sqlite3_key(
            $this->db,
            $key,
            strlen($key)
        );

        if ($result !== 0) {
            $message = FFI::string(
                $this->ffi->sqlite3_errmsg($this->db)
            );

            $this->close();

            throw new RuntimeException(
                "Unable to apply SQLCipher key: {$message}"
            );
        }
    }

    public function exec(string $sql): void
    {
        $this->ensureOpen();

        $error = $this->ffi->new('char *');

        $result = $this->ffi->sqlite3_exec(
            $this->db,
            $sql,
            null,
            null,
            FFI::addr($error)
        );

        if ($result !== 0) {
            $message = 'SQL execution failed.';

            if ($error->cdata !== null) {
                $message = FFI::string($error->cdata);
            }

            throw new RuntimeException($message);
        }
    }

    public function version(): string
    {
        return FFI::string(
            $this->ffi->sqlite3_libversion()
        );
    }

    public function close(): void
    {
        if ($this->db === null) {
            return;
        }

        $result = $this->ffi->sqlite3_close($this->db);

        if ($result !== 0) {
            throw new RuntimeException(
                "Unable to close database: {$result}"
            );
        }

        $this->db = null;
    }

    private function ensureOpen(): void
    {
        if ($this->db === null) {
            throw new RuntimeException(
                'Database is not open.'
            );
        }
    }

    public function __destruct()
    {
        if ($this->db !== null) {
            $this->ffi->sqlite3_close($this->db);
            $this->db = null;
        }
    }
}
