<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Infrastructure\Config\Environment;

/**
 * PDO connection factory. Credentials come from .env (see .env.example).
 *
 * ERRMODE_EXCEPTION + native prepared statements (no emulation) are set
 * on purpose: every query goes through real server-side prepared SQL.
 */
final class Database
{
    public static function connect(): \PDO
    {
        $host = Environment::get('DB_HOST', '127.0.0.1');
        $port = Environment::get('DB_PORT', '3306');
        $name = Environment::get('DB_NAME', 'reservations_db');
        $user = Environment::get('DB_USER', 'root');
        $password = Environment::get('DB_PASS', '');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        return new \PDO($dsn, $user, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
