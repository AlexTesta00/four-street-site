<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Account;

use DateTimeImmutable;
use FourStreet\Account\DuplicateEmail;
use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Account\Housekeeping;
use FourStreet\Account\NewUser;
use FourStreet\Account\PdoUserRepository;
use FourStreet\Account\Role;
use FourStreet\Account\Session\PdoSessionRepository;
use FourStreet\Account\Session\Session;
use FourStreet\Account\Token\AccountToken;
use FourStreet\Account\Token\PdoAccountTokenRepository;
use FourStreet\Account\Token\TokenPurpose;
use FourStreet\Account\User;
use FourStreet\Database\Database;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Database\Migration\Migrator;
use FourStreet\Database\Row;
use FourStreet\Database\UtcDateTime;
use FourStreet\Mail\Mailbox;
use FourStreet\Security\RateLimit\PdoRateLimitStore;
use FourStreet\Tests\Support\MovableClock;
use FourStreet\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use UnitEnum;

/**
 * I repository PDO sul database dei test: SQLite in memoria, oppure MariaDB in CI (gruppo "database").
 */
#[Group('database')]
#[CoversClass(PdoUserRepository::class)]
#[CoversClass(PdoSessionRepository::class)]
#[CoversClass(PdoAccountTokenRepository::class)]
#[CoversClass(Housekeeping::class)]
#[CoversClass(User::class)]
#[CoversClass(Session::class)]
#[CoversClass(AccountToken::class)]
#[CoversClass(DuplicateEmail::class)]
#[UsesClass(EmailAddress::class)]
#[UsesClass(FullName::class)]
#[UsesClass(NewUser::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(Row::class)]
#[UsesClass(UtcDateTime::class)]
#[UsesClass(Mailbox::class)]
#[UsesClass(PdoRateLimitStore::class)]
final class RepositoriesTest extends TestCase
{
    private MovableClock $clock;

    private Database $database;

    private PdoUserRepository $users;

    private PdoSessionRepository $sessions;

    private PdoAccountTokenRepository $tokens;

    protected function setUp(): void
    {
        $this->clock = MovableClock::at('2026-11-25 08:00:00');
        $this->database = TestDatabase::create($this->clock);
        $this->users = new PdoUserRepository($this->database);
        $this->sessions = new PdoSessionRepository($this->database);
        $this->tokens = new PdoAccountTokenRepository($this->database);
    }

    public function testCreatesAndFindsAnUnverifiedCustomer(): void
    {
        $user = $this->users->create($this->registration('mario@example.com'));

        self::assertGreaterThan(0, $user->id);
        self::assertSame('Mario Rossi', $user->fullName);
        self::assertSame('mario@example.com', $user->email->value);
        self::assertSame('hash-1', $user->passwordHash);
        self::assertNull($user->emailVerifiedAt);
        self::assertFalse($user->isVerified());
        self::assertSame(Role::Customer, $user->role);
        self::assertSame('2026-11-25 07:00:00', UtcDateTime::format($user->createdAt));
        self::assertSame('2026-11-25 07:00:00', UtcDateTime::format($user->privacyAcceptedAt));
        self::assertSame(str_repeat('f', 64), $user->signupBrowserHash);
        // Il nome di un account in sospeso non va nelle email: l'ha scritto chiunque abbia compilato il modulo.
        self::assertSame('', $user->mailbox()->name);
        self::assertNull($user->greetingName());
        self::assertSame(self::values($user), self::values($this->users->findByEmail($user->email)));
        self::assertSame(self::values($user), self::values($this->users->findById($user->id)));
        self::assertNull($this->users->findById($user->id + 1));
        self::assertNull($this->users->findByEmail($this->email('nessuno@example.com')));
    }

    public function testTheSameEmailCannotHaveTwoAccounts(): void
    {
        $this->users->create($this->registration('mario@example.com'));

        $this->expectException(DuplicateEmail::class);

        $this->users->create($this->registration('mario@example.com'));
    }

