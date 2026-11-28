<?php

declare(strict_types=1);

namespace FourStreet\Booking\Staff;

/**
 * Perché un giro di email della palestra si è fermato prima della fine (GymEmailSender): le email dopo non sono state
 * provate, e restano com'erano, in attesa o non partite.
 */
enum GymEmailStop
{
    /** È finito il tempo degli invii della richiesta (GymEmailSender::SECONDS_PER_REQUEST). */
    case TimeUp;

    /** Due invii falliti di fila: il server di posta non risponde. */
    case MailDown;
}
