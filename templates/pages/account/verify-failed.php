<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Template;

/**
 * Un link di conferma scaduto, oppure inesistente o incompleto: si chiede un link nuovo.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var bool $expired il link era giusto, ma sono passate più di 48 ore
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · ' . ($expired ? 'Link scaduto' : 'Link non valido'),
    'eyebrow' => 'Registrazione',
    'heading' => $expired ? 'Il link è scaduto.' : 'Link non valido.',
    'lead' => $expired
        ? 'I link di conferma valgono 48 ore. Fatti mandare un nuovo link: arriva in pochi secondi.'
        : 'Il link è incompleto o non vale più. Controlla di averlo aperto per intero, oppure fatti mandare un nuovo link.',
]);
?>
<div class="fs-actions">
  <a href="<?= $view->e(AccountUrls::VERIFY_RESEND) ?>" class="fs-btn fs-btn--primary fs-btn--block">
    <?= $view->icon('mail') ?>
    <span>Mandami un nuovo link</span>
  </a>
  <a href="<?= $view->e(AccountUrls::LOGIN) ?>" class="fs-btn fs-btn--secondary fs-btn--block">Accedi</a>
</div>
