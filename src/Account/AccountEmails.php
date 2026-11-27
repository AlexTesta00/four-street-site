<?php

declare(strict_types=1);

namespace FourStreet\Account;

use DateTimeImmutable;
use FourStreet\Account\Token\TokenPurpose;
use FourStreet\Config\SiteConfig;
use FourStreet\Http\Origin;
use FourStreet\Mail\MailTemplates;
use FourStreet\Mail\Message;
use FourStreet\Security\Token;
use FourStreet\Text\ItalianDateFormatter;

/**
 * Le email dell'area clienti. I link sono assoluti e nascono dall'origine pubblica del sito
 * (base_url di config/local.php), mai dall'intestazione Host della richiesta. Il nome della persona
 * (nel destinatario e nel saluto) compare solo a email confermata: prima è un testo scritto da chiunque
 * abbia compilato la registrazione, e non deve arrivare nella casella di un altro.
 */
final readonly class AccountEmails
{
    public function __construct(
        private MailTemplates $templates,
        private Origin $origin,
        private SiteConfig $site,
        private ItalianDateFormatter $dates,
    ) {}

    /** Il link per confermare l'email, dopo la registrazione. */
    public function verification(User $user, Token $token): Message
    {
        return $this->templates->message($user->mailbox(), 'Conferma la tua email', 'verification', $this->data($user, [
            'url' => $this->origin->url(AccountUrls::verify($token)),
            'hours' => intdiv(TokenPurpose::EmailVerification->lifetimeSeconds(), 3600),
        ]));
    }

    /** Qualcuno ha provato a registrarsi con un'email che ha già un account confermato. */
    public function alreadyRegistered(User $user, ReturnPath $returnTo): Message
    {
        return $this->templates->message($user->mailbox(), 'Hai già un account 4Street', 'already-registered', $this->data($user, [
            'loginUrl' => $this->origin->url(AccountUrls::to(AccountUrls::LOGIN, $returnTo)),
            'resetUrl' => $this->origin->url(AccountUrls::to(AccountUrls::FORGOT_PASSWORD, $returnTo)),
        ]));
    }

    /** Il link per scegliere una nuova password. */
    public function passwordReset(User $user, Token $token): Message
    {
        return $this->templates->message($user->mailbox(), 'Scegli una nuova password', 'password-reset', $this->data($user, [
            'url' => $this->origin->url(AccountUrls::resetPassword($token)),
        ]));
    }

    /**
     * Qualcuno ha chiesto una nuova password per un account mai confermato: la registrazione non è completa
     * e va rifatta (prende il posto di quella in sospeso). Il link porta alla pagina di registrazione.
     */
    public function registrationIncomplete(User $user, ReturnPath $returnTo): Message
    {
        return $this->templates->message($user->mailbox(), 'Completa la registrazione', 'registration-incomplete', $this->data($user, [
            'registerUrl' => $this->origin->url(AccountUrls::to(AccountUrls::REGISTER, $returnTo)),
        ]));
    }

    /** Avviso dopo il cambio della password. */
    public function passwordChanged(User $user, DateTimeImmutable $at): Message
    {
        $local = $at->setTimezone($this->site->timezone);

        return $this->templates->message($user->mailbox(), 'La tua password è cambiata', 'password-changed', $this->data($user, [
            'when' => $this->dates->format($local, capitalize: false) . ' alle ' . $local->format('H:i'),
            'resetUrl' => $this->origin->url(AccountUrls::FORGOT_PASSWORD),
        ]));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function data(User $user, array $data): array
    {
        $name = $user->greetingName();

        return $data + [
            'greeting' => $name === null ? 'Ciao,' : 'Ciao ' . $name . ',',
            'siteName' => $this->site->name,
            'siteUrl' => (string) $this->origin,
            'address' => $this->site->address,
        ];
    }
}
