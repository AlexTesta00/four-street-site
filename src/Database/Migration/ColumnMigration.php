<?php

declare(strict_types=1);

namespace FourStreet\Database\Migration;

/**
 * Una migrazione che aggiunge una colonna a una tabella che c'è già. ALTER TABLE … ADD COLUMN non si rilancia così
 * com'è (né SQLite né MySQL 8 hanno ADD COLUMN IF NOT EXISTS): il Migrator guarda prima se la colonna c'è, e se c'è la
 * migrazione era già fatta, interrotta prima di arrivare nel registro, e si registra soltanto.
 *
 * Perché basti guardare la colonna, su MySQL le istruzioni sono una sola: un ALTER TABLE è atomico, e la colonna c'è
 * con tutti i suoi vincoli oppure non c'è (SchemaTest lo controlla per ogni migrazione così). Su SQLite la migrazione
 * è comunque una transazione.
 */
interface ColumnMigration extends Migration
{
    /** La tabella a cui la migrazione aggiunge la colonna. */
    public function table(): string;

    /** La colonna che la migrazione aggiunge. */
    public function column(): string;
}
