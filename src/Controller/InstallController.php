<?php

declare(strict_types=1);

namespace Ozoto\Controller;

use Ozoto\App;
use Ozoto\Csrf;
use Ozoto\Database;
use Ozoto\Session;
use Ozoto\Support\Phone;
use PDO;

/**
 * Tek seferlik kurulum sihirbazı: veritabanı tablolarını oluşturur, yönetici hesabını açar ve
 * config/local.php dosyasını yazar. local.php oluştuktan sonra bu sayfa 404 döner.
 */
final class InstallController
{
    public function form(): void
    {
        if (App::installed()) {
            abort(404);
        }
        $this->render([
            'driver' => in_array('mysql', PDO::getAvailableDrivers(), true) ? 'mysql' : 'sqlite',
            'db_host' => 'localhost',
            'db_port' => '3306',
        ], []);
    }

    public function install(): void
    {
        if (App::installed()) {
            abort(404);
        }
        $in = array_map(static fn ($v) => is_string($v) ? trim($v) : '', $_POST);
        $errors = [];

        if (!Csrf::valid()) {
            $errors['_form'] = 'Sayfanın süresi dolmuş, lütfen tekrar gönderin.';
        }
        if (!hash_equals((string) config('install_token_hash'), hash('sha256', strtoupper($in['token'] ?? '')))) {
            usleep(500_000);
            $errors['token'] = 'Kurulum anahtarı hatalı.';
        }
        if (in_array(false, self::requirements(), true)) {
            $errors['_form'] = 'Sunucu gereksinimleri karşılanmıyor (yukarıdaki listeye bakın).';
        }

        $driver = ($in['driver'] ?? '') === 'sqlite' ? 'sqlite' : 'mysql';
        if (!in_array($driver, PDO::getAvailableDrivers(), true)) {
            $errors['driver'] = 'Bu sunucuda PDO ' . $driver . ' sürücüsü yok.';
        }
        if ($driver === 'mysql' && (($in['db_name'] ?? '') === '' || ($in['db_user'] ?? '') === '')) {
            $errors['db'] = 'Veritabanı adı ve kullanıcı adı gerekli.';
        }

        $email = mb_strtolower($in['admin_email'] ?? '');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['admin_email'] = 'Geçerli bir e-posta yazın.';
        }
        $password = (string) ($_POST['admin_password'] ?? '');
        if (mb_strlen($password) < 10) {
            $errors['admin_password'] = 'Şifre en az 10 karakter olmalı.';
        } elseif ($password !== (string) ($_POST['admin_password2'] ?? '')) {
            $errors['admin_password'] = 'Şifreler eşleşmiyor.';
        }

        $notify = $in['notify_email'] ?? '';
        if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL) === false) {
            $errors['notify_email'] = 'Geçerli bir e-posta yazın veya boş bırakın.';
        }
        $phone = $in['site_phone'] ?? '';
        if ($phone !== '' && Phone::normalize($phone) === null) {
            $errors['site_phone'] = 'Geçerli bir telefon yazın veya boş bırakın.';
        }
        $whatsapp = $in['site_whatsapp'] ?? '';
        if ($whatsapp !== '' && Phone::normalize($whatsapp) === null) {
            $errors['site_whatsapp'] = 'Geçerli bir numara yazın veya boş bırakın.';
        }

        if ($errors !== []) {
            $this->render($in, $errors, 422);
            return;
        }

        $db = [
            'driver' => $driver,
            'host' => ($in['db_host'] ?? '') ?: 'localhost',
            'port' => (int) (($in['db_port'] ?? '') ?: 3306),
            'name' => $in['db_name'] ?? '',
            'user' => $in['db_user'] ?? '',
            'pass' => (string) ($_POST['db_pass'] ?? ''),
            'path' => 'storage/database.sqlite',
        ];

        try {
            $pdo = Database::connect($db);
            Database::migrate($pdo, $driver);
            $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $exists->execute([$email]);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($exists->fetchColumn() !== false) {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE email = ?')->execute([$hash, $email]);
            } else {
                $pdo->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, ?)')->execute([$email, $hash, now()]);
            }
        } catch (\PDOException $e) {
            error_log((string) $e);
            $this->render($in, ['db' => 'Veritabanına bağlanılamadı: ' . $e->getMessage()], 422);
            return;
        }

        $local = [
            'app_key' => bin2hex(random_bytes(32)),
            'notify_email' => $notify,
            'site' => [
                'phone' => $phone !== '' ? Phone::pretty((string) Phone::normalize($phone)) : '',
                'whatsapp' => $whatsapp !== '' ? Phone::international($whatsapp) : '',
                'email' => $in['site_email'] ?? '',
                'company' => $in['site_company'] ?? '',
                'address' => $in['site_address'] ?? '',
            ],
            'db' => $db,
        ];
        $code = "<?php\n\n// /kurulum tarafından " . date('d.m.Y H:i') . " tarihinde oluşturuldu. Git'e eklemeyin.\n\nreturn "
            . var_export($local, true) . ";\n";
        if (file_put_contents(BASE_PATH . '/config/local.php', $code, LOCK_EX) === false) {
            $this->render($in, ['_form' => 'config klasörüne yazılamadı. Plesk Dosya Yöneticisi\'nden "config" klasörüne yazma izni verin.'], 500);
            return;
        }

        Session::flash('notice', 'Kurulum tamamlandı. Yönetici hesabınızla giriş yapabilirsiniz.');
        redirect('/yonetim/giris');
    }

    /** @return array<string, bool> */
    public static function requirements(): array
    {
        $drivers = class_exists(PDO::class) ? PDO::getAvailableDrivers() : [];
        $writable = static fn (string $dir): bool => is_dir(BASE_PATH . '/' . $dir) && is_writable(BASE_PATH . '/' . $dir);
        return [
            'PHP 8.2 veya üzeri (şu an ' . PHP_VERSION . ')' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'PDO MySQL veya SQLite sürücüsü' => array_intersect(['mysql', 'sqlite'], $drivers) !== [],
            'mbstring eklentisi' => extension_loaded('mbstring'),
            'config klasörü yazılabilir' => $writable('config'),
            'storage/uploads klasörü yazılabilir' => $writable('storage/uploads'),
            'storage/sessions klasörü yazılabilir' => $writable('storage/sessions'),
            'storage/logs klasörü yazılabilir' => $writable('storage/logs'),
        ];
    }

    /**
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function render(array $old, array $errors, int $status = 200): void
    {
        view('install', [
            'title' => 'Kurulum – Ozoto',
            'old' => $old,
            'errors' => $errors,
            'requirements' => self::requirements(),
            'drivers' => PDO::getAvailableDrivers(),
        ], 'admin/layout', $status);
    }
}
