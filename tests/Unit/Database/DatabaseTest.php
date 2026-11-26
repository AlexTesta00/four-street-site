<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Database;

use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Database\Driver;
use FourStreet\Database\Migration\Migrator;
use FourStreet\Database\Row;
use FourStreet\Database\UtcDateTime;
use FourStreet\Tests\Support\FrozenClock;
use FourStreet\Tests\Support\TestDatabase;
use LogicException;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Il comportamento di Database uguale su SQLite e su MySQL/MariaDB: gira anche con il gruppo "database".
 */
#[Group('database')]
#[CoversClass(Database::class)]
#[CoversClass(Row::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(UtcDateTime::class)]
final class DatabaseTest extends TestCase
{
    private Database $database;

    protected function setUp(): void
    {
        $this->database = TestDatabase::create();
    }

    public function testTheConnectionOpensOnlyAtTheFirstQuery(): void
    {
        $database = new Database(DatabaseConfig::sqlite(':memory:'));

        self::assertFalse($database->isConnected());
        self::assertSame(Driver::Sqlite, $database->driver());
        self::assertSame(1, $database->fetchOne('SELECT 1 AS one')?->int('one'));
        self::assertTrue($database->isConnected());
    }

    public function testWritesAndReadsWithTypedParameters(): void
    {
        $id = $this->insertUser('anna@example.com');

        self::assertGreaterThan(0, $id);
        $row = $this->database->fetchOne('SELECT id, full_name, email_verified_at FROM users WHERE id = :id', ['id' => $id]);
        self::assertNotNull($row);
        self::assertSame($id, $row->int('id'));
        self::assertSame('Anna Bianchi', $row->string('full_name'));
        self::assertNull($row->nullableString('email_verified_at'));
        self::assertNull($row->nullableDateTime('email_verified_at'));
        self::assertNull($this->database->fetchOne('SELECT id FROM users WHERE id = :id', ['id' => $id + 1]));
    }

    public function testFetchAllKeepsTheOrder(): void
    {
        $this->insertUser('b@example.com');
        $this->insertUser('a@example.com');

        $emails = array_map(
            static fn(Row $row): string => $row->string('email'),
            $this->database->fetchAll('SELECT email FROM users ORDER BY email'),
        );

        self::assertSame(['a@example.com', 'b@example.com'], $emails);
    }

    public function testExecuteCountsTheRowsItTouched(): void
    {
        $this->insertUser('a@example.com');
        $this->insertUser('b@example.com');

        self::assertSame(2, $this->database->execute('UPDATE users SET role = :role', ['role' => 'admin']));
        self::assertSame(0, $this->database->execute('DELETE FROM users WHERE email = :email', ['email' => 'c@example.com']));
    }

    public function testDatesComeBackInUtc(): void
    {
        $id = $this->insertUser('a@example.com');

        $created = $this->database->fetchOne('SELECT created_at FROM users WHERE id = :id', ['id' => $id])?->dateTime('created_at');

        self::assertNotNull($created);
        self::assertSame('2026-11-25T07:00:00+00:00', $created->format(DATE_ATOM));
    }

    public function testATransactionCommitsOrRollsBack(): void
    {
        $this->database->transaction(fn(): int => $this->insertUser('dentro@example.com'));

        try {
            $this->database->transaction(function (): void {
                $this->insertUser('annullato@example.com');

                throw new LogicException('Qualcosa è andato storto.');
            });
        } catch (LogicException $error) {
            // La transazione rilancia l'eccezione di chi la usa, così com'è.
            self::assertSame('Qualcosa è andato storto.', $error->getMessage());
        }

        self::assertSame(1, $this->rows('users'));
    }

    public function testANestedTransactionJoinsTheOuterOne(): void
    {
        try {
            $this->database->transaction(function (): void {
                $this->database->transaction(fn(): int => $this->insertUser('interno@example.com'));

                throw new LogicException('Annulla tutto.');
            });
        } catch (LogicException) {
        }

        self::assertSame(0, $this->rows('users'));
    }

    public function testForeignKeysAreEnforced(): void
    {
        $this->expectException(PDOException::class);

        $this->database->execute(
            'INSERT INTO sessions (token_hash, user_id, created_at, last_seen_at, expires_at) VALUES (:token_hash, :user_id, :created_at, :last_seen_at, :expires_at)',
            ['token_hash' => str_repeat('a', 64), 'user_id' => 999, 'created_at' => '2026-11-25 07:00:00', 'last_seen_at' => '2026-11-25 07:00:00', 'expires_at' => '2026-12-25 07:00:00'],
        );
    }

    public function testDeletingAUserDeletesItsSessions(): void
    {
        $id = $this->insertUser('a@example.com');
        $this->database->execute(
            'INSERT INTO sessions (token_hash, user_id, created_at, last_seen_at, expires_at) VALUES (:token_hash, :user_id, :created_at, :last_seen_at, :expires_at)',
            ['token_hash' => str_repeat('a', 64), 'user_id' => $id, 'created_at' => '2026-11-25 07:00:00', 'last_seen_at' => '2026-11-25 07:00:00', 'expires_at' => '2026-12-25 07:00:00'],
        );

        $this->database->execute('DELETE FROM users WHERE id = :id', ['id' => $id]);

        self::assertSame(0, $this->rows('sessions'));
    }

    public function testTextIsStoredAsUtf8Mb4(): void
    {
        $id = $this->insertUser('emoji@example.com', 'Zoë 💪 D\'Angelo');

        self::assertSame('Zoë 💪 D\'Angelo', $this->database->fetchOne('SELECT full_name FROM users WHERE id = :id', ['id' => $id])?->string('full_name'));
    }

    private function insertUser(string $email, string $name = 'Anna Bianchi'): int
    {
        $this->database->execute(
            'INSERT INTO users (full_name, email, password_hash, email_verified_at, privacy_accepted_at, role, created_at, updated_at)'
            . ' VALUES (:full_name, :email, :password_hash, :email_verified_at, :privacy_accepted_at, :role, :created_at, :updated_at)',
            [
                'full_name' => $name,
                'email' => $email,
                'password_hash' => 'hash',
                'email_verified_at' => null,
                'privacy_accepted_at' => UtcDateTime::format(FrozenClock::at('2026-11-25 08:00:00')->now()),
                'role' => 'customer',
                'created_at' => '2026-11-25 07:00:00',
                'updated_at' => '2026-11-25 07:00:00',
            ],
        );

        return $this->database->lastInsertId();
    }

    private function rows(string $table): int
    {
        return $this->database->fetchOne('SELECT COUNT(*) AS total FROM ' . $table)?->int('total') ?? -1;
    }
}
