<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Account\User;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /account/: il saluto, i dati dell'account, le prenotazioni e l'uscita.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var User $user
 * @var string $createdOn "Giovedì 26 novembre 2026"
 * @var Alert|null $alert
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Il tuo account',
    'eyebrow' => 'Il tuo account',
    'heading' => 'Ciao, ' . $user->fullName . '.',
    'lead' => null,
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>

<section aria-labelledby="prenotazioni-titolo" class="fs-account__section">
  <h2 id="prenotazioni-titolo" class="fs-label text-fg-subtle">Le tue prenotazioni</h2>
  <?= $view->partial('partials/account/bookings') ?>
</section>

<section aria-labelledby="dati-titolo" class="fs-account__section">
  <h2 id="dati-titolo" class="fs-label text-fg-subtle">I tuoi dati</h2>
  <dl class="fs-dl fs-dl--stack">
    <div class="fs-dl__row">
      <dt class="fs-label">Nome</dt>
      <dd><?= $view->e($user->fullName) ?></dd>
    </div>
    <div class="fs-dl__row">
      <dt class="fs-label">Email</dt>
      <dd><?= $view->e($user->email->value) ?></dd>
    </div>
    <div class="fs-dl__row">
      <dt class="fs-label">Iscritto dal</dt>
      <dd><?= $view->e($createdOn) ?></dd>
    </div>
  </dl>
</section>

<form method="post" action="<?= $view->e(AccountUrls::LOGOUT) ?>" data-submit-once>
  <?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => null]) ?>
  <button type="submit" class="fs-btn fs-btn--secondary fs-btn--block sm:w-auto">
    <?= $view->icon('exit') ?>
    <span>Esci</span>
  </button>
</form>
