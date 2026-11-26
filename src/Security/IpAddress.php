<?php

declare(strict_types=1);

namespace FourStreet\Security;

/**
 * L'indirizzo IP come soggetto dei limiti di tentativi.
 * Un IPv4 conta da solo; un IPv6 conta per la sua rete /64, perché un singolo cliente di solito ne ha
 * una intera e potrebbe cambiare indirizzo a ogni tentativo.
 */
final class IpAddress
{
    public const string UNKNOWN = 'sconosciuto';

    public static function rateLimitSubject(string $address): string
    {
        $binary = filter_var($address, FILTER_VALIDATE_IP) === false ? false : inet_pton($address);
        if ($binary === false) {
            return self::UNKNOWN;
        }
        if (strlen($binary) === 4) {
            return (string) inet_ntop($binary);
        }
        // IPv4 scritto come IPv6 (::ffff:203.0.113.7): è un IPv4.
        if (str_starts_with($binary, str_repeat("\0", 10) . "\xFF\xFF")) {
            return (string) inet_ntop(substr($binary, 12));
        }

        return bin2hex(substr($binary, 0, 8)) . '/64';
    }
}
