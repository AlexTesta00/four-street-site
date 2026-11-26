<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Account\Form\ConfirmEmailForm;
use FourStreet\Account\ReturnPath;
use FourStreet\Config\SiteConfig;
use FourStreet\Security\Csrf\CsrfProtection;
use FourStreet\View\Alert;
use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * /account/verifica/?token=… aperto fuori dal browser della registrazione (un altro dispositivo, il browser
 * dell'app di posta): l'email si conferma con la password scelta registrandosi. Il token viaggia nel campo
 * nascosto; l'email dell'account sta anche in un campo nascosto con autocomplete="username", così il gestore
 * delle password propone quella giusta.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var ConfirmEmailForm $form
 * @var string $token
 * @var string $email l'email dell'account
 * @var ReturnPath $returnTo la destinazione scelta alla registrazione
 * @var Alert|null $alert
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => ($form->errors->isEmpty() ? '' : 'Errore: ') . '4Street · Conferma l\'email',
    'eyebrow' => 'Registrazione',
    'heading' => 'Conferma l\'email.',
    'lead' => 'Per attivare l\'account di ' . $email . ' scrivi la password che hai scelto registrandoti. Dopo entri subito.',
]);

$forgot = new Html(sprintf(
    '<a class="fs-text-link fs-field__link" href="%s">Password dimenticata?</a>',
    $view->e(AccountUrls::to(AccountUrls::FORGOT_PASSWORD, $returnTo)),
));
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?= $view->partial('partials/form/error-summary', ['errors' => $form->errors]) ?>
<form method="post" action="<?= $view->e(AccountUrls::VERIFY) ?>" class="fs-form" novalidate data-submit-once>
  <input type="hidden" name="<?= $view->e(CsrfProtection::FIELD) ?>" value="<?= $view->e($csrf) ?>">
  <input type="hidden" name="<?= $view->e(AccountUrls::TOKEN_PARAMETER) ?>" value="<?= $view->e($token) ?>">
  <input type="email" autocomplete="username" value="<?= $view->e($email) ?>" hidden readonly>
  <?= $view->partial('partials/form/password', [
      'name' => ConfirmEmailForm::PASSWORD,
      'label' => 'Password',
      'autocomplete' => 'current-password',
      'error' => $form->errors->get(ConfirmEmailForm::PASSWORD),
      'hint' => null,
      'after' => $forgot,
  ]) ?>
  <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Conferma e accedi</button>
</form>
<div class="fs-auth__note">
  <?= $view->icon('info', 'fs-auth__note-icon') ?>
  <p>Non hai creato tu questo account? Chiudi questa pagina: senza la password l'account non si attiva.</p>
</div>
