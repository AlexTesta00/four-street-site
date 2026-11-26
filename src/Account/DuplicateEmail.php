<?php

declare(strict_types=1);

namespace FourStreet\Account;

use RuntimeException;

/**
 * L'email è già di un altro account (due registrazioni nello stesso istante: la seconda perde).
 */
final class DuplicateEmail extends RuntimeException {}
