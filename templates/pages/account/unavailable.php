<?php

declare(strict_types=1);

use FourStreet\Config\SiteConfig;
use FourStreet\View\Template;

/**
 * Ogni pagina dell'area clienti quando il server non è ancora configurato (503).
 *
 * @var Template $view
 * @var SiteConfig $site
 * @var string $year
 */

$view->layout('layouts/account', [
    'site' => $site,
    'year' => $year,
    'title' => '4Street · Area clienti',
    'eyebrow' => 'Area clienti',
    'heading' => 'Arriva presto.',
    'lead' => 'L\'area clienti non è ancora attiva. Intanto, per il tuo posto in sala, scrivici.',
]);

$whatsapp = $site->link('whatsapp');
?>
<div class="fs-actions">
<?php if ($whatsapp !== null): ?>
  <a href="<?= $view->e($whatsapp) ?>" target="_blank" rel="noopener" class="fs-btn fs-btn--primary fs-btn--block">
    <?= $view->icon('whatsapp') ?>
    <span>Scrivici su WhatsApp</span>
  </a>
<?php endif; ?>
  <a href="/" class="fs-btn fs-btn--secondary fs-btn--block">Torna alla home</a>
</div>
