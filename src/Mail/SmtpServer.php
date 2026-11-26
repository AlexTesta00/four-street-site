<?php

declare(strict_types=1);

namespace FourStreet\Mail;

use FourStreet\Config\ConfigValues;
use SensitiveParameter;

/**
 * La casella SMTP che manda le email (per Hostinger: smtp.hostinger.com, porta 465).
 * Porta 465: TLS dal primo byte. Ogni altra porta (587): STARTTLS obbligatorio, così la password
 * non viaggia mai in chiaro; solo un server su questo computer (localhost, per le prove) può farne a meno.
 */
final readonly class SmtpServer
{
    private const array LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1'];

    public function __construct(
        public string $host,
        public int $port,
        public ?string $username = null,
        #[SensitiveParameter]
        public ?string $password = null,
    ) {}

    /**
     * Le chiavi SMTP della sezione "mail" di config/local.php.
     */
    public static function fromConfig(ConfigValues $mail): self
    {
        $host = $mail->text('host');
        if (preg_match('/\A[A-Za-z0-9.-]+\z/', $host) !== 1) {
            throw $mail->invalid('host', 'deve essere il nome del server SMTP, come "smtp.hostinger.com"', $host);
        }
        $port = $mail->positiveInt('port');
        if ($port > 65_535) {
            throw $mail->invalid('port', 'deve essere una porta, di solito 465 oppure 587', (string) $port);
        }

        return new self($host, $port, $mail->optionalText('username'), $mail->optionalSecret('password'));
    }

    /** TLS dalla connessione (porta 465, "SSL" nei pannelli dei provider). */
    public function implicitTls(): bool
    {
        return $this->port === 465;
    }

    /** Senza TLS non si manda nulla, tranne verso un server di prova su questo computer. */
    public function requiresTls(): bool
    {
        return !in_array(strtolower($this->host), self::LOCAL_HOSTS, true);
    }
}
