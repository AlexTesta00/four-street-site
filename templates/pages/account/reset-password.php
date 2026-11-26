<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Account\Form\NewPasswordForm;
use FourStreet\Config\SiteConfig;
use FourStreet\Security\Csrf\CsrfProtection;
use FourStreet\View\Template;

/**
 * /account/nuova-password/?token=…: la password nuova. Il token viaggia nel campo nascosto; l'email
 * dell'account sta anche in un campo nascosto con autocomplete="username", così il gestore delle password
 * aggiorna la voce giusta.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var NewPasswordForm $form
 * @var string $token
 * @var string $email l'email dell'account
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => ($form->errors->isEmpty() ? '' : 'Errore: ') . '4Street · Nuova password',
    'eyebrow' => 'Area clienti',
    'heading' => 'Nuova password.',
    'lead' => 'Scegli la nuova password per ' . $email . '. Dopo entri subito, e chiudiamo l\'accesso sugli altri dispositivi.',
]);
?>
<?= $view->partial('partials/form/error-summary', ['errors' => $form->errors]) ?>
<form method="post" action="<?= $view->e(AccountUrls::RESET_PASSWORD) ?>" class="fs-form" novalidate data-submit-once>
  <input type="hidden" name="<?= $view->e(CsrfProtection::FIELD) ?>" value="<?= $view->e($csrf) ?>">
  <input type="hidden" name="<?= $view->e(AccountUrls::TOKEN_PARAMETER) ?>" value="<?= $view->e($token) ?>">
  <input type="email" autocomplete="username" value="<?= $view->e($email) ?>" hidden readonly>
  <?= $view->partial('partials/form/password', [
      'name' => NewPasswordForm::PASSWORD,
      'label' => 'Nuova password',
      'autocomplete' => 'new-password',
      'error' => $form->errors->get(NewPasswordForm::PASSWORD),
      'hint' => 'Almeno 8 caratteri. Una frase che ricordi va benissimo.',
      'after' => null,
  ]) ?>
  <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Salva la password</button>
</form>