    public function testVerificationPasswordAndReplacement(): void
    {
        $user = $this->users->create($this->registration('mario@example.com'));
        $this->clock->travel('+1 hour');

        $this->users->replaceUnverified($user->id, new NewUser($this->fullName('Anna Bianchi'), $user->email, 'hash-2', $this->clock->now(), str_repeat('e', 64)));
        $replaced = $this->find($user->id);
        self::assertSame('Anna Bianchi', $replaced->fullName);
        self::assertSame('hash-2', $replaced->passwordHash);
        self::assertSame(str_repeat('e', 64), $replaced->signupBrowserHash, 'Il link fa entrare solo dal browser dell\'ultima registrazione.');
        self::assertSame('2026-11-25 08:00:00', UtcDateTime::format($replaced->privacyAcceptedAt));
        self::assertSame('2026-11-25 07:00:00', UtcDateTime::format($replaced->createdAt));

        $this->users->markEmailVerified($user->id, $this->clock->now());
        $this->clock->travel('+1 hour');
        $this->users->markEmailVerified($user->id, $this->clock->now());
        $verified = $this->find($user->id);
        self::assertNotNull($verified->emailVerifiedAt);
        self::assertSame('2026-11-25 08:00:00', UtcDateTime::format($verified->emailVerifiedAt), 'La prima conferma resta.');
        self::assertNull($verified->signupBrowserHash, 'Confermata l\'email, l\'impronta del browser non serve più.');
        self::assertSame('Anna Bianchi', $verified->mailbox()->name);
        self::assertSame('Anna Bianchi', $verified->greetingName());

        // Un account confermato non si sostituisce più.
        $this->users->replaceUnverified($user->id, new NewUser($this->fullName('Altra Persona'), $user->email, 'hash-3', $this->clock->now(), str_repeat('d', 64)));
        self::assertSame('Anna Bianchi', $this->find($user->id)->fullName);
        self::assertNull($this->find($user->id)->signupBrowserHash);

        $this->users->updatePasswordHash($user->id, 'hash-4', $this->clock->now());
        $updated = $this->find($user->id);
        self::assertSame('hash-4', $updated->passwordHash);
        self::assertSame('2026-11-25 09:00:00', UtcDateTime::format($updated->updatedAt));
    }

    public function testSessions(): void
    {
        $user = $this->users->create($this->registration('mario@example.com'));
        $now = $this->clock->now();
        $session = new Session(str_repeat('a', 64), $user->id, $now, $now, $now->modify('+30 days'));
        $this->sessions->create($session);
        $this->sessions->create(new Session(str_repeat('b', 64), $user->id, $now, $now, $now->modify('+1 day')));

        self::assertSame(self::values($session), self::values($this->sessions->find(str_repeat('a', 64))));
        self::assertNull($this->sessions->find(str_repeat('c', 64)));
        self::assertFalse($session->isExpiredAt($now->modify('+30 days -1 second')));
        self::assertTrue($session->isExpiredAt($now->modify('+30 days')));

        $this->sessions->touch(str_repeat('a', 64), $now->modify('+1 day'), $now->modify('+31 days'));
        $touched = $this->sessions->find(str_repeat('a', 64));
        self::assertNotNull($touched);
        self::assertSame('2026-12-26 07:00:00', UtcDateTime::format($touched->expiresAt));
        self::assertSame('2026-11-26 07:00:00', UtcDateTime::format($touched->lastSeenAt));

        self::assertSame(1, $this->sessions->deleteExpired($now->modify('+2 days')));
        $this->sessions->delete(str_repeat('a', 64));
        self::assertNull($this->sessions->find(str_repeat('a', 64)));

        $this->sessions->create($session);
        $this->sessions->deleteForUser($user->id);
        self::assertNull($this->sessions->find(str_repeat('a', 64)));
    }

