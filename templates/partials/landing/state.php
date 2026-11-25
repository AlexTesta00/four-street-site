<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Un testo che allo zero del countdown cambia: la versione "aperti" e quella di prima dell'apertura.
 * Prima dello zero la versione "aperti" è già in pagina, nascosta: lo script la mostra senza ricaricare.
 * Dopo l'apertura la versione di prima non serve più e non viene scritta. Niente a capo in uscita.
 *
 * @var Template $view
 * @var bool $open la palestra è aperta (o l'anteprima "mappa" lo finge)
 * @var Html|string $before prima dell'apertura
 * @var Html|string $after dal giorno dell'apertura
 */
?>
<span data-state="open"<?= $open ? '' : ' hidden' ?>><?= $view->e($after) ?></span><?php if (!$open): ?><span data-state="pre"><?= $view->e($before) ?></span><?php endif; ?>
