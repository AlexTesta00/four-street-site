<?php

declare(strict_types=1);

namespace FourStreet\Mail;

use FourStreet\Config\ConfigValues;
use FourStreet\Config\Paths;

/**
 * La posta in uscita: chi la firma e da dove parte (una casella SMTP, oppure una cartella di file .eml).
 */
final readonly class MailConfig
{
    private const array KEYS = ['transport', 'host', 'port', 'username', 'password', 'directory', 'from', 'from_name'];

    private const string DEFAULT_NAME = '4Street';

    public function __construct(
        public Mailbox $sender,
        public SmtpServer|MailDirectory $transport,
    ) {}

    /**
     * Sezione "mail" di config/local.php.
     *
     * @param string $appRoot radice dell'applicazione, per una cartella indicata con un percorso relativo
     */
    public static function fromConfig(ConfigValues $mail, string $appRoot): self
    {
        $mail->assertKnownKeys(self::KEYS);

        $from = $mail->text('from');
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw $mail->invalid('from', 'deve essere l\'indirizzo che firma le email, come "prenotazioni@fourstreet.studio"', $from);
        }
        $sender = new Mailbox($from, $mail->optionalText('from_name') ?? self::DEFAULT_NAME);

        $transport = $mail->text('transport');

        return new self($sender, match ($transport) {
            'smtp' => SmtpServer::fromConfig($mail),
            'file' => new MailDirectory(Paths::resolve($mail->text('directory'), $appRoot)),
            default => throw $mail->invalid('transport', 'deve essere "smtp" (posta vera) oppure "file" (file .eml in una cartella)', $transport),
        });
    }
}
