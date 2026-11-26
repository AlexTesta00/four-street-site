// Prova build/ come andrà in produzione: server PHP integrato sulla cartella build/,
// con build/index.php come router. Due giri:
//
// 1. Senza configurazione locale, come un server appena caricato: codici di stato, tipi di contenuto
//    e alcuni testi delle pagine principali, l'area clienti che risponde 503 ("non ancora attiva"),
//    poi la forma del pacchetto (vendor senza dev, _app/ protetta, niente config/local.php né var/).
// 2. Con una configurazione locale temporanea fuori da build/ (SQLite e posta in file .eml in una
//    cartella temporanea), indicata con FOURSTREET_LOCAL_CONFIG: migrazioni con la console del
//    pacchetto, registrazione, link letto dall'email (da un altro browser chiede la password),
//    conferma, pagina dell'account, password dimenticata con l'email mandata dopo la risposta.
//
// Alla fine build/ deve essere identica a com'era: è l'artefatto che va in produzione.
//
// Uso: npm run assemble && npm run smoke
// Variabili: PHP_BIN (predefinito "php"), SMOKE_PORT (predefinito 8089).

import { spawn, spawnSync } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { existsSync, mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, relative } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const buildDir = join(root, 'build');
const php = process.env.PHP_BIN || 'php';
const port = Number(process.env.SMOKE_PORT || 8089);
const base = `http://127.0.0.1:${port}`;

/** La variabile d'ambiente con il percorso della configurazione locale (LocalConfig::FILE_VARIABLE). */
const LOCAL_CONFIG_VARIABLE = 'FOURSTREET_LOCAL_CONFIG';

/**
 * Richieste da provare senza configurazione locale. I testi cercati (body) e quelli che non devono
 * esserci (absent) non dipendono dalla data: prima o dopo l'apertura la landing ha le stesse sezioni.
 */
const REQUESTS = [
  {
    path: '/',
    status: 200,
    type: 'text/html',
    body: [
      '<html lang="it">',
      '<h1 id="titolo"',
      'data-state="open"',
      'data-slot-picker',
      'id="dove"',
      'aria-label="Azioni rapide"',
      '<script type="application/ld+json">',
      '/assets/css/app.css?v=',
      '/assets/js/app.js?v=',
      '/assets/img/logo.jpg?v=',
    ],
    // La Content-Security-Policy non ammette stili in linea né siti incorporati.
    absent: [' style=', '<iframe', 'Set-Cookie'],
  },
  { path: '/links/', status: 200, type: 'text/html', body: ['<html lang="it">', 'class="fs-link'] },
  { path: '/links', status: 301, location: '/links/' },
  {
    path: '/calendario.php',
    status: 200,
    type: 'text/calendar',
    body: ['BEGIN:VCALENDAR', 'BEGIN:VEVENT', 'END:VCALENDAR'],
  },
  {
    path: '/assets/css/app.css',
    status: 200,
    type: 'text/css',
    body: ['.fs-btn', '.fs-slot', '.fs-input', '.fs-alert'],
  },
  {
    path: '/assets/js/app.js',
    status: 200,
    type: 'javascript',
    body: [
      'data-countdown',
      'data-slot-picker',
      'data-action-bar',
      'data-reveal',
      'data-password-toggle',
      'data-submit-once',
      'data-error-summary',
    ],
  },
  { path: '/assets/img/logo.jpg', status: 200, type: 'image/jpeg' },
  { path: '/pagina-che-non-esiste', status: 404, type: 'text/html' },
  { path: '/_app/composer.json', status: 404 },
  { path: '/_app/bin/console', status: 404 },
  // L'area clienti senza config/local.php: una pagina cortese, mai un errore.
  { path: '/account/accedi/', status: 503, type: 'text/html', body: ['Arriva presto.'] },
  { path: '/account/', status: 503, type: 'text/html', body: ['Arriva presto.'] },
];

/** Forma del pacchetto: cosa deve esserci e cosa non deve mai arrivare sul server. */
const REQUIRED_FILES = [
  'index.php',
  '_app/.htaccess',
  '_app/vendor/autoload.php',
  '_app/bin/console',
  '_app/config/local.example.php',
  '_app/database/migrations/0001_create_users.php',
];
const FORBIDDEN_PATHS = [
  '.env',
  '.git',
  'node_modules',
  'resources',
  'tests',
  '.tools',
  'var',
  '_app/var',
  '_app/config/local.php',
];

