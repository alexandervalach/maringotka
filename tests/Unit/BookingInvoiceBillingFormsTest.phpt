<?php

declare(strict_types=1);

use App\Forms\BookingInvoiceFormFactory;
use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$factory = new BookingInvoiceFormFactory();

$customerForm = $factory->createCustomer([
  'customer_name' => 'Testovaci zakaznik',
  'customer_address' => 'Ukazkova 1, Testovacie mesto',
  'customer_ico' => '',
  'customer_dic' => '',
]);

Assert::same('Testovaci zakaznik', $customerForm['customer_name']->getValue());
Assert::same('Ukazkova 1, Testovacie mesto', $customerForm['customer_address']->getValue());
Assert::true($customerForm->getComponent('issue') !== null);
Assert::false($customerForm['customer_address']->isRequired());

$emptyAddressForm = $factory->createCustomer([
  'customer_name' => 'Skusobny host',
  'customer_address' => '',
  'customer_ico' => '',
  'customer_dic' => '',
]);
Assert::same('', $emptyAddressForm['customer_address']->getValue());

$quickForm = $factory->createQuickIssue();
Assert::true($quickForm->getComponent('issue') !== null);

$supplierForm = $factory->createSupplierSettings([
  'name' => 'Fiktivna prevadzka',
  'address' => 'Ukazkova 2, Testovacie mesto',
  'ico' => '00000000',
  'dic' => '',
]);

Assert::same('Fiktivna prevadzka', $supplierForm['supplier_name']->getValue());
Assert::null($supplierForm->getComponent('non_vat_payer', false));
