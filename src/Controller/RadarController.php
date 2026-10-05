<?php

declare(strict_types=1);

namespace Ozoto\Controller;

use Ozoto\Auth;
use Ozoto\Csrf;
use Ozoto\Database;
use Ozoto\Radar\Collector;
use Ozoto\Radar\Diagnostics;
use Ozoto\Radar\ListingParser;
use Ozoto\Radar\Listings;
use Ozoto\Radar\Sources\EmailNotifications;
use Ozoto\Radar\Sources\SearchEngine;
use Ozoto\Radar\Telegram;
use Ozoto\Session;
use Ozoto\Settings;
use Ozoto\Support\Catalog;

/** Yönetim panelindeki Kelepir Radar ekranları. */
final class RadarController
{
    public function index(): void
    {
        $user = Auth::require();
        $filters = array_map(static fn ($v): string => is_string($v) ? $v : '', $_GET);
        $result = Listings::search($filters);

        view('admin/radar/index', [
            'title' => 'Kelepir Radar – Öz Oto Yönetim',
            'user' => $user,
            'f' => $filters,
            'result' => $result,
            'stats' => Listings::stats(),
            'brands' => Listings::distinct('brand'),
            'cities' => Listings::distinct('city'),
            'sites' => Listings::distinct('site'),
            'saved' => Database::connection()->query('SELECT * FROM radar_saved_searches ORDER BY name')->fetchAll(),
            'notice' => Session::pull('notice'),
            'searchConfigured' => SearchEngine::configured() && Settings::get('search_enabled') === '1',
            'lastCollect' => Settings::get('last_collect_at'),
        ], 'admin/layout');
    }

    public function show(string $id): void
    {
        $user = Auth::require();
        $listing = $this->find($id);
        view('admin/radar/show', [
            'title' => ($listing['title'] ?: 'İlan') . ' – Kelepir Radar',
            'user' => $user,
            'l' => $listing,
            'history' => Listings::priceHistory((int) $listing['id']),
            'notes' => json_decode((string) ($listing['score_notes'] ?? '[]'), true) ?: [],
            'options' => $this->options(),
            'notice' => Session::pull('notice'),
        ], 'admin/layout');
    }

    public function create(): void
    {
        $user = Auth::require();
        $this->renderForm($user, [], []);
    }

    public function store(): void
    {
        $user = Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        [$data, $errors] = $this->validate($_POST, true);
        if ($errors !== []) {
            $this->renderForm($user, $_POST, $errors, 422);
            return;
        }
        $saved = Listings::upsert('manuel', $data);
        Listings::valuate($saved['id']);
        Session::flash('notice', $saved['created'] ? 'İlan eklendi ve değerlendi.' : 'Bu link zaten kayıtlıydı; bilgileri güncellendi.');
        redirect('/yonetim/radar/' . $saved['id']);
    }

    public function update(string $id): void
    {
        Auth::require();
        $listing = $this->find($id);
        if (!Csrf::valid()) {
            abort(419);
        }

        $changes = [];
        $status = (string) ($_POST['status'] ?? '');
        if (isset(Listings::STATUSES[$status])) {
            $changes['status'] = $status;
        }
        if (array_key_exists('notes', $_POST)) {
            $changes['notes'] = mb_substr(trim((string) $_POST['notes']), 0, 5000) ?: null;
        }

        if (isset($_POST['edit'])) {
            [$data, $errors] = $this->validate($_POST, false);
            if ($errors === []) {
                if ($data['price'] !== null && (int) $data['price'] !== (int) $listing['price']) {
                    Database::connection()->prepare('INSERT INTO radar_price_history (listing_id, price, seen_at) VALUES (?, ?, ?)')
                        ->execute([$listing['id'], $data['price'], now()]);
                    $changes['price_changed_at'] = now();
                }
                unset($data['url']);
                $changes += $data;
                $changes['model_key'] = ListingParser::modelKey($data['model']);
                $changes['needs_valuation'] = 1;
            } else {
                Session::flash('notice', 'Kaydedilmedi: ' . implode(' ', $errors));
                redirect('/yonetim/radar/' . $listing['id']);
            }
        }

        $changes['updated_at'] = now();
        Listings::update((int) $listing['id'], $changes);
        if (isset($changes['needs_valuation'])) {
            Listings::valuate((int) $listing['id']);
        }
        Session::flash('notice', 'Kaydedildi.');
        redirect('/yonetim/radar/' . $listing['id']);
    }

