<?php

declare(strict_types=1);

namespace FourStreet\Account;

/**
 * Dove si va dopo l'accesso: la pagina chiesta (parametro "ritorno"); senza, l'account, oppure, per il titolare,
 * la sua pagina di lavoro (il pannello delle prenotazioni), se il sito ne ha una. L'area clienti non sa quale
 * sia: gliela passa Application.
 */
final readonly class SignInDestination
{
    /**
     * @param string|null $adminHome la pagina del titolare, per esempio "/gestione/"; null se non c'è
     */
    public function __construct(
        private ?string $adminHome = null,
    ) {}

    public function for(User $user, ReturnPath $requested): string
    {
        if ($requested->isDefault() && $this->adminHome !== null && $user->role === Role::Admin) {
            return $this->adminHome;
        }

        return $requested->value;
    }
}
