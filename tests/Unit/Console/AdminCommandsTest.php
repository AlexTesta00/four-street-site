<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Console;

use FourStreet\Account\AdminRoles;
use FourStreet\Account\EmailAddress;
use FourStreet\Account\FullName;
use FourStreet\Account\NewUser;
use FourStreet\Account\PdoUserRepository;
use FourStreet\Account\Role;
use FourStreet\Account\RoleChange;
use FourStreet\Account\Session\PdoSessionRepository;
use FourStreet\Account\Session\Session;
use FourStreet\Account\User;
use FourStreet\Console\Command\AdminGrantCommand;
use FourStreet\Console\Command\AdminListCommand;
use FourStreet\Console\Command\AdminRevokeCommand;
use FourStreet\Console\Command\EmailArgument;
use FourStreet\Console\ConsoleApplication;
use FourStreet\Console\Output;
use FourStreet\Database\Database;
use FourStreet\Tests\Support\FrozenClock;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Il ruolo di titolare dalla console: admin:grant, admin:revoke e admin:list, solo su account che esistono e,
 * per darlo, con l'email confermata. Togliere il ruolo chiude le sessioni dell'account.
 */
#[Group('database')]
#[CoversClass(AdminGrantCommand::class)]
#[CoversClass(AdminRevokeCommand::class)]
#[CoversClass(AdminListCommand::class)]
#[CoversClass(EmailArgument::class)]
#[CoversClass(AdminRoles::class)]
#[CoversClass(ConsoleApplication::class)]
final class AdminCommandsTest extends TestCase
{
    private Database $database;

    private ConsoleApplication $console;

    /** @var resource */
    private mixed $stdout;

    /** @var resource */
    private mixed $stderr;

    protected function setUp(): void
    {
        $this->stdout = self::memory();
        $this->stderr = self::memory();
        $this->database = TestDatabase::create();
        $this->console = ConsoleApplication::create(SiteFixture::rootDirectory(), FrozenClock::at('2026-11-25 08:00:00'), $this->database);
        $this->user('titolare@example.test', 'Titolare Prova', verified: true);
        $this->user('sospeso@example.test', 'In Sospeso', verified: false);
    }

    public function testGrantListAndRevoke(): void
    {
        self::assertSame(0, $this->command('admin:list'));
        self::assertSame("Nessun titolare. Per nominarne uno: php bin/console admin:grant <email>\n", $this->out());

        self::assertSame(0, $this->command('admin:grant', '  Titolare@Example.TEST '));
        self::assertSame("titolare@example.test ora è titolare: dopo l'accesso trova il pannello delle prenotazioni, /gestione/.\n", $this->out());
        self::assertSame(Role::Admin, $this->role('titolare@example.test'));

        self::assertSame(0, $this->command('admin:grant', 'titolare@example.test'), 'Rilanciato non cambia nulla.');
        self::assertSame("titolare@example.test è già titolare: nulla da cambiare.\n", $this->out());

        self::assertSame(0, $this->command('admin:list'));
        self::assertSame("titolare@example.test  Titolare Prova\n", $this->out());

        // Due sessioni aperte del titolare e una di un altro: togliere il ruolo chiude solo le sue.
        $this->user('cliente@example.test', 'Cliente Prova', verified: true);
        $this->session('titolare@example.test', 'a');
        $this->session('titolare@example.test', 'b');
        $this->session('cliente@example.test', 'c');

        self::assertSame(0, $this->command('admin:revoke', 'titolare@example.test'));
        self::assertSame("titolare@example.test non è più titolare: le sue sessioni sono chiuse su ogni dispositivo.\n", $this->out());
        self::assertSame(Role::Customer, $this->role('titolare@example.test'));
        self::assertSame(1, $this->database->fetchOne('SELECT COUNT(*) AS total FROM sessions')?->int('total'));

        self::assertSame(0, $this->command('admin:revoke', 'titolare@example.test'));
        self::assertSame("titolare@example.test non è titolare: nulla da cambiare.\n", $this->out());
        self::assertSame('', $this->err());
    }

