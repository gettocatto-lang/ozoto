<?php

declare(strict_types=1);

namespace Ozoto\Controller;

use Ozoto\Support\Catalog;
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
            'name' => 'Ozoto',
            'url' => site_url('/'),
            'logo' => site_url('/assets/img/og.png'),
            'description' => 'Acil satılık araçlara hızlı nakit teklif. Türkiye\'nin 81 ilinden başvuru.',
            'areaServed' => ['@type' => 'Country', 'name' => 'Türkiye'],
        ];
        if ($phone !== '') {
            $organization['telephone'] = $phone;
        }

        view('home', [
            'title' => 'Acil Satılık Aracınız Anında Nakite | Hızlı Araç Alımı – Ozoto',
            'description' => 'Aracınızı hemen satmak mı istiyorsunuz? 2 dakikada formu doldurun, uzmanımız sizi arasın ve aracınıza hızlı nakit teklif versin. Türkiye\'nin 81 ilinden başvuru.',
            'canonical' => site_url('/'),
            'faqs' => $faqs,
            'brands' => Catalog::brands(),
            'years' => Catalog::years(),
            'jsonLd' => [
                $organization,
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    'name' => 'Ozoto',
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
            'title' => 'KVKK Aydınlatma Metni – Ozoto',
            'description' => 'Ozoto kişisel verilerin korunması ve işlenmesi hakkında aydınlatma metni.',
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
