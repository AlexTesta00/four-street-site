<?php

declare(strict_types=1);

namespace FourStreet\Account;

use DateTimeImmutable;
use FourStreet\Mail\Mailbox;

/**
 * Un account dell'area clienti, come sta nel database. Gli istanti sono in UTC.
 */
final readonly class User
{
    /**
     * @param string|null $signupBrowserHash finché l'email non è confermata, l'impronta del cookie del browser
     *                                       che ha scelto la password: solo lì il link di conferma basta da solo
     */
    public function __construct(
        public int $id,
        public string $fullName,
        public EmailAddress $email,
        public string $passwordHash,
        public ?DateTimeImmutable $emailVerifiedAt,
        public DateTimeImmutable $privacyAcceptedAt,
        public Role $role,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $signupBrowserHash = null,
    ) {}

    /** L'email è confermata: solo allora l'account può entrare. */
    public function isVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    /**
     * Il destinatario delle email dell'account. Il nome compare solo a email confermata: prima è un testo
     * scritto da chiunque abbia compilato il modulo, e non deve arrivare nella casella di un altro.
     */
    public function mailbox(): Mailbox
    {
        return $this->email->mailbox($this->isVerified() ? $this->fullName : '');
    }

    /** Il nome con cui salutare nelle email, oppure null se l'email non è ancora confermata. */
    public function greetingName(): ?string
    {
        return $this->isVerified() ? $this->fullName : null;
    }
}
