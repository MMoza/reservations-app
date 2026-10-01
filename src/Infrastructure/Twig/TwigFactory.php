<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Infrastructure\Config\Environment;

/**
 * Builds the Twig environment used by the server-rendered pages.
 *
 * Autoescaping stays on for html (Twig's default); in debug mode templates
 * are recompiled on every request and strict_variables catches typos early.
 */
final class TwigFactory
{
    public static function create(): \Twig\Environment
    {
        $projectRoot = dirname(__DIR__, 3);
        $isDebug = Environment::get('APP_DEBUG', '0') === '1';

        $loader = new \Twig\Loader\FilesystemLoader($projectRoot . '/templates');

        return new \Twig\Environment($loader, [
            'cache' => $isDebug ? false : $projectRoot . '/var/cache/twig',
            'autoescape' => 'html',
            'strict_variables' => $isDebug,
        ]);
    }
}
