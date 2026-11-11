<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Model\BookingPaymentDetails;
use InvalidArgumentException;

/**
 * Default editable email text, placeholder expansion and safe HTML rendering.
 * No SMTP transport or database access.
 */
final class PaymentEmailTemplate
{
  private const DEFAULT_SUBJECT = 'Platobné údaje k rezervácii č. {{booking_id}}';

  private const DEFAULT_BODY = <<<'MARKDOWN'
Dobrý deň,

zasielame platobné údaje k potvrdenej rezervácii č. **{{booking_id}}**.

**Príjemca:** {{holder}}

**IBAN:** {{iban}}

BIC: {{bic}}

**Suma:** {{amount}} EUR

**Variabilný symbol:** {{variable_symbol}}

**Správa pre príjemcu:** Rezervacia VS {{variable_symbol}}

Platbu odošlite bankovým prevodom na uvedený účet. Ak vaša banka podporuje PAY by square, môžete využiť priložený QR kód (ak je priložený).

Pred potvrdením platby skontrolujte platobné údaje.

Ďakujeme.
MARKDOWN;

  /**
   * @return array{subject: string, body: string, show_logo: bool}
   */
  public static function defaults(): array
  {
    return [
      'subject' => self::DEFAULT_SUBJECT,
      'body' => self::DEFAULT_BODY,
      'show_logo' => true,
    ];
  }

  /**
   * @return void
   */
  public static function validateTemplate(string $subject, string $body): void
  {
    if (trim($subject) === '' || strlen($subject) > 180 || strpbrk($subject, "\r\n") !== false) {
      throw new InvalidArgumentException('Predmet musí mať najviac 180 znakov a nesmie obsahovať nový riadok.');
    }
    if (trim($body) === '' || strlen($body) > 10000) {
      throw new InvalidArgumentException('Text e-mailu musí mať najviac 10 000 znakov.');
    }
    foreach (['{{holder}}', '{{iban}}', '{{amount}}', '{{variable_symbol}}'] as $required) {
      if (!str_contains($body, $required)) {
        throw new InvalidArgumentException('V texte chýba povinná značka ' . $required . '.');
      }
    }
    foreach ([$subject, $body] as $text) {
      preg_match_all('/\{\{([^{}]*)\}\}/u', $text, $matches);
      foreach ($matches[1] as $placeholder) {
        if (!in_array($placeholder, ['booking_id', 'holder', 'iban', 'bic', 'amount', 'variable_symbol'], true)) {
          throw new InvalidArgumentException('Neznáma značka {{' . $placeholder . '}}.');
        }
      }
    }
  }

  /**
   * @param array{subject: string, body: string, show_logo: bool}|null $template
   * @return array{subject: string, body: string, html: string, show_logo: bool}
   */
  public static function content(
    int $bookingId,
    string $holder,
    string $iban,
    ?string $bic,
    int $cents,
    string $variableSymbol,
    ?array $template = null,
  ): array {
    $template ??= self::defaults();
    self::validateTemplate($template['subject'], $template['body']);
    $values = [
      '{{booking_id}}' => (string) $bookingId,
      '{{holder}}' => $holder,
      '{{iban}}' => $iban,
      '{{bic}}' => $bic ?: '—',
      '{{amount}}' => BookingPaymentDetails::amount($cents),
      '{{variable_symbol}}' => $variableSymbol,
    ];
    $subject = strtr($template['subject'], $values);
    // When no BIC is configured, omit the optional BIC line entirely.
    $bodyTemplate = $template['body'];
    if ($bic === null || $bic === '') {
      $bodyTemplate = preg_replace('/^BIC: \{\{bic\}\}\n?/m', '', $bodyTemplate) ?? $bodyTemplate;
    }
    $body = strtr($bodyTemplate, $values);
    return [
      'subject' => $subject,
      'body' => self::plain($body),
      'html' => self::html($body),
      'show_logo' => $template['show_logo'],
    ];
  }

  /**
   * Plain-text fallback for the browser's mailto action.
   *
   * @param array{subject: string, body: string, show_logo: bool}|null $template
   * @return string
   */
  public static function body(
    int $bookingId,
    string $holder,
    string $iban,
    ?string $bic,
    int $cents,
    string $variableSymbol,
    ?array $template = null,
  ): string {
    return self::content($bookingId, $holder, $iban, $bic, $cents, $variableSymbol, $template)['body'];
  }

  /**
   * Removes the limited Markdown controls for plain-text fallback and mailto.
   *
   * @return string
   */
  private static function plain(string $text): string
  {
    $text = preg_replace('/^# /m', '', $text) ?? $text;
    return str_replace(['**', '*'], '', $text);
  }

  /**
   * Converts plain paragraphs and limited Markdown emphasis into safe HTML.
   * Does not accept links, raw HTML, scripts or arbitrary image URLs.
   *
   * @return string
   */
  private static function html(string $text): string
  {
    $safe = htmlspecialchars(str_replace(["\r\n", "\r"], "\n", $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $blocks = preg_split('/\n{2,}/', trim($safe)) ?: [];
    $html = '';
    foreach ($blocks as $block) {
      $block = preg_replace('/\*\*([^*\n]+)\*\*/u', '<strong>$1</strong>', $block) ?? $block;
      $block = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '<em>$1</em>', $block) ?? $block;
      if (str_starts_with($block, '# ')) {
        $html .= '<h2>' . substr($block, 2) . '</h2>';
      } else {
        $html .= '<p>' . str_replace("\n", '<br>', $block) . '</p>';
      }
    }
    return $html;
  }

  /**
   * Creates the branded email body. The same HTML is used for preview and SMTP.
   *
   * @return string
   */
  public static function htmlDocument(
    string $bodyHtml,
    bool $showLogo,
    bool $logoExists = true,
    string $logoSrc = 'logo.png',
  ): string
  {
    $logo = $showLogo && $logoExists
      ? '<img src="' . htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8') . '" alt="Maringotka na Závadke" width="160" style="max-width:160px;height:auto;display:block;margin-top:24px">'
      : '';
    return '<!doctype html><html lang="sk"><head><meta charset="UTF-8"></head>'
      . '<body style="margin:0;padding:24px;background:#f7f6f1;font-family:Arial,sans-serif;color:#263c31">'
      . '<div style="max-width:620px;margin:auto;padding:30px;background:#fff;border:1px solid #e7e3d8;border-radius:12px">'
      . '<div style="font-size:15px;line-height:1.7">' . $bodyHtml . '</div>' . $logo
      . '<p style="margin-top:32px;padding-top:16px;border-top:1px solid #eee;font-size:12px;color:#68766d">Maringotka na Závadke</p>'
      . '</div></body></html>';
  }
}
