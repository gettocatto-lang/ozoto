<?php

declare(strict_types=1);

namespace Ozoto;

final class Auth
{
    private const IDLE_TIMEOUT = 7200;

    /** @return array{id: int, email: string}|null */
    public static function user(): ?array
    {
        Session::start();
        $id = $_SESSION['uid'] ?? null;
        if (!is_int($id)) {
            return null;
        }
        if (time() - (int) ($_SESSION['last_seen'] ?? 0) > self::IDLE_TIMEOUT) {
            self::logout();
            return null;
        }
        $_SESSION['last_seen'] = time();
        return ['id' => $id, 'email' => (string) ($_SESSION['email'] ?? '')];
    }

    /** @return array{id: int, email: string} */
    public static function require(): array
    {
        if (!App::installed()) {
            redirect('/kurulum');
        }
        return self::user() ?? redirect('/yonetim/giris');
    }

    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, email, password_hash FROM users WHERE email = ?');
        $stmt->execute([mb_strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        Session::start();
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['email'] = (string) $user['email'];
        $_SESSION['last_seen'] = time();
        return true;
    }

    public static function logout(): void
    {
        Session::start();
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
