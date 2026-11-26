<?php

declare(strict_types=1);

namespace FourStreet\Config;

use FourStreet\Database\DatabaseConfig;
use FourStreet\Http\Origin;
use FourStreet\Mail\Mailbox;
use FourStreet\Mail\MailConfig;
use FourStreet\Mail\MailDirectory;
use FourStreet\Security\Fingerprinter;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Configurazione locale (config/local.php): ciò che dipende dal server e non va mai nella repo, cioè
 * database, posta in uscita, chiave segreta e indirizzo pubblico del sito. Il file è ignorato da git
 * e il pacchetto di deploy non lo contiene mai: sul server si crea a mano da config/local.example.php.
 *
 * Senza il file: in sviluppo valgono valori che funzionano da soli (SQLite in var/, posta in var/mail/);
 * nel pacchetto di deploy l'area clienti risulta non configurata (load() restituisce null).
 */
final readonly class LocalConfig
{
    /** Variabile d'ambiente con il percorso di un altro file al posto di config/local.php (la usa lo smoke test). */
    public const string FILE_VARIABLE = 'FOURSTREET_LOCAL_CONFIG';

    /** L'indirizzo del server di sviluppo (npm run serve). */
    public const string DEVELOPMENT_ORIGIN = 'http://localhost:8000';

    private const array KEYS = ['base_url', 'secret', 'database', 'mail', 'session'];

    private const array SESSION_KEYS = ['idle_days'];

    /** Dopo quanti giorni senza visite una sessione scade. */
    private const int DEFAULT_IDLE_DAYS = 30;

    /** Solo per lo sviluppo, senza config/local.php: non protegge nulla, e non deve. */
    private const string DEVELOPMENT_SECRET = 'sviluppo-locale-chiave-pubblica-non-usare-online';

    /**
     * @param string $secret chiave per le impronte (HMAC) degli indirizzi IP e delle email nei contatori
     * @param positive-int $sessionIdleDays giorni senza visite dopo cui una sessione scade
     */
    private function __construct(
        public Origin $origin,
        #[SensitiveParameter]
        public string $secret,
        public DatabaseConfig $database,
        public MailConfig $mail,
        public int $sessionIdleDays,
    ) {}

    /**
     * La configurazione locale da usare, oppure null se l'area clienti non è configurata.
     *
     * @param string|null $file il file indicato dalla variabile d'ambiente FILE_VARIABLE, se c'è
     * @param string $appRoot radice dell'applicazione (repo in sviluppo, _app/ nel pacchetto)
     * @param string|null $siteUrl site_url di config/site.php: l'origine pubblica, se base_url manca
     */
    public static function load(?string $file, string $appRoot, ?string $siteUrl): ?self
    {
        if ($file !== null) {
            if (!is_file($file)) {
                throw new InvalidSiteConfig(sprintf('Configurazione locale non trovata: %s (indicata da %s).', $file, self::FILE_VARIABLE));
            }

            return self::fromFile($file, $appRoot, $siteUrl);
        }

        $default = rtrim($appRoot, '/\\') . '/config/local.php';
        if (is_file($default)) {
            return self::fromFile($default, $appRoot, $siteUrl);
        }

        return Environment::of($appRoot) === Environment::Development ? self::development($appRoot) : null;
    }

    public static function fromFile(string $file, string $appRoot, ?string $siteUrl): self
    {
        $values = require $file;
        if (!is_array($values)) {
            throw new InvalidSiteConfig(sprintf('Il file di configurazione %s deve restituire un array.', $file));
        }

        return self::fromArray($values, $appRoot, $siteUrl, sprintf('Configurazione locale (%s)', $file));
    }

    /**
     * @param array<mixed> $values
     * @param string $source come chiamare la configurazione nei messaggi d'errore
     */
    public static function fromArray(array $values, string $appRoot, ?string $siteUrl, string $source = 'Configurazione locale'): self
    {
        $config = ConfigValues::root($values, $source);
        $config->assertKnownKeys(self::KEYS);
        $session = $config->section('session');
        $session->assertKnownKeys(self::SESSION_KEYS);

        return new self(
            self::origin($config, $siteUrl),
            self::secret($config),
            DatabaseConfig::fromConfig($config->requiredSection('database'), $appRoot),
            MailConfig::fromConfig($config->requiredSection('mail'), $appRoot),
            $session->optionalPositiveInt('idle_days', self::DEFAULT_IDLE_DAYS),
        );
    }

    /**
     * I valori di sviluppo: database SQLite in var/4street.sqlite, email in var/mail/*.eml,
     * link delle email verso http://localhost:8000. var/ è ignorata da git.
     */
    public static function development(string $appRoot): self
    {
        $var = rtrim($appRoot, '/\\') . '/var';

        return new self(
            Origin::fromUrl(self::DEVELOPMENT_ORIGIN),
            self::DEVELOPMENT_SECRET,
            DatabaseConfig::sqlite($var . '/4street.sqlite'),
            new MailConfig(new Mailbox('sviluppo@fourstreet.studio', '4Street (sviluppo)'), new MailDirectory($var . '/mail')),
            self::DEFAULT_IDLE_DAYS,
        );
    }

    private static function origin(ConfigValues $config, ?string $siteUrl): Origin
    {
        $baseUrl = $config->optionalUrl('base_url');
        if ($baseUrl === null && $siteUrl === null) {
            throw $config->invalid('base_url', 'manca: indica l\'indirizzo pubblico del sito, come "https://fourstreet.studio" (oppure site_url in config/site.php)', null);
        }

        try {
            return Origin::fromUrl($baseUrl ?? $siteUrl);
        } catch (InvalidArgumentException $error) {
            throw $baseUrl !== null
                ? $config->invalid('base_url', $error->getMessage(), $baseUrl)
                : $config->invalid('base_url', 'manca, e site_url di config/site.php non vale come origine: ' . $error->getMessage(), $siteUrl);
        }
    }

    private static function secret(ConfigValues $config): string
    {
        $secret = $config->optionalSecret('secret');
        if ($secret === null || strlen($secret) < Fingerprinter::MIN_SECRET_LENGTH) {
            throw $config->invalid(
                'secret',
                sprintf('deve essere una chiave casuale di almeno %d caratteri: generala con php -r "echo bin2hex(random_bytes(32));"', Fingerprinter::MIN_SECRET_LENGTH),
                $secret,
                hidden: true,
            );
        }

        return $secret;
    }
}
