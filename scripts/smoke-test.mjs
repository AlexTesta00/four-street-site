// Prova build/ come andrà in produzione: server PHP integrato sulla cartella build/,
// con build/index.php come router. Due giri:
//
// 1. Senza configurazione locale, come un server appena caricato: codici di stato, tipi di contenuto
//    e alcuni testi delle pagine principali, l'area clienti, le prenotazioni e il pannello del titolare
//    che rispondono 503 ("non ancora attiva", "Prenotazioni presto"), poi la forma del pacchetto
//    (vendor senza dev, _app/ protetta, niente config/local.php né var/).
// 2. Con una configurazione locale temporanea fuori da build/ (SQLite e posta in file .eml in una
//    cartella temporanea), indicata con FOURSTREET_LOCAL_CONFIG: migrazioni con la console del
//    pacchetto, registrazione, link letto dall'email (da un altro browser chiede la password),
//    conferma, pagina dell'account, password dimenticata con l'email mandata dopo la risposta.
//    Poi, se booking.online è true in config/site.php del pacchetto, la prenotazione di una fascia:
//    landing con le fasce vere, scelta, riepilogo, conferma, email con l'evento .ics, prenotazione
//    nell'account, disdetta e la sua email. Con booking.online a false controlla "Prenotazioni presto".
//    Infine il pannello del titolare: 404 per il cliente, admin:grant dalla console del pacchetto,
//    una persona senza account e un cliente inseriti dal pannello, le due disdette con le loro email
//    (solo al cliente), il mese, la settimana tipo, la settimana, le chiusure, le disdette e le impostazioni,
//    la chiusura di un giorno con il cliente prenotato (conferma, disdetta, email con il motivo), la pagina
//    Disdette con l'email arrivata, la stessa email segnata non partita e rimandata da lì una volta sola,
//    la riapertura, admin:list e admin:revoke, che chiude le sessioni.
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
      'data-panel-nav',
    ],
  },
  { path: '/assets/img/logo.jpg', status: 200, type: 'image/jpeg' },
  { path: '/pagina-che-non-esiste', status: 404, type: 'text/html' },
  { path: '/_app/composer.json', status: 404 },
  { path: '/_app/bin/console', status: 404 },
  // L'area clienti e le prenotazioni senza config/local.php: una pagina cortese, mai un errore.
  { path: '/account/accedi/', status: 503, type: 'text/html', body: ['Arriva presto.'] },
  { path: '/account/', status: 503, type: 'text/html', body: ['Arriva presto.'] },
  { path: '/prenota/', status: 503, type: 'text/html', body: ['Prenotazioni presto.'] },
  {
    path: '/prenota/conferma/?giorno=2026-12-07&fascia=18%3A30',
    status: 503,
    type: 'text/html',
    body: ['Prenotazioni presto.'],
  },
  {
    path: '/account/prenotazioni/1/disdici/',
    status: 503,
    type: 'text/html',
    body: ['Arriva presto.'],
  },
  // Il pannello del titolare senza config/local.php: come il resto dell'area clienti.
  { path: '/gestione/', status: 503, type: 'text/html', body: ['Arriva presto.'] },
  {
    path: '/gestione/prenotazioni/nuova/?giorno=2026-12-07&fascia=18%3A30',
    status: 503,
    type: 'text/html',
    body: ['Arriva presto.'],
  },
  {
    path: '/gestione/prenotazioni/1/disdici/',
    status: 503,
    type: 'text/html',
    body: ['Arriva presto.'],
  },
  ...[
    '/gestione/calendario/',
    '/gestione/orari/',
    '/gestione/orari/settimana/',
    '/gestione/chiusure/',
    '/gestione/disdette/',
    '/gestione/impostazioni/',
  ].map((path) => ({ path, status: 503, type: 'text/html', body: ['Arriva presto.'] })),
];

