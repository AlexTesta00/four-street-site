<?php

declare(strict_types=1);

use FourStreet\Schedule\Countdown;
use FourStreet\View\Template;

/**
 * Le cifre cromate del countdown. Lo script le aggiorna allo scatto di ogni secondo: data-unit dice
 * di quale unità sono le celle, role="timer" porta la frase per i lettori di schermo.
 *
 * @var Template $view
 * @var Countdown $countdown
 * @var int $firstStep passo di entrata della prima unità (fs-step-N); le altre seguono una per passo
 */
?>
<div class="fs-cd-box">
  <div class="fs-cd" role="timer" aria-label="Conto alla rovescia per l'inaugurazione">
<?php foreach ($countdown->units() as $index => $unit): ?>
    <div class="fs-cd__unit fs-anim-rise fs-step-<?= $view->e($firstStep + $index) ?><?= $unit->key === 'secondi' ? ' fs-cd__unit--live' : '' ?>" data-unit="<?= $view->e($unit->key) ?>" aria-hidden="true">
      <div class="fs-cd__digits">
<?php foreach ($unit->digits() as $digit): ?>
        <span class="fs-cd__cell"><span class="fs-cd__old fs-chrome"></span><span class="fs-cd__new fs-chrome"><?= $view->e($digit) ?></span></span>
<?php endforeach; ?>
      </div>
      <div class="fs-cd__label fs-label"><?= $view->e($unit->label) ?></div>
    </div>
<?php endforeach; ?>
  </div>
</div>
