<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Учебная защита формы от CSRF: случайный токен в session + скрытое поле формы.
 * POST принимается, только если токен из формы совпал с токеном из session.
 */
final class Csrf
{
    public const FIELD = '_csrf';
    private const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function isValid(mixed $submitted): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($submitted) && is_string($expected) && hash_equals($expected, $submitted);
    }
}