    public function destroy(string $id): void
    {
        Auth::require();
        $listing = $this->find($id);
        if (!Csrf::valid()) {
            abort(419);
        }
        Listings::delete((int) $listing['id']);
        Session::flash('notice', 'İlan silindi.');
        redirect('/yonetim/radar');
    }

    public function revaluate(string $id): void
    {
        Auth::require();
        $listing = $this->find($id);
        if (!Csrf::valid()) {
            abort(419);
        }
        Listings::valuate((int) $listing['id']);
        Session::flash('notice', 'Yeniden değerlendi.');
        redirect('/yonetim/radar/' . $listing['id']);
    }

    public function settings(): void
    {
        $user = Auth::require();
        view('admin/radar/settings', [
            'title' => 'Radar ayarları – Öz Oto Yönetim',
            'user' => $user,
            'apiKeySet' => SearchEngine::configured(),
            'enabled' => Settings::get('search_enabled') === '1',
            'queries' => implode("\n", SearchEngine::queries()),
            'freshness' => Settings::get('search_freshness') ?: 'pd',
            'interval' => (int) (Settings::get('search_interval') ?: 180),
            'mail' => [
                'enabled' => Settings::get('mail_enabled') === '1',
                'host' => Settings::get('mail_host') ?? 'localhost',
                'port' => Settings::get('mail_port') ?? '143',
                'security' => Settings::get('mail_security') ?? 'none',
                'user' => Settings::get('mail_user') ?? 'radar@ozoto.online',
                'pass_set' => Settings::secret('mail_pass') !== '',
                'folder' => Settings::get('mail_folder') ?? 'INBOX',
                'senders' => implode("\n", EmailNotifications::allowedSenders()),
                'delete_days' => Settings::get('mail_delete_days') ?? '7',
            ],
            'preview' => Session::pull('preview'),
            'diagnostics' => Diagnostics::last(),
            'probe' => Diagnostics::lastProbe(),
            'telegram' => [
                'token_set' => Settings::secret('tg_token') !== '',
                'bot' => (string) Settings::get('tg_bot', ''),
                'code' => (string) Settings::get('tg_code', ''),
                'chat_name' => (string) Settings::get('tg_chat_name', ''),
                'linked' => Telegram::configured(),
                'enabled' => Settings::get('tg_enabled') === '1',
                'min_score' => Telegram::minScore(),
            ],
            'extension' => [
                'configured' => (string) Settings::get('extension_token_hash', '') !== '',
                'token' => Session::pull('extension_token'),
                'zip' => class_exists(\ZipArchive::class),
            ],
            'cronUrl' => site_url('/cron/radar?anahtar=' . Collector::cronKey()),
            'runs' => Collector::recentRuns(),
            'notice' => Session::pull('notice'),
        ], 'admin/layout');
    }

    public function saveSettings(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        $key = trim((string) ($_POST['search_api_key'] ?? ''));
        if ($key !== '') {
            Settings::setSecret('search_api_key', $key);
        }
        if (!empty($_POST['search_api_key_clear'])) {
            Settings::setSecret('search_api_key', '');
        }
        Settings::set('search_enabled', empty($_POST['search_enabled']) ? '0' : '1');
        $queries = trim(str_replace("\r\n", "\n", (string) ($_POST['search_queries'] ?? '')));
        Settings::set('search_queries', mb_substr($queries !== '' ? $queries : SearchEngine::DEFAULT_QUERIES, 0, 10000));
        Settings::set('search_interval', (string) max(15, min(1440, (int) ($_POST['search_interval'] ?? 180))));
        $freshness = (string) ($_POST['search_freshness'] ?? 'pd');
        Settings::set('search_freshness', in_array($freshness, ['pd', 'pw', 'pm'], true) ? $freshness : 'pd');
        Session::flash('notice', 'Ayarlar kaydedildi.');
        redirect('/yonetim/radar/ayarlar');
    }

