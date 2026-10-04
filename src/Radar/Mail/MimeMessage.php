<?php

declare(strict_types=1);

namespace Ozoto\Radar\Mail;

/** Ham e-postayı (RFC 822/MIME) çözer: başlıklar, HTML ve düz metin gövdesi. */
final class MimeMessage
{
    /** @param array<string, string> $headers @param list<array{type: string, body: string}> $parts */
    private function __construct(private readonly array $headers, private readonly array $parts)
    {
    }

    public static function parse(string $raw): self
    {
        $raw = str_replace("\r\n", "\n", $raw);
        [$head] = self::split($raw);
        $parts = [];
        self::parsePart($raw, $parts, 0);
        return new self(self::parseHeaders($head), $parts);
    }

    public function header(string $name): string
    {
        return self::decodeHeader($this->headers[strtolower($name)] ?? '');
    }

    public function subject(): string
    {
        return $this->header('subject');
    }

    /** Gönderenin e-posta adresi (küçük harf). */
    public function from(): string
    {
        $from = $this->header('from');
        if (preg_match('/<([^>]+)>/', $from, $m) === 1) {
            $from = $m[1];
        }
        return strtolower(trim($from, " \t\"'"));
    }

    public function html(): ?string
    {
        return $this->firstPart('text/html');
    }

    public function text(): ?string
    {
        return $this->firstPart('text/plain');
    }

    private function firstPart(string $type): ?string
    {
        foreach ($this->parts as $part) {
            if ($part['type'] === $type && trim($part['body']) !== '') {
                return $part['body'];
            }
        }
        return null;
    }

    /** @param list<array{type: string, body: string}> $parts */
    private static function parsePart(string $raw, array &$parts, int $depth): void
    {
        if ($depth > 6) {
            return;
        }
        [$head, $body] = self::split($raw);
        $headers = self::parseHeaders($head);
        $contentType = $headers['content-type'] ?? 'text/plain';
        $type = strtolower(trim(explode(';', $contentType)[0]));

        if (str_starts_with($type, 'multipart/') && preg_match('/boundary\s*=\s*"?([^";]+)"?/i', $contentType, $m) === 1) {
            $chunks = explode('--' . $m[1], $body);
            array_shift($chunks);
            foreach ($chunks as $chunk) {
                if (str_starts_with($chunk, '--')) {
                    break;
                }
                self::parsePart(ltrim($chunk, "\n"), $parts, $depth + 1);
            }
            return;
        }
        if ($type === 'message/rfc822') {
            // İletilmiş (forward) e-posta eki: içindeki e-postayı da çöz.
            self::parsePart($body, $parts, $depth + 1);
            return;
        }

        $decoded = match (strtolower(trim($headers['content-transfer-encoding'] ?? ''))) {
            'base64' => (string) base64_decode((string) preg_replace('/\s+/', '', $body)),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        $charset = preg_match('/charset\s*=\s*"?([\w\-:.]+)"?/i', $contentType, $cm) === 1 ? strtoupper($cm[1]) : 'UTF-8';
        $parts[] = ['type' => $type, 'body' => self::toUtf8($decoded, $charset)];
    }

    /** @return array{0: string, 1: string} */
    private static function split(string $raw): array
    {
        $pos = strpos($raw, "\n\n");
        return $pos === false ? [$raw, ''] : [substr($raw, 0, $pos), substr($raw, $pos + 2)];
    }

    /** @return array<string, string> */
    private static function parseHeaders(string $head): array
    {
        $headers = [];
        $unfolded = (string) preg_replace('/\n[ \t]+/', ' ', $head);
        foreach (explode("\n", $unfolded) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $name = strtolower(trim($name));
                $headers[$name] ??= trim($value);
            }
        }
        return $headers;
    }

    private static function decodeHeader(string $value): string
    {
        if (!str_contains($value, '=?')) {
            return $value;
        }
        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded)) {
                return $decoded;
            }
        }
        return function_exists('mb_decode_mimeheader') ? mb_decode_mimeheader($value) : $value;
    }

    private static function toUtf8(string $text, string $charset): string
    {
        if (in_array($charset, ['UTF-8', 'UTF8', 'US-ASCII', 'ASCII'], true)) {
            return $text;
        }
        $aliases = ['WINDOWS-1254' => 'CP1254', 'ISO-8859-9' => 'ISO-8859-9', 'LATIN5' => 'ISO-8859-9'];
        $from = $aliases[$charset] ?? $charset;
        if (function_exists('iconv')) {
            $converted = @iconv($from, 'UTF-8//IGNORE', $text);
            if (is_string($converted)) {
                return $converted;
            }
        }
        try {
            return mb_convert_encoding($text, 'UTF-8', $charset);
        } catch (\Throwable) {
            return $text;
        }
    }
}
