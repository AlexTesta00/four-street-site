<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Account\Form\EmailForm;
use FourStreet\Account\ReturnPath;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Template;

/**
 * /account/password-dimenticata/: l'email dell'account, per ricevere il link della nuova password.
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
    'title' => ($form->errors->isEmpty() ? '' : 'Errore: ') . '4Street · Password dimenticata',
    'eyebrow' => 'Area clienti',
    'heading' => 'Password dimenticata.',
    'lead' => 'Scrivi l\'email del tuo account: ti mandiamo un link per sceglierne una nuova.',
]);
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?= $view->partial('partials/form/error-summary', ['errors' => $form->errors]) ?>
<form method="post" action="<?= $view->e(AccountUrls::FORGOT_PASSWORD) ?>" class="fs-form" novalidate data-submit-once>
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
<p class="fs-auth__switch">Te la sei ricordata? <a class="fs-text-link" href="<?= $view->e(AccountUrls::to(AccountUrls::LOGIN, $returnTo)) ?>">Accedi</a></p>
