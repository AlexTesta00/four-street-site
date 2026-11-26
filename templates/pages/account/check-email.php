<?php

declare(strict_types=1);

use FourStreet\Account\Token\TokenPurpose;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * "Controlla la posta": dopo la registrazione (o un nuovo link di conferma) e dopo la richiesta di una
 * nuova password. Lo stesso testo che l'email abbia un account o no: per questo dice "se" e non dice a chi.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var TokenPurpose $purpose quale email sta arrivando
 * @var Alert|null $alert l'email non è partita
 * @var string $retryUrl dove chiedere un link nuovo
 * @var string $loginUrl
 */

$verification = $purpose === TokenPurpose::EmailVerification;

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Controlla la posta',
    'eyebrow' => $verification ? 'Registrazione' : 'Password dimenticata',
    'heading' => 'Controlla la posta.',
    'lead' => $verification
        ? 'Ti abbiamo scritto all\'indirizzo che hai indicato: apri l\'email di 4Street e tocca il link. Il link di conferma vale 48 ore.'
        : 'Se l\'indirizzo corrisponde a un account, ti abbiamo mandato il link per scegliere una nuova password. Il link vale un\'ora e si usa una volta sola.',
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<div class="fs-auth__note">
  <?= $view->icon('mail', 'fs-auth__note-icon') ?>
  <p>Non trovi l'email? Guarda nello spam e nelle promozioni. Se non c'è, <a class="fs-text-link" href="<?= $view->e($retryUrl) ?>">fatti mandare un nuovo link</a>.</p>
</div>
<p class="fs-auth__switch"><?= $verification ? 'Hai già confermato?' : 'Te la sei ricordata?' ?> <a class="fs-text-link" href="<?= $view->e($loginUrl) ?>">Accedi</a></p>
