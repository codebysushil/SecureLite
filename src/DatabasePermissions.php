<?php

declare(strict_types=1);

namespace SecureLite;

use RuntimeException;

final class DatabasePermissions
{
    private function __construct() {}

    public static function secure(string $database): void
    {
        $database = self::normalizePath($database);
        $directory = dirname($database);

        self::secureDirectory($directory);

        if (is_file($database) && ! chmod($database, 0600)) {
            throw new RuntimeException(
                "Unable to secure database file: {$database}"
            );
        }

        self::secureSQLiteFiles($directory, basename($database));
    }

    private static function secureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
            throw new RuntimeException(
                "Unable to create database directory: {$directory}"
            );
        }

        if (! chmod($directory, 0700)) {
            throw new RuntimeException(
                "Unable to secure database directory: {$directory}"
            );
        }
    }

    private static function secureSQLiteFiles(
        string $directory,
        string $database
    ): void {
        $files = [
            "{$database}-wal",
            "{$database}-shm",
            "{$database}-journal",
        ];

        foreach ($files as $file) {
            $path = $directory.DIRECTORY_SEPARATOR.$file;

            if (is_file($path)) {
                chmod($path, 0600);
            }
        }
    }

    private static function normalizePath(string $database): string
    {
        if ($database === '') {
            throw new RuntimeException('Database path cannot be empty.');
        }

        $database = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $database
        );

        return $database;
    }
}
