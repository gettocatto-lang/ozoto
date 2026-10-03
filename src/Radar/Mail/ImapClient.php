<?php

declare(strict_types=1);

namespace Ozoto\Radar\Mail;

use Ozoto\Support\Http;

/**
 * Küçük IMAP istemcisi. PHP 8.4'te imap eklentisi çekirdekten çıktığı için soketle konuşur.
 * Sadece Radar'ın ihtiyacı olan komutlar: LOGIN, SELECT, UID SEARCH, UID FETCH, UID STORE, EXPUNGE.
 */
final class ImapClient
{
    /** @var resource|null */
    private $stream = null;
    private int $tag = 0;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $security,
        private readonly int $timeout = 20,
    ) {
    }

    public function connect(): void
    {
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $this->host];
        if (($caFile = Http::caFile()) !== null) {
            $ssl['cafile'] = $caFile;
        }
        $remote = ($this->security === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $stream = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $ssl]));
        if ($stream === false) {
            throw new \RuntimeException(sprintf('Posta sunucusuna bağlanılamadı (%s:%d): %s', $this->host, $this->port, $errstr ?: 'bilinmeyen hata'));
        }
        $this->stream = $stream;
        stream_set_timeout($this->stream, $this->timeout);

        $greeting = $this->readLine();
        if (!preg_match('/^\* (OK|PREAUTH)/i', $greeting)) {
            throw new \RuntimeException('Posta sunucusu beklenmeyen yanıt verdi: ' . mb_substr($greeting, 0, 120));
        }
        if ($this->security === 'starttls') {
            $this->command('STARTTLS');
            if (!@stream_socket_enable_crypto($this->stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('STARTTLS ile güvenli bağlantı kurulamadı.');
            }
        }
    }

    public function login(string $user, string $password): void
    {
        try {
            $this->command('LOGIN ' . self::quote($user) . ' ' . self::quote($password));
        } catch (\RuntimeException) {
            throw new \RuntimeException('Posta kutusuna giriş yapılamadı: kullanıcı adı veya şifre hatalı.');
        }
    }

    /** @return int klasördeki e-posta sayısı */
    public function select(string $folder): int
    {
        $exists = 0;
        foreach ($this->command('SELECT ' . self::quote($folder))['lines'] as $line) {
            if (preg_match('/^\* (\d+) EXISTS/i', $line, $m) === 1) {
                $exists = (int) $m[1];
            }
        }
        return $exists;
    }

    /** @return list<int> */
    public function search(string $criteria): array
    {
        $uids = [];
        foreach ($this->command('UID SEARCH ' . $criteria)['lines'] as $line) {
            if (preg_match('/^\* SEARCH\s*(.*)$/i', $line, $m) === 1) {
                foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $uid) {
                    if (ctype_digit($uid)) {
                        $uids[] = (int) $uid;
                    }
                }
            }
        }
        sort($uids);
        return $uids;
    }

    /** Tam e-postayı getirir; \Seen bayrağını değiştirmez (BODY.PEEK). */
    public function fetch(int $uid): ?string
    {
        $literals = $this->command('UID FETCH ' . $uid . ' (BODY.PEEK[])')['literals'];
        return $literals[0] ?? null;
    }

    /** @param list<int> $uids */
    public function addFlags(array $uids, string $flags): void
    {
        foreach (array_chunk($uids, 200) as $chunk) {
            $this->command('UID STORE ' . implode(',', $chunk) . ' +FLAGS.SILENT (' . $flags . ')');
        }
    }

    public function expunge(): void
    {
        $this->command('EXPUNGE');
    }

    public function logout(): void
    {
        if ($this->stream !== null) {
            try {
                $this->command('LOGOUT');
            } catch (\Throwable) {
                // bağlantı zaten kapanmış olabilir
            }
            fclose($this->stream);
            $this->stream = null;
        }
    }

    /** IMAP tarih biçimi (ay adları İngilizce): 04-Oct-2026 */
    public static function date(int $timestamp): string
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return date('d', $timestamp) . '-' . $months[(int) date('n', $timestamp) - 1] . '-' . date('Y', $timestamp);
    }

    /** @return array{lines: list<string>, literals: list<string>} */
    private function command(string $command): array
    {
        if ($this->stream === null) {
            throw new \RuntimeException('Posta sunucusuna bağlı değil.');
        }
        $tag = 'R' . str_pad((string) ++$this->tag, 4, '0', STR_PAD_LEFT);
        fwrite($this->stream, $tag . ' ' . $command . "\r\n");

        $lines = [];
        $literals = [];
        while (true) {
            $line = $this->readLine();
            if (preg_match('/\{(\d+)\}$/', $line, $m) === 1) {
                $lines[] = $line;
                $literals[] = $this->readBytes((int) $m[1]);
                continue;
            }
            if (str_starts_with($line, $tag . ' ')) {
                if (preg_match('/^' . $tag . ' OK/i', $line) !== 1) {
                    // Komutun kendisi (şifre içerebilir) mesaja eklenmez.
                    throw new \RuntimeException('Posta sunucusu hatası: ' . mb_substr(substr($line, strlen($tag) + 1), 0, 160));
                }
                return ['lines' => $lines, 'literals' => $literals];
            }
            $lines[] = $line;
        }
    }

    private function readLine(): string
    {
        $line = fgets($this->stream);
        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);
            throw new \RuntimeException(!empty($meta['timed_out']) ? 'Posta sunucusu zaman aşımına uğradı.' : 'Posta sunucusu bağlantıyı kapattı.');
        }
        return rtrim($line, "\r\n");
    }

    private function readBytes(int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->stream, min(65536, $length - strlen($data)));
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('Posta sunucusundan e-posta okunamadı.');
            }
            $data .= $chunk;
        }
        return $data;
    }

    private static function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
