<?php

declare(strict_types=1);

use FourStreet\View\Template;

/**
 * Qualcuno ha chiesto una nuova password per un account mai confermato: la registrazione non è completa
 * e va rifatta.
 *
 * @var Template $view
 * @var string $greeting "Ciao,": l'email non è confermata, quindi niente nome
 * @var string $registerUrl la pagina di registrazione
 * @var string $siteName
 * @var string $address
 * @var string $siteUrl
 */

$view->layout('emails/layout/html', [
    'title' => 'Completa la registrazione',
    'preheader' => 'La registrazione con questo indirizzo non è stata completata: rifalla per attivare l\'account.',
    'siteName' => $siteName,
    'address' => $address,
    'siteUrl' => $siteUrl,
]);
?>
<?= $view->partial('emails/partials/paragraph', ['text' => $greeting, 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'qualcuno, forse tu, ha chiesto una nuova password per l\'account 4Street di questo indirizzo. La registrazione però non è stata completata: l\'email non è mai stata confermata, quindi l\'account non è attivo.', 'muted' => false]) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'Registrati di nuovo con la password che preferisci: ti mandiamo un nuovo link per confermare l\'email.', 'muted' => false]) ?>
<?= $view->partial('emails/partials/button', ['url' => $registerUrl, 'label' => 'Registrati']) ?>
<?= $view->partial('emails/partials/paragraph', ['text' => 'Se non sei stato tu, ignora questa email: senza conferma l\'account non si attiva.', 'muted' => true]) ?>
