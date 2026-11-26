<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Template;

/**
 * Un link per la nuova password che non vale più: scaduto (un'ora), già usato o incompleto.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Link non valido',
    'eyebrow' => 'Password dimenticata',
    'heading' => 'Il link non vale più.',
    'lead' => 'I link per la nuova password valgono un\'ora e si usano una volta sola. Chiedine uno nuovo: arriva in pochi secondi.',
]);
?>
<a href="<?= $view->e(AccountUrls::FORGOT_PASSWORD) ?>" class="fs-btn fs-btn--primary fs-btn--block">
  <?= $view->icon('mail') ?>
  <span>Chiedi un nuovo link</span>
</a>
