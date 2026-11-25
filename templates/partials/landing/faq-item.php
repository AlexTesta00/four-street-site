<?php

declare(strict_types=1);

use FourStreet\View\Html;
use FourStreet\View\Template;

/**
 * Una domanda frequente: la domanda apre e chiude la risposta (details e summary del browser).
 *
 * @var Template $view
 * @var string $question
 * @var Html|string $answer
 * @var bool $expanded aperta quando si carica la pagina
 */
?>
<details class="fs-faq__item"<?= $expanded ? ' open' : '' ?>>
  <summary class="fs-faq__question">
    <span class="text-lead text-fg"><?= $view->e($question) ?></span>
    <?= $view->icon('plus', 'fs-faq__icon') ?>
  </summary>
  <p class="fs-faq__answer text-body"><?= $view->e($answer) ?></p>
</details>
