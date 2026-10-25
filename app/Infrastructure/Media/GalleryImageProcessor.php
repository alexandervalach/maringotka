<?php

declare(strict_types=1);

namespace App\Infrastructure\Media;

use GdImage;
use Nette\Http\FileUpload;
use RuntimeException;
use Throwable;

/**
 * Converts uploaded JPEG and PNG images to bounded responsive gallery variants.
 */
final class GalleryImageProcessor
{
  public const MAX_BYTES = 32 * 1024 * 1024;
  public const MAX_PIXELS = 50_000_000;

  /**
   * Determines which image sizes can be generated without upscaling.
   *
   * @param int $sourceWidth Original image width.
   * @return list<int> Distinct output widths in ascending order.
   */
  public static function variantWidths(int $sourceWidth): array
  {
    if ($sourceWidth < 1) {
      throw new \InvalidArgumentException('Invalid source width.');
    }
    $maximum = min(1600, $sourceWidth);
    $widths = array_values(array_filter(
      [320, 480, 960, 1600],
      static fn (int $width): bool => $width <= $maximum,
    ));
    if (!in_array($maximum, $widths, true)) {
      $widths[] = $maximum;
      sort($widths, SORT_NUMERIC);
    }
    return $widths;
  }

  /**
   * Validates the file and creates a JPEG fallback, WebP variants and thumbnail.
   *
   * @param FileUpload $upload Uploaded JPEG or PNG file.
   * @return array{storage_key: string, fallback: string, srcset: string, thumbnail: string, width: int, height: int, byte_size: int, sha256: string, files: list<string>} Generated media metadata.
   */
  public function process(FileUpload $upload): array
  {
    if (!$upload->isOk() || !$upload->hasFile() || $upload->getSize() < 1 || $upload->getSize() > self::MAX_BYTES) {
      throw new RuntimeException('Neplatný súbor alebo veľkosť nad 32 MB.');
    }
    if (!extension_loaded('gd') || !function_exists('imagewebp')
      || !function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
      throw new RuntimeException('Na nahrávanie je potrebné GD s podporou JPEG, PNG a WebP.');
    }

    $path = $upload->getTemporaryFile();
    $info = @getimagesize($path);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
      throw new RuntimeException('Podporované sú iba obrázky JPG a PNG.');
    }
    $width = (int) $info[0];
    $height = (int) $info[1];
    if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
      throw new RuntimeException('Fotografia má nepodporované rozmery.');
    }

    $source = $info[2] === IMAGETYPE_JPEG
      ? @imagecreatefromjpeg($path)
      : @imagecreatefrompng($path);
    if (!$source instanceof GdImage) {
      throw new RuntimeException('Obrázok sa nepodarilo načítať.');
    }
    $directory = dirname(__DIR__, 3) . '/www/assets/images/media';
    $created = [];
    try {
      if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('Adresár galérie nie je zapisovateľný.');
      }
      if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $source = $this->orientJpeg($source, $path);
      }
      $width = imagesx($source);
      $height = imagesy($source);
      $maximum = min(1600, $width);
      $prefix = 'upload-' . bin2hex(random_bytes(16));
      $fallback = $prefix . '.jpg';
      $jpegPath = $directory . '/' . $fallback;
      $main = $this->resize($source, $maximum, $width, $height, true);
      $this->writeJpeg($main, $jpegPath, $created);
      unset($main);

      $sets = [];
      $thumbnail = '';
      foreach (self::variantWidths($width) as $variantWidth) {
        $variant = $this->resize($source, $variantWidth, $width, $height, false);
        $filename = $prefix . '-' . $variantWidth . '.webp';
        $target = $directory . '/' . $filename;
        $created[] = $target;
        if (!imagewebp($variant, $target, 82) || !is_file($target) || filesize($target) === 0) {
          throw new RuntimeException('Nepodarilo sa vytvoriť WebP.');
        }
        unset($variant);
        if ($thumbnail === '') {
          $thumbnail = $filename;
        }
        $sets[$variantWidth] = '/assets/images/media/' . $filename . ' ' . $variantWidth . 'w';
      }
      $bytes = filesize($jpegPath);
      $hash = hash_file('sha256', $jpegPath);
      if ($bytes === false || $hash === false) {
        throw new RuntimeException('Nepodarilo sa overiť výslednú fotografiu.');
      }
      return [
        'storage_key' => 'assets/images/media/' . $fallback,
        'fallback' => $fallback,
        'srcset' => implode(', ', $sets),
        'thumbnail' => $thumbnail,
        'width' => $maximum,
        'height' => max(1, (int) round($height * $maximum / $width)),
        'byte_size' => $bytes,
        'sha256' => $hash,
        'files' => $created,
      ];
    } catch (Throwable $error) {
      foreach ($created as $file) {
        @unlink($file);
      }
      throw $error;
    }
  }

  /**
   * Creates a scaled GD image, using white for the JPEG fallback of transparent PNGs.
   *
   * @param GdImage $source Decoded source image.
   * @param int $targetWidth Requested output width.
   * @param int $width Source width.
   * @param int $height Source height.
   * @param bool $flatten Whether to flatten transparency onto white.
   * @return GdImage Resized image.
   */
  private function resize(GdImage $source, int $targetWidth, int $width, int $height, bool $flatten): GdImage
  {
    $targetHeight = max(1, (int) round($height * $targetWidth / $width));
    $destination = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$destination instanceof GdImage) {
      throw new RuntimeException('Nedostatok pamäte na spracovanie fotografie.');
    }
    if ($flatten) {
      imagefill($destination, 0, 0, imagecolorallocate($destination, 255, 255, 255));
    } else {
      imagealphablending($destination, false);
      imagesavealpha($destination, true);
      imagefill($destination, 0, 0, imagecolorallocatealpha($destination, 0, 0, 0, 127));
    }
    imagecopyresampled($destination, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    return $destination;
  }

  /**
   * Writes the browser-compatible fallback and tracks it for failure cleanup.
   *
   * @param GdImage $image Resized image.
   * @param string $path Final target path.
   * @param list<string> $created Files to delete on failure.
   * @return void
   */
  private function writeJpeg(GdImage $image, string $path, array &$created): void
  {
    $created[] = $path;
    imageinterlace($image, true);
    if (!imagejpeg($image, $path, 85) || !is_file($path) || filesize($path) === 0) {
      throw new RuntimeException('Nepodarilo sa vytvoriť JPEG.');
    }
  }

  /**
   * Applies the common JPEG EXIF rotation orientations when metadata is available.
   *
   * @param GdImage $source Decoded source.
   * @param string $path Original uploaded file.
   * @return GdImage Correctly oriented image.
   */
  private function orientJpeg(GdImage $source, string $path): GdImage
  {
    $exif = @exif_read_data($path);
    $orientation = is_array($exif) ? ($exif['Orientation'] ?? null) : null;
    $angle = match ($orientation) {
      3 => 180,
      6 => 270,
      8 => 90,
      default => 0,
    };
    if ($angle === 0) {
      return $source;
    }
    $rotated = imagerotate($source, $angle, 0);
    if (!$rotated instanceof GdImage) {
      throw new RuntimeException('Nepodarilo sa otočiť fotografiu.');
    }
    return $rotated;
  }
}
