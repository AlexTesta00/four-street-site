<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Security\Token;

/**
 * Un link per la nuova password ancora valido: il token, il suo account e la pagina a cui tornare dopo.
 */
final readonly class ResetTicket
{
    public function __construct(
        public Token $token,
        public User $user,
        public ReturnPath $returnTo,
    ) {}
}
