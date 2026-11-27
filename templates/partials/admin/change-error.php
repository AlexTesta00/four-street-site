<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Il perché di una modifica dell'orario che non riguarda un campo (il giorno è passato, l'orario da togliere non c'è
 * più, la settimana dopo è già cominciata): in cima alla parte della pagina dove punta il riepilogo degli errori
 * (il giorno, la copia, l'elenco delle chiusure), scritto come l'errore di un campo.
 *
 * @var Template $view
 * @var string $id l'id della parte della pagina
 * @var string $error
 */
?>
<p class="fs-field__error" id="<?= $view->e($id) ?>-errore"><?= $view->icon('alert') ?><span><span class="sr-only">Errore: </span><?= $view->e($error) ?></span></p>
