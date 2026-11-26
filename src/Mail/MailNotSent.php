<?php

declare(strict_types=1);

namespace FourStreet\Mail;

use RuntimeException;

/**
 * Un'email non è partita (server SMTP irraggiungibile, credenziali sbagliate, cartella non scrivibile).
 * Non è mai un errore 500: chi manda l'email lo registra e la pagina propone di riprovare.
 */
final class MailNotSent extends RuntimeException {}
