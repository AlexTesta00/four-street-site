<?php

declare(strict_types=1);

namespace FourStreet\Account\Session;

use FourStreet\Account\User;
use FourStreet\Http\Request;
use LogicException;

/**
 * Chi sta guardando la pagina: un ospite, oppure un utente che ha fatto l'accesso.
 * Lo mette nella richiesta il middleware StartSession; i controller lo leggono con Visitor::of($request).
 */
final readonly class Visitor
{
    private function __construct(
        public ?User $user,
    ) {}

    public static function guest(): self
    {
        return new self(null);
    }

    public static function member(User $user): self
    {
        return new self($user);
    }

    /** Il visitatore della richiesta. Una rotta senza StartSession è un errore di programmazione. */
    public static function of(Request $request): self
    {
        return $request->context(self::class)
            ?? throw new LogicException('Nessun visitatore nella richiesta: la rotta deve passare da StartSession.');
    }

    public function isLoggedIn(): bool
    {
        return $this->user !== null;
    }

    /** L'utente che ha fatto l'accesso: nelle rotte protette da RequireLogin c'è sempre. */
    public function user(): User
    {
        return $this->user ?? throw new LogicException('Nessun utente: la rotta deve passare da RequireLogin.');
    }
}
