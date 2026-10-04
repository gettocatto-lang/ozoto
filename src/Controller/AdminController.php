<?php

declare(strict_types=1);

namespace Ozoto\Controller;

use Ozoto\App;
use Ozoto\Auth;
use Ozoto\Csrf;
use Ozoto\Database;
use Ozoto\RateLimiter;
use Ozoto\Session;
use Ozoto\Support\Catalog;

final class AdminController
{
    private const PER_PAGE = 25;

    public function loginForm(): void
    {
        if (!App::installed()) {
            redirect('/kurulum');
        }
        if (Auth::user() !== null) {
            redirect('/yonetim');
        }
        $this->renderLogin('', null);
    }

    public function login(): void
    {
        if (!App::installed()) {
            redirect('/kurulum');
        }
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!Csrf::valid()) {
            $this->renderLogin($email, 'Sayfanın süresi dolmuş, lütfen tekrar deneyin.', 419);
            return;
        }
        $key = client_ip_hash();
        if (RateLimiter::hits('login', $key, 900) >= 10) {
            $this->renderLogin($email, 'Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.', 429);
            return;
        }
        if (!Auth::attempt($email, (string) ($_POST['password'] ?? ''))) {
            RateLimiter::record('login', $key);
            $this->renderLogin($email, 'E-posta veya şifre hatalı.', 401);
            return;
        }
        redirect('/yonetim');
    }

    public function logout(): void
    {
        if (Csrf::valid()) {
            Auth::logout();
        }
        redirect('/yonetim/giris');
    }

    public function index(): void
    {
        $user = Auth::require();
        $pdo = Database::connection();
        $statuses = Catalog::statuses();

        $status = (string) ($_GET['durum'] ?? '');
        $status = isset($statuses[$status]) ? $status : '';
        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['sayfa'] ?? 1));

        $where = [];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            $parts = [];
            foreach (['full_name', 'brand', 'model', 'city', 'ref_code'] as $column) {
                $parts[] = $column . " LIKE ? ESCAPE '!'";
                $params[] = $like;
            }
            $qDigits = digits($q);
            if (strlen($qDigits) >= 4) {
                $parts[] = 'phone LIKE ?';
                $params[] = '%' . $qDigits . '%';
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $count = $pdo->prepare('SELECT COUNT(*) FROM applications ' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        $stmt = $pdo->prepare(
            'SELECT a.*, (SELECT COUNT(*) FROM application_photos p WHERE p.application_id = a.id) AS photo_count
             FROM applications a ' . $whereSql . ' ORDER BY a.created_at DESC, a.id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE)
        );
        $stmt->execute($params);

        $counts = array_fill_keys(array_keys($statuses), 0);
        foreach ($pdo->query('SELECT status, COUNT(*) AS c FROM applications GROUP BY status') as $row) {
            $counts[$row['status']] = (int) $row['c'];
        }

        view('admin/index', [
            'title' => 'Başvurular – Ozoto Yönetim',
            'user' => $user,
            'rows' => $stmt->fetchAll(),
            'statuses' => $statuses,
            'counts' => $counts,
            'status' => $status,
            'q' => $q,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ], 'admin/layout');
    }

    public function show(string $id): void
    {
        $user = Auth::require();
        $application = $this->find($id);
        $photos = Database::connection()->prepare('SELECT id, mime, size FROM application_photos WHERE application_id = ? ORDER BY id');
        $photos->execute([$application['id']]);

        view('admin/show', [
            'title' => $application['ref_code'] . ' – Ozoto Yönetim',
            'user' => $user,
            'a' => $application,
            'photos' => $photos->fetchAll(),
            'statuses' => Catalog::statuses(),
            'saved' => Session::pull('saved', false),
        ], 'admin/layout');
    }

    public function update(string $id): void
    {
        Auth::require();
        $application = $this->find($id);
        if (!Csrf::valid()) {
            abort(419);
        }
        $status = (string) ($_POST['status'] ?? '');
        if (!isset(Catalog::statuses()[$status])) {
            $status = (string) $application['status'];
        }
        $notes = mb_substr(trim((string) ($_POST['admin_notes'] ?? '')), 0, 5000);

        Database::connection()
            ->prepare('UPDATE applications SET status = ?, admin_notes = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, $notes !== '' ? $notes : null, now(), $application['id']]);

        Session::flash('saved', true);
        redirect('/yonetim/basvuru/' . $application['id']);
    }

    public function photo(string $id): void
    {
        Auth::require();
        $stmt = Database::connection()->prepare('SELECT file_name, mime FROM application_photos WHERE id = ?');
        $stmt->execute([(int) $id]);
        $photo = $stmt->fetch();
        if (!$photo) {
            abort(404);
        }
        $root = realpath(BASE_PATH . '/storage/uploads');
        $path = realpath(BASE_PATH . '/storage/' . $photo['file_name']);
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            abort(404);
        }
        header('Content-Type: ' . $photo['mime']);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
    }

    /** @return array<string, mixed> */
    private function find(string $id): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM applications WHERE id = ?');
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch();
        if (!$row) {
            abort(404);
        }
        return $row;
    }

    private function renderLogin(string $email, ?string $error, int $status = 200): void
    {
        view('admin/login', [
            'title' => 'Giriş – Ozoto Yönetim',
            'email' => $email,
            'error' => $error,
            'notice' => Session::pull('notice'),
        ], 'admin/layout', $status);
    }
}