const failures = [];

function check(ok, message) {
  console.log(`${ok ? 'ok  ' : 'NO  '} ${message}`);
  if (!ok) failures.push(message);
}

function checkBundle() {
  for (const file of REQUIRED_FILES) {
    check(existsSync(join(buildDir, file)), `build/${file} presente`);
  }
  for (const path of FORBIDDEN_PATHS) {
    check(!existsSync(join(buildDir, path)), `build/${path} assente`);
  }
  const htaccess = join(buildDir, '_app/.htaccess');
  if (existsSync(htaccess)) {
    check(
      readFileSync(htaccess, 'utf8').includes('Require all denied'),
      "build/_app/.htaccess nega l'accesso",
    );
  }
  const installed = join(buildDir, '_app/vendor/composer/installed.json');
  if (existsSync(installed)) {
    const { dev } = JSON.parse(readFileSync(installed, 'utf8'));
    check(dev === false, 'vendor installato senza pacchetti dev');
  }
}

/** Impronta di ogni file di build/ (percorso => SHA-256 del contenuto). */
function snapshot(dir) {
  const files = new Map();
  const walk = (current) => {
    for (const entry of readdirSync(current, { withFileTypes: true })) {
      const path = join(current, entry.name);
      if (entry.isDirectory()) walk(path);
      else
        files.set(
          relative(dir, path),
          createHash('sha256').update(readFileSync(path)).digest('hex'),
        );
    }
  };
  walk(dir);
  return files;
}

function sameSnapshot(before, after) {
  if (before.size !== after.size) return false;
  for (const [path, hash] of before) {
    if (after.get(path) !== hash) return false;
  }
  return true;
}

/** L'ambiente dei processi PHP: senza FOURSTREET_LOCAL_CONFIG, a meno di indicarla. */
function environment(localConfig) {
  const env = { ...process.env };
  delete env[LOCAL_CONFIG_VARIABLE];
  if (localConfig) env[LOCAL_CONFIG_VARIABLE] = localConfig;
  return env;
}

function startServer(env) {
  const log = [];
  const server = spawn(
    php,
    ['-S', `127.0.0.1:${port}`, '-t', buildDir, join(buildDir, 'index.php')],
    { cwd: buildDir, env, stdio: ['ignore', 'pipe', 'pipe'] },
  );
  server.stdout.on('data', (chunk) => log.push(String(chunk)));
  server.stderr.on('data', (chunk) => log.push(String(chunk)));
  server.on('error', (error) => log.push(`${php}: ${error.message}\n`));
  return { server, log };
}

async function stopServer(server) {
  if (server.exitCode !== null || server.signalCode !== null) return;
  const exited = new Promise((resolve) => {
    server.once('exit', resolve);
  });
  server.kill();
  await exited;
}

async function waitForServer(server) {
  for (let attempt = 0; attempt < 50; attempt += 1) {
    if (server.exitCode !== null) return false;
    try {
      await fetch(`${base}/`, { redirect: 'manual' });
      return true;
    } catch {
      await delay(200);
    }
  }
  return false;
}

async function checkRequest({ path, status, type, body = [], absent = [], location }) {
  const response = await fetch(`${base}${path}`, { redirect: 'manual' });
  const contentType = response.headers.get('content-type') ?? '';
  const text = await response.text();
  check(response.status === status, `GET ${path} risponde ${response.status} (atteso ${status})`);
  if (type) {
    check(contentType.includes(type), `GET ${path} tipo "${contentType}" (atteso ${type})`);
  }
  if (location) {
    const target = response.headers.get('location') ?? '';
    check(target === location, `GET ${path} rimanda a "${target}" (atteso ${location})`);
  }
  for (const marker of body) {
    check(text.includes(marker), `GET ${path} contiene ${marker}`);
  }
  for (const marker of absent) {
    check(!text.includes(marker), `GET ${path} non contiene ${marker}`);
  }
}

