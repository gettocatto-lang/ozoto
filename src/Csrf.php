<?php

declare(strict_types=1);

namespace Ozoto;

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function valid(): bool
    {
        Session::start();
        $sent = $_POST['_token'] ?? null;
        $expected = $_SESSION['_csrf'] ?? null;
        return is_string($sent) && is_string($expected) && hash_equals($expected, $sent);
    }
}
