<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Nette\Neon\Neon;
use Tester\Assert;

$base = dirname(__DIR__, 2);
$sk = Neon::decodeFile($base . '/lang/messages.sk.neon');
$en = Neon::decodeFile($base . '/lang/messages.en.neon');
$templatesDir = $base . '/app/Presenters/templates';

$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator(
    $templatesDir,
    FilesystemIterator::SKIP_DOTS,
  ),
);

foreach ($iterator as $file) {
  if (!$file->isFile() || $file->getExtension() !== 'latte') {
    continue;
  }

  $filename = $file->getPathname();
  $template = file_get_contents($filename);

  Assert::type('string', $template);

  preg_match_all(
    '/\{_\s*[\x27\x22](messages\.[^\x27\x22]+)[\x27\x22]\s*\}/',
    $template,
    $matches,
  );

  foreach ($matches[1] as $identifier) {
    $key = substr($identifier, strlen('messages.'));

    Assert::true(
      array_key_exists($key, $sk),
      "Missing SK translation: $filename : $identifier",
    );

    Assert::true(
      array_key_exists($key, $en),
      "Missing EN translation: $filename : $identifier",
    );
  }
}
