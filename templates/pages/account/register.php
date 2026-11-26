<?php

declare(strict_types=1);

use FourStreet\Account\AccountUrls;
use FourStreet\Account\Form\RegistrationForm;
use FourStreet\Account\FullName;
use FourStreet\Account\ReturnPath;
use FourStreet\Config\SiteConfig;
use FourStreet\View\Alert;
use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * /account/registrati/: nome e cognome, email, password e l'informativa sulla privacy.
 * Finché l'informativa non è pubblicata, al posto del link c'è il suo segnaposto (se i segnaposto sono accesi).
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 * @var string $csrf
 * @var RegistrationForm $form
 * @var ReturnPath $returnTo
 * @var Alert|null $alert
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => ($form->errors->isEmpty() ? '' : 'Errore: ') . '4Street · Crea l\'account',
    'eyebrow' => 'Area clienti',
    'heading' => 'Crea il tuo account.',
    'lead' => 'Ti serve per prenotare il tuo posto. Ti chiediamo solo l\'essenziale.',
]);

$privacyUrl = $site->content->privacyUrl;
$privacy = match (true) {
    $privacyUrl !== null => new Html(sprintf(
        'Ho letto l\'<a class="fs-text-link" href="%s" target="_blank" rel="noopener">informativa sulla privacy<span class="sr-only"> (si apre in una nuova scheda)</span></a>.',
        $view->e($privacyUrl),
    )),
    $site->content->showPlaceholders => new Html('Ho letto l\'informativa sulla privacy. <span class="fs-ph">[Informativa privacy]</span>'),
    default => 'Ho letto l\'informativa sulla privacy.',
};
?>
<?php if ($alert !== null): ?>
<?= $view->partial('partials/alert', ['alert' => $alert]) ?>
<?php endif; ?>
<?= $view->partial('partials/form/error-summary', ['errors' => $form->errors]) ?>
<form method="post" action="<?= $view->e(AccountUrls::REGISTER) ?>" class="fs-form" novalidate data-submit-once>
  <?= $view->partial('partials/form/hidden', ['csrf' => $csrf, 'returnTo' => $returnTo]) ?>
  <?= $view->partial('partials/form/field', [
      'name' => RegistrationForm::NAME,
      'label' => 'Nome e cognome',
      'type' => 'text',
      'value' => $form->name,
      'error' => $form->errors->get(RegistrationForm::NAME),
      'hint' => null,
      'attributes' => ['autocomplete' => 'name', 'autocapitalize' => 'words', 'spellcheck' => 'false', 'required' => true, 'maxlength' => (string) FullName::MAX_LENGTH],
  ]) ?>
  <?= $view->partial('partials/form/field', [
      'name' => RegistrationForm::EMAIL,
      'label' => 'Email',
      'type' => 'email',
      'value' => $form->email,
      'error' => $form->errors->get(RegistrationForm::EMAIL),
      'hint' => 'Ti mandiamo un link per confermarla.',
      'attributes' => ['autocomplete' => 'email', 'inputmode' => 'email', 'autocapitalize' => 'none', 'spellcheck' => 'false', 'required' => true, 'maxlength' => '254'],
  ]) ?>
  <?= $view->partial('partials/form/password', [
      'name' => RegistrationForm::PASSWORD,
      'label' => 'Password',
      'autocomplete' => 'new-password',
      'error' => $form->errors->get(RegistrationForm::PASSWORD),
      'hint' => 'Almeno 8 caratteri. Una frase che ricordi va benissimo.',
      'after' => null,
  ]) ?>
  <?= $view->partial('partials/form/checkbox', [
      'name' => RegistrationForm::PRIVACY,
      'label' => $privacy,
      'checked' => $form->privacy,
      'error' => $form->errors->get(RegistrationForm::PRIVACY),
  ]) ?>
  <button type="submit" class="fs-btn fs-btn--primary fs-btn--block">Crea l'account</button>
</form>
<p class="fs-auth__switch">Hai già un account? <a class="fs-text-link" href="<?= $view->e(AccountUrls::to(AccountUrls::LOGIN, $returnTo)) ?>">Accedi</a></p>