/** Forma del pacchetto: cosa deve esserci e cosa non deve mai arrivare sul server. */
const REQUIRED_FILES = [
  'index.php',
  '_app/.htaccess',
  '_app/vendor/autoload.php',
  '_app/bin/console',
  '_app/config/local.example.php',
  '_app/database/migrations/0001_create_users.php',
  '_app/database/migrations/0005_create_bookings.php',
  '_app/database/migrations/0006_create_schedule.php',
  '_app/database/migrations/0007_add_bookings_gym_email.php',
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

/** Un'email così com'è: gli allegati in base64 non si toccano. */
function readRawEmail(mailDir, name) {
  return readFileSync(join(mailDir, name), 'utf8');
}

/** L'evento .ics allegato a un'email (base64), decodificato; vuoto se non c'è. */
function calendarAttachment(raw) {
  const part = raw.match(
    /filename=prenotazione-4street\.ics\r?\n\r?\n([A-Za-z0-9+/=\r\n]+?)\r?\n--/,
  )?.[1];
  return part ? Buffer.from(part.replace(/\s+/g, ''), 'base64').toString('utf8') : '';
}

/** booking.online di config/site.php nel pacchetto, letto con PHP. */
function bookingsOnline() {
  const result = spawnSync(
    php,
    [
      '-r',
      'echo json_encode((require $argv[1])["booking"]["online"] ?? null);',
      join(buildDir, '_app/config/site.php'),
    ],
    { encoding: 'utf8' },
  );
  return result.status === 0 && result.stdout.trim() === 'true';
}

/**
 * La prenotazione di una fascia con il browser che ha appena fatto l'accesso: landing, scelta,
 * riepilogo, conferma, email con l'evento, account, disdetta ed email della disdetta.
 */
async function checkBookingJourney(browser, mailDir) {
  if (!bookingsOnline()) {
    const soon = await browser.get('/prenota/');
    check(
      soon.status === 503 && (await soon.text()).includes('Prenotazioni presto.'),
      `con booking.online a false /prenota/ risponde "Prenotazioni presto" (${soon.status})`,
    );
    console.log('     booking.online è false in config/site.php: la prenotazione non si prova.');
    return;
  }

  const home = await browser.get('/');
  const homePage = await home.text();
  check(
    home.status === 200 && homePage.includes('<form method="get" action="/prenota/conferma/"'),
    'la landing ha le fasce vere, con il modulo verso il riepilogo',
  );
  check(
    home.headers.get('cache-control') === 'no-cache',
    'la landing con i posti veri non va in cache',
  );
  check(home.headers.getSetCookie().length === 0, 'la landing non dà cookie');

  const day = await browser.get('/prenota/');
  const dayPage = await day.text();
  const slotLink = dayPage
    .match(/href="(\/prenota\/conferma\/\?giorno=\d{4}-\d{2}-\d{2}&amp;fascia=\d{2}%3A\d{2})"/)?.[1]
    ?.replaceAll('&amp;', '&');
  check(
    day.status === 200 && slotLink !== undefined,
    `GET /prenota/ ha una fascia da scegliere (${day.status})`,
  );
  if (slotLink === undefined) return;

  const summary = await browser.get(slotLink);
  const summaryPage = await summary.text();
  const giorno = summaryPage.match(/name="giorno" value="(\d{4}-\d{2}-\d{2})"/)?.[1];
  const fascia = summaryPage.match(/name="fascia" value="(\d{2}:\d{2})"/)?.[1];
  check(
    summary.status === 200 && giorno !== undefined && fascia !== undefined,
    `il riepilogo ${slotLink} ha il modulo di conferma (${summary.status})`,
  );

  const before = emailFiles(mailDir).length;
  const booked = await browser.post('/prenota/conferma/', {
    csrf: browser.cookies.get('csrf') ?? '',
    giorno: giorno ?? '',
    fascia: fascia ?? '',
  });
  await booked.text();
  check(
    booked.status === 303 &&
      booked.headers.get('location') === '/account/?avviso=prenotazione-confermata&prenotazione=1',
    `POST /prenota/conferma/ prenota e porta all'account (${booked.status} ${booked.headers.get('location')})`,
  );

  const afterBooking = emailFiles(mailDir);
  const confirmation =
    afterBooking.length === before + 1 ? readRawEmail(mailDir, afterBooking.at(-1)) : '';
  check(
    confirmation.includes('Subject: Prenotazione confermata'),
    `parte subito l'email di conferma (email: ${afterBooking.length})`,
  );
  const ics = calendarAttachment(confirmation);
  check(
    ics.includes('BEGIN:VEVENT') &&
      ics.includes('UID:prenotazione-1@') &&
      /DTSTART:\d{8}T\d{6}Z/.test(ics) &&
      /DTEND:\d{8}T\d{6}Z/.test(ics),
    "l'email ha in allegato l'evento .ics della prenotazione",
  );

  const account = await browser.get('/account/?avviso=prenotazione-confermata&prenotazione=1');
  const accountPage = await account.text();
  check(
    account.status === 200 &&
      accountPage.includes('Prenotazione confermata') &&
      accountPage.includes('href="/account/prenotazioni/1/disdici/"'),
    "l'account mostra la prenotazione, con Disdici",
  );

  const cancelPage = await browser.get('/account/prenotazioni/1/disdici/');
  await cancelPage.text();
  check(cancelPage.status === 200, `GET della disdetta chiede conferma (${cancelPage.status})`);
  const cancelled = await browser.post('/account/prenotazioni/1/disdici/', {
    csrf: browser.cookies.get('csrf') ?? '',
  });
  await cancelled.text();
  check(
    cancelled.status === 303 &&
      cancelled.headers.get('location') === '/account/?avviso=prenotazione-disdetta&prenotazione=1',
    `POST della disdetta disdice e porta all'account (${cancelled.status})`,
  );
  const afterCancel = emailFiles(mailDir);
  const cancellation =
    afterCancel.length === afterBooking.length + 1 ? readRawEmail(mailDir, afterCancel.at(-1)) : '';
  check(
    cancellation.includes('Subject: Prenotazione disdetta') &&
      !cancellation.includes('text/calendar'),
    "parte l'email della disdetta, senza allegato",
  );
}

/** Il primo "Aggiungi" del pannello, dal giorno che si apre (o da quello indicato) e poi con "giorno dopo": il modulo di una fascia. */
async function findAddLink(browser, start = '/gestione/') {
  let path = start;
  for (let step = 0; step < 31; step += 1) {
    const page = await (await browser.get(path)).text();
    const add = page.match(
      /href="(\/gestione\/prenotazioni\/nuova\/\?giorno=\d{4}-\d{2}-\d{2}&amp;fascia=\d{2}%3A\d{2})"/,
    )?.[1];
    if (add) return add.replaceAll('&amp;', '&');
    const next = page.match(
      /href="(\/gestione\/\?giorno=\d{4}-\d{2}-\d{2})"[^>]*fs-daynav__step--next/,
    )?.[1];
    if (!next) return undefined;
    path = next;
  }
  return undefined;
}

/**
 * Il pannello del titolare con il browser del cliente dello smoke test: prima non c'è (404), poi
 * admin:grant dalla console del pacchetto, il giorno con le fasce, una persona senza account e il cliente
 * stesso inseriti dal pannello (conferma con l'evento solo al cliente), le due disdette (l'email solo al
 * cliente, quella della palestra), admin:list, e admin:revoke che chiude le sessioni.
 */
async function checkAdminJourney(browser, localConfig, mailDir, databaseFile) {
  const before = await browser.get('/gestione/');
  await before.text();
  check(before.status === 404, `un cliente non vede il pannello: GET /gestione/ ${before.status}`);

  const grant = runConsole(localConfig, ['admin:grant', 'smoke@example.com']);
  check(
    grant.status === 0 && grant.stdout.includes('ora è titolare'),
    `bin/console admin:grant dà il ruolo di titolare (uscita ${grant.status})`,
  );

  const panel = await browser.get('/gestione/');
  const panelPage = await panel.text();
  check(
    panel.status === 200 &&
      panelPage.includes('Gestione prenotazioni') &&
      panelPage.includes('<nav class="fs-daynav"') &&
      panel.headers.get('cache-control') === 'no-store',
    `il titolare entra nel pannello (${panel.status})`,
  );

  const addLink = await findAddLink(browser);
  check(addLink !== undefined, 'il pannello ha una fascia dove aggiungere qualcuno');
  if (addLink === undefined) return;
  const form = await browser.get(addLink);
  const formPage = await form.text();
  const giorno = formPage.match(/name="giorno" value="(\d{4}-\d{2}-\d{2})"/)?.[1] ?? '';
  const fascia = formPage.match(/name="fascia" value="(\d{2}:\d{2})"/)?.[1] ?? '';
  check(
    form.status === 200 && giorno !== '' && fascia !== '',
    `il modulo ${addLink} (${form.status})`,
  );

  const add = (fields) =>
    browser.post('/gestione/prenotazioni/nuova/', {
      csrf: browser.cookies.get('csrf') ?? '',
      giorno,
      fascia,
      email: '',
      nome: '',
      ...fields,
    });
  const bookingId = (response) =>
    response.headers.get('location')?.match(/[?&]prenotazione=(\d+)$/)?.[1];

  const emailsBefore = emailFiles(mailDir).length;
  const guest = await add({ nome: 'Ospite Smoke' });
  await guest.text();
  const guestId = bookingId(guest);
  check(
    guest.status === 303 &&
      guest.headers
        .get('location')
        ?.startsWith(`/gestione/?giorno=${giorno}&avviso=prenotazione-inserita&`) &&
      guestId !== undefined,
    `POST del pannello inserisce una persona senza account (${guest.status} ${guest.headers.get('location')})`,
  );
  const dayPage = await (await browser.get(guest.headers.get('location') ?? '/gestione/')).text();
  check(
    dayPage.includes('Ospite Smoke') && dayPage.includes('Prenotazione inserita'),
    "il giorno mostra la persona e l'avviso",
  );
  check(
    emailFiles(mailDir).length === emailsBefore,
    "per chi non ha l'account non parte nessuna email",
  );

  const customer = await add({ email: 'smoke@example.com' });
  await customer.text();
  const customerId = bookingId(customer);
  const afterCustomer = emailFiles(mailDir);
  const confirmation =
    afterCustomer.length === emailsBefore + 1 ? readRawEmail(mailDir, afterCustomer.at(-1)) : '';
  check(
    customer.status === 303 &&
      customerId !== undefined &&
      confirmation.includes('Subject: Prenotazione confermata') &&
      calendarAttachment(confirmation).includes('BEGIN:VEVENT'),
    `il cliente inserito dal pannello riceve la conferma con l'evento (${customer.status})`,
  );

  const cancel = async (id) => {
    const page = await browser.get(`/gestione/prenotazioni/${id}/disdici/`);
    await page.text();
    const done = await browser.post(`/gestione/prenotazioni/${id}/disdici/`, {
      csrf: browser.cookies.get('csrf') ?? '',
    });
    await done.text();
    return [page.status, done];
  };
  const [guestPage, guestCancelled] = await cancel(guestId);
  check(
    guestPage === 200 &&
      guestCancelled.status === 303 &&
      guestCancelled.headers.get('location')?.includes('avviso=prenotazione-disdetta&') &&
      emailFiles(mailDir).length === afterCustomer.length,
    `la disdetta di chi non ha l'account, senza email (${guestPage} ${guestCancelled.status})`,
  );
  const [customerPage, customerCancelled] = await cancel(customerId);
  const afterCancel = emailFiles(mailDir);
  const byGym =
    afterCancel.length === afterCustomer.length + 1
      ? readRawEmail(mailDir, afterCancel.at(-1))
      : '';
  check(
    customerPage === 200 &&
      customerCancelled.status === 303 &&
      byGym.includes('Subject: Prenotazione disdetta dalla palestra'),
    `la disdetta del cliente manda l'email della palestra (${customerPage} ${customerCancelled.status})`,
  );

  await checkScheduleJourney(browser, mailDir, databaseFile);

  const list = runConsole(localConfig, ['admin:list']);
  check(
    list.status === 0 && list.stdout.includes('smoke@example.com'),
    'bin/console admin:list elenca il titolare',
  );
  const revoke = runConsole(localConfig, ['admin:revoke', 'smoke@example.com']);
  check(
    revoke.status === 0 && revoke.stdout.includes('non è più titolare'),
    `bin/console admin:revoke toglie il ruolo (uscita ${revoke.status})`,
  );
  const after = await browser.get('/gestione/');
  await after.text();
  check(
    after.status === 303 && after.headers.get('location') === '/account/accedi/?ritorno=/gestione/',
    `admin:revoke chiude le sessioni: il pannello rimanda all'accesso (${after.status})`,
  );
}

/**
 * Le pagine nuove del pannello con il titolare: il mese, la settimana tipo, la settimana, le disdette e le impostazioni;
 * poi una chiusura di un giorno da domani in poi (oggi qualche fascia potrebbe essere già cominciata, e oggi non si
 * chiude più) con il cliente prenotato: la pagina di conferma con chi c'è, la disdetta con l'email della palestra (con
 * il motivo), la chiusura nell'elenco, la pagina Disdette con l'email arrivata; poi la stessa email segnata non partita
 * nel database (come con il server di posta fermo), rimandata da lì una volta sola; infine la riapertura. Alla fine il
 * giorno è com'era.
 */
async function checkScheduleJourney(browser, mailDir, databaseFile) {
  for (const [path, marker] of [
    ['/gestione/calendario/', 'fs-month'],
    ['/gestione/orari/', 'Settimana tipo.</h1>'],
    ['/gestione/orari/settimana/', 'fs-adminnav'],
    ['/gestione/chiusure/', 'Chiusure.</h1>'],
    ['/gestione/disdette/', 'Disdette.</h1>'],
    ['/gestione/impostazioni/', 'Impostazioni.</h1>'],
  ]) {
    const page = await browser.get(path);
    const text = await page.text();
    check(
      page.status === 200 &&
        text.includes(marker) &&
        text.includes('data-panel-nav') &&
        page.headers.get('cache-control') === 'no-store',
      `il titolare apre ${path} (${page.status})`,
    );
  }

  const today = await (await browser.get('/gestione/')).text();
  const tomorrow = today.match(
    /href="(\/gestione\/\?giorno=\d{4}-\d{2}-\d{2})"[^>]*fs-daynav__step--next/,
  )?.[1];
  const addLink = tomorrow ? await findAddLink(browser, tomorrow) : undefined;
  const giorno = addLink?.match(/giorno=(\d{4}-\d{2}-\d{2})/)?.[1];
  check(
    addLink !== undefined && giorno !== undefined,
    'il pannello ha una fascia da domani in poi',
  );
  if (addLink === undefined || giorno === undefined) return;
  const time = addLink.replace(/.*fascia=(\d{2})%3A(\d{2}).*/, '$1:$2');
  await (await browser.get(addLink)).text();
  const booked = await browser.post('/gestione/prenotazioni/nuova/', {
    csrf: browser.cookies.get('csrf') ?? '',
    giorno,
    fascia: time,
    email: 'smoke@example.com',
    nome: '',
  });
  await booked.text();
  check(
    booked.status === 303,
    `il cliente è prenotato il ${giorno} alle ${time} (${booked.status})`,
  );
  const emailsBefore = emailFiles(mailDir).length;
  const close = (fields) =>
    browser.post('/gestione/chiusure/', {
      csrf: browser.cookies.get('csrf') ?? '',
      azione: 'chiudi',
      da: giorno,
      a: '',
      nota: 'prova dello smoke test',
      ...fields,
    });

  const confirmation = await close({});
  const confirmationPage = await confirmation.text();
  const fingerprint = confirmationPage.match(/name="prenotazioni" value="([\d,]+)"/)?.[1];
  check(
    confirmation.status === 200 &&
      fingerprint !== undefined &&
      confirmationPage.includes('Prova Smoke') &&
      emailFiles(mailDir).length === emailsBefore,
    `chiudere un giorno con un cliente prenotato chiede conferma, con il suo nome (${confirmation.status})`,
  );
  if (fingerprint === undefined) return;

  const closed = await close({ prenotazioni: fingerprint });
  await closed.text();
  const location = closed.headers.get('location') ?? '';
  const afterClose = emailFiles(mailDir);
  const byGym = afterClose.length === emailsBefore + 1 ? readEmail(mailDir, afterClose.at(-1)) : '';
  check(
    closed.status === 303 &&
      location.startsWith('/gestione/chiusure/?avviso=chiusura-salvata&disdette=1') &&
      !location.includes('prova') &&
      byGym.includes('Subject: Prenotazione disdetta dalla palestra') &&
      byGym.includes('Quel giorno la palestra resta chiusa.') &&
      !byGym.includes('prova dello smoke test'),
    `la chiusura confermata disdice e manda l'email con il motivo, mai la nota (${closed.status} ${location})`,
  );
  const list = await (await browser.get(location)).text();
  check(
    list.includes('prova dello smoke test') && list.includes('Chiusura salvata'),
    "l'elenco delle chiusure ha la nota del titolare, con l'avviso",
  );
  const day = await (await browser.get(`/prenota/?giorno=${giorno}`)).text();
  check(
    !day.includes(`giorno=${giorno}&amp;fascia=`),
    'il giorno chiuso non ha fasce da prenotare',
  );

  await checkCancellationsPage(browser, mailDir, databaseFile, fingerprint);

  const reopened = await browser.post('/gestione/chiusure/', {
    csrf: browser.cookies.get('csrf') ?? '',
    azione: 'riapri',
    da: giorno,
    a: giorno,
  });
  await reopened.text();
  check(
    reopened.status === 303 &&
      reopened.headers.get('location')?.startsWith('/gestione/chiusure/?avviso=chiusura-tolta'),
    `la riapertura torna alla settimana tipo (${reopened.status})`,
  );
}

/**
 * La pagina Disdette dopo la chiusura del giorno: la disdetta del cliente con l'email arrivata, niente da mandare.
 * Poi la stessa email segnata non partita nel database, come se il server di posta non avesse risposto: la pagina la
 * mostra, "Invia le email rimaste" la manda (con il motivo, il giorno è ancora chiuso) e un secondo invio del modulo
 * non la rimanda.
 */
async function checkCancellationsPage(browser, mailDir, databaseFile, bookingIds) {
  const page = await (await browser.get('/gestione/disdette/')).text();
  check(
    page.includes('Prova Smoke') &&
      page.includes('Email inviata') &&
      !page.includes('Invia le email rimaste'),
    "la pagina Disdette ha la disdetta del cliente, con l'email arrivata",
  );

  const marked = markGymEmailFailed(databaseFile, bookingIds.split(',')[0]);
  const failedPage = await (await browser.get('/gestione/disdette/')).text();
  check(
    marked.status === 0 &&
      marked.stdout.trim() === '1' &&
      failedPage.includes('Email non partita') &&
      failedPage.includes('Invia le email rimaste'),
    `un'email non partita si vede nella pagina Disdette, con il bottone (${marked.stdout.trim()})`,
  );

  const emailsBefore = emailFiles(mailDir).length;
  const resent = await browser.post('/gestione/disdette/', {
    csrf: browser.cookies.get('csrf') ?? '',
  });
  await resent.text();
  const afterResend = emailFiles(mailDir);
  const byGym =
    afterResend.length === emailsBefore + 1 ? readEmail(mailDir, afterResend.at(-1)) : '';
  check(
    resent.status === 303 &&
      resent.headers.get('location') === '/gestione/disdette/?avviso=email-inviate&partite=1' &&
      byGym.includes('Subject: Prenotazione disdetta dalla palestra') &&
      byGym.includes('Quel giorno la palestra resta chiusa.'),
    `"Invia le email rimaste" la manda, con il motivo (${resent.status} ${resent.headers.get('location')})`,
  );

  const again = await browser.post('/gestione/disdette/', {
    csrf: browser.cookies.get('csrf') ?? '',
  });
  await again.text();
  check(
    again.status === 303 &&
      again.headers.get('location') === '/gestione/disdette/?avviso=nessuna-email-da-inviare' &&
      emailFiles(mailDir).length === afterResend.length,
    `un secondo invio non la rimanda (${again.status} ${again.headers.get('location')})`,
  );
}

/** Segna non partita l'email della palestra di una prenotazione, nel database SQLite della prova. */
function markGymEmailFailed(databaseFile, bookingId) {
  return spawnSync(
    php,
    [
      '-r',
      '$s = (new PDO("sqlite:" . $argv[1]))->prepare("UPDATE bookings SET gym_email = \'failed\' WHERE id = ?");' +
        ' $s->execute([(int) $argv[2]]); echo $s->rowCount();',
      databaseFile,
      bookingId,
    ],
    { encoding: 'utf8' },
  );
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
  const databaseFile = join(temporary, '4street.sqlite');
  writeFileSync(
    localConfig,
    [
      '<?php',
      '',
      '// Configurazione locale temporanea dello smoke test: SQLite e posta in file, fuori da build/.',
      'return [',
      `    'base_url' => ${phpString(base)},`,
      `    'secret' => ${phpString(randomBytes(32).toString('hex'))},`,
      `    'database' => ['dsn' => ${phpString(`sqlite:${posix(databaseFile)}`)}],`,
      `    'mail' => ['transport' => 'file', 'directory' => ${phpString(posix(mailDir))}, 'from' => 'prove@fourstreet.studio'],`,
      '];',
      '',
    ].join('\n'),
  );

  const migrate = runConsole(localConfig, ['migrate']);
  check(
    migrate.status === 0 && migrate.stdout.includes('Database aggiornato: 7 migrazioni applicate.'),
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

    // La prenotazione di una fascia, con il browser che ha fatto l'accesso.
    await checkBookingJourney(browser, mailDir);
    // Il pannello del titolare, con lo stesso browser dopo admin:grant.
    await checkAdminJourney(browser, localConfig, mailDir, databaseFile);
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
