<?php

declare(strict_types=1);

use App\Model\SmtpTransportFactory;
use Nette\Mail\SmtpMailer;
use Tester\Assert;

require dirname(__DIR__) . '/bootstrap.php';

$keys = ['SMTP_HOST', 'SMTP_USER', 'SMTP_PASSWORD', 'SMTP_PORT', 'SMTP_ENCRYPTION', 'MAIL_FROM'];
$original = [];
foreach ($keys as $key) {
  $original[$key] = getenv($key);
}

try {
  foreach ([
    'SMTP_HOST' => 'smtp.example.test',
    'SMTP_USER' => 'test',
    'SMTP_PASSWORD' => 'test-password',
    'SMTP_PORT' => '587',
    'SMTP_ENCRYPTION' => 'tls',
    'MAIL_FROM' => 'sender@example.test',
  ] as $key => $value) {
    putenv($key . '=' . $value);
  }

  Assert::true(SmtpTransportFactory::configured());
  Assert::type(SmtpMailer::class, SmtpTransportFactory::create());

  putenv('SMTP_PORT=invalid');
  Assert::false(SmtpTransportFactory::configured());
  Assert::exception(static fn () => SmtpTransportFactory::create(), RuntimeException::class);

  putenv('SMTP_PORT=587');
  putenv('SMTP_ENCRYPTION=none');
  Assert::false(SmtpTransportFactory::configured());

  putenv('SMTP_ENCRYPTION=tls');
  putenv('MAIL_FROM=bad address');
  Assert::false(SmtpTransportFactory::configured());
} finally {
  foreach ($original as $key => $value) {
    if ($value === false) {
      putenv($key);
    } else {
      putenv($key . '=' . $value);
    }
  }
}
