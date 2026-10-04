<?php

declare(strict_types=1);

// Radar şablonlarında ortak küçük yardımcılar (bir kez yüklenir).

if (!function_exists('radar_score_class')) {
    function radar_score_class(?int $score): string
    {
        return match (true) {
            $score === null => 'score-none',
            $score >= 70 => 'score-hot',
            $score >= 50 => 'score-good',
            $score >= 30 => 'score-ok',
            default => 'score-low',
        };
    }

    function radar_discount(mixed $pct): string
    {
        if ($pct === null || $pct === '') {
            return '—';
        }
        $pct = (float) $pct;
        return $pct >= 0
            ? '%' . number_format($pct, 0, ',', '.') . ' altında'
            : '%' . number_format(abs($pct), 0, ',', '.') . ' üstünde';
    }

    function radar_countdown(?string $endsAt): string
    {
        if ($endsAt === null || $endsAt === '') {
            return '';
        }
        $seconds = strtotime($endsAt) - time();
        if ($seconds <= 0) {
            return 'bitti';
        }
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        return $days > 0 ? $days . ' gün ' . $hours . ' sa' : $hours . ' sa ' . intdiv($seconds % 3600, 60) . ' dk';
    }

    function radar_title(array $l): string
    {
        $vehicle = trim(implode(' ', array_filter([(string) ($l['brand'] ?? ''), (string) ($l['model'] ?? '')])));
        return $vehicle !== '' ? $vehicle : ((string) ($l['title'] ?? '') ?: 'Başlıksız ilan');
    }
}