    /** Telegram bildirimi: bot anahtarı, eşik puanı, aç/kapa. */
    public function saveTelegram(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        $messages = [];
        $token = trim((string) ($_POST['tg_token'] ?? ''));
        if ($token !== '') {
            try {
                $messages[] = 'Bot kaydedildi: @' . Telegram::saveToken($token) . '. Şimdi "Telegram\'da botu aç" ile sohbeti bağlayın.';
            } catch (\Throwable $e) {
                Session::flash('notice', 'Bot kaydedilemedi: ' . $e->getMessage());
                redirect('/yonetim/radar/ayarlar#telegram');
            }
        }
        Settings::set('tg_min_score', (string) max(0, min(100, (int) ($_POST['tg_min_score'] ?? Telegram::DEFAULT_MIN_SCORE))));
        $enable = !empty($_POST['tg_enabled']);
        if ($enable && Settings::get('tg_enabled') !== '1') {
            // Açıldığı andan önceki ilanlar için toplu bildirim gönderilmez.
            Settings::set('tg_since', now());
        }
        Settings::set('tg_enabled', $enable ? '1' : '0');
        $messages[] = 'Telegram ayarları kaydedildi.';
        Session::flash('notice', implode(' ', $messages));
        redirect('/yonetim/radar/ayarlar#telegram');
    }

    public function linkTelegram(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        try {
            Session::flash('notice', 'Telegram bağlandı: ' . Telegram::link() . '. Telefonunuza onay mesajı gitti.');
        } catch (\Throwable $e) {
            Session::flash('notice', 'Bağlanamadı: ' . $e->getMessage());
        }
        redirect('/yonetim/radar/ayarlar#telegram');
    }

    public function testTelegram(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        $sample = [
            'id' => 0, 'brand' => 'Renault', 'model' => 'Clio 1.5 dCi Touch', 'title' => '', 'model_year' => 2019, 'km' => 85000,
            'damage' => 'Boyasız', 'city' => 'İstanbul', 'price' => 640000, 'market_value' => 840000, 'discount_pct' => 23.8,
            'score' => 72, 'urgent' => 1, 'is_auction' => 0, 'auction_ends_at' => null, 'url' => site_url('/'),
        ];
        try {
            Telegram::send("🧪 <b>Deneme mesajı</b> — gerçek bir kelepir bildirimi böyle görünür:\n\n" . Telegram::format($sample));
            Session::flash('notice', 'Deneme mesajı gönderildi.');
        } catch (\Throwable $e) {
            Session::flash('notice', 'Gönderilemedi: ' . $e->getMessage());
        }
        redirect('/yonetim/radar/ayarlar#telegram');
    }

    public function runNow(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        @set_time_limit(90);
        $summary = Collector::run(40, true);
        Session::flash('notice', 'Tarama bitti: ' . implode(' · ', $summary['messages']));
        redirect((string) ($_POST['back'] ?? '') === 'ayarlar' ? '/yonetim/radar/ayarlar' : '/yonetim/radar');
    }

