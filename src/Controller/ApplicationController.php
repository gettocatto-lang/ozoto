<?php

declare(strict_types=1);

namespace Ozoto\Controller;

use Ozoto\App;
use Ozoto\Csrf;
use Ozoto\Database;
use Ozoto\Mailer;
use Ozoto\RateLimiter;
use Ozoto\Session;
use Ozoto\Support\Catalog;
use Ozoto\Support\Phone;
use Ozoto\Support\PhotoUploader;

final class ApplicationController
{
    public function create(): void
    {
        Session::start();
        $_SESSION['form_started'] = time();
        $this->renderForm([
            'brand' => (string) ($_GET['marka'] ?? ''),
            'model_year' => (string) ($_GET['yil'] ?? ''),
        ], []);
    }

    public function store(): void
    {
        // post_max_size aşılırsa PHP $_POST'u tamamen boşaltır.
        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->renderForm([], ['photos' => 'Yüklediğiniz fotoğrafların toplam boyutu çok büyük. Daha az veya daha küçük fotoğraf seçin.'], 413);
            return;
        }
        if (!Csrf::valid()) {
            $this->renderForm($_POST, ['_form' => 'Sayfanın süresi dolmuş. Lütfen formu tekrar gönderin.'], 419);
            return;
        }

        // Bot tuzakları: gizli alan doldurulmuşsa veya form 3 saniyeden hızlı gönderildiyse kaydetmeden teşekkür et.
        $started = (int) ($_SESSION['form_started'] ?? 0);
        if (trim((string) ($_POST['website'] ?? '')) !== '' || ($started > 0 && time() - $started < 3)) {
            redirect('/basvuru-alindi');
        }

        if (!App::installed()) {
            $this->renderForm($_POST, ['_form' => 'Sistem şu an kurulum aşamasında. Lütfen bizi telefonla arayın.'], 503);
            return;
        }

        [$data, $errors] = $this->validate($_POST);
        [$photos, $photoError] = PhotoUploader::collect($_FILES['photos'] ?? null);
        if ($photoError !== null) {
            $errors['photos'] = $photoError;
        }
        if ($errors !== []) {
            $this->renderForm($_POST, $errors, 422);
            return;
        }

        $ipHash = client_ip_hash();
        if (!RateLimiter::attempt('apply', $ipHash, 5, 3600)) {
            $this->renderForm($_POST, ['_form' => 'Kısa sürede çok fazla başvuru yapıldı. Lütfen daha sonra tekrar deneyin veya bizi arayın.'], 429);
            return;
        }

        $ref = $this->save($data, $photos, $ipHash);
        $this->notify($ref, $data, count($photos));