/** Un browser minimo: tiene i cookie e li rimanda, come farebbe quello vero. */
function createBrowser() {
  const cookies = new Map();
  const remember = (response) => {
    for (const line of response.headers.getSetCookie()) {
      const [pair] = line.split(';');
      const separator = pair.indexOf('=');
      const name = pair.slice(0, separator);
      if (/;\s*Max-Age=0(?:;|$)/i.test(line)) cookies.delete(name);
      else cookies.set(name, pair.slice(separator + 1));
    }
  };
  const header = () => [...cookies].map(([name, value]) => `${name}=${value}`).join('; ');
  return {
    cookies,
    async get(path) {
      const response = await fetch(`${base}${path}`, {
        redirect: 'manual',
        headers: { cookie: header() },
      });
      remember(response);
      return response;
    },
    async post(path, fields) {
      const response = await fetch(`${base}${path}`, {
        method: 'POST',
        redirect: 'manual',
        headers: { cookie: header(), origin: base },
        body: new URLSearchParams(fields),
      });
      remember(response);
      return response;
    },
  };
}

/** Il testo di un messaggio MIME scritto in quoted-printable, decodificato (i link sono ASCII). */
function decodeQuotedPrintable(text) {
  return text
    .replace(/=\r?\n/g, '')
    .replace(/=([0-9A-F]{2})/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16)));
}

/** I file .eml della cartella della posta, dal più vecchio (il nome comincia con data e ora). */
function emailFiles(mailDir) {
  return existsSync(mailDir)
    ? readdirSync(mailDir)
        .filter((name) => name.endsWith('.eml'))
        .sort()
    : [];
}

/** Un'email scritta da FileMailer, decodificata. */
function readEmail(mailDir, name) {
  return decodeQuotedPrintable(readFileSync(join(mailDir, name), 'utf8'));
}

/** La console del pacchetto, con la configurazione locale indicata ("--": gli argomenti sono della console). */
function runConsole(localConfig, args) {
  return spawnSync(php, ['-f', join(buildDir, '_app/bin/console'), '--', ...args], {
    cwd: buildDir,
    env: environment(localConfig),
    encoding: 'utf8',
  });
}

