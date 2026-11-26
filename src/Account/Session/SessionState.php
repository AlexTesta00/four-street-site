<?php

declare(strict_types=1);

namespace FourStreet\Account\Session;

use FourStreet\Account\User;
use FourStreet\Http\Cookie;

/**
 * Cosa ha trovato SessionManager nel cookie della richiesta: il visitatore e, se serve, il cookie
 * da rimandare (la sessione allungata, oppure un cookie scaduto o falso da cancellare).
 */
final readonly class SessionState
{
    private function __construct(
        public Visitor $visitor,
        public ?Cookie $cookie,
    ) {}

    public static function guest(?Cookie $cookie = null): self
    {
        return new self(Visitor::guest(), $cookie);
    }

    public static function member(User $user, ?Cookie $cookie = null): self
    {
        return new self(Visitor::member($user), $cookie);
    }
}
