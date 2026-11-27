<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Account\Session\RequireAdmin;
use FourStreet\Account\Session\RequireLogin;
use FourStreet\Account\Session\StartSession;
use FourStreet\Http\Middleware\Middleware;
use FourStreet\Http\Middleware\NoReferrer;
use FourStreet\Http\Middleware\PrivatePage;
use FourStreet\Security\Csrf\CsrfProtection;

/**
 * I middleware delle pagine con dati personali, pronti per Route::through(). Li usa l'area clienti
 * e ogni modulo che ha bisogno di sapere chi è il visitatore (le prenotazioni, il pannello del titolare):
 *
 *     Route::form('/prenota/', $controller)->through(...$guards->member())
 */
final readonly class AccountGuards
{
    public function __construct(
        private PrivatePage $privatePage,
        private CsrfProtection $csrf,
        private StartSession $session,
        private RequireLogin $requireLogin,
        private NoReferrer $noReferrer,
        private RequireAdmin $requireAdmin,
    ) {}

    /**
     * Per tutti: niente cache né indicizzazione, moduli protetti dal CSRF, visitatore riconosciuto.
     *
     * @return list<Middleware>
     */
    public function page(): array
    {
        return [$this->privatePage, $this->csrf, $this->session];
    }

    /**
     * Solo per chi ha fatto l'accesso: gli altri vanno alla pagina di accesso e poi tornano qui.
     *
     * @return list<Middleware>
     */
    public function member(): array
    {
        return [...$this->page(), $this->requireLogin];
    }

    /**
     * Solo per il titolare: come member() (gli ospiti vanno alla pagina di accesso e tornano qui), e per chi
     * è entrato senza essere titolare la pagina non esiste (404).
     *
     * @return list<Middleware>
     */
    public function admin(): array
    {
        return [...$this->member(), $this->requireAdmin];
    }

    /**
     * Pagine aperte da un link con un token: come page(), e l'indirizzo non va mai nel Referer.
     *
     * @return list<Middleware>
     */
    public function tokenPage(): array
    {
        return [$this->noReferrer, ...$this->page()];
    }
}
