<?php

declare(strict_types=1);

namespace Ozoto\Support;

final class Catalog
{
    /** @return list<string> */
    public static function cities(): array
    {
        return [
            'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara', 'Antalya', 'Ardahan',
            'Artvin', 'Aydın', 'Balıkesir', 'Bartın', 'Batman', 'Bayburt', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu',
            'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Düzce', 'Edirne',
            'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay',
            'Iğdır', 'Isparta', 'İstanbul', 'İzmir', 'Kahramanmaraş', 'Karabük', 'Karaman', 'Kars', 'Kastamonu',
            'Kayseri', 'Kilis', 'Kırıkkale', 'Kırklareli', 'Kırşehir', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya',
            'Manisa', 'Mardin', 'Mersin', 'Muğla', 'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Osmaniye', 'Rize',
            'Sakarya', 'Samsun', 'Şanlıurfa', 'Siirt', 'Sinop', 'Şırnak', 'Sivas', 'Tekirdağ', 'Tokat', 'Trabzon',
            'Tunceli', 'Uşak', 'Van', 'Yalova', 'Yozgat', 'Zonguldak',
        ];
    }

    /** @return list<string> */
    public static function brands(): array
    {
        return [
            'Alfa Romeo', 'Audi', 'BMW', 'BYD', 'Chery', 'Chevrolet', 'Citroën', 'Cupra', 'Dacia', 'DS', 'Fiat',
            'Ford', 'Honda', 'Hyundai', 'Jeep', 'Kia', 'Land Rover', 'Lexus', 'Mazda', 'Mercedes-Benz', 'MG',
            'Mini', 'Mitsubishi', 'Nissan', 'Opel', 'Peugeot', 'Porsche', 'Renault', 'Seat', 'Skoda', 'Smart',
            'Subaru', 'Suzuki', 'Tesla', 'Tofaş', 'Togg', 'Toyota', 'Volkswagen', 'Volvo', 'Diğer',
        ];
    }

    /** @return list<int> */
    public static function years(): array
    {
        return range((int) date('Y') + 1, 1980);
    }

    /** @return list<string> */
    public static function fuels(): array
    {
        return ['Benzin', 'Dizel', 'Benzin & LPG', 'Hibrit', 'Elektrik'];
    }

    /** @return list<string> */
    public static function gearboxes(): array
    {
        return ['Manuel', 'Otomatik', 'Yarı otomatik'];
    }

    /** @return list<string> */
    public static function damages(): array
    {
        return ['Hatasız / boyasız', 'Boyalı', 'Değişenli', 'Boyalı ve değişenli', 'Ağır hasar kayıtlı'];
    }

    /** @return list<string> */
    public static function urgencies(): array
    {
        return ['Bugün', 'Bu hafta', 'Bu ay', 'Acelem yok'];
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            'new' => 'Yeni',
            'called' => 'Arandı',
            'offered' => 'Teklif verildi',
            'bought' => 'Satın alındı',
            'rejected' => 'Olumsuz',
        ];
    }
}
