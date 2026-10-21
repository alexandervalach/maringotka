<?php

declare(strict_types=1);

namespace App\Model;

use RuntimeException;

/** 
 * Existing optimized photographs; MANIFEST.json remains the single media manifest. 
 */
final class GalleryCatalog
{
  /** 
   * @return list<array{key: string, fallback: string, srcset: string, width: int, height: int}> 
   */
  public static function items(): array
  {
    $path = dirname(__DIR__, 2) . '/MANIFEST.json';
    $raw = file_get_contents($path);
    if ($raw === false) {
      throw new RuntimeException('Missing MANIFEST.json.');
    }
    $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || !isset($manifest['gallery_order']) || !is_array($manifest['gallery_order'])) {
      throw new RuntimeException('Invalid gallery manifest.');
    }
    $result = [];
    foreach ($manifest['gallery_order'] as $key) {
      if (!is_string($key) || !isset($manifest[$key]) || !is_array($manifest[$key])) {
        throw new RuntimeException('Gallery image missing from manifest.');
      }
      $data = $manifest[$key];
      $fallback = $data['fallback'] ?? null;
      $size = $data['original'] ?? null;
      if (!is_string($fallback) || preg_match('/^[a-zA-Z0-9_-]+\.jpg$/D', $fallback) !== 1
        || !is_array($size) || count($size) !== 2 || !is_int($size[0]) || !is_int($size[1])) {
        throw new RuntimeException('Invalid gallery image metadata.');
      }
      $srcset = [];
      foreach ($data['outputs'] ?? [] as $width => $variant) {
        if (!is_array($variant) || !isset($variant['file']) || !is_string($variant['file'])
          || preg_match('/^[a-zA-Z0-9_-]+\.webp$/D', $variant['file']) !== 1) {
          continue;
        }
        $srcset[] = '/assets/images/media/' . $variant['file'] . ' ' . (int) $width . 'w';
      }
      $result[] = [
        'key' => $key,
        'fallback' => $fallback,
        'srcset' => implode(', ', $srcset),
        'width' => $size[0],
        'height' => $size[1],
      ];
    }
    return $result;
  }
}
