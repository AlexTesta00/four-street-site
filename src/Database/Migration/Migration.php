<?php

declare(strict_types=1);

namespace FourStreet\Database\Migration;

use FourStreet\Database\Driver;

/**
 * Una modifica dello schema del database, in un file di database/migrations/ ("0001_create_users.php")
 * che restituisce un oggetto con questa interfaccia. Le migrazioni si applicano in ordine di nome, una volta sola:
 * la tabella schema_migrations ricorda quelle già fatte.
 */
interface Migration
{
    /**
     * Le istruzioni SQL per quel database, nell'ordine in cui eseguirle.
     * Ognuna deve poter girare di nuovo senza danni (CREATE TABLE IF NOT EXISTS, indici dentro CREATE TABLE
     * su MySQL): su MySQL le istruzioni sullo schema chiudono la transazione, quindi una migrazione
     * interrotta a metà si rilancia così com'è.
     *
     * @return non-empty-list<string>
     */
    public function statements(Driver $driver): array;
}
