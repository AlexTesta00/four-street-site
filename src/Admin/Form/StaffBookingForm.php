<?php

declare(strict_types=1);

namespace FourStreet\Admin\Form;

use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Http\Form\FormErrors;
use FourStreet\Http\Request;
use LogicException;

/**
 * Il modulo della prenotazione inserita dal titolare: per chi è il posto. Due campi alternativi, se ne compila
 * esattamente uno: l'email di un cliente registrato (gli arriva la conferma e la trova nel suo account), oppure
 * il nome di una persona senza account (chi scrive su WhatsApp o passa in palestra: nessuna email).
 * Qui si controlla solo come sono scritti; se l'email è di un cliente lo dice StaffBooking.
 * I valori scritti tornano nel modulo se c'è un errore.
 */
final readonly class StaffBookingForm
{
    public const string EMAIL = 'email';
    public const string NAME = 'nome';

    private function __construct(
        public string $email,
        public string $name,
        public FormErrors $errors,
    ) {}

    public static function blank(): self
    {
        return new self('', '', new FormErrors());
    }

    public static function fromRequest(Request $request): self
    {
        $email = $request->bodyParam(self::EMAIL) ?? '';
        $name = $request->bodyParam(self::NAME) ?? '';
        $hasEmail = trim($email) !== '';
        $hasName = FullName::normalize($name) !== '';

        $errors = new FormErrors();
        if (!$hasEmail && !$hasName) {
            $errors = $errors->with(self::EMAIL, 'Scrivi l\'email del cliente registrato, oppure il nome di chi non ha l\'account.');
        } elseif ($hasEmail && $hasName) {
            $errors = $errors->with(self::NAME, 'Scrivi l\'email oppure il nome, non tutti e due: con l\'email la prenotazione va nell\'account del cliente.');
        } elseif ($hasEmail && EmailAddress::tryFrom($email) === null) {
            $errors = $errors->with(self::EMAIL, 'Scrivi un indirizzo email valido, come nome@esempio.it.');
        } elseif ($hasName && FullName::tryFrom($name) === null) {
            $errors = $errors->with(self::NAME, mb_strlen(FullName::normalize($name)) > FullName::MAX_LENGTH
                ? sprintf('Il nome può avere al massimo %d caratteri.', FullName::MAX_LENGTH)
                : 'Scrivi il nome della persona, almeno due lettere.');
        }

        return new self($email, $name, $errors);
    }

    /** Lo stesso modulo, con l'email che non è di un cliente registrato (o non è ancora confermata). */
    public function withUnknownCustomer(): self
    {
        return new self($this->email, $this->name, (new FormErrors())->with(
            self::EMAIL,
            'Nessun cliente con questa email, o non l\'ha ancora confermata. Controlla l\'indirizzo, oppure scrivi il nome qui sotto.',
        ));
    }

    /**
     * Lo stesso modulo, con il cliente che ha già una prenotazione quel giorno (se ne ha una al giorno). Se quella
     * si disdice ancora, il modulo dice come spostarla; se è già iniziata, non propone una disdetta che non c'è.
     *
     * @param string $time l'orario della sua prenotazione, "07:00"
     * @param bool $cancellable quella prenotazione non è ancora iniziata: il titolare la può disdire
     */
    public function withBookingThatDay(string $time, bool $cancellable): self
    {
        return new self($this->email, $this->name, (new FormErrors())->with(self::EMAIL, $cancellable
            ? sprintf('Questo cliente è già prenotato quel giorno, alle %s, e si prenota una fascia al giorno. Per spostarlo, disdici prima quella prenotazione.', $time)
            : sprintf('Questo cliente quel giorno è già stato prenotato alle %s, e si prenota una fascia al giorno: quella fascia è già iniziata.', $time)));
    }

    public function isValid(): bool
    {
        return $this->errors->isEmpty();
    }

    /** L'email del cliente registrato, se il modulo è per lui; null se è per una persona senza account. */
    public function customerEmail(): ?EmailAddress
    {
        $this->assertValid();

        return trim($this->email) === '' ? null : EmailAddress::tryFrom($this->email);
    }

    /** Il nome della persona senza account, se il modulo è per lei. */
    public function guestName(): ?FullName
    {
        $this->assertValid();

        return FullName::normalize($this->name) === '' ? null : FullName::tryFrom($this->name);
    }

    private function assertValid(): void
    {
        if (!$this->isValid()) {
            throw new LogicException('Modulo della prenotazione non valido.');
        }
    }
}
