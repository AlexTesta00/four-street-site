<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * I dettagli di una prenotazione nell'email HTML: un'etichetta e un valore per riga, fra due filetti.
 * Una tabella con gli stili in linea, come vogliono i programmi di posta.
 *
 * @var Template $view
 * @var array<string, string> $rows etichetta => valore
 */

$sans = "Archivo,'Helvetica Neue',Helvetica,Arial,sans-serif";
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;border-top:1px solid #242422;">
<?php foreach ($rows as $label => $value): ?>
  <tr>
    <td valign="top" style="width:96px;padding:12px 16px 12px 0;border-bottom:1px solid #242422;color:#8e8f92;font-family:<?= $view->e($sans) ?>;font-size:12px;line-height:20px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;"><?= $view->e($label) ?></td>
    <td valign="top" style="padding:12px 0;border-bottom:1px solid #242422;color:#f3f3f4;font-family:<?= $view->e($sans) ?>;font-size:16px;line-height:20px;"><?= $view->e($value) ?></td>
  </tr>
<?php endforeach; ?>
</table>
