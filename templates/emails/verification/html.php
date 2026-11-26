<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Email di conferma dopo la registrazione.
 *
 * @var Template $view
 * @var string $greeting "Ciao,": l'email non è ancora confermata, quindi niente nome
 * @var string $url il link di conferma
 * @var int $hours quanto vale il link
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/html', [
    'title' => 'Conferma la tua email',
    'preheader' => sprintf('Tocca il link per attivare il tuo account 4Street. Vale %d ore.', $hours),
    'siteName' => $siteName,
    'address' => $address,
    'siteUrl' => $siteUrl,
]);
?>
<?= $view->partial('emails/partials/paragraph', ['text' => $greeting, 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'conferma la tua email per attivare l\'account 4Street: tocca il bottone qui sotto.', 'muted' => false]) ?>
<?= $view->partial('emails/partials/button', ['url' => $url, 'label' => 'Conferma l\'email']) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => sprintf('Il link vale %d ore e si usa una volta sola. Se non hai creato tu l\'account, ignora questa email: senza conferma l\'account non si attiva.', $hours), 'muted' => true]) ?>
