<?php

declare(strict_types=1);

namespace Ozoto\Support;

/** Küçük HTTP istemcisi: curl varsa onu, yoksa PHP akışlarını kullanır. */
final class Http
{
    public const USER_AGENT = 'OzOtoRadar/1.0 (+https://ozoto.online)';

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    public static function get(string $url, array $headers = [], int $timeout = 15): array
    {
        $lines = ['User-Agent: ' . self::USER_AGENT];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $caFile = self::caFile();

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $lines,
                CURLOPT_ENCODING => '',
            ];
            if ($caFile !== null) {
                $options[CURLOPT_CAINFO] = $caFile;
            }
            curl_setopt_array($ch, $options);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            if ($body === false) {
                throw new \RuntimeException('HTTP isteği başarısız: ' . $error);
            }
            return ['status' => $status, 'body' => (string) $body];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $lines),
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => $caFile !== null ? ['cafile' => $caFile] : [],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('HTTP isteği başarısız: ' . $url);
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
                $status = (int) $m[1];
            }
        }
        return ['status' => $status, 'body' => $body];
    }

    /**
     * Windows'taki PHP'de çoğu zaman güvenilir sertifika listesi tanımlı değildir; o durumda projedeki
     * Mozilla listesi (config/cacert.pem) kullanılır. Sertifika doğrulaması hiçbir zaman kapatılmaz.
     */
    private static function caFile(): ?string
    {
        if ((string) ini_get('curl.cainfo') !== '' || (string) ini_get('openssl.cafile') !== '') {
            return null;
        }
        $bundle = BASE_PATH . '/config/cacert.pem';
        return is_file($bundle) ? $bundle : null;
    }

    /**
     * @param array<string, string> $headers
     * @return mixed
     */
    public static function json(string $url, array $headers = [], int $timeout = 15): mixed
    {
        $response = self::get($url, ['Accept' => 'application/json'] + $headers, $timeout);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new \RuntimeException('HTTP ' . $response['status'] . ': ' . mb_substr($response['body'], 0, 200));
        }
        return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
    }
}
