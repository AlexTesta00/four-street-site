<?php

declare(strict_types=1);

use FourStreet\Content\PendingContent;
use FourStreet\View\Template;

/**
 * Piè di pagina: marchio e indirizzo (con l'area clienti, se il server la offre), contatti, lo shop (presto),
 * i dati legali e il copyright.
 * Informativa privacy e dati della società, finché mancano, sono segnaposto; con i segnaposto spenti
 * non ci sono (e se mancano entrambi, nemmeno la loro riga).
 *
 * @var Template $view
 * @var PendingContent $content
 * @var string $address
 * @var string|null $instagram
 * @var string|null $whatsapp
 * @var string|null $accountUrl
 * @var string $year anno corrente, "2026"
 */

// Solo i link pubblicati in configurazione.
$contacts = [];
foreach (['Instagram' => ['instagram', $instagram], 'WhatsApp' => ['whatsapp', $whatsapp]] as $label => [$icon, $href]) {
    if ($href !== null) {
        $contacts[] = ['label' => $label, 'icon' => $icon, 'href' => $href];
    }
}
$showsPrivacy = $content->shows($content->privacyUrl);
$showsCompany = $content->shows($content->company);
?>
<footer class="border-t border-hairline">
  <div class="fs-gutter mx-auto flex w-full max-w-container flex-col gap-6 py-12 lg:gap-12 lg:py-16">

    <div class="grid gap-6 md:grid-cols-3 lg:gap-10">
      <div class="flex flex-col items-start gap-4">
        <a href="/" class="fs-bar__mark" aria-label="4Street, home">4Street</a>
        <p class="text-body-sm text-fg-muted"><?= $view->e($address) ?></p>
<?php if ($accountUrl !== null): ?>
        <a href="<?= $view->e($accountUrl) ?>" class="fs-btn fs-btn--ghost fs-btn--sm -ml-2">
          <?= $view->icon('user') ?>
          <span class="fs-btn__label">Area clienti</span>
        </a>
<?php endif; ?>
      </div>
      <nav aria-label="Contatti" class="flex flex-col items-start gap-1">
<?php foreach ($contacts as $contact): ?>
        <a href="<?= $view->e($contact['href']) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--ghost fs-btn--sm -ml-2">
          <?= $view->icon($contact['icon']) ?>
          <span class="fs-btn__label"><?= $view->e($contact['label']) ?></span>
          <?= $view->icon('arrow', 'fs-btn__arrow') ?>
        </a>
<?php endforeach; ?>
        <a href="/links/" class="fs-btn fs-btn--ghost fs-btn--sm -ml-2">
          <?= $view->icon('list') ?>
          <span class="fs-btn__label">Tutti i link</span>
        </a>
      </nav>
      <div class="flex items-center gap-3">
        <span class="text-body-sm text-fg-subtle">Shop online</span>
        <span class="fs-tag">Presto</span>
      </div>
    </div>

    <div class="flex flex-col items-start gap-6 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between lg:gap-4 lg:border-t lg:border-hairline lg:pt-6">
<?php if ($showsPrivacy || $showsCompany): ?>
      <div class="flex flex-col items-start gap-2 lg:flex-row lg:flex-wrap lg:items-center">
<?php if ($content->privacyUrl !== null): ?>
        <a href="<?= $view->e($content->privacyUrl) ?>" class="fs-text-link inline-flex min-h-11 items-center text-body-sm">Informativa privacy</a>
<?php elseif ($showsPrivacy): ?>
        <span class="fs-tag fs-tag--placeholder">[Informativa privacy]</span>
<?php endif; ?>
<?php if ($content->company !== null): ?>
        <p class="text-body-sm text-fg-subtle"><?= $view->e($content->company) ?></p>
<?php elseif ($showsCompany): ?>
        <span class="fs-tag fs-tag--placeholder">[Ragione sociale e COE da confermare]</span>
<?php endif; ?>
      </div>
<?php endif; ?>
      <p class="fs-micro normal-case text-fg-subtle">© <?= $view->e($year) ?> 4Street</p>
    </div>

  </div>
</footer>
