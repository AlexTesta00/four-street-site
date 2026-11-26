<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Layout HTML delle email: fondo asfalto, testo chiaro, un solo bottone oro. Gli stili sono in linea
 * e l'impaginazione è a tabelle, come vogliono i programmi di posta; nessuna immagine né font remoti.
 * Il marchio è testo, quindi si legge anche con le immagini bloccate.
 *
 * @var Template $view
 * @var Html $content
 * @var string $title titolo dell'email (h1)
 * @var string $preheader la riga di anteprima che il programma di posta mostra accanto all'oggetto
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$sans = "Archivo,'Helvetica Neue',Helvetica,Arial,sans-serif";
$serif = "'Bodoni Moda','Bodoni 72',Didot,'Times New Roman',serif";
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<meta name="supported-color-schemes" content="dark">
<title><?= $view->e($title) ?></title>
</head>
<body style="margin:0;padding:0;background-color:#0b0b0a;">
<div style="display:none;max-height:0;max-width:0;overflow:hidden;opacity:0;color:#0b0b0a;"><?= $view->e($preheader) ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0b0b0a" style="background-color:#0b0b0a;">
  <tr>
    <td align="center" style="padding:32px 16px 40px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
        <tr>
          <td style="padding:0 0 24px;border-bottom:1px solid #242422;">
            <span style="display:inline-block;padding:7px 12px;border:1px solid #33332f;color:#f3f3f4;font-family:<?= $view->e($sans) ?>;font-size:12px;line-height:16px;font-weight:600;letter-spacing:0.22em;text-transform:uppercase;">4Street</span>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 0 16px;">
            <h1 style="margin:0;color:#f3f3f4;font-family:<?= $view->e($serif) ?>;font-size:32px;line-height:38px;font-weight:400;"><?= $view->e($title) ?></h1>
          </td>
        </tr>
        <tr>
          <td style="padding:0 0 16px;">
<?= $content ?>
          </td>
        </tr>
        <tr>
          <td style="padding:24px 0 0;border-top:1px solid #242422;color:#8e8f92;font-family:<?= $view->e($sans) ?>;font-size:13px;line-height:20px;">
            <?= $view->e($siteName) ?> · <?= $view->e($address) ?><br>
            <a href="<?= $view->e($siteUrl) ?>" style="color:#bebfc2;text-decoration:underline;"><?= $view->e(preg_replace('#\Ahttps?://#', '', $siteUrl) ?? $siteUrl) ?></a>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
