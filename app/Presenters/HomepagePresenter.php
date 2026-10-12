<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Presenter;

final class HomepagePresenter extends Presenter
{
  public function renderDefault(string $locale = 'sk'): void
  {
    $locale = $locale === 'en' ? 'en' : 'sk';
    $copy = [
      'sk' => [
        'home' => 'Domov', 'gallery' => 'Galéria', 'surroundings' => 'Okolie',
        'pricing' => 'Cenník', 'voucher' => 'Darčekový poukaz',
        'contact' => 'Kontakt', 'booking' => 'Rezervovať pobyt',
        'tagline' => 'Pokoj. Príroda. Výhľad na Tatry.',
        'headline' => 'Maringotka na Závadke',
        'intro' => 'Rezervujte si nezabudnuteľný pobyt v Maringotke na Závadke s výhľadom na Vysoké Tatry. Komfortné ubytovanie pre dve osoby, súkromná sauna a kúpacia kaďa uprostred prírody.',
        'discover' => 'Objavte naše miesto',
        'galleryText' => 'Fotografie ubytovania čoskoro doplníme.',
        'surroundingsText' => 'Závadka, výhľady na Tatry a výlety do Slovenského raja.',
        'pricingText' => 'Sezónny cenník pripravujeme. Cena sa bude odvíjať od termínu pobytu.',
        'voucherText' => 'Darčekové poukazy pripravujeme.',
        'contactText' => 'Kontaktné údaje čoskoro doplníme.',
        'bookingText' => 'Online rezervácie pripravujeme. Zatiaľ tu nie je možné odoslať rezerváciu.',
        'footer' => 'Oddych v prírode na Závadke.', 'menu' => 'Otvoriť menu',
        'placeholder' => 'Ukážkový obsah – fotografie a informácie budú doplnené',
      ],
      'en' => [
        'home' => 'Home', 'gallery' => 'Gallery', 'surroundings' => 'Explore',
        'pricing' => 'Prices', 'voucher' => 'Gift voucher',
        'contact' => 'Contact', 'booking' => 'Book a stay',
        'tagline' => 'Peace. Nature. Views of the Tatras.',
        'headline' => 'Maringotka na Závadke',
        'intro' => 'Enjoy a memorable stay at Maringotka na Závadke overlooking the High Tatras. Comfortable accommodation for two, a private sauna and an outdoor bathing tub surrounded by nature.',
        'discover' => 'Discover our place',
        'galleryText' => 'Accommodation photographs will be added soon.',
        'surroundingsText' => 'Závadka, mountain views and trips to Slovak Paradise.',
        'pricingText' => 'Seasonal prices will be published soon. Prices will depend on your travel dates.',
        'voucherText' => 'Gift vouchers are coming soon.',
        'contactText' => 'Contact details will be added soon.',
        'bookingText' => 'Online booking is being prepared. Reservations cannot yet be submitted here.',
        'footer' => 'Relax in nature in Závadka.', 'menu' => 'Open menu',
        'placeholder' => 'Preview content – images and details to be provided',
      ],
    ];

    $this->template->add('locale', $locale);
    $this->template->add('copy', $copy[$locale]);
    $this->template->add('title', 'Maringotka na Závadke');
  }
}
