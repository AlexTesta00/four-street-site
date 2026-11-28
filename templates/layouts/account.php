<?php

declare(strict_types=1);

use FourStreet\Config\SiteConfig;
use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Layout compatto delle pagine dell'area clienti (e delle altre pagine private, come il modulo scaduto):
 * marchio e ritorno al sito in alto, il logo, l'occhiello, il titolo e il contenuto (di solito un modulo).
 * Su mobile una colonna, con il logo in una striscia; da lg il logo è un quadrato a sinistra.
 * Nessun motore di ricerca la indicizza.
 *
 * @var Template $view
 * @var Html $content
 * @var SiteConfig $site
 * @var string $year anno corrente, "2026"
 * @var string $title titolo della scheda, già completo ("4Street · Accedi", "Errore: 4Street · Accedi")
 * @var string $eyebrow occhiello sopra il titolo, "Area clienti"
 * @var Html|string $heading titolo della pagina (h1)
 * @var Html|string|null $lead la frase sotto il titolo
 * @var bool|null $compact facoltativo: su mobile niente striscia del logo, così fasce e bottone di conferma
 *                         stanno nel primo schermo (le pagine delle prenotazioni); da lg il logo resta
 * @var Html|null $nav facoltativo: la navigazione di una sezione, sopra l'occhiello (il pannello del titolare,
 *                     partials/admin/nav); da lg una riga a tutta larghezza, sopra il logo e il contenuto
 */

$pending = $site->content;
$logoClass = ($compact ?? false) ? 'fs-auth__logo hidden lg:block' : 'fs-auth__logo';
?>
<!doctype html>
<html lang="it">
<?= $view->partial('partials/document-head', [
    'title' => $title,
    'description' => '4Street, palestra a San Marino. Area clienti.',
    'siteUrl' => null,
    'structuredData' => null,
    'noindex' => true,
]) ?>
<body>

<a href="#contenuto" class="fs-skip fs-label">Vai al contenuto</a>

<header class="fs-bar fs-gutter fs-anim-fade">
  <a href="/" class="fs-bar__mark" aria-label="4Street, home">4Street</a>
  <a href="/" class="fs-btn fs-btn--ghost fs-btn--sm -mr-2">
    <span class="fs-btn__label"><span class="min-[360px]:hidden">Al sito</span><span class="hidden min-[360px]:inline">Torna al sito</span></span>
    <?= $view->icon('arrow', 'fs-btn__arrow') ?>
  </a>
</header>

<main id="contenuto" class="fs-auth">
  <div class="fs-auth__inner">
<?php if (($nav ?? null) !== null): ?>
    <div class="fs-auth__nav">
<?= $nav ?>
    </div>
<?php endif; ?>
    <?= $view->partial('partials/logo', ['figureClass' => $logoClass, 'imageClass' => '', 'priority' => true]) ?>

    <div class="fs-auth__body">
      <div class="fs-auth__head">
        <p class="fs-label fs-anim-rise fs-step-2 text-fg-subtle"><?= $view->e($eyebrow) ?></p>
        <h1 class="fs-auth__title fs-anim-rise fs-step-3 font-display text-display-md text-balance text-fg"><?= $view->e($heading) ?></h1>
<?php if ($lead !== null): ?>
        <p class="fs-anim-rise fs-step-4 max-w-content text-body text-fg-muted"><?= $view->e($lead) ?></p>
<?php endif; ?>
      </div>

<?= $content ?>
    </div>
  </div>
</main>

<footer class="fs-auth__footer fs-gutter">
  <p class="fs-micro normal-case text-fg-subtle">© <?= $view->e($year) ?> 4Street</p>
<?php if ($pending->privacyUrl !== null): ?>
  <a href="<?= $view->e($pending->privacyUrl) ?>" class="fs-text-link inline-flex min-h-11 items-center text-body-sm">Informativa privacy</a>
<?php elseif ($pending->showPlaceholders): ?>
  <span class="fs-tag fs-tag--placeholder">[Informativa privacy]</span>
<?php endif; ?>
</footer>

<script src="<?= $view->asset('/assets/js/app.js') ?>" defer></script>
</body>
</html>
