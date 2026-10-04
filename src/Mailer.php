<?php

declare(strict_types=1);

namespace Ozoto;

final class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $from = (string) App::config('mail_from', 'noreply@ozoto.online');
        $headers = [
            'From' => 'Ozoto <' . $from . '>',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
        ];
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
        if (!$ok) {
            error_log('E-posta gönderilemedi: ' . $to);
        }
        return $ok;
    }
}
