<?php

declare(strict_types=1);

namespace FourStreet\Account;

use FourStreet\Account\Token\AccountToken;
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
 * La conferma dell'email: il link monouso dell'email di registrazione, e la richiesta di un link nuovo.
 *
 * Il link da solo conferma l'email solo nel browser che ha inviato la registrazione (SignupBrowser).
 * Altrove (un altro dispositivo, il browser dell'app di posta, un programma che controlla i link) chiede
 * anche la password scelta registrandosi, e finché non la riceve non cambia nulla. Così un link aperto
 * da chi non ha creato l'account non lo attiva: chiunque può registrare un indirizzo non suo, e se il
 * destinatario aprisse il link per curiosità l'account sarebbe confermato con la password di un altro.
 */
final readonly class EmailVerification
{
    public function __construct(
        private UserRepository $users,
        private AccountTokenRepository $tokenRepository,
        private AccountTokens $tokens,
        private AccountEmails $emails,
        private AccountMailer $mail,
        private PasswordHasher $passwords,
        private RateLimiter $limiter,
        private Clock $clock,
    ) {}

    /**
     * Il link aperto: nel browser della registrazione conferma, altrove chiede la password (PasswordRequired)
     * senza cambiare nulla. Chi riapre un link già usato su un account confermato non vede un errore:
     * gli si dice di accedere.
     *
     * @param string|null $browserHash l'impronta del cookie SignupBrowser del browser che apre il link
     */
    public function verify(#[SensitiveParameter] string $value, ?string $browserHash): VerificationResult
    {
        $link = $this->open($value);
        if ($link instanceof VerificationResult) {
            return $link;
        }

        [$record, $user] = $link;
        $registeringBrowser = $user->signupBrowserHash !== null && $browserHash !== null
            && hash_equals($user->signupBrowserHash, $browserHash);

        return $registeringBrowser
            ? $this->confirm($record, $user)
            : VerificationResult::passwordRequired($user, ReturnPath::fromInput($record->returnPath));
    }

    /**
     * La password scritta nella pagina del link aperto altrove. I tentativi contano come quelli di accesso
     * (gli stessi contatori, prima di verificare): il link non serve a provare password all'infinito.
     * Una password vuota non è un tentativo: la pagina la chiede di nuovo (PasswordRequired).
     */
    public function verifyWithPassword(#[SensitiveParameter] string $value, #[SensitiveParameter] string $password, #[SensitiveParameter] string $ip): VerificationResult
    {
        $link = $this->open($value);
        if ($link instanceof VerificationResult) {
            return $link;
        }

        [$record, $user] = $link;
        $returnTo = ReturnPath::fromInput($record->returnPath);
        if ($password === '') {
            return VerificationResult::passwordRequired($user, $returnTo);
        }

        $status = AccountThrottle::hitLogin($this->limiter, $user->email, $ip);
        if (!$status->allowed) {
            return VerificationResult::throttled($user, $returnTo, $status->retryAfter);
        }
        if (!$this->passwords->verify($password, $user->passwordHash)) {
            return VerificationResult::wrongPassword($user, $returnTo);
        }

        AccountThrottle::resetLogin($this->limiter, $user->email, $ip);

        return $this->confirm($record, $user);
    }

    /**
     * Un link di conferma nuovo. Parte solo per un account non ancora confermato, ma la risposta
     * è sempre la stessa: non rivela se l'email ha un account. Per questo l'email parte dopo la
     * risposta (né il tempo né un invio fallito cambiano la pagina) e il risultato è Sent o Throttled.
     * Il tetto delle email si conta sempre, prima di cercare l'account, come nella registrazione: se
     * contasse solo per gli account da confermare, chi lo esaurisce qui e poi si registra con la stessa
     * email capirebbe dal tempo di risposta della registrazione se l'account c'è.
     */
    public function resend(EmailAddress $email, ReturnPath $returnTo, #[SensitiveParameter] string $ip): Delivery
    {
        $ipStatus = $this->limiter->hit(AccountThrottle::EmailRequestPerIp->limit(), IpAddress::rateLimitSubject($ip));
        if (!$ipStatus->allowed) {
            return Delivery::throttled($ipStatus->retryAfter);
        }
        if (!$this->mail->reserve($email)) {
            return Delivery::sent();
        }

        $user = $this->users->findByEmail($email);
        if ($user !== null && !$user->isVerified()) {
            $token = $this->tokens->issue($user, TokenPurpose::EmailVerification, $returnTo);
            $this->mail->sendLater($this->emails->verification($user, $token));
        }

        return Delivery::sent();
    }

    /**
     * Il link e il suo account, se il link vale ancora; altrimenti il risultato da mostrare.
     *
     * @return array{AccountToken, User}|VerificationResult
     */
    private function open(#[SensitiveParameter] string $value): array|VerificationResult
    {
        $token = Token::fromString($value);
        $record = $token === null ? null : $this->tokenRepository->find($token->hash(), TokenPurpose::EmailVerification);
        $user = $record === null ? null : $this->users->findById($record->userId);
        if ($record === null || $user === null) {
            return VerificationResult::invalid();
        }
        // Già confermata, con questo link o in un altro modo.
        if ($user->isVerified()) {
            return VerificationResult::alreadyVerified();
        }
        if ($record->isUsed()) {
            return VerificationResult::invalid();
        }

        return $record->isExpiredAt($this->clock->now()) ? VerificationResult::expired() : [$record, $user];
    }

    private function confirm(AccountToken $record, User $user): VerificationResult
    {
        $now = $this->clock->now();
        if (!$this->tokenRepository->consume($record->tokenHash, TokenPurpose::EmailVerification, $now)) {
            // Lo stesso link usato due volte nello stesso istante: l'altra richiesta ha già confermato.
            return VerificationResult::alreadyVerified();
        }

        if (!$this->users->markEmailVerified($user, $now)) {
            // Fra la lettura del link e la conferma una registrazione nuova ha preso il posto di quella letta
            // (con un'altra password e un link suo): questo link non vale più.
            return VerificationResult::invalid();
        }
        $this->tokenRepository->deleteUnused($user->id, TokenPurpose::EmailVerification);
        $verified = $this->users->findById($user->id) ?? throw new UnexpectedValueException('Account sparito durante la conferma.');

        return VerificationResult::verified($verified, ReturnPath::fromInput($record->returnPath));
    }
}
