<?php

declare(strict_types=1);

namespace SecureLite;

use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

final class Statement
{
    public function __construct(
        private readonly SQLite3Stmt $statement,
    ) {
    }

    public function bind(
        string|int $parameter,
        mixed $value,
        ?int $type = null,
    ): self {
        if ($type === null) {
            $type = match (true) {
                is_int($value) => SQLITE3_INTEGER,
                is_float($value) => SQLITE3_FLOAT,
                is_null($value) => SQLITE3_NULL,
                default => SQLITE3_TEXT,
            };
        }

        $this->statement->bindValue(
            $parameter,
            $value,
            $type
        );

        return $this;
    }

    public function execute(): SQLite3Result
    {
        $result = $this->statement->execute();

        if (!$result instanceof SQLite3Result) {
            throw new Exception(
                'SQL statement execution failed.'
            );
        }

        return $result;
    }

    public function fetch(
        SQLite3Result $result
    ): ?array {
        $row = $result->fetchArray(
            SQLITE3_ASSOC
        );

        return $row === false ? null : $row;
    }

    public function reset(): self
    {
        $this->statement->reset();

        return $this;
    }

    public function clear(): self
    {
        $this->statement->clear();

        return $this;
    }
}
