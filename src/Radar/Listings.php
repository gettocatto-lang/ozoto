<?php

declare(strict_types=1);

namespace Ozoto\Radar;

use Ozoto\Database;
use Ozoto\Support\Text;

/** radar_listings tablosu: ekleme/güncelleme, arama, sıralama, filtreleme. */
final class Listings
{
    public const PER_PAGE = 50;

    public const STATUSES = [
        'new' => 'Yeni',
        'favorite' => 'Favori',
        'watching' => 'Takipte',
        'offered' => 'Teklif verildi',
        'bought' => 'Alındı',
        'dismissed' => 'Elendi',
    ];

    public const SORTS = [
        'puan' => 'Kelepir puanı (yüksekten)',
        'fark' => 'Piyasa farkı (en ucuzdan)',
        'fiyat_artan' => 'Fiyat (düşükten yükseğe)',
        'fiyat_azalan' => 'Fiyat (yüksekten düşüğe)',
        'km' => 'Kilometre (düşükten)',
        'yil' => 'Model yılı (yeniden)',
        'yeni' => 'Eklenme (en yeni)',
        'ihale' => 'İhale bitişi (en yakın)',
    ];

    public const SOURCES = [
        'manuel' => 'Elle eklenen',
        'arama' => 'Arama motoru',
    ];

    private const FIELDS = [
        'site', 'source_ref', 'url', 'title', 'description', 'brand', 'model', 'model_year', 'km', 'fuel', 'gearbox',
        'damage', 'city', 'price', 'is_auction', 'auction_ends_at',
    ];

    /**
     * Aynı link daha önce eklendiyse günceller (fiyat değiştiyse geçmişe yazar), yoksa yeni kayıt açar.
     *
     * @param array<string, mixed> $data
     * @return array{id: int, created: bool}
     */
    public static function upsert(string $source, array $data): array
    {
        $pdo = Database::connection();
        $url = trim((string) ($data['url'] ?? ''));
        if ($url !== '' && !ListingParser::isWebUrl($url)) {
            $url = '';
        }
        $data['url'] = $url !== '' ? $url : null;
        $hash = $url !== '' ? ListingParser::urlHash($url) : hash('sha256', 'manuel:' . bin2hex(random_bytes(16)));
        if ($url !== '') {
            $info = ListingParser::parseUrl($url);
            $data['site'] ??= $info['site'];
            $data['source_ref'] ??= $info['source_ref'];
        }
        if (!empty($data['brand'])) {
            $data['brand'] = ListingParser::canonicalBrand((string) $data['brand']) ?? $data['brand'];
        }

        $stmt = $pdo->prepare('SELECT * FROM radar_listings WHERE url_hash = ?');
        $stmt->execute([$hash]);
        $existing = $stmt->fetch();
        $now = now();

        if ($existing) {
            $changes = ['last_seen_at' => $now, 'updated_at' => $now];
            foreach (self::FIELDS as $field) {
                // Eksik alanları doldur; elle girilmiş değerleri boş veriyle ezme.
                if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '' && ($existing[$field] === null || $existing[$field] === '')) {
                    $changes[$field] = $data[$field];
                }
            }
            $newPrice = isset($data['price']) ? (int) $data['price'] : null;
            if ($newPrice !== null && $existing['price'] !== null && $newPrice !== (int) $existing['price']) {
                $changes['price'] = $newPrice;
                $changes['price_changed_at'] = $now;
                self::recordPrice((int) $existing['id'], $newPrice);
            } elseif ($newPrice !== null && $existing['price'] === null) {
                self::recordPrice((int) $existing['id'], $newPrice);
            }
            if (count($changes) > 2) {
                $changes['needs_valuation'] = 1;
            }
            $merged = $changes + $existing;
            $changes['model_key'] = ListingParser::modelKey($merged['model']);
            $changes['search_text'] = self::searchText($merged);
            self::update((int) $existing['id'], $changes);
            return ['id' => (int) $existing['id'], 'created' => false];
        }

        $row = [];
        foreach (self::FIELDS as $field) {
            $row[$field] = $data[$field] ?? null;
        }
        $row['is_auction'] = (int) ($row['is_auction'] ?? 0);
        $slug = $url !== '' ? ListingParser::parseUrl($url)['slug_text'] : '';
        $parsed = ListingParser::parseText(trim(($row['title'] ?? '') . ' ' . ($row['description'] ?? '') . ' ' . $slug));
        // Kaynağın vermediği alanları başlık/açıklama/link metninden tamamla.
        foreach (['brand', 'model', 'model_year', 'km', 'price', 'city', 'fuel', 'gearbox', 'damage'] as $field) {
            if (($row[$field] === null || $row[$field] === '') && $parsed[$field] !== null) {
                $row[$field] = $parsed[$field];
            }
        }
        $row += [
            'source' => $source,
            'url_hash' => $hash,
            'model_key' => ListingParser::modelKey($row['model']),
            'urgent' => $parsed['urgent'] ? 1 : 0,
            'needs_valuation' => 1,
            'status' => 'new',
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $row['search_text'] = self::searchText($row);
        $columns = array_keys($row);
        $pdo->prepare(sprintf(
            'INSERT INTO radar_listings (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        ))->execute(array_values($row));
        $id = (int) $pdo->lastInsertId();
        if ($row['price'] !== null) {
            self::recordPrice($id, (int) $row['price']);
        }
        return ['id' => $id, 'created' => true];
    }

    /** @param array<string, mixed> $changes */
    public static function update(int $id, array $changes): void
    {
        if ($changes === []) {
            return;
        }
        $sets = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($changes)));
        Database::connection()->prepare('UPDATE radar_listings SET ' . $sets . ' WHERE id = ?')
            ->execute([...array_values($changes), $id]);
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM radar_listings WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function delete(int $id): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM radar_price_history WHERE listing_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM radar_listings WHERE id = ?')->execute([$id]);
    }

