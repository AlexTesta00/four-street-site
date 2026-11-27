<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Account\User;
use FourStreet\Http\Request;
use FourStreet\View\Html;

/**
 * Una sezione della pagina dell'account che porta un altro modulo (le prenotazioni): l'area clienti
 * la mette in pagina senza sapere cosa contiene. Riceve la richiesta (per i suoi avvisi) e il cliente.
 */
interface DashboardSection
{
    public function render(Request $request, User $customer): Html;
}
