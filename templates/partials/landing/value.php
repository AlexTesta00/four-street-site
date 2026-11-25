<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Un contenuto che può mancare, dentro una frase o una riga: il valore, oppure il suo segnaposto.
 * Se va in pagina o no lo decide chi lo usa (PendingContent::shows()). Niente a capo in uscita.
 *
 * @var Template $view
 * @var string|null $value
 * @var string $placeholder tra parentesi quadre, "[Orari da confermare]"
 */
?>
<?php if ($value !== null): ?><?= $view->e($value) ?><?php else: ?><span class="fs-ph"><?= $view->e($placeholder) ?></span><?php endif; ?>
