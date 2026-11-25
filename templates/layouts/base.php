<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Struttura comune delle pagine HTML.
 *
 * @var Template $view
 * @var Html $content corpo della pagina
 * @var string $title
 * @var string $description
 * @var string|null $siteUrl indirizzo pubblico del sito, per l'anteprima social
 * @var Html|null $structuredData dati strutturati JSON-LD della pagina, se ne ha
 */
?>
<!doctype html>
<html lang="it">
<?= $view->partial('partials/document-head', ['title' => $title, 'description' => $description, 'siteUrl' => $siteUrl, 'structuredData' => $structuredData]) ?>
<body>

<?= $content ?>
</body>
</html>
