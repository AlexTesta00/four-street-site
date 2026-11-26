<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Il bottone oro dell'email, fatto con una tabella (funziona anche in Outlook), e sotto lo stesso
 * indirizzo scritto per intero, per chi non può toccare il bottone.
 *
 * @var Template $view
 * @var string $url
 * @var string $label
 */

$sans = "Archivo,'Helvetica Neue',Helvetica,Arial,sans-serif";
?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
  <tr>
    <td bgcolor="#cdaa66" style="background-color:#cdaa66;">
      <a href="<?= $view->e($url) ?>" style="display:inline-block;padding:16px 28px;color:#0b0b0a;font-family:<?= $view->e($sans) ?>;font-size:13px;line-height:16px;font-weight:600;letter-spacing:0.16em;text-transform:uppercase;text-decoration:none;"><?= $view->e($label) ?></a>
    </td>
  </tr>
</table>
<p style="margin:0 0 16px;color:#8e8f92;font-family:<?= $view->e($sans) ?>;font-size:13px;line-height:20px;">Se il bottone non funziona, copia questo indirizzo nel browser:<br><a href="<?= $view->e($url) ?>" style="color:#cdaa66;text-decoration:underline;word-break:break-all;"><?= $view->e($url) ?></a></p>
