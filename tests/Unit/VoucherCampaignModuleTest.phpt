<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Forms\VoucherCampaignFormFactory;
use App\Model\VoucherCampaignRepository;
use App\Presenters\BasePresenter;
use App\Presenters\VoucherCampaignPresenter;
use App\Router\RouterFactory;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use Tester\Assert;

Assert::true(is_subclass_of(VoucherCampaignPresenter::class, BasePresenter::class));

$factory = new VoucherCampaignFormFactory();
$campaign = $factory->createCampaign();
foreach (['name', 'price', 'nights', 'validity_months', 'includes_wellness', 'includes_wine', 'is_active', 'save'] as $field) {
  Assert::true(isset($campaign[$field]));
}

Assert::same([
  'name' => 'Zimný pobyt',
  'name_en' => '',
  'price_cents' => 13000,
  'nights' => 1,
  'validity_months' => 6,
  'includes_wellness' => true,
  'includes_wine' => true,
  'is_active' => false,
], array_diff_key(VoucherCampaignRepository::normalize([
  'name' => ' Zimný pobyt ',
  'name_en' => '',
  'price' => '130,00',
  'nights' => 1,
  'validity_months' => 6,
  'includes_wellness' => true,
  'includes_wine' => true,
  'is_active' => false,
]), ['updated_at' => true]));

Assert::same('130,00', VoucherCampaignRepository::euros(13000));
Assert::exception(static fn () => VoucherCampaignRepository::normalize([
  'name' => 'Test', 'price' => '1e8', 'nights' => 1, 'validity_months' => 6,
]), InvalidArgumentException::class);
Assert::exception(static fn () => VoucherCampaignRepository::normalize([
  'name' => 'Test', 'price' => '130', 'nights' => 31, 'validity_months' => 6,
]), InvalidArgumentException::class);

$router = RouterFactory::createRouter();
foreach ([
  '/admin/voucher-campaigns' => ['default', null],
  '/admin/voucher-campaigns/new' => ['edit', null],
  '/admin/voucher-campaigns/7' => ['edit', '7'],
] as $path => [$action, $id]) {
  $match = $router->match(new Request(new UrlScript('http://localhost' . $path, '/index.php')));
  Assert::type('array', $match);
  Assert::same('VoucherCampaign', $match['presenter']);
  Assert::same($action, $match['action']);
  if ($id !== null) {
    Assert::same($id, (string) $match['id']);
  }
}

$schema = file_get_contents(dirname(__DIR__, 2) . '/migrations/202611160001_voucher_campaigns.sql');
Assert::contains('voucher_campaigns', $schema);
Assert::contains('is_active boolean NOT NULL DEFAULT false', $schema);
