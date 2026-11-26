<?php

declare(strict_types=1);

namespace FourStreet\Database;

/**
 * I database supportati: SQLite in sviluppo e nei test, MySQL 8 o MariaDB 10.6+ in produzione.
 * Lo stesso codice gira su entrambi; dove l'SQL cambia (schema, upsert) si sceglie con match.
 */
enum Driver: string
{
    case Sqlite = 'sqlite';
    case MySql = 'mysql';
}
