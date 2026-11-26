<?php

declare(strict_types=1);

use FourStreet\Account\ReturnPath;
use FourStreet\Security\Csrf\CsrfProtection;
use FourStreet\View\Template;

/**
 * I campi nascosti di ogni modulo: il token CSRF e, se c'è, la pagina a cui tornare dopo.
 *
 * @var Template $view
 * @var string $csrf
 * @var ReturnPath|null $returnTo
 */
?>
<input type="hidden" name="<?= $view->e(CsrfProtection::FIELD) ?>" value="<?= $view->e($csrf) ?>">
<?php if ($returnTo !== null && !$returnTo->isDefault()): ?>
<input type="hidden" name="<?= $view->e(ReturnPath::PARAMETER) ?>" value="<?= $view->e($returnTo->value) ?>">
<?php endif; ?>
