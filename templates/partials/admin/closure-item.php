<?php

declare(strict_types=1);

use FourStreet\Admin\AdminUrls;
use FourStreet\Admin\Form\ChangeAction;
use FourStreet\Admin\Form\ChangeForm;
use FourStreet\Admin\Http\ScheduleDates;
use FourStreet\Booking\Staff\ClosureRange;
use FourStreet\View\Template;

/**
 * Una chiusura in programma: quando ("Giovedì 24 dicembre", "Da giovedì 24 a sabato 26 dicembre", l'anno quando serve),
 * la nota del titolare (a uso interno: il pannello è solo suo) e quanti giorni; a destra "Riapri", un modulo POST a sé
 * con le due date nascoste. Riaprire riporta i giorni alla settimana tipo; di solito non disdice nulla (vedi ClosuresController).
 * I bottoni sono tanti e uguali: il nome dice quale chiusura riaprono.
 *
 * @var Template $view
 * @var string $csrf
 * @var ClosureRange $closure
 * @var ScheduleDates $days
 */

$when = $days->range($closure->from, $closure->to, capitalize: true);
// I giorni del calendario, contati come differenza fra due date (calendarDay() è a mezzanotte UTC: niente cambio dell'ora).
$count = $closure->from->calendarDay()->diff($closure->to->calendarDay())->days + 1;
$meta = array_filter([$closure->note, $count > 1 ? $count . ' giorni' : null], static fn(?string $part): bool => $part !== null);
?>
    <li class="fs-closures__item">
      <div class="fs-closures__text">
        <p class="fs-closures__when"><?= $view->e($when) ?></p>
<?php if ($meta !== []): ?>
        <p class="fs-closures__meta"><?= $view->e(implode(' · ', $meta)) ?></p>
<?php endif; ?>
      </div>
      <form method="post" action="<?= $view->e(AdminUrls::CLOSURES) ?>" class="fs-closures__action" data-submit-once>
        <?= $view->partial('partials/admin/change-hidden', ['csrf' => $csrf, 'fields' => [
            ChangeForm::ACTION => ChangeAction::Reopen->value,
            ChangeForm::FROM => $closure->from->value,
            ChangeForm::TO => $closure->to->value,
        ]]) ?>
        <button type="submit" class="fs-btn fs-btn--secondary fs-btn--sm" aria-label="<?= $view->e('Riapri, ' . lcfirst($when)) ?>">Riapri</button>
      </form>
    </li>
