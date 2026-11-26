<?php

declare(strict_types=1);

namespace FourStreet\Security\Password;

use SensitiveParameter;

/**
 * Calcola e verifica gli hash delle password. Dietro un'interfaccia: in produzione Argon2id (o bcrypt),
 * nei test gli stessi algoritmi con un costo basso.
 */
interface PasswordHasher
{
    public function hash(#[SensitiveParameter] string $password): string;

    public function verify(#[SensitiveParameter] string $password, string $hash): bool;

    /** L'hash è stato calcolato con parametri diversi da quelli di oggi: va rifatto al prossimo accesso. */
    public function needsRehash(string $hash): bool;

    /**
     * Un hash che nessuna password verifica, con i parametri di oggi: verificarlo costa quanto un hash vero.
     * Serve quando l'email non corrisponde a nessun account, perché il tempo di risposta non lo riveli.
     */
    public function dummyHash(): string;

    /** I byte della password che l'algoritmo considera davvero (bcrypt: 72), oppure null se non ha limite. */
    public function maxBytes(): ?int;
}
