<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Config;

use FourStreet\Config\ConfigValues;
use FourStreet\Config\Environment;
use FourStreet\Config\InvalidSiteConfig;
use FourStreet\Config\LocalConfig;
use FourStreet\Config\Paths;
use FourStreet\Database\DatabaseConfig;
use FourStreet\Database\Driver;
use FourStreet\Http\Origin;
use FourStreet\Mail\Mailbox;
use FourStreet\Mail\MailConfig;
use FourStreet\Mail\MailDirectory;
use FourStreet\Mail\SmtpServer;
use FourStreet\Security\Fingerprinter;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalConfig::class)]
#[CoversClass(Environment::class)]
#[CoversClass(Paths::class)]
#[CoversClass(DatabaseConfig::class)]
#[CoversClass(MailConfig::class)]
#[CoversClass(SmtpServer::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(InvalidSiteConfig::class)]
#[UsesClass(Origin::class)]
#[UsesClass(Mailbox::class)]
#[UsesClass(MailDirectory::class)]
final class LocalConfigTest extends TestCase
{
    private const string APP_ROOT = '/srv/4street';
    private const string SITE_URL = 'https://fourstreet.studio';

    private TemporaryDirectory $temporary;

    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory('4street-config');
    }

    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    /**
     * Una configurazione di produzione completa, come quella del server.
     *
     * @return array{
     *     base_url: string,
     *     secret: string,
     *     database: array{dsn: string, username: string, password: string},
     *     mail: array{transport: string, host: string, port: int, username: string, password: string, from: string, from_name: string},
     *     session: array{idle_days: int},
     * }
     */
    private static function production(): array
    {
        return [
            'base_url' => 'https://fourstreet.studio',
            'secret' => str_repeat('a1', 32),
            'database' => [
                'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=u1_4street',
                'username' => 'u1_4street',
                'password' => ' con spazi ',
            ],
            'mail' => [
                'transport' => 'smtp',
                'host' => 'smtp.hostinger.com',
                'port' => 465,
                'username' => 'prenotazioni@fourstreet.studio',
                'password' => 'segreta',
                'from' => 'prenotazioni@fourstreet.studio',
                'from_name' => '4Street',
            ],
            'session' => ['idle_days' => 14],
        ];
    }

    public function testReadsAProductionConfiguration(): void
    {
        $config = LocalConfig::fromArray(self::production(), self::APP_ROOT, self::SITE_URL);

        self::assertSame('https://fourstreet.studio', (string) $config->origin);
        self::assertSame(str_repeat('a1', 32), $config->secret);
        self::assertSame(Driver::MySql, $config->database->driver);
        self::assertSame('mysql:host=127.0.0.1;port=3306;dbname=u1_4street', $config->database->dsn);
        self::assertSame('u1_4street', $config->database->username);
        self::assertSame(' con spazi ', $config->database->password, 'Le password si prendono come sono.');
        self::assertSame('prenotazioni@fourstreet.studio', $config->mail->sender->email);
        self::assertSame('4Street', $config->mail->sender->name);
        $smtp = $config->mail->transport;
        self::assertInstanceOf(SmtpServer::class, $smtp);
        self::assertSame('smtp.hostinger.com', $smtp->host);
        self::assertSame(465, $smtp->port);
        self::assertTrue($smtp->implicitTls());
        self::assertTrue($smtp->requiresTls());
        self::assertSame(14, $config->sessionIdleDays);
    }

    public function testOptionalValues(): void
    {
        $values = self::production();
        unset($values['base_url'], $values['session'], $values['database']['username'], $values['database']['password'], $values['mail']['from_name']);
        $values['mail']['port'] = 587;
        $config = LocalConfig::fromArray($values, self::APP_ROOT, self::SITE_URL);

        self::assertSame('https://fourstreet.studio', (string) $config->origin, 'Senza base_url vale site_url.');
        self::assertSame(30, $config->sessionIdleDays);
        self::assertNull($config->database->username);
        self::assertNull($config->database->password);
        self::assertSame('4Street', $config->mail->sender->name);
        self::assertInstanceOf(SmtpServer::class, $config->mail->transport);
        self::assertFalse($config->mail->transport->implicitTls());
    }

    public function testSqliteAndFileMailWithPathsFromTheApplicationRoot(): void
    {
        $values = self::production();
        $values['database'] = ['dsn' => 'sqlite:var/prova.sqlite'];
        $values['mail'] = ['transport' => 'file', 'directory' => 'var/posta', 'from' => 'prove@fourstreet.studio'];
        $config = LocalConfig::fromArray($values, self::APP_ROOT . '/', self::SITE_URL);

        self::assertSame('sqlite:/srv/4street/var/prova.sqlite', $config->database->dsn);
        self::assertSame('/srv/4street/var/prova.sqlite', $config->database->sqliteFile());
        self::assertInstanceOf(MailDirectory::class, $config->mail->transport);
        self::assertSame('/srv/4street/var/posta', $config->mail->transport->path);

        $values['database'] = ['dsn' => 'sqlite:C:\dati\4street.sqlite'];
        self::assertSame('sqlite:C:\dati\4street.sqlite', LocalConfig::fromArray($values, self::APP_ROOT, null)->database->dsn);
        $values['database'] = ['dsn' => 'sqlite::memory:'];
        self::assertNull(LocalConfig::fromArray($values, self::APP_ROOT, null)->database->sqliteFile());
    }

    public function testTheDevelopmentDefaults(): void
    {
        $config = LocalConfig::development('/repo');

        self::assertSame(LocalConfig::DEVELOPMENT_ORIGIN, (string) $config->origin);
        self::assertSame('/repo/var/4street.sqlite', $config->database->sqliteFile());
        self::assertInstanceOf(MailDirectory::class, $config->mail->transport);
        self::assertSame('/repo/var/mail', $config->mail->transport->path);
        self::assertGreaterThanOrEqual(Fingerprinter::MIN_SECRET_LENGTH, strlen($config->secret));
    }

    public function testWithoutAFileDevelopmentUsesItsDefaultsAndThePackageIsNotConfigured(): void
    {
        $repository = $this->temporaryDirectory('repo');
        $package = $this->temporaryDirectory('_app');

        $development = LocalConfig::load(null, $repository, self::SITE_URL);
        self::assertNotNull($development);
        self::assertSame(LocalConfig::DEVELOPMENT_ORIGIN, (string) $development->origin);
        self::assertNull(LocalConfig::load(null, $package, self::SITE_URL));
        self::assertSame(Environment::Development, Environment::of($repository));
        self::assertSame(Environment::Production, Environment::of($package . '/'));
    }

    public function testTheLocalFileWinsInBothPlaces(): void
    {
        $package = $this->temporaryDirectory('_app');
        mkdir($package . '/config');
        file_put_contents($package . '/config/local.php', '<?php return ' . var_export(self::production(), true) . ';');

        $config = LocalConfig::load(null, $package, self::SITE_URL);

        self::assertNotNull($config);
        self::assertSame(Driver::MySql, $config->database->driver);
    }

    public function testTheEnvironmentVariableChoosesAnotherFile(): void
    {
        $directory = $this->temporaryDirectory('altrove');
        file_put_contents($directory . '/local.php', '<?php return ' . var_export(self::production(), true) . ';');

        $config = LocalConfig::load($directory . '/local.php', self::APP_ROOT, self::SITE_URL);

        self::assertNotNull($config);
        self::assertSame('smtp.hostinger.com', $config->mail->transport instanceof SmtpServer ? $config->mail->transport->host : null);
    }

    public function testAFileNamedByTheEnvironmentVariableMustExist(): void
    {
        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage('Configurazione locale non trovata: /non/esiste.php (indicata da FOURSTREET_LOCAL_CONFIG).');

        LocalConfig::load('/non/esiste.php', self::APP_ROOT, self::SITE_URL);
    }

    public function testTheFileMustReturnAnArray(): void
    {
        $directory = $this->temporaryDirectory('rotto');
        file_put_contents($directory . '/local.php', "<?php\nreturn 'no';\n");

        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage('deve restituire un array');

        LocalConfig::load($directory . '/local.php', self::APP_ROOT, self::SITE_URL);
    }

    public function testErrorsNameTheFile(): void
    {
        $directory = $this->temporaryDirectory('errore');
        $values = self::production();
        $values['database']['dsn'] = 'pgsql:host=localhost';
        file_put_contents($directory . '/local.php', '<?php return ' . var_export($values, true) . ';');

        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage('Configurazione locale (' . $directory . '/local.php) non valida: "database.dsn" deve iniziare con "mysql:"');

        LocalConfig::load($directory . '/local.php', self::APP_ROOT, self::SITE_URL);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidValues(): iterable
    {
        $production = self::production();

        yield 'chiave sconosciuta' => [[...$production, 'databse' => []], 'chiavi sconosciute (databse)'];
        yield 'base_url in http' => [[...$production, 'base_url' => 'http://fourstreet.studio'], '"base_url" deve iniziare con https://'];
        yield 'base_url con un percorso' => [[...$production, 'base_url' => 'https://fourstreet.studio/sito/'], '"base_url" deve essere solo l\'origine'];
        yield 'secret mancante' => [array_diff_key($production, ['secret' => true]), '"secret" deve essere una chiave casuale di almeno 32 caratteri'];
        yield 'secret corto' => [[...$production, 'secret' => 'corta'], '(trovato: un testo di 5 caratteri)'];
        yield 'database mancante' => [array_diff_key($production, ['database' => true]), '"database" deve essere un elenco chiave => valore'];
        yield 'DSN senza database' => [[...$production, 'database' => ['dsn' => 'mysql:host=127.0.0.1']], '"database.dsn" deve indicare il database con dbname=…'];
        yield 'password del database non testo' => [[...$production, 'database' => ['dsn' => 'sqlite::memory:', 'password' => 123]], '"database.password" deve essere un testo, oppure vuoto (trovato: int)'];
        yield 'password con a capo' => [[...$production, 'database' => ['dsn' => 'sqlite::memory:', 'password' => "a\nb"]], '"database.password" deve stare su una riga, senza caratteri di controllo (trovato: un testo di 3 caratteri)'];
        yield 'trasporto sconosciuto' => [[...$production, 'mail' => [...$production['mail'], 'transport' => 'sendmail']], '"mail.transport" deve essere "smtp" (posta vera) oppure "file"'];
        yield 'mittente non email' => [[...$production, 'mail' => [...$production['mail'], 'from' => '4Street']], '"mail.from" deve essere l\'indirizzo che firma le email'];
        yield 'host con spazi' => [[...$production, 'mail' => [...$production['mail'], 'host' => 'smtp hostinger com']], '"mail.host" deve essere il nome del server SMTP'];
        yield 'porta impossibile' => [[...$production, 'mail' => [...$production['mail'], 'port' => 70000]], '"mail.port" deve essere una porta'];
        yield 'porta come testo' => [[...$production, 'mail' => [...$production['mail'], 'port' => '465']], '"mail.port" deve essere un numero intero maggiore di zero'];
        yield 'giorni di sessione a zero' => [[...$production, 'session' => ['idle_days' => 0]], '"session.idle_days" deve essere un numero intero maggiore di zero'];
        yield 'chiave sconosciuta nella sessione' => [[...$production, 'session' => ['days' => 3]], 'chiavi sconosciute in "session" (days)'];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('invalidValues')]
    public function testFailsWithAClearMessage(array $values, string $message): void
    {
        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage($message);

        LocalConfig::fromArray($values, self::APP_ROOT, self::SITE_URL);
    }

    public function testWithoutAnyPublicAddress(): void
    {
        $values = self::production();
        unset($values['base_url']);

        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage('"base_url" manca: indica l\'indirizzo pubblico del sito');

        LocalConfig::fromArray($values, self::APP_ROOT, null);
    }

    public function testASiteUrlThatIsNotAnOrigin(): void
    {
        $values = self::production();
        unset($values['base_url']);

        $this->expectException(InvalidSiteConfig::class);
        $this->expectExceptionMessage('"base_url" manca, e site_url di config/site.php non vale come origine');

        LocalConfig::fromArray($values, self::APP_ROOT, 'https://fourstreet.studio/sito');
    }

    public function testSecretsNeverAppearInErrors(): void
    {
        try {
            LocalConfig::fromArray([...self::production(), 'secret' => 'troppo-corta'], self::APP_ROOT, self::SITE_URL);
            self::fail('La configurazione non doveva essere valida.');
        } catch (InvalidSiteConfig $error) {
            self::assertStringNotContainsString('troppo-corta', $error->getMessage());
        }
    }

    public function testTheExampleFileIsValid(): void
    {
        $values = require SiteFixture::rootDirectory() . '/config/local.example.php';
        self::assertIsArray($values);

        $config = LocalConfig::fromArray([...$values, 'secret' => str_repeat('b', 64)], self::APP_ROOT, self::SITE_URL);

        self::assertSame(Driver::MySql, $config->database->driver);
    }

    /**
     * Una cartella vuota con quel nome: "_app" è il pacchetto di deploy, ogni altro nome una repo.
     */
    private function temporaryDirectory(string $name): string
    {
        return $this->temporary->directory($name);
    }
}