/** Un testo PHP tra apici singoli. */
function phpString(value) {
  return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

/** Percorso con le barre "/", che PHP accetta anche su Windows. */
function posix(path) {
  return path.split('\\').join('/');
}

/** Il secondo giro: area clienti configurata, dalla registrazione alla pagina dell'account. */
async function checkAccountJourney(temporary) {
  const mailDir = join(temporary, 'mail');
  const localConfig = join(temporary, 'local.php');
  writeFileSync(
    localConfig,
    [
      '<?php',
      '',
      '// Configurazione locale temporanea dello smoke test: SQLite e posta in file, fuori da build/.',
      'return [',
      `    'base_url' => ${phpString(base)},`,
      `    'secret' => ${phpString(randomBytes(32).toString('hex'))},`,
      `    'database' => ['dsn' => ${phpString(`sqlite:${posix(join(temporary, '4street.sqlite'))}`)}],`,
      `    'mail' => ['transport' => 'file', 'directory' => ${phpString(posix(mailDir))}, 'from' => 'prove@fourstreet.studio'],`,
      '];',
      '',
    ].join('\n'),
  );

  const migrate = runConsole(localConfig, ['migrate']);
  check(
    migrate.status === 0 && migrate.stdout.includes('Database aggiornato: 4 migrazioni applicate.'),
    `bin/console migrate applica le migrazioni (uscita ${migrate.status})`,
  );
  if (migrate.status !== 0) console.error(migrate.stdout, migrate.stderr);
  const again = runConsole(localConfig, ['migrate']);
  check(
    again.status === 0 && again.stdout.includes('già aggiornato'),
    'bin/console migrate rilanciato non rifà nulla',
  );

  const { server, log } = startServer(environment(localConfig));
  try {
    if (!(await waitForServer(server))) {
      check(false, `il server PHP non risponde su ${base} (configurazione locale)`);
      console.error(log.join(''));
      return;
    }
    const browser = createBrowser();

    const form = await browser.get('/account/registrati/');
    const page = await form.text();
    const token = page.match(/name="csrf" value="([A-Za-z0-9_-]{43})"/)?.[1];
    check(form.status === 200, `GET /account/registrati/ risponde ${form.status} (atteso 200)`);
    check(
      token !== undefined && browser.cookies.get('csrf') === token,
      'il modulo ha il token CSRF del suo cookie',
    );

    const sent = await browser.post('/account/registrati/', {
      csrf: token ?? '',
      nome: 'Prova Smoke',
      email: 'smoke@example.com',
      password: 'una password di prova',
      privacy: '1',
    });
    check(
      sent.status === 303 &&
        sent.headers.get('location') === '/account/registrati/controlla-la-posta/',
      `POST /account/registrati/ rimanda a "Controlla la posta" (${sent.status} ${sent.headers.get('location')})`,
    );
    check(browser.cookies.has('signup'), 'il cookie del browser della registrazione è arrivato');

    const emails = emailFiles(mailDir);
    check(
      emails.length === 1,
      `una email in ${posix(relative(temporary, mailDir))}/ (trovate ${emails.length})`,
    );
    const message = emails.length > 0 ? readEmail(mailDir, emails[0]) : '';
    const link = message.match(
      new RegExp(`${base}(/account/verifica/\\?token=[A-Za-z0-9_-]{43})`),
    )?.[1];
    check(link !== undefined, "l'email ha il link di conferma verso l'origine configurata");

    // Da un altro browser il link chiede la password e non cambia nulla.
    const elsewhere = link ? await createBrowser().get(link) : null;
    const elsewherePage = elsewhere ? await elsewhere.text() : '';
    check(
      elsewhere?.status === 200 &&
        elsewherePage.includes('Conferma l&#039;email.</h1>') &&
        !elsewhere.headers.getSetCookie().some((line) => line.startsWith('session=')),
      `da un altro browser il link chiede la password, senza sessione (${elsewhere?.status})`,
    );

    const verified = link ? await browser.get(link) : null;
    check(
      verified?.status === 303 &&
        verified.headers.get('location') === '/account/?avviso=email-confermata',
      `il link conferma l'email e apre la sessione (${verified?.status})`,
    );
    check(browser.cookies.has('session'), 'il cookie di sessione è arrivato');

    const account = await browser.get('/account/');
    const accountPage = await account.text();
    check(account.status === 200, `GET /account/ risponde ${account.status} (atteso 200)`);
    check(accountPage.includes('Ciao, Prova Smoke.'), "la pagina dell'account saluta per nome");
    check(
      account.headers.get('cache-control') === 'no-store',
      "la pagina dell'account non va in cache",
    );

    // La password dimenticata: l'email parte dopo la risposta (Application::finish() in index.php).
    const guest = createBrowser();
    const forgotForm = await guest.get('/account/password-dimenticata/');
    const forgotToken = (await forgotForm.text()).match(
      /name="csrf" value="([A-Za-z0-9_-]{43})"/,
    )?.[1];
    const requested = await guest.post('/account/password-dimenticata/', {
      csrf: forgotToken ?? '',
      email: 'smoke@example.com',
    });
    await requested.text();
    check(
      requested.status === 303 &&
        requested.headers.get('location') === '/account/password-dimenticata/controlla-la-posta/',
      `POST /account/password-dimenticata/ rimanda a "Controlla la posta" (${requested.status})`,
    );
    const afterRequest = emailFiles(mailDir);
    check(
      afterRequest.length === 2 &&
        readEmail(mailDir, afterRequest[1]).includes('Subject: Scegli una nuova password'),
      `dopo la risposta parte l'email della nuova password (email: ${afterRequest.length})`,
    );
  } finally {
    await stopServer(server);
  }
  if (failures.length > 0) console.error(log.join('') || '(log del server vuoto)');
}

if (!existsSync(join(buildDir, 'index.php'))) {
  console.error("smoke-test: build/ non c'è. Prima esegui npm run assemble.");
  process.exit(1);
}

const before = snapshot(buildDir);
checkBundle();

// 1. Il pacchetto così com'è, senza configurazione locale.
const first = startServer(environment(null));
try {
  if (await waitForServer(first.server)) {
    for (const request of REQUESTS) {
      await checkRequest(request);
    }
  } else {
    check(false, `il server PHP non risponde su ${base}`);
  }
} finally {
  await stopServer(first.server);
}
if (failures.length > 0) {
  console.error('\nLog del server PHP:\n');
  console.error(first.log.join('') || '(vuoto)');
}

// 2. Con una configurazione locale temporanea, fuori da build/.
const temporary = mkdtempSync(join(tmpdir(), '4street-smoke-'));
try {
  await checkAccountJourney(temporary);
} finally {
  rmSync(temporary, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
}

check(sameSnapshot(before, snapshot(buildDir)), 'build/ è identica a prima della prova');

if (failures.length > 0) {
  console.error(`\nsmoke-test: ${failures.length} controlli falliti.`);
  process.exit(1);
}
console.log('\nsmoke-test: tutto in ordine.');
