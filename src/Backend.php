<?php

declare(strict_types=1);

namespace FourStreet;

use FourStreet\Clock\Clock;
use FourStreet\Config\LocalConfig;
use FourStreet\Database\Database;
use FourStreet\Http\Origin;
use FourStreet\Mail\FileMailer;
use FourStreet\Mail\Mailer;
use FourStreet\Mail\SmtpMailer;
use FourStreet\Mail\SmtpServer;
use FourStreet\Security\Password\NativePasswordHasher;
use FourStreet\Security\Password\PasswordHasher;
use SensitiveParameter;

/**
 * Il lato server delle funzioni con i dati: database, posta, password e impostazioni di config/local.php.
 * Senza (Application::create() con null) l'area clienti risponde "non ancora attiva" e il resto del sito
 * funziona come sempre. I test ne costruiscono uno con SQLite in memoria e la posta in memoria.
 */
final readonly class Backend
{
    /**
     * @param Origin $origin indirizzo pubblico del sito: link delle email, cookie Secure, controllo di Origin
     * @param string $secret chiave delle impronte (HMAC) di indirizzi IP ed email nei contatori dei tentativi
     * @param positive-int $sessionIdleDays giorni senza visite dopo cui una sessione scade
     */
    public function __construct(
        public Database $database,
        public Mailer $mailer,
        public PasswordHasher $passwords,
        public Origin $origin,
        #[SensitiveParameter]
        public string $secret,
        public int $sessionIdleDays = 30,
    ) {}

    public static function fromLocalConfig(LocalConfig $config, Clock $clock): self
    {
        $mail = $config->mail;

        return new self(
            new Database($config->database),
            $mail->transport instanceof SmtpServer
                ? SmtpMailer::connect($mail->transport, $mail->sender, $clock)
                : FileMailer::inDirectory($mail->transport, $mail->sender, $clock),
            NativePasswordHasher::recommended(),
            $config->origin,
            $config->secret,
            $config->sessionIdleDays,
        );
    }
}