        Session::flash('ref', $ref);
        unset($_SESSION['form_started']);
        redirect('/basvuru-alindi');
    }

    public function thanks(): void
    {
        view('thanks', [
            'title' => 'Başvurunuz alındı – Ozoto',
            'noindex' => true,
            'ref' => Session::pull('ref'),
        ]);
    }

    /**
     * @param array<string, mixed> $in
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validate(array $in): array
    {
        $str = static fn (string $key): string => trim((string) ($in[$key] ?? ''));
        $optionalInt = static function (string $raw): ?int {
            $d = digits($raw);
            return $d === '' ? null : (strlen($d) > 9 ? PHP_INT_MAX : (int) $d);
        };
        $errors = [];

        $name = preg_replace('/\s+/u', ' ', $str('full_name')) ?? '';
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            $errors['full_name'] = 'Lütfen adınızı ve soyadınızı yazın.';
        }

        $phone = Phone::normalize($str('phone'));
        if ($phone === null) {
            $errors['phone'] = 'Geçerli bir telefon numarası yazın (ör. 0532 123 45 67).';
        }

        $city = $str('city');
        if (!in_array($city, Catalog::cities(), true)) {
            $errors['city'] = 'Aracın bulunduğu ili seçin.';
        }

        $brand = $str('brand');
        if (!in_array($brand, Catalog::brands(), true)) {
            $errors['brand'] = 'Marka seçin.';
        }

        $model = $str('model');
        if (mb_strlen($model) < 1 || mb_strlen($model) > 80) {
            $errors['model'] = 'Model ve paket bilgisini yazın (ör. Corolla 1.6 Vision).';
        }

        $year = (int) digits($str('model_year'));
        if ($year < 1970 || $year > (int) date('Y') + 1) {
            $errors['model_year'] = 'Model yılını seçin.';
        }

        $km = $optionalInt($str('km'));
        if ($km === null || $km > 2_000_000) {
            $errors['km'] = 'Kilometreyi yazın (ör. 125.000).';
        }

        foreach (['fuel' => Catalog::fuels(), 'gearbox' => Catalog::gearboxes(), 'damage' => Catalog::damages(), 'urgency' => Catalog::urgencies()] as $key => $options) {
            if (!in_array($str($key), $options, true)) {
                $errors[$key] = 'Lütfen bir seçim yapın.';
            }
        }

        $tramer = $optionalInt($str('tramer_amount'));
        if ($tramer !== null && $tramer > 50_000_000) {
            $errors['tramer_amount'] = 'Tramer tutarını kontrol edin.';
        }

        $price = $optionalInt($str('expected_price'));
        if ($price !== null && ($price < 1_000 || $price > 500_000_000)) {
            $errors['expected_price'] = 'Beklediğiniz fiyatı TL olarak yazın (ör. 850.000).';
        }

        $notes = $str('notes');
        if (mb_strlen($notes) > 2000) {
            $errors['notes'] = 'Açıklama en fazla 2000 karakter olabilir.';
        }

        if (empty($in['kvkk'])) {
            $errors['kvkk'] = 'Devam etmek için aydınlatma metnini okuduğunuzu onaylayın.';
        }

        $data = [
            'full_name' => $name,
            'phone' => (string) $phone,
            'city' => $city,
            'district' => mb_substr($str('district'), 0, 60) ?: null,
            'brand' => $brand,
            'model' => $model,
            'model_year' => $year,
            'km' => (int) $km,
            'fuel' => $str('fuel'),
            'gearbox' => $str('gearbox'),
            'damage' => $str('damage'),
            'tramer_amount' => $tramer,
            'expected_price' => $price,
            'urgency' => $str('urgency'),
            'notes' => $notes !== '' ? $notes : null,
            'kvkk_consent' => 1,
            'marketing_consent' => empty($in['marketing']) ? 0 : 1,
        ];
        return [$data, $errors];
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{tmp: string, mime: string, ext: string, size: int}> $photos
     */
    private function save(array $data, array $photos, string $ipHash): string
    {
        $pdo = Database::connection();
        $stored = [];
        $pdo->beginTransaction();
        try {
            $ref = $this->uniqueRef();
            $row = $data + [
                'ref_code' => $ref,
                'status' => 'new',
                'ip_hash' => $ipHash,
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $columns = array_keys($row);
            $pdo->prepare(sprintf(
                'INSERT INTO applications (%s) VALUES (%s)',
                implode(', ', $columns),
                implode(', ', array_fill(0, count($columns), '?'))
            ))->execute(array_values($row));
            $id = (int) $pdo->lastInsertId();

            $insertPhoto = $pdo->prepare(
                'INSERT INTO application_photos (application_id, file_name, mime, size, created_at) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($photos as $photo) {
                $name = PhotoUploader::store($photo);
                $stored[] = $name;
                $insertPhoto->execute([$id, $name, $photo['mime'], $photo['size'], now()]);
            }
            $pdo->commit();
            return $ref;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            array_walk($stored, [PhotoUploader::class, 'delete']);
            throw $e;
        }
    }

    private function uniqueRef(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $stmt = Database::connection()->prepare('SELECT 1 FROM applications WHERE ref_code = ?');
        do {
            $ref = 'OZ';
            for ($i = 0; $i < 6; $i++) {
                $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $stmt->execute([$ref]);
        } while ($stmt->fetchColumn() !== false);
        return $ref;
    }

    /** @param array<string, mixed> $d */
    private function notify(string $ref, array $d, int $photoCount): void
    {
        $to = (string) config('notify_email');
        if ($to === '') {
            return;
        }
        $body = implode("\n", [
            'Yeni nakit teklif başvurusu: ' . $ref,
            '',
            'Ad soyad : ' . $d['full_name'],
            'Telefon  : ' . Phone::pretty((string) $d['phone']),
            'Konum    : ' . $d['city'] . ($d['district'] ? ' / ' . $d['district'] : ''),
            'Araç     : ' . $d['brand'] . ' ' . $d['model'] . ' (' . $d['model_year'] . ')',
            'Km       : ' . format_number((int) $d['km']),
            'Durum    : ' . $d['damage'] . ', ' . $d['fuel'] . ', ' . $d['gearbox'],
            'Beklenen : ' . format_tl($d['expected_price']),
            'Aciliyet : ' . $d['urgency'],
            'Fotoğraf : ' . $photoCount,
            '',
            'Yönetim paneli: ' . site_url('/yonetim'),
        ]);
        Mailer::send($to, 'Yeni başvuru ' . $ref . ' – ' . $d['brand'] . ' ' . $d['model'], $body);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function renderForm(array $old, array $errors, int $status = 200): void
    {
        view('apply', [
            'title' => 'Aracımı Hemen Sat – Ücretsiz Nakit Teklif Al | Ozoto',
            'description' => 'Acil satılık aracınız için 2 dakikada başvurun. Uzmanımız sizi arasın, aracınıza hızlı nakit teklif versin. Hasarlı ve yüksek kilometreli araçlar dahil.',
            'canonical' => site_url('/aracimi-hemen-sat'),
            'old' => array_map(static fn ($v) => is_string($v) ? $v : '', $old),
            'errors' => $errors,
            'cities' => Catalog::cities(),
            'brands' => Catalog::brands(),
            'years' => Catalog::years(),
            'fuels' => Catalog::fuels(),
            'gearboxes' => Catalog::gearboxes(),
            'damages' => Catalog::damages(),
            'urgencies' => Catalog::urgencies(),
            'maxPhotos' => PhotoUploader::MAX_FILES,
            'jsonLd' => [[
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana sayfa', 'item' => site_url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Aracımı hemen sat', 'item' => site_url('/aracimi-hemen-sat')],
                ],
            ]],
        ], 'layout', $status);
    }
}
