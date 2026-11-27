<?php

declare(strict_types=1);

namespace FourStreet\Admin\Http;

use FourStreet\Account\Http\DashboardSection;
use FourStreet\Account\Role;
use FourStreet\Account\User;
use FourStreet\Http\Request;
use FourStreet\View\Html;
use FourStreet\View\View;

/**
 * Nella pagina dell'account del titolare, il link al pannello delle prenotazioni. Per un cliente non c'è nulla:
 * nessuna pagina che vede un cliente dice che il pannello esiste.
 */
final readonly class AdminLinkSection implements DashboardSection
{
    public function __construct(
        private View $view,
    ) {}

    public function render(Request $request, User $customer): Html
    {
        return $customer->role === Role::Admin ? $this->view->render('partials/admin/account-link') : new Html('');
    }
}
