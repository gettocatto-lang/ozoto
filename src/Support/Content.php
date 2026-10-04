<?php

declare(strict_types=1);

namespace Ozoto\Support;

final class Content
{
    /** @return list<array{q: string, a: string}> */
    public static function faqs(): array
    {
        return [
            [
                'q' => 'Aracımı ne kadar sürede satabilirim?',
                'a' => 'Formu doldurduktan sonra uzmanımız sizi arar ve aracınızın bilgilerine göre teklifini sunar. Teklifi kabul ederseniz satış noterde yapılır; acil durumlarda süreci çoğu zaman aynı gün tamamlıyoruz.',
            ],
            [
                'q' => 'Teklif almak ücretli mi?',
                'a' => 'Hayır. Teklif almak tamamen ücretsizdir ve sizi hiçbir şekilde bağlamaz. Teklifi beğenmezseniz satmak zorunda değilsiniz.',
            ],
            [
                'q' => 'Hasarlı, boyalı veya yüksek kilometreli araç alıyor musunuz?',
                'a' => 'Evet. Boyalı, değişenli, ağır hasar kayıtlı veya yüksek kilometreli araçlar için de teklif veriyoruz. Formda aracın durumunu doğru belirtmeniz teklifin isabetini artırır.',
            ],
            [
                'q' => 'Ödeme nasıl yapılıyor?',
                'a' => 'Satış noterde resmi devirle yapılır ve ödemeniz satış anında güvenli şekilde hesabınıza geçer. Yanınızda nakit taşımanıza gerek kalmaz.',
            ],
            [
                'q' => 'Hangi illerden başvuru kabul ediyorsunuz?',
                'a' => 'Türkiye\'nin 81 ilinden başvuru kabul ediyoruz. Formda aracınızın bulunduğu ili seçmeniz yeterli.',
            ],
            [
                'q' => 'Bilgilerim güvende mi?',
                'a' => 'Bilgileriniz yalnızca aracınıza teklif vermek ve sizinle iletişime geçmek için kullanılır, satılmaz ve reklam amacıyla paylaşılmaz. Ayrıntılar KVKK aydınlatma metnimizde yer alır.',
            ],
        ];
    }

    /**
     * @param list<array{q: string, a: string}> $faqs
     * @return array<string, mixed>
     */
    public static function faqSchema(array $faqs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $f): array => [
                '@type' => 'Question',
                'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ], $faqs),
        ];
    }
}
