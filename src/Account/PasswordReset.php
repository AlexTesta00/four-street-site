<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Account\Session\SessionManager;
use FourStreet\Account\Token\AccountTokenRepository;
use FourStreet\Account\Token\AccountTokens;
use FourStreet\Account\Token\TokenPurpose;
use FourStreet\Clock\Clock;
use FourStreet\Security\IpAddress;
use FourStreet\Security\Password\PasswordHasher;
use FourStreet\Security\RateLimit\RateLimiter;
use FourStreet\Security\Token;
use SensitiveParameter;
use UnexpectedValueException;

/**
 * La password dimenticata: un link monouso valido un'ora, poi la password nuova. Vale solo per gli account
 * con l'email confermata: un account in sospeso ha il nome, la password e il consenso di chi ha compilato la
 * registrazione, magari un estraneo, e la nuova password non deve farli propri.
 * La richiesta risponde sempre allo stesso modo, che l'email abbia un account o no.
 */
final readonly class PasswordReset
{
    public function __construct(
        private UserRepository $users,
        private AccountTokenRepository $tokenRepository,
        private AccountTokens $tokens,
        private PasswordHasher $passwords,
        private SessionManager $sessions,
        private AccountEmails $emails,
        private AccountMailer $mail,
        private RateLimiter $limiter,
        private Clock $clock,
    ) {}

    /**
     * Manda il link per la nuova password, se l'email ha un account confermato; a un account mai confermato
     * scrive invece che la registrazione non è completa, con il link per rifarla (la registrazione nuova
     * prende il posto di quella in sospeso). L'email parte dopo la risposta, così la pagina non cambia né
     * nel tempo né per un invio fallito: il risultato è Sent o Throttled.
     * Il tetto delle email si conta sempre, prima di cercare l'account, come nella registrazione: se contasse
     * solo per gli account che esistono, chi lo esaurisce qui e poi si registra con la stessa email capirebbe
     * dal tempo di risposta della registrazione se l'account c'è.
     */
    public function request(EmailAddress $email, ReturnPath $returnTo, #[SensitiveParameter] string $ip): Delivery
    {
        $ipStatus = $this->limiter->hit(AccountThrottle::EmailRequestPerIp->limit(), IpAddress::rateLimitSubject($ip));
        if (!$ipStatus->allowed) {
            return Delivery::throttled($ipStatus->retryAfter);
        }
        if (!$this->mail->reserve($email)) {
            return Delivery::sent();
        }

        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return Delivery::sent();
        }
        if ($user->isVerified()) {
            $token = $this->tokens->issue($user, TokenPurpose::PasswordReset, $returnTo);
            $this->mail->sendLater($this->emails->passwordReset($user, $token));
        } else {
            $this->mail->sendLater($this->emails->registrationIncomplete($user, $returnTo));
        }

        return Delivery::sent();
    }

    /** Il link aperto, se vale ancora (esiste, non è scaduto, non è stato usato) e l'account è confermato. */
    public function ticket(#[SensitiveParameter] ?string $value): ?ResetTicket
    {
        $token = Token::fromString($value);
        $record = $token === null ? null : $this->tokenRepository->find($token->hash(), TokenPurpose::PasswordReset);
        if ($token === null || $record === null || $record->isUsed() || $record->isExpiredAt($this->clock->now())) {
            return null;
        }
        $user = $this->users->findById($record->userId);

        return $user === null || !$user->isVerified() ? null : new ResetTicket($token, $user, ReturnPath::fromInput($record->returnPath));
    }

    /**
     * Cambia la password e usa il link. Poi chiude le sessioni dell'utente su ogni dispositivo
     * e lo avvisa per email.
     *
     * @return User|null l'utente aggiornato, oppure null se nel frattempo il link non valeva più
     */
    public function complete(ResetTicket $ticket, #[SensitiveParameter] string $password): ?User
    {
        $now = $this->clock->now();
        if (!$this->tokenRepository->consume($ticket->token->hash(), TokenPurpose::PasswordReset, $now)) {
            return null;
        }

        $userId = $ticket->user->id;
        $this->users->updatePasswordHash($userId, $this->passwords->hash($password), $now);
        $this->tokenRepository->deleteUnused($userId, TokenPurpose::PasswordReset);
        $this->sessions->endAll($ticket->user);
        $user = $this->users->findById($userId) ?? throw new UnexpectedValueException('Account sparito durante il cambio della password.');

        // L'avviso è per la sicurezza: non conta nel limite di email e parte dopo la risposta; se non parte
        // resta nel log, ma la password è cambiata comunque.
        $this->mail->sendLater($this->emails->passwordChanged($ticket->user, $now));

        return $user;
    }
}
