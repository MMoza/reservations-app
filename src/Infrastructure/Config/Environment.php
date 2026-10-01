<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

/**
 * Minimal .env loader (KEY=VALUE lines, # comments, optional quotes).
 * Existing environment variables always win over file values.
 */
final class Environment
{
    public static function load(string $basePath): void
    {
        $file = rtrim($basePath, '/') . '/.env';
        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separatorPosition = strpos($line, '=');
            if ($separatorPosition === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separatorPosition));
            $value = trim(substr($line, $separatorPosition + 1));

            if ($key === '') {
                continue;
            }

            $value = self::stripQuotes($value);

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    private static function stripQuotes(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' || $first === "'") && $last === $first) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