    public function testTokensAreConsumedOnceAndOnlyBeforeExpiry(): void
    {
        $user = $this->users->create($this->registration('mario@example.com'));
        $now = $this->clock->now();
        $token = new AccountToken(str_repeat('a', 64), $user->id, TokenPurpose::EmailVerification, '/prenota/', $now, $now->modify('+48 hours'));
        $this->tokens->add($token);

        self::assertSame(self::values($token), self::values($this->tokens->find(str_repeat('a', 64), TokenPurpose::EmailVerification)));
        self::assertNull($this->tokens->find(str_repeat('a', 64), TokenPurpose::PasswordReset), 'Un link vale solo per il suo scopo.');
        self::assertFalse($this->tokens->consume(str_repeat('a', 64), TokenPurpose::PasswordReset, $now));
        self::assertFalse($this->tokens->consume(str_repeat('a', 64), TokenPurpose::EmailVerification, $now->modify('+48 hours')));
        self::assertTrue($this->tokens->consume(str_repeat('a', 64), TokenPurpose::EmailVerification, $now));
        self::assertFalse($this->tokens->consume(str_repeat('a', 64), TokenPurpose::EmailVerification, $now));

        $used = $this->tokens->find(str_repeat('a', 64), TokenPurpose::EmailVerification);
        self::assertNotNull($used);
        self::assertTrue($used->isUsed());
        self::assertSame('/prenota/', $used->returnPath);
        self::assertFalse($used->isExpiredAt($now->modify('+48 hours -1 second')));
        self::assertTrue($used->isExpiredAt($now->modify('+48 hours')));
    }

    public function testOnlyUnusedTokensOfThatPurposeAreDeleted(): void
    {
        $user = $this->users->create($this->registration('mario@example.com'));
        $now = $this->clock->now();
        foreach (['a' => TokenPurpose::EmailVerification, 'b' => TokenPurpose::EmailVerification, 'c' => TokenPurpose::PasswordReset] as $char => $purpose) {
            $this->tokens->add(new AccountToken(str_repeat($char, 64), $user->id, $purpose, null, $now, $now->modify('+1 hour')));
        }
        $this->tokens->consume(str_repeat('a', 64), TokenPurpose::EmailVerification, $now);

        $this->tokens->deleteUnused($user->id, TokenPurpose::EmailVerification);

        self::assertNotNull($this->tokens->find(str_repeat('a', 64), TokenPurpose::EmailVerification), 'Quello usato resta.');
        self::assertNull($this->tokens->find(str_repeat('b', 64), TokenPurpose::EmailVerification));
        self::assertNotNull($this->tokens->find(str_repeat('c', 64), TokenPurpose::PasswordReset));
        self::assertSame(2, $this->tokens->deleteExpired($now->modify('+1 hour')));
    }

    public function testHousekeepingDeletesWhatHasExpired(): void
    {
        $user = $this->users->create($this->registration('mario@example.com'));
        $now = $this->clock->now();
        $this->sessions->create(new Session(str_repeat('a', 64), $user->id, $now, $now, $now->modify('+1 hour')));
        $this->tokens->add(new AccountToken(str_repeat('b', 64), $user->id, TokenPurpose::PasswordReset, null, $now, $now->modify('+1 hour')));
        $rateLimits = new PdoRateLimitStore($this->database);
        $rateLimits->increment(str_repeat('c', 64), $now, $now->modify('+15 minutes'));
        $housekeeping = new Housekeeping($this->sessions, $this->tokens, $rateLimits, $this->clock);

        self::assertSame(['sessions' => 0, 'tokens' => 0, 'rate_limits' => 0], $housekeeping->run());
        $this->clock->travel('+1 hour');
        self::assertSame(['sessions' => 1, 'tokens' => 1, 'rate_limits' => 1], $housekeeping->run());
    }

    /**
     * I valori di un oggetto letto dal database, confrontabili con assertSame: le date come testo UTC.
     *
     * @return array<mixed>|null
     */
    private static function values(User|Session|AccountToken|null $object): ?array
    {
        if ($object === null) {
            return null;
        }
        $values = [];
        foreach (get_object_vars($object) as $name => $value) {
            $values[$name] = match (true) {
                $value instanceof DateTimeImmutable => UtcDateTime::format($value),
                $value instanceof EmailAddress => $value->value,
                $value instanceof UnitEnum => $value->name,
                default => $value,
            };
        }

        return $values;
    }

    private function find(int $id): User
    {
        $user = $this->users->findById($id);
        self::assertNotNull($user);

        return $user;
    }

    private function registration(string $email): NewUser
    {
        return new NewUser($this->fullName('Mario Rossi'), $this->email($email), 'hash-1', $this->clock->now(), str_repeat('f', 64));
    }

    private function email(string $email): EmailAddress
    {
        $address = EmailAddress::tryFrom($email);
        self::assertNotNull($address);

        return $address;
    }

    private function fullName(string $name): FullName
    {
        $fullName = FullName::tryFrom($name);
        self::assertNotNull($fullName);

        return $fullName;
    }
}