    public function saveSearch(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80);
        $query = mb_substr(ltrim((string) ($_POST['query'] ?? ''), '?'), 0, 1000);
        if ($name !== '') {
            Database::connection()->prepare('INSERT INTO radar_saved_searches (name, query_string, created_at) VALUES (?, ?, ?)')
                ->execute([$name, $query, now()]);
            Session::flash('notice', '"' . $name . '" araması kaydedildi.');
        }
        redirect('/yonetim/radar' . ($query !== '' ? '?' . $query : ''));
    }

    public function deleteSearch(string $id): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        Database::connection()->prepare('DELETE FROM radar_saved_searches WHERE id = ?')->execute([(int) $id]);
        redirect('/yonetim/radar');
    }

    public function saveMailSettings(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        $str = static fn (string $k): string => trim((string) ($_POST[$k] ?? ''));
        Settings::set('mail_enabled', empty($_POST['mail_enabled']) ? '0' : '1');
        Settings::set('mail_host', mb_substr($str('mail_host') ?: 'localhost', 0, 200));
        Settings::set('mail_port', (string) max(1, min(65535, (int) ($str('mail_port') ?: 143))));
        Settings::set('mail_security', in_array($str('mail_security'), ['none', 'ssl', 'starttls'], true) ? $str('mail_security') : 'none');
        Settings::set('mail_user', mb_substr($str('mail_user'), 0, 200));
        if ((string) ($_POST['mail_pass'] ?? '') !== '') {
            Settings::setSecret('mail_pass', (string) $_POST['mail_pass']);
        }
        Settings::set('mail_folder', mb_substr($str('mail_folder') ?: 'INBOX', 0, 100));
        Settings::set('mail_senders', mb_substr($str('mail_senders') ?: EmailNotifications::DEFAULT_SENDERS, 0, 2000));
        Settings::set('mail_delete_days', $str('mail_delete_days') === '' ? '7' : (string) max(0, min(365, (int) $str('mail_delete_days'))));
        Session::flash('notice', 'Posta kutusu ayarları kaydedildi.');
        redirect('/yonetim/radar/ayarlar#eposta');
    }

    public function testMail(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        @set_time_limit(60);
        try {
            $message = EmailNotifications::test();
        } catch (\Throwable $e) {
            $message = 'Bağlantı başarısız: ' . $e->getMessage();
        }
        Session::flash('notice', $message);
        redirect('/yonetim/radar/ayarlar#eposta');
    }

    /** Radar posta kutusundaki son e-postalar: üyelik doğrulama linkleri ve gelen bildirimlerin biçimi. */
    public function mailbox(): void
    {
        $user = Auth::require();
        @set_time_limit(60);
        $messages = [];
        $error = null;
        if (!EmailNotifications::configured()) {
            $error = 'Önce Radar ayarlarından posta kutusunu bağlayın.';
        } else {
            try {
                $messages = EmailNotifications::recent(10);
            } catch (\Throwable $e) {
                $error = 'Posta kutusu okunamadı: ' . $e->getMessage();
            }
        }
        view('admin/radar/mailbox', [
            'title' => 'Radar posta kutusu – Öz Oto Yönetim',
            'user' => $user,
            'messages' => $messages,
            'error' => $error,
            'mailUser' => (string) Settings::get('mail_user'),
        ], 'admin/layout');
    }

    /** Kamu ihale kaynaklarına bu sunucudan erişilebiliyor mu? (Toplayıcılar buna göre yazılacak.) */
    public function diagnose(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        @set_time_limit(150);
        $ok = count(array_filter(Diagnostics::run(), static fn (array $r): bool => $r['status'] >= 200 && $r['status'] < 300));
        Session::flash('notice', 'Kaynak testi bitti: ' . $ok . ' kaynak erişilebilir. Sonuçlar aşağıda.');
        redirect('/yonetim/radar/ayarlar#kaynaklar');
    }

    /** Kamu ihale sitesinin bir sayfasını sunucudan inceler (toplayıcı yazmak için yapı ve veri adresleri). */
    public function probe(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        @set_time_limit(60);
        $method = ($_POST['method'] ?? '') === 'POST' ? 'POST' : 'GET';
        try {
            $result = Diagnostics::probe(trim((string) ($_POST['url'] ?? '')), $method, (string) ($_POST['body'] ?? ''));
            Session::flash('notice', 'İnceleme bitti: HTTP ' . $result['status'] . ', ' . count($result['endpoints']) . ' adres bulundu.');
        } catch (\Throwable $e) {
            Session::flash('notice', 'İncelenemedi: ' . $e->getMessage());
        }
        redirect('/yonetim/radar/ayarlar#inceleme');
    }

    /** Eklenti için yeni anahtar üretir; anahtar yalnızca bir kez gösterilir, veritabanında özeti tutulur. */
    public function extensionToken(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        $token = 'oz_' . bin2hex(random_bytes(20));
        Settings::set('extension_token_hash', hash('sha256', $token));
        Session::flash('extension_token', $token);
        redirect('/yonetim/radar/ayarlar#eklenti');
    }

    /** Eklenti klasörünü zip olarak indirir (Chrome → "Paketlenmemiş öğe yükle" için). */
    public function extensionDownload(): void
    {
        Auth::require();
        $dir = BASE_PATH . '/extension';
        if (!class_exists(\ZipArchive::class) || !is_dir($dir)) {
            Session::flash('notice', 'Sunucuda zip desteği yok. Eklenti klasörünü GitHub deposundaki "extension" klasöründen indirin.');
            redirect('/yonetim/radar/ayarlar#eklenti');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ozr');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = 'oz-oto-radar/' . str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
            $zip->addFile($file->getPathname(), $relative);
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="oz-oto-radar.zip"');
        header('Content-Length: ' . (string) filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
    }

    /** Bir bildirim e-postasını (.eml) yükleyip neler çıkarıldığını gösterir; istenirse Radar'a ekler. */
    public function previewMail(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        $file = $_FILES['eml'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            Session::flash('notice', 'E-posta dosyası yüklenemedi.');
            redirect('/yonetim/radar/ayarlar#eposta');
        }
        $raw = (string) file_get_contents((string) $file['tmp_name'], false, null, 0, 5 * 1024 * 1024);
        $save = !empty($_POST['save']);
        $result = EmailNotifications::process($raw, null, $save);
        Session::flash('preview', [
            'from' => $result['from'],
            'subject' => $result['subject'],
            'allowed' => $result['allowed'],
            'saved' => $save,
            'added' => $result['added'],
            'listings' => array_map(static fn (array $l): array => array_intersect_key($l, array_flip(['url', 'title', 'brand', 'model', 'model_year', 'km', 'price', 'city'])), array_slice($result['listings'], 0, 100)),
        ]);
        redirect('/yonetim/radar/ayarlar#onizleme');
    }

    /**
     * Elle ekleme/düzenleme formu. Boş bırakılan alanlar linkteki metinden tamamlanır.
     *
     * @param array<string, mixed> $in
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function validate(array $in, bool $requireUrlOrBrand): array
    {
        $str = static fn (string $k): string => trim((string) ($in[$k] ?? ''));
        $num = static fn (string $k): ?int => digits($str($k)) === '' || strlen(digits($str($k))) > 10 ? null : (int) digits($str($k));
        $errors = [];

        $url = $str('url');
        if ($url !== '' && (filter_var($url, FILTER_VALIDATE_URL) === false || !ListingParser::isWebUrl($url))) {
            $errors[] = 'Link geçerli değil.';
        }
        $parsed = $url !== '' ? ListingParser::parseText(ListingParser::parseUrl($url)['slug_text'] . ' ' . $str('title')) : ListingParser::parseText($str('title'));

        $brand = $str('brand') !== '' ? $str('brand') : (string) ($parsed['brand'] ?? '');
        if ($brand !== '' && !in_array($brand, Catalog::brands(), true)) {
            $errors[] = 'Marka listede yok.';
        }
        if ($requireUrlOrBrand && $url === '' && $brand === '') {
            $errors[] = 'En azından link veya marka girin.';
        }
        $year = $num('model_year') ?? $parsed['model_year'];
        if ($year !== null && ($year < 1970 || $year > (int) date('Y') + 1)) {
            $errors[] = 'Model yılı geçersiz.';
        }
        $endsAt = $str('auction_ends_at');
        $endsTs = $endsAt !== '' ? strtotime($endsAt) : false;

        $data = [
            'url' => $url !== '' ? $url : null,
            'title' => mb_substr($str('title'), 0, 300) ?: null,
            'brand' => $brand !== '' ? $brand : null,
            'model' => mb_substr($str('model'), 0, 120) ?: ($parsed['model'] ?? null),
            'model_year' => $year,
            'km' => $num('km') ?? $parsed['km'],
            'price' => $num('price') ?? $parsed['price'],
            'city' => in_array($str('city'), Catalog::cities(), true) ? $str('city') : ($parsed['city'] ?? null),
            'damage' => in_array($str('damage'), Catalog::damages(), true) ? $str('damage') : ($parsed['damage'] ?? null),
            'gearbox' => in_array($str('gearbox'), Catalog::gearboxes(), true) ? $str('gearbox') : ($parsed['gearbox'] ?? null),
            'fuel' => in_array($str('fuel'), Catalog::fuels(), true) ? $str('fuel') : ($parsed['fuel'] ?? null),
            'description' => mb_substr($str('description'), 0, 2000) ?: null,
            'is_auction' => empty($in['is_auction']) ? 0 : 1,
            'auction_ends_at' => $endsTs !== false ? date('Y-m-d H:i:s', $endsTs) : null,
        ];
        return [$data, $errors];
    }

    /**
     * @param array{id: int, email: string} $user
     * @param array<string, mixed> $old
     * @param list<string> $errors
     */
    private function renderForm(array $user, array $old, array $errors, int $status = 200): void
    {
        view('admin/radar/form', [
            'title' => 'İlan ekle – Kelepir Radar',
            'user' => $user,
            'old' => array_map(static fn ($v): string => is_string($v) ? $v : '', $old),
            'errors' => $errors,
            'options' => $this->options(),
        ], 'admin/layout', $status);
    }

    /** @return array<string, list<string|int>> */
    private function options(): array
    {
        return [
            'brands' => array_values(array_diff(Catalog::brands(), ['Diğer'])),
            'cities' => Catalog::cities(),
            'years' => Catalog::years(),
            'damages' => Catalog::damages(),
            'gearboxes' => Catalog::gearboxes(),
            'fuels' => Catalog::fuels(),
        ];
    }

    /** @return array<string, mixed> */
    private function find(string $id): array
    {
        return Listings::find((int) $id) ?? abort(404);
    }
}
