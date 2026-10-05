<?php

declare(strict_types=1);

namespace Ozoto\Controller;

use Ozoto\Support\Content;

final class HomeController
{
    public function index(): void
    {
        $faqs = Content::faqs();
        $phone = (string) config('site.phone');

        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'Öz Oto',
            'url' => site_url('/'),
            'logo' => site_url('/assets/img/og.png'),
            'description' => 'Acil satılık araçlara net teklif, noterde devir ve aynı gün ödeme. Türkiye\'nin 81 ilinden başvuru.',
            'areaServed' => ['@type' => 'Country', 'name' => 'Türkiye'],
        ];
        if ($phone !== '') {
            $organization['telephone'] = $phone;
        }

        view('home', [
            'title' => 'Acil Satılık Aracınız Anında Nakite | Aynı Gün Ödeme – Öz Oto',
            'description' => 'Acil satılık aracınıza net teklif: kilometre, boya-değişen ve tramer kaydına göre fiyat, noterde devir, aynı gün ödeme. 2 dakikada başvurun; 81 ilden araç alıyoruz.',
            'canonical' => site_url('/'),
            'faqs' => $faqs,
            'jsonLd' => [
                $organization,
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => 'Öz Oto',
                    'url' => site_url('/'),
                    'inLanguage' => 'tr-TR',
                ],
                Content::faqSchema($faqs),
            ],
        ]);
    }

    public function kvkk(): void
    {
        view('kvkk', [
            'title' => 'KVKK Aydınlatma Metni – Öz Oto',
            'description' => 'Öz Oto kişisel verilerin korunması ve işlenmesi hakkında aydınlatma metni.',
            'canonical' => site_url('/kvkk'),
        ]);
    }

    public function sitemap(): void
    {
        $pages = [
            ['/', '1.0', 'weekly'],
            ['/aracimi-hemen-sat', '0.9', 'weekly'],
            ['/kvkk', '0.2', 'yearly'],
        ];
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($pages as [$path, $priority, $freq]) {
            echo '  <url><loc>' . e(site_url($path)) . '</loc><changefreq>' . $freq . '</changefreq><priority>' . $priority . "</priority></url>\n";
        }
        echo '</urlset>' . "\n";
    }
}
