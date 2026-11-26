<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Account\Form\EmailForm;
use FourStreet\Account\ReturnPath;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /account/verifica/nuovo-link/: un nuovo link per confermare l'email.
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var EmailForm $form
 * @var ReturnPath $returnTo
 * @var Alert|null $alert
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => ($form->errors->isEmpty() ? '' : 'Errore: ') . '4Street · Nuovo link di conferma',
    'eyebrow' => 'Registrazione',
    'heading' => 'Nuovo link di conferma.',
    'lead' => 'Scrivi l\'email con cui ti sei registrato: ti mandiamo un nuovo link per confermarla.',
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?= $view->partial('partials/form/error-summary', ['errors' => $form->errors]) ?>
<form method="post" action="<?= $view->e(AccountUrls::VERIFY_RESEND) ?>" class="fs-form" novalidate data-submit-once>
  <?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => $returnTo]) ?>
  <?= $view->partial('partials/form/field', [
      'name' => EmailForm::EMAIL,
      'label' => 'Email',
      'type' => 'email',
      'value' => $form->email,
      'error' => $form->errors->get(EmailForm::EMAIL),
      'hint' => null,
      'attributes' => ['autocomplete' => 'email', 'inputmode' => 'email', 'autocapitalize' => 'none', 'spellcheck' => 'false', 'required' => true, 'maxlength' => '254'],
  ]) ?>
  <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Mandami il link</button>
</form>
<p class="fs-auth__switch">Hai già confermato? <a class="fs-text-link" href="<?= $view->e(AccountUrls::to(AccountUrls::LOGIN, $returnTo)) ?>">Accedi</a></p>