    public static function valuate(int $id): void
    {
        $listing = self::find($id);
        if ($listing !== null) {
            self::update($id, Valuator::valuate($listing));
        }
    }

    /** Değerlemesi bekleyen kayıtları sırayla işler; süre dolunca durur. */
    public static function valuatePending(int $limit, float $deadline): int
    {
        $stmt = Database::connection()->prepare('SELECT id FROM radar_listings WHERE needs_valuation = 1 ORDER BY id LIMIT ' . $limit);
        $stmt->execute();
        $done = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            if (microtime(true) > $deadline) {
                break;
            }
            self::valuate((int) $id);
            $done++;
        }
        return $done;
    }

    /** @return list<array{price: int, seen_at: string}> */
    public static function priceHistory(int $id): array
    {
        $stmt = Database::connection()->prepare('SELECT price, seen_at FROM radar_price_history WHERE listing_id = ? ORDER BY seen_at, id');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, string> $f filtreler (GET parametreleri)
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public static function search(array $f): array
    {
        $where = [];
        $params = [];
        $int = static fn (string $key): ?int => isset($f[$key]) && digits($f[$key]) !== '' ? (int) digits($f[$key]) : null;

        if (($q = trim($f['q'] ?? '')) !== '') {
            foreach (Text::tokens($q) as $token) {
                $where[] = "search_text LIKE ? ESCAPE '!'";
                $params[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $token) . '%';
            }
        }
        foreach (['marka' => 'brand', 'sehir' => 'city', 'kaynak' => 'site', 'hasar' => 'damage', 'vites' => 'gearbox', 'yakit' => 'fuel'] as $param => $column) {
            if (($value = trim($f[$param] ?? '')) !== '') {
                $where[] = $column . ' = ?';
                $params[] = $value;
            }
        }
        if (($model = trim($f['model'] ?? '')) !== '') {
            $where[] = 'model_key = ?';
            $params[] = ListingParser::modelKey($model);
        }
        foreach (['yil_min' => 'model_year >= ?', 'yil_max' => 'model_year <= ?', 'km_max' => 'km <= ?', 'fiyat_min' => 'price >= ?', 'fiyat_max' => 'price <= ?', 'puan_min' => 'score >= ?'] as $key => $clause) {
            if (($value = $int($key)) !== null) {
                $where[] = $clause;
                $params[] = $value;
            }
        }
        if (!empty($f['ihale'])) {
            $where[] = 'is_auction = 1';
        }
        if (!empty($f['puanli'])) {
            $where[] = 'score IS NOT NULL';
        }
        if (!empty($f['acil'])) {
            $where[] = 'urgent = 1';
        }
        $status = $f['durum'] ?? '';
        if (isset(self::STATUSES[$status])) {
            $where[] = 'status = ?';
            $params[] = $status;
        } elseif ($status !== 'hepsi') {
            $where[] = "status <> 'dismissed'";
        }

        $order = match ($f['sirala'] ?? 'puan') {
            'fark' => '(discount_pct IS NULL), discount_pct DESC',
            'fiyat_artan' => '(price IS NULL), price ASC',
            'fiyat_azalan' => '(price IS NULL), price DESC',
            'km' => '(km IS NULL), km ASC',
            'yil' => '(model_year IS NULL), model_year DESC',
            'yeni' => 'first_seen_at DESC',
            'ihale' => '(auction_ends_at IS NULL), auction_ends_at ASC',
            default => '(score IS NULL), score DESC, discount_pct DESC',
        };

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $pdo = Database::connection();
        $count = $pdo->prepare('SELECT COUNT(*) FROM radar_listings ' . $whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) ($f['sayfa'] ?? 1)), $pages);

        $stmt = $pdo->prepare('SELECT * FROM radar_listings ' . $whereSql . ' ORDER BY ' . $order . ', id DESC LIMIT '
            . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE));
        $stmt->execute($params);
        return ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return list<string> Filtre menüleri için kayıtlarda geçen farklı değerler */
    public static function distinct(string $column): array
    {
        if (!in_array($column, ['brand', 'city', 'site'], true)) {
            return [];
        }
        $stmt = Database::connection()->query("SELECT DISTINCT $column FROM radar_listings WHERE $column IS NOT NULL AND $column <> '' ORDER BY $column");
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @return array{total: int, scored: int, hot: int, pending: int} */
    public static function stats(): array
    {
        $row = Database::connection()->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN score IS NOT NULL THEN 1 ELSE 0 END) AS scored,
                    SUM(CASE WHEN score >= 60 AND status <> 'dismissed' THEN 1 ELSE 0 END) AS hot,
                    SUM(CASE WHEN needs_valuation = 1 THEN 1 ELSE 0 END) AS pending
             FROM radar_listings"
        )->fetch();
        return array_map('intval', $row ?: ['total' => 0, 'scored' => 0, 'hot' => 0, 'pending' => 0]);
    }

    private static function recordPrice(int $id, int $price): void
    {
        Database::connection()->prepare('INSERT INTO radar_price_history (listing_id, price, seen_at) VALUES (?, ?, ?)')
            ->execute([$id, $price, now()]);
    }

    /** @param array<string, mixed> $row */
    private static function searchText(array $row): string
    {
        return Text::fold(implode(' ', array_filter([
            $row['title'] ?? null, $row['brand'] ?? null, $row['model'] ?? null, $row['city'] ?? null,
            $row['source_ref'] ?? null, $row['site'] ?? null, $row['model_year'] ?? null,
        ], static fn ($v): bool => $v !== null && $v !== '')));
    }
}
