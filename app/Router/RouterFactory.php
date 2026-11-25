<?php

declare(strict_types=1);

namespace App\Router;

use App\Localization\SupportedLocales;
use Nette\Application\Routers\RouteList;

final class RouterFactory
{
  public static function createRouter(): RouteList
  {
    $router = new RouteList();
    $router->addRoute('admin/login', ['presenter' => 'Admin', 'action' => 'login']);
    $router->addRoute('admin/home/<locale>', ['presenter' => 'Homepage', 'action' => 'edit']);
    $router->addRoute('admin/contact', ['presenter' => 'Contact', 'action' => 'edit']);
    $router->addRoute('admin/pricing', ['presenter' => 'Pricing', 'action' => 'edit']);
    
    $router->addRoute('admin/voucher-mail-templates/<event ordered|paid>/<locale sk|en>', [
      'presenter' => 'VoucherMailTemplate', 'action' => 'edit',
    ]);

    $router->addRoute('admin/voucher-mail-templates', [
      'presenter' => 'VoucherMailTemplate', 'action' => 'default',
    ]);

    $router->addRoute('admin/voucher-campaigns/issued/<id \d+>/pdf', ['presenter' => 'VoucherCampaign', 'action' => 'download']);
    $router->addRoute('admin/voucher-campaigns/<id \d+>/issue', ['presenter' => 'VoucherCampaign', 'action' => 'issue']);
    $router->addRoute('admin/voucher-campaigns/new', ['presenter' => 'VoucherCampaign', 'action' => 'edit']);
    $router->addRoute('admin/voucher-campaigns/<id \d+>', ['presenter' => 'VoucherCampaign', 'action' => 'edit']);
    $router->addRoute('admin/voucher-campaigns', ['presenter' => 'VoucherCampaign', 'action' => 'default']);
    $router->addRoute('admin/gallery/<id \d+>', ['presenter' => 'Gallery', 'action' => 'edit']);
    $router->addRoute('admin/gallery', ['presenter' => 'Gallery', 'action' => 'manage']);
    $router->addRoute('admin/bookings/<id \d+>', ['presenter' => 'BookingManagement', 'action' => 'edit']);
    $router->addRoute('admin/bookings', ['presenter' => 'BookingManagement', 'action' => 'default']);
    $router->addRoute('admin/payment-settings', 'PaymentSettings:default');
    $router->addRoute('admin/mail-templates/payment', 'PaymentSettings:paymentEmail');
    $router->addRoute('admin/mail-templates/confirmed', 'PaymentSettings:confirmed');
    $router->addRoute('admin/mail-templates/paid', [
      'presenter' => 'PaymentSettings',
      'action' => 'paid',
    ]);
    $router->addRoute('admin/mail-templates/received', 'PaymentSettings:received');
    $router->addRoute('admin/mail-templates', 'PaymentSettings:received');
    
    $router->addRoute('admin/booking-calendar', [
      'presenter' => 'BookingCalendar',
      'action' => 'default',
    ]);
    
    $router->addRoute('admin/booking-overview', [
      'presenter' => 'BookingOverview',
      'action' => 'default',
    ]);

    $router->addRoute('admin', ['presenter' => 'Admin', 'action' => 'default']);

    $router->addRoute('darcekovy-poukaz/objednat/<id \d+>', [
      'presenter' => 'Voucher', 'action' => 'order', 'locale' => 'sk',
    ]);

    $router->addRoute('en/gift-voucher/order/<id \d+>', [
      'presenter' => 'Voucher', 'action' => 'order', 'locale' => 'en',
    ]);

    $router->addRoute('darcekovy-poukaz/objednavka/<reference [A-F0-9]{24}>', [
      'presenter' => 'Voucher', 'action' => 'ordered', 'locale' => 'sk',
    ]);

    $router->addRoute('en/gift-voucher/ordered/<reference [A-F0-9]{24}>', [
      'presenter' => 'Voucher', 'action' => 'ordered', 'locale' => 'en',
    ]);

    $router->addRoute('rezervacia/overit-poukaz', [
      'presenter' => 'Booking', 'action' => 'checkVoucher', 'locale' => 'sk',
    ]);

    $router->addRoute('en/booking/check-voucher', [
      'presenter' => 'Booking', 'action' => 'checkVoucher', 'locale' => 'en',
    ]);

    foreach (PublicRoutes::PATHS as $action => $_) {
      foreach (SupportedLocales::CODES as $locale) {
        $router->addRoute(PublicRoutes::pathFor($action, $locale), [
          'presenter' => match ($action) {
            'contact' => 'Contact',
            'pricing' => 'Pricing',
            'gallery' => 'Gallery',
            'booking' => 'Booking',
            'surroundings' => 'Surroundings',
            'voucher' => 'Voucher',
            default => 'Homepage',
          },
          'action' => in_array($action, ['contact', 'pricing', 'gallery', 'booking', 'surroundings', 'voucher'], true) ? 'default' : $action,
          'locale' => $locale,
        ]);
      }
    }

    return $router;
  }
}
