<?php

declare(strict_types=1);

namespace App\Router;

final class PublicRoutes
{
    /** @var array<string, array{sk: string, en: string}> */
    public const PATHS = [
        'default' => ['sk' => '', 'en' => 'en'],
        'gallery' => ['sk' => 'galeria', 'en' => 'en/gallery'],
        'surroundings' => ['sk' => 'okolie', 'en' => 'en/surroundings'],
        'pricing' => ['sk' => 'cennik', 'en' => 'en/pricing'],
        'voucher' => ['sk' => 'darcekovy-poukaz', 'en' => 'en/gift-voucher'],
        'contact' => ['sk' => 'kontakt', 'en' => 'en/contact'],
        'booking' => ['sk' => 'rezervacia', 'en' => 'en/booking'],
    ];

    /** @return array<string, string> */
    public static function forLocale(string $locale): array
    {
        $result = [];
        foreach (self::PATHS as $action => $paths) {
            $result[$action] = '/' . $paths[$locale === 'en' ? 'en' : 'sk'];
        }
        return $result;
    }
}
