// Prepara build/, il pacchetto che la CI carica in public_html su Hostinger.
//
//   build/          copia di public/: front controller, .htaccess, asset costruiti
//   build/_app/     src, templates, config, composer.json, composer.lock e vendor senza pacchetti dev,
//                   con un .htaccess che nega ogni accesso dal browser
//
// public/index.php trova da solo l'applicazione: usa _app/ se esiste, altrimenti la radice della repo.
// Composer: comando preso da COMPOSER_BIN (predefinito "composer"), per esempio
// COMPOSER_BIN="php .tools/composer.phar".
// Si lancia con npm run assemble, che prima ricostruisce sempre CSS e JavaScript:
// lo script controlla solo che i file costruiti ci siano.

import { spawnSync } from 'node:child_process';
import {
  copyFileSync,
  existsSync,
  mkdirSync,
  readdirSync,
  rmSync,
  statSync,
  writeFileSync,
} from 'node:fs';
import { basename, dirname, extname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const publicDir = join(root, 'public');
const buildDir = join(root, 'build');
const appDir = join(buildDir, '_app');

/** Cartelle dell'applicazione copiate in _app/: dentro si tengono solo i file con queste estensioni. */
const APP_DIRS = ['src', 'templates', 'config'];
const APP_EXTENSIONS = new Set(['.php']);
const APP_FILES = ['composer.json', 'composer.lock'];

/** File costruiti da npm run build: senza, il sito non avrebbe stili né script. */
const BUILT_ASSETS = ['assets/css/app.css', 'assets/js/app.js'];

/** File di sistema che non devono finire sul server. */
const JUNK_FILES = new Set(['desktop.ini', 'thumbs.db', '.ds_store']);

/**
 * Require per Apache 2.4 e LiteSpeed, Deny per Apache 2.2, RewriteRule per i server
 * che dall'.htaccess leggono solo le regole di rewrite (OpenLiteSpeed).
 */
const DENY_ALL_HTACCESS = `# Cartella interna dell'applicazione: mai raggiungibile dal browser.
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Order allow,deny
  Deny from all
</IfModule>
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteRule ^ - [F]
</IfModule>
`;

function log(message) {
  console.log(`assemble: ${message}`);
}

function fail(message) {
  console.error(`\nassemble: ERRORE: ${message}\n`);
  process.exit(1);
}

function display(path) {
  return relative(root, path).split('\\').join('/');
}

function isJunk(name) {
  return JUNK_FILES.has(name.toLowerCase());
}

/** Tutti i file sotto una cartella, ricorsivamente. */
function listFiles(dir) {
  const files = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) files.push(...listFiles(path));
    else if (entry.isFile()) files.push(path);
  }
  return files;
}

/** Copia i file per cui keep() è vero; crea solo le cartelle che servono. Restituisce gli scartati. */
function copyTree(from, to, keep) {
  const skipped = [];
  for (const file of listFiles(from)) {
    const target = join(to, relative(from, file));
    if (!keep(file)) {
      skipped.push(file);
      continue;
    }
    mkdirSync(dirname(target), { recursive: true });
    copyFileSync(file, target);
  }
  return skipped;
}

// 1. Controlli: senza questi file il pacchetto non funzionerebbe.
if (!existsSync(join(publicDir, 'index.php'))) {
  fail('manca public/index.php, il front controller del sito.');
}
for (const file of BUILT_ASSETS) {
  const path = join(publicDir, file);
  if (!existsSync(path) || statSync(path).size === 0) {
    fail(
      `public/${file} manca o è vuoto. Costruiscilo con npm run build (npm run assemble lo fa da solo).`,
    );
  }
}
for (const dir of APP_DIRS) {
  if (!existsSync(join(root, dir))) fail(`manca la cartella ${dir}/.`);
}
for (const name of APP_FILES) {
  if (!existsSync(join(root, name))) fail(`manca ${name}.`);
}

// 2. Si parte sempre da una cartella vuota.
rmSync(buildDir, { recursive: true, force: true, maxRetries: 3 });
log('build/ pulita');

// 3. La radice web: tutto public/, tranne i file di sistema.
const skippedPublic = copyTree(publicDir, buildDir, (file) => !isJunk(basename(file)));
log('public/ copiata in build/');

// 4. L'applicazione in build/_app/: solo PHP, più i file di Composer.
const skippedApp = [];
for (const dir of APP_DIRS) {
  skippedApp.push(
    ...copyTree(join(root, dir), join(appDir, dir), (file) =>
      APP_EXTENSIONS.has(extname(file).toLowerCase()),
    ),
  );
}
for (const name of APP_FILES) {
  copyFileSync(join(root, name), join(appDir, name));
}
writeFileSync(join(appDir, '.htaccess'), DENY_ALL_HTACCESS);
log(`${APP_DIRS.join(', ')} e ${APP_FILES.join(', ')} copiati in build/_app/`);

for (const file of [...skippedPublic, ...skippedApp]) {
  log(`saltato ${display(file)}`);
}

// 5. Dipendenze di produzione (nessun pacchetto dev) e autoloader ottimizzato.
const composer = process.env.COMPOSER_BIN?.trim() || 'composer';
const command = `${composer} install --no-dev --optimize-autoloader --no-interaction --working-dir=build/_app`;
log(command);
// La shell serve a interpretare COMPOSER_BIN come un comando completo (per esempio "php composer.phar").
const result = spawnSync(command, { cwd: root, stdio: 'inherit', shell: true });
if (result.error) fail(`Composer non è partito: ${result.error.message}`);
if (result.status !== 0) {
  fail(
    `Composer è uscito con il codice ${result.status}. Comando usato (COMPOSER_BIN): ${composer}`,
  );
}
if (!existsSync(join(appDir, 'vendor', 'autoload.php'))) {
  fail('Composer non ha creato build/_app/vendor/autoload.php.');
}

// 6. Riepilogo.
const files = listFiles(buildDir);
const bytes = files.reduce((total, file) => total + statSync(file).size, 0);
log(`pronto: ${files.length} file, ${(bytes / 1024).toFixed(0)} KiB in build/`);
