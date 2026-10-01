<?php

declare(strict_types=1);

namespace SecureLite;

use SQLite3;
use SQLite3Result;
use SQLite3Stmt;
use Throwable;

final class Connection
{
    private SQLite3 $connection;

    private bool $transaction = false;

    public function __construct(
        string $filename,
        string $key,
        int $busyTimeout = 5000,
    ) {
        if ($key === '') {
            throw new Exception(
                'SQLCipher encryption key cannot be empty.'
            );
        }

        $directory = dirname($filename);

        if (! is_dir($directory)) {
            throw new Exception(
                "Database directory does not exist: {$directory}"
            );
        }

        DatabasePermissions::secure($filename);
        $this->connection = new SQLite3($filename);

        $this->connection->enableExceptions(true);
        $this->connection->busyTimeout($busyTimeout);

        /*
         * SQLCipher encryption key.
         *
         * This must be executed immediately after
         * opening the database and before normal queries.
         */
        $this->connection->exec(
            'PRAGMA key = '.$this->quoteKey($key)
        );

        /*
         * Verify that SQLCipher can actually read
         * the encrypted database.
         */
        $this->verify();

        /*
         * SQLite security/integrity settings.
         */
        $this->connection->exec(
            'PRAGMA foreign_keys = ON'
        );

        $this->connection->exec(
            'PRAGMA secure_delete = ON'
        );

        /*
         * WAL improves concurrent read/write behavior.
         */
        $this->connection->exec(
            'PRAGMA journal_mode = WAL'
        );

        /*
         * Reasonable durability setting.
         */
        $this->connection->exec(
            'PRAGMA synchronous = NORMAL'
        );
    }

    private function quoteKey(string $key): string
    {
        /*
         * SQLCipher accepts a quoted key.
         * Escape single quotes for SQL.
         */
        return "'".str_replace(
            "'",
            "''",
            $key
        )."'";
    }

    private function verify(): void
    {
        try {
            $result = $this->connection->querySingle(
                'SELECT count(*) FROM sqlite_master'
            );

            if (! is_int($result) && ! is_numeric($result)) {
                throw new Exception(
                    'Unable to verify SQLCipher database.'
                );
            }
        } catch (Throwable $e) {
            throw new Exception(
                'Invalid SQLCipher key or corrupted database.',
                previous: $e
            );
        }
    }

    public function exec(string $sql): bool
    {
        return $this->connection->exec($sql);
    }

    public function query(string $sql): SQLite3Result
    {
        return $this->connection->query($sql);
    }

    public function prepare(string $sql): Statement
    {
        $statement = $this->connection->prepare($sql);

        if (! $statement instanceof SQLite3Stmt) {
            throw new Exception(
                'Unable to prepare SQL statement.'
            );
        }

        return new Statement($statement);
    }

    public function querySingle(
        string $sql,
        bool $entireRow = false,
    ): mixed {
        return $this->connection->querySingle(
            $sql,
            $entireRow
        );
    }

    public function begin(): void
    {
        if ($this->transaction) {
            throw new Exception(
                'A transaction is already active.'
            );
        }

        $this->connection->exec('BEGIN');
        $this->transaction = true;
    }

    public function commit(): void
    {
        if (! $this->transaction) {
            throw new Exception(
                'No active transaction.'
            );
        }

        $this->connection->exec('COMMIT');
        $this->transaction = false;
    }

    public function rollback(): void
    {
        if (! $this->transaction) {
            return;
        }

        $this->connection->exec('ROLLBACK');
        $this->transaction = false;
    }

    public function transaction(
        callable $callback
    ): mixed {
        $this->begin();

        try {
            $result = $callback($this);

            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->rollback();

            throw $e;
        }
    }

    public function lastInsertId(): int
    {
        return $this->connection->lastInsertRowID();
    }

    public function changes(): int
    {
        return $this->connection->changes();
    }

    public function close(): void
    {
        $this->connection->close();
    }

    public function raw(): SQLite3
    {
        return $this->connection;
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (Throwable) {
            // Ignore shutdown errors.
        }
    }
}
