<?php

declare(strict_types=1);

namespace Ozoto\Controller;

use Ozoto\App;
use Ozoto\Auth;
use Ozoto\Csrf;
use Ozoto\Database;
use Ozoto\RateLimiter;
use Ozoto\Session;
use Ozoto\Settings;
use Ozoto\Support\Phone;

/** Yönetim → Hesap ve site: sitede görünen iletişim bilgileri ve yönetici şifresi. */
final class AccountController
{
    public function show(): void
    {
        $user = Auth::require();
        $site = [];
        foreach (App::PANEL_KEYS as $key) {
            $site[$key] = (string) config($key, '');
        }
        $this->render($user, $site, []);
    }

    public function saveSite(): void
    {
        $user = Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        $in = [];
        foreach (App::PANEL_KEYS as $key) {
            $in[$key] = trim((string) ($_POST[str_replace('.', '_', $key)] ?? ''));
        }

        $errors = [];
        foreach (['site.phone' => 'Geçerli bir telefon yazın veya boş bırakın.', 'site.whatsapp' => 'Geçerli bir numara yazın veya boş bırakın.'] as $key => $message) {
            if ($in[$key] !== '' && Phone::normalize($in[$key]) === null) {
                $errors[$key] = $message;
            }
        }
        foreach (['site.email', 'notify_email'] as $key) {
            if ($in[$key] !== '' && filter_var($in[$key], FILTER_VALIDATE_EMAIL) === false) {
                $errors[$key] = 'Geçerli bir e-posta yazın veya boş bırakın.';
            }
        }
        foreach (['site.company' => 150, 'site.address' => 250] as $key => $max) {
            if (mb_strlen($in[$key]) > $max) {
                $errors[$key] = 'En fazla ' . $max . ' karakter.';
            }
        }
        if ($errors !== []) {
            $this->render($user, $in, $errors, 422);
            return;
        }

        if ($in['site.phone'] !== '') {
            $in['site.phone'] = Phone::pretty((string) Phone::normalize($in['site.phone']));
        }
        if ($in['site.whatsapp'] !== '') {
            $in['site.whatsapp'] = Phone::international($in['site.whatsapp']);
        }
        foreach ($in as $key => $value) {
            Settings::set('config.' . $key, $value);
        }
        Session::flash('notice', 'Site bilgileri kaydedildi.');
        redirect('/yonetim/hesap');
    }

    public function changePassword(): void
    {
        $user = Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $site = [];
        foreach (App::PANEL_KEYS as $key) {
            $site[$key] = (string) config($key, '');
        }

        $key = client_ip_hash();
        if (RateLimiter::hits('password', $key, 900) >= 10) {
            $this->render($user, $site, ['password' => 'Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.'], 429);
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = (string) $stmt->fetchColumn();

        $error = null;
        if ($hash === '' || !password_verify($current, $hash)) {
            RateLimiter::record('password', $key);
            $error = 'Mevcut şifre hatalı.';
        } elseif (mb_strlen($new) < 10) {
            $error = 'Yeni şifre en az 10 karakter olmalı.';
        } elseif ($new !== (string) ($_POST['new_password2'] ?? '')) {
            $error = 'Yeni şifreler eşleşmiyor.';
        }
        if ($error !== null) {
            $this->render($user, $site, ['password' => $error], 422);
            return;
        }

        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        Session::flash('notice', 'Şifreniz değiştirildi.');
        redirect('/yonetim/hesap');
    }

    /**
     * @param array{id: int, email: string} $user
     * @param array<string, string> $site
     * @param array<string, string> $errors
     */
    private function render(array $user, array $site, array $errors, int $status = 200): void
    {
        view('admin/account', [
            'title' => 'Hesap ve site – Öz Oto Yönetim',
            'user' => $user,
            'site' => $site,
            'errors' => $errors,
            'notice' => Session::pull('notice'),
        ], 'admin/layout', $status);
    }
}
