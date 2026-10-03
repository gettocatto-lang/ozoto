<?php

declare(strict_types=1);

namespace Ozoto\Controller;

use Ozoto\Auth;
use Ozoto\Csrf;
use Ozoto\Database;
use Ozoto\Radar\Collector;
use Ozoto\Radar\ListingParser;
use Ozoto\Radar\Listings;
use Ozoto\Radar\Sources\SearchEngine;
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
            Settings::set('search_api_key', $key);
        }
        if (!empty($_POST['search_api_key_clear'])) {
            Settings::set('search_api_key', '');
        }
        Settings::set('search_enabled', empty($_POST['search_enabled']) ? '0' : '1');
        $queries = trim(str_replace("\r\n", "\n", (string) ($_POST['search_queries'] ?? '')));
        Settings::set('search_queries', mb_substr($queries !== '' ? $queries : SearchEngine::DEFAULT_QUERIES, 0, 10000));
        $freshness = (string) ($_POST['search_freshness'] ?? 'pd');
        Settings::set('search_freshness', in_array($freshness, ['pd', 'pw', 'pm'], true) ? $freshness : 'pd');
        Session::flash('notice', 'Ayarlar kaydedildi.');
        redirect('/yonetim/radar/ayarlar');
    }

    public function runNow(): void
    {
        Auth::require();
        if (!Csrf::valid()) {
            abort(419);
        }
        @set_time_limit(90);
        $summary = Collector::run(40);
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
