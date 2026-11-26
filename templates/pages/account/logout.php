<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Template;

/**
 * /account/esci/ aperto con GET: la conferma dell'uscita. Si esce solo con il bottone (POST e token CSRF).
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Esci',
    'eyebrow' => 'Il tuo account',
    'heading' => 'Vuoi uscire?',
    'lead' => 'Chiudi l\'accesso su questo dispositivo. Per rientrare ti serviranno email e password.',
]);
?>
<form method="post" action="<?= $view->e(AccountUrls::LOGOUT) ?>" class="fs-actions" data-submit-once>
  <?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => null]) ?>
  <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">
    <?= $view->icon('exit') ?>
    <span>Esci</span>
  </button>
  <a href="<?= $view->e(AccountUrls::HOME) ?>" class="fs-btn fs-btn--secondary fs-btn--block">Resta</a>
</form>
