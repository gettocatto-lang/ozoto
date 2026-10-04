<?php

declare(strict_types=1);

namespace Ozoto\Support;

final class PhotoUploader
{
    public const MAX_FILES = 10;
    public const MAX_BYTES = 12 * 1024 * 1024;

    private const TYPES = [
        IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
        IMAGETYPE_PNG => ['image/png', 'png'],
        IMAGETYPE_WEBP => ['image/webp', 'webp'],
    ];

    /**
     * $_FILES['photos'] dizisini doğrular.
     *
     * @return array{0: list<array{tmp: string, mime: string, ext: string, size: int}>, 1: ?string}
     */
    public static function collect(mixed $files): array
    {
        if (!is_array($files) || !is_array($files['error'] ?? null)) {
            return [[], null];
        }

        $valid = [];
        foreach ($files['error'] as $i => $error) {
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                return [[], 'Fotoğraflardan biri çok büyük. Lütfen daha küçük bir fotoğraf seçin.'];
            }
            if ($error !== UPLOAD_ERR_OK) {
                return [[], 'Fotoğraflar yüklenemedi. Lütfen tekrar deneyin.'];
            }

            $tmp = (string) $files['tmp_name'][$i];
            $size = (int) $files['size'][$i];
            if (!is_uploaded_file($tmp) || $size > self::MAX_BYTES) {
                return [[], 'Fotoğraflardan biri çok büyük. Lütfen daha küçük bir fotoğraf seçin.'];
            }
            $info = @getimagesize($tmp);
            if ($info === false || !isset(self::TYPES[$info[2]])) {
                return [[], 'Sadece JPG, PNG veya WEBP fotoğraf yükleyebilirsiniz.'];
            }
            [$mime, $ext] = self::TYPES[$info[2]];
            $valid[] = ['tmp' => $tmp, 'mime' => $mime, 'ext' => $ext, 'size' => $size];
        }

        if (count($valid) > self::MAX_FILES) {
            return [[], 'En fazla ' . self::MAX_FILES . ' fotoğraf yükleyebilirsiniz.'];
        }
        return [$valid, null];
    }

    /**
     * Fotoğrafı web'den erişilemeyen storage/uploads klasörüne taşır.
     *
     * @param array{tmp: string, mime: string, ext: string, size: int} $file
     * @return string storage/ klasörüne göre dosya yolu
     */
    public static function store(array $file): string
    {
        $dir = 'uploads/' . date('Y/m');
        $absDir = BASE_PATH . '/storage/' . $dir;
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new \RuntimeException('Yükleme klasörü oluşturulamadı: ' . $absDir);
        }
        $name = $dir . '/' . bin2hex(random_bytes(16)) . '.' . $file['ext'];
        if (!move_uploaded_file($file['tmp'], BASE_PATH . '/storage/' . $name)) {
            throw new \RuntimeException('Fotoğraf kaydedilemedi: ' . $name);
        }
        return $name;
    }

    public static function delete(string $name): void
    {
        $path = BASE_PATH . '/storage/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
