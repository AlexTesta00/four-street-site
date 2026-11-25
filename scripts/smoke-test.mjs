// Prova build/ come andrà in produzione: server PHP integrato sulla cartella build/,
// con build/index.php come router. Controlla codici di stato, tipi di contenuto e alcuni
// testi delle pagine principali, poi la forma del pacchetto (vendor senza dev, _app/ protetta).
//
// Uso: npm run assemble && npm run smoke
// Variabili: PHP_BIN (predefinito "php"), SMOKE_PORT (predefinito 8089).

import { spawn } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const buildDir = join(root, 'build');
const php = process.env.PHP_BIN || 'php';
const port = Number(process.env.SMOKE_PORT || 8089);
const base = `http://127.0.0.1:${port}`;

/** Richieste da provare. I testi cercati non dipendono dalla data (prima o dopo l'apertura). */
const REQUESTS = [
  {
    path: '/',
    status: 200,
    type: 'text/html',
    body: [
      '<html lang="it">',
      'id="live"',
      '/assets/css/app.css?v=',
      '/assets/js/app.js?v=',
      '/assets/img/logo.jpg?v=',
    ],
  },
  { path: '/links/', status: 200, type: 'text/html', body: ['<html lang="it">', 'class="fs-link'] },
  { path: '/links', status: 301, location: '/links/' },
  {
    path: '/calendario.php',
    status: 200,
    type: 'text/calendar',
    body: ['BEGIN:VCALENDAR', 'BEGIN:VEVENT', 'END:VCALENDAR'],
  },
  { path: '/assets/css/app.css', status: 200, type: 'text/css', body: ['.fs-btn'] },
  { path: '/assets/js/app.js', status: 200, type: 'javascript', body: ['data-countdown'] },
  { path: '/assets/img/logo.jpg', status: 200, type: 'image/jpeg' },
  { path: '/pagina-che-non-esiste', status: 404, type: 'text/html' },
  { path: '/_app/composer.json', status: 404 },
];

/** Forma del pacchetto: cosa deve esserci e cosa non deve mai arrivare sul server. */
const REQUIRED_FILES = ['index.php', '_app/.htaccess', '_app/vendor/autoload.php'];
const FORBIDDEN_PATHS = ['.env', '.git', 'node_modules', 'resources', 'tests', '.tools'];

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

async function checkRequest({ path, status, type, body = [], location }) {
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
}

if (!existsSync(join(buildDir, 'index.php'))) {
  console.error("smoke-test: build/ non c'è. Prima esegui npm run assemble.");
  process.exit(1);
}

checkBundle();

const serverLog = [];
const server = spawn(
  php,
  ['-S', `127.0.0.1:${port}`, '-t', buildDir, join(buildDir, 'index.php')],
  {
    cwd: buildDir,
    stdio: ['ignore', 'pipe', 'pipe'],
  },
);
server.stdout.on('data', (chunk) => serverLog.push(String(chunk)));
server.stderr.on('data', (chunk) => serverLog.push(String(chunk)));
server.on('error', (error) => serverLog.push(`${php}: ${error.message}\n`));

try {
  if (await waitForServer(server)) {
    for (const request of REQUESTS) {
      await checkRequest(request);
    }
  } else {
    check(false, `il server PHP non risponde su ${base}`);
  }
} finally {
  server.kill();
}

if (failures.length > 0) {
  console.error(`\nsmoke-test: ${failures.length} controlli falliti. Log del server PHP:\n`);
  console.error(serverLog.join('') || '(vuoto)');
  process.exit(1);
}
console.log('\nsmoke-test: tutto in ordine.');
