<?php

declare(strict_types=1);

namespace FourStreet\Booking\Schedule;

use RuntimeException;

/**
 * Due richieste hanno portato l'orario nel database nello stesso istante, e questa ha perso: la sua transazione
 * va annullata e rifatta da capo, perché su MySQL quello che ha già letto non vede le righe appena copiate
 * dall'altra. ScheduleEditor la rifà; non esce dal dominio.
 */
final class ScheduleConflict extends RuntimeException {}
