<?php

// Varsayılan ayarlar. Sunucuya özel ayarlar (veritabanı, şifreler) /kurulum sihirbazının
// oluşturduğu config/local.php dosyasında tutulur; o dosya Git'e girmez.

return [
    'debug' => false,
    'timezone' => 'Europe/Istanbul',
    'app_key' => '',
    'mail_from' => 'noreply@ozoto.online',
    'notify_email' => '',

    'site' => [
        'name' => 'Ozoto',
        'url' => 'https://ozoto.online',
        'phone' => '',
        'whatsapp' => '',
        'email' => '',
        'company' => '',
        'address' => '',
    ],

    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'name' => '',
        'user' => '',
        'pass' => '',
        'path' => 'storage/database.sqlite',
    ],

    // /kurulum sayfasını açan tek kullanımlık anahtarın SHA-256 özeti (anahtarın kendisi burada değil).
    'install_token_hash' => 'd9186b31799a09e391919a5b84633a6a570bd131401dd956f8f8be58b693a2a9',
];