    public function testOnlyAnExistingAccountWithAConfirmedEmailBecomesAdmin(): void
    {
        self::assertSame(1, $this->command('admin:grant', 'nessuno@example.test'));
        self::assertSame("Nessun account con l'email nessuno@example.test: prima registrati dal sito e conferma l'email, poi rilancia il comando.\n", $this->err());

        self::assertSame(1, $this->command('admin:grant', 'sospeso@example.test'));
        self::assertSame("L'account sospeso@example.test non ha ancora confermato l'email: apri il link dell'email di conferma, poi rilancia il comando.\n", $this->err());
        self::assertSame(Role::Customer, $this->role('sospeso@example.test'));

        self::assertSame(1, $this->command('admin:revoke', 'nessuno@example.test'));
        self::assertSame("Nessun account con l'email nessuno@example.test.\n", $this->err());
        self::assertSame('', $this->out());
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function wrongArguments(): iterable
    {
        yield 'senza email' => [[], "Manca l'email dell'account. Uso: php bin/console admin:grant <email>\n"];
        yield 'due email' => [['a@example.test', 'b@example.test'], "Un'email alla volta. Uso: php bin/console admin:grant <email>\n"];
        yield 'email scritta male' => [['titolare@'], "Email non valida: \"titolare@\". Uso: php bin/console admin:grant <email>\n"];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('wrongArguments')]
    public function testTheEmailArgumentIsExplained(array $arguments, string $message): void
    {
        self::assertSame(1, $this->command('admin:grant', ...$arguments));
        self::assertSame($message, $this->err());
        self::assertSame([], (new AdminRoles(new PdoUserRepository($this->database), new PdoSessionRepository($this->database), FrozenClock::at('2026-11-25 08:00:00')))->admins());
    }

    public function testTheServiceSaysWhatChanged(): void
    {
        $roles = new AdminRoles(new PdoUserRepository($this->database), new PdoSessionRepository($this->database), FrozenClock::at('2026-11-25 08:00:00'));
        $email = EmailAddress::tryFrom('titolare@example.test') ?? self::fail();

        self::assertSame(RoleChange::Unchanged, $roles->revoke($email));
        self::assertSame(RoleChange::Changed, $roles->grant($email));
        self::assertSame(RoleChange::Unchanged, $roles->grant($email));
        self::assertSame(['titolare@example.test'], array_map(static fn(User $user): string => $user->email->value, $roles->admins()));
        self::assertSame(RoleChange::Changed, $roles->revoke($email));
        self::assertSame(RoleChange::Unverified, $roles->grant(EmailAddress::tryFrom('sospeso@example.test') ?? self::fail()));
        self::assertSame(RoleChange::UnknownAccount, $roles->grant(EmailAddress::tryFrom('nessuno@example.test') ?? self::fail()));
    }

    public function testWithoutADatabaseTheRoleCommandsExplainWhatToDo(): void
    {
        $console = ConsoleApplication::create(SiteFixture::rootDirectory(), FrozenClock::at('2026-11-25 08:00:00'), null);

        foreach (['admin:grant', 'admin:revoke', 'admin:list'] as $command) {
            self::assertSame(1, $console->run([$command, 'titolare@example.test'], $this->streams()));
            self::assertSame("Il database non è configurato: crea config/local.php partendo da config/local.example.php.\n", $this->err());
        }
    }

    private function command(string ...$arguments): int
    {
        return $this->console->run(array_values($arguments), $this->streams());
    }

    /** Nuovi flussi per ogni comando: out() ed err() leggono solo l'ultimo. */
    private function streams(): Output
    {
        $this->stdout = self::memory();
        $this->stderr = self::memory();

        return new Output($this->stdout, $this->stderr);
    }

    private function out(): string
    {
        return self::written($this->stdout);
    }

    private function err(): string
    {
        return self::written($this->stderr);
    }

    private function user(string $email, string $name, bool $verified): void
    {
        $users = new PdoUserRepository($this->database);
        $user = $users->create(new NewUser(FullName::tryFrom($name) ?? self::fail(), EmailAddress::tryFrom($email) ?? self::fail(), 'hash', FrozenClock::at('2026-11-25 08:00:00')->now(), str_repeat('0', 64)));
        if ($verified) {
            $users->markEmailVerified($user->id, FrozenClock::at('2026-11-25 08:00:00')->now());
        }
    }

    private function role(string $email): Role
    {
        return (new PdoUserRepository($this->database))->findByEmail(EmailAddress::tryFrom($email) ?? self::fail())->role ?? self::fail();
    }

    private function session(string $email, string $token): void
    {
        $user = (new PdoUserRepository($this->database))->findByEmail(EmailAddress::tryFrom($email) ?? self::fail()) ?? self::fail();
        $now = FrozenClock::at('2026-11-25 08:00:00')->now();
        (new PdoSessionRepository($this->database))->create(new Session(str_repeat($token, 64), $user->id, $now, $now, $now->modify('+30 days')));
    }

    /**
     * @param resource $stream
     */
    private static function written(mixed $stream): string
    {
        rewind($stream);

        return str_replace(PHP_EOL, "\n", (string) stream_get_contents($stream));
    }

    /**
     * @return resource
     */
    private static function memory(): mixed
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        return $stream;
    }
}
