<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Return the shared MariaDB connection.
 *
 * Configure DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASSWORD in the PHP
 * process environment. Credentials should not be committed to this file.
 */
function databaseConnection(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $required = ['DB_NAME', 'DB_USER', 'DB_PASSWORD'];
    $settings = [];

    foreach ($required as $key) {
        $value = getenv($key);
        if ($value === false) {
            throw new RuntimeException("Missing database configuration: {$key}");
        }
        $settings[$key] = $value;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $settings['DB_NAME']
    );

    $connection = new PDO($dsn, $settings['DB_USER'], $settings['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}
