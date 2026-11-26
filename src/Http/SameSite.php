<?php

declare(strict_types=1);

namespace FourStreet\Http;

/**
 * Quando il browser manda un cookie con una richiesta partita da un altro sito.
 */
enum SameSite: string
{
    /** Solo con le richieste del sito stesso. */
    case Strict = 'Strict';

    /** Anche aprendo un link da un altro sito (una email), mai con i moduli inviati da un altro sito. */
    case Lax = 'Lax';
}
