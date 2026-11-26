<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Account\Form\EmailForm;
use FourStreet\Account\Form\LoginForm;
use FourStreet\Account\ReturnPath;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * /account/accedi/: email e password. Dopo un accesso non riuscito, sotto il modulo, il promemoria
 * per chi si è appena registrato e il bottone per un nuovo link di conferma, con l'email già scritta.
 * Lo vede chiunque sbagli: la pagina non dice se l'account esiste o se è da confermare.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var LoginForm $form
 * @var ReturnPath $returnTo
 * @var Alert|null $alert
 * @var bool $offerNewLink l'accesso non è riuscito
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => ($form->errors->isEmpty() ? '' : 'Errore: ') . '4Street · Accedi',
    'eyebrow' => 'Area clienti',
    'heading' => 'Accedi.',
    'lead' => 'Entra nel tuo account 4Street.',
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
<form method="post" action="<?= $view->e(AccountUrls::LOGIN) ?>" class="fs-form" novalidate data-submit-once>
  <?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => $returnTo]) ?>
  <?= $view->partial('partials/form/field', [
      'name' => LoginForm::EMAIL,
      'label' => 'Email',
      'type' => 'email',
      'value' => $form->email,
      'error' => $form->errors->get(LoginForm::EMAIL),
      'hint' => null,
      'attributes' => ['autocomplete' => 'username', 'inputmode' => 'email', 'autocapitalize' => 'none', 'spellcheck' => 'false', 'required' => true, 'maxlength' => '254'],
  ]) ?>
  <?= $view->partial('partials/form/password', [
      'name' => LoginForm::PASSWORD,
      'label' => 'Password',
      'autocomplete' => 'current-password',
      'error' => $form->errors->get(LoginForm::PASSWORD),
      'hint' => null,
      'after' => $forgot,
  ]) ?>
  <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Accedi</button>
</form>
<?php if ($offerNewLink): ?>
<div class="fs-auth__note">
  <?= $view->icon('mail', 'fs-auth__note-icon') ?>
  <div class="fs-auth__note-body">
    <p>Ti sei registrato da poco? Prima di accedere conferma l'email con il link che ti abbiamo mandato.</p>
    <form method="post" action="<?= $view->e(AccountUrls::VERIFY_RESEND) ?>" data-submit-once>
      <?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => $returnTo]) ?>
      <input type="hidden" name="<?= $view->e(EmailForm::EMAIL) ?>" value="<?= $view->e($form->email) ?>">
      <button type="submit" class="fs-btn fs-btn--secondary fs-btn--sm">Mandami il link</button>
    </form>
  </div>
</div>
<?php endif; ?>
<p class="fs-auth__switch">Non hai un account? <a class="fs-text-link" href="<?= $view->e(AccountUrls::to(AccountUrls::REGISTER, $returnTo)) ?>">Registrati</a></p>
