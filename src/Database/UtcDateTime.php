<?php

declare(strict_types=1);

namespace FourStreet\Database;

use DateTimeImmutable;
use DateTimeZone;
use UnexpectedValueException;

/**
 * Date e ore nel database: sempre in UTC, nel formato delle colonne DATETIME ("2026-12-06 14:00:00").
 * La conversione verso Europe/Rome si fa solo quando si mostrano.
 */
final class UtcDateTime
{
    public const string FORMAT = 'Y-m-d H:i:s';

    public static function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }

    public static function parse(string $value): DateTimeImmutable
    {
        $time = DateTimeImmutable::createFromFormat('!' . self::FORMAT, $value, new DateTimeZone('UTC'));
        if ($time === false || $time->format(self::FORMAT) !== $value) {
            throw new UnexpectedValueException(sprintf('Data e ora non valide nel database: "%s".', $value));
        }

        return $time;
    }
}
