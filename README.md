# 4Street · sito

Sito di 4Street, palestra a San Marino: [fourstreet.studio](https://fourstreet.studio).

Oggi il sito ha tre indirizzi:

- `/`: la landing. La regola dei cinque posti per fascia oraria, come funzionerà la prenotazione, lo spazio, dove siamo con la mappa illustrata, le domande frequenti e l'invito a venire. Su mobile e tablet una barra in fondo allo schermo tiene a portata "Vieni a trovarci" e WhatsApp. Fino all'inaugurazione la pagina ha il conto alla rovescia; allo zero passa allo stato "aperti" senza ricaricare, e dal giorno dell'apertura il server la scrive già così. Per provare gli stati: `/?anteprima=mappa` (la pagina come se fossimo aperti) e `/?anteprima=zero` (il conto arriva a zero dopo 12 secondi).
- `/links/`: la pagina dei link.
- `/calendario.php`: l'evento dell'inaugurazione da aggiungere al calendario (file `.ics`).

Stack: HTML, Tailwind CSS, JavaScript e PHP, senza framework. Produzione: hosting condiviso Hostinger, PHP 8.3, Apache/LiteSpeed con `.htaccess`, deploy via FTPS in `public_html`.

## Architettura in breve

```text
config/site.php      contenuti e impostazioni del sito
public/              radice web: solo ciò che il browser può raggiungere
  index.php          unico punto d'ingresso PHP (front controller)
  .htaccess          rimanda gli indirizzi a index.php, blocca file nascosti e _app/, cache degli asset
  assets/            immagini; css/app.css e js/app.js li genera la build
resources/css/       sorgenti Tailwind: app.css importa token, base, componenti (uno per file) e movimento
resources/js/        sorgenti JavaScript: app.js collega le funzioni di features/, la logica pura sta in lib/
src/                 classi PHP, namespace FourStreet\
templates/           viste PHP: layouts/, partials/ (partials/landing/: le sezioni della home), pages/
tests/               test PHPUnit
scripts/             script Node: build del JavaScript, pacchetto di deploy, prova del pacchetto
.github/workflows/   CI/CD
```

Ogni richiesta passa da `public/index.php`. Il router in `src/` sceglie il controller, il controller prepara i dati, il template produce l'HTML. `pages/home.php` compone soltanto le sezioni della landing, una per partial.

Le pagine non hanno nulla in linea: niente attributi `style`, niente `<style>`, niente script scritti nella pagina. Per questo la Content-Security-Policy (`src/Http/SecurityHeaders.php`) ammette solo stili e script del sito (più i fogli di Google Fonts) e nessun sito incorporato: i ritardi delle animazioni stanno nelle classi CSS, la mappa è un disegno SVG. Lo script si aggancia agli attributi `data-*`, mai alle classi di stile, e senza JavaScript la pagina è completa.

`public/index.php` trova l'applicazione così: se accanto a sé c'è la cartella `_app/` usa quella, altrimenti la radice della repo. In sviluppo l'applicazione è la repo stessa. In produzione è `public_html/_app/`, perché l'utente FTP non può scrivere sopra `public_html`.

Il pacchetto di deploy è la cartella `build/`, creata da `npm run assemble`:

```text
build/               copia di public/, con CSS e JavaScript costruiti
build/_app/          src, templates, config, composer.json, composer.lock
build/_app/vendor/   autoloader di Composer, senza pacchetti di sviluppo
build/_app/.htaccess nega ogni accesso dal browser
```

La CI carica `build/` dentro `public_html`.

Gli indirizzi di CSS, JavaScript e logo hanno `?v=` seguito dalla data di modifica del file: dopo un deploy il browser scarica la versione nuova.

## Contenuti da confermare

Orari, prezzi, cosa c'è in sala, le tre foto della sezione "Lo spazio", l'informativa privacy e i dati della società non sono ancora noti. Stanno nella sezione `content` di `config/site.php`, vuoti finché il cliente non li conferma.

- Un contenuto vuoto diventa il suo segnaposto tra parentesi quadre: "[Orari da confermare]", "[Prezzi da confermare]", "[Foto: la sala, bianco e nero]".
- `show_placeholders` è l'interruttore. Con `false` la pagina toglie tutto ciò che conterrebbe solo un segnaposto: la riga degli orari, le domande su orari e prezzi, la frase sulla sala, le foto che mancano, i dati legali. Così il sito può andare online senza segnaposto prima che arrivino i contenuti.
- Un contenuto compilato va in pagina com'è: le foto come immagini (file in `public/assets/img/`, con larghezza, altezza e testo alternativo), l'informativa come link.

"Mappa illustrativa" e "[Fasce illustrative]" non sono segnaposto: dicono il vero (la mappa è un disegno, le fasce sono esempi) e restano sempre.

## Prenotazioni: l'aggancio per la fase successiva

La sezione "Come funziona" mostra come si prenoterà: un gruppo di radio con le fasce di esempio, che funziona anche senza JavaScript; lo script aggiorna solo il riepilogo "La tua fascia". Prenotare dal sito non si può ancora: il bottone "Prenota il posto" è disattivato e la pagina rimanda a WhatsApp.

- `booking.capacity` in `config/site.php` è il numero di posti per fascia: da lì vengono tutti i numeri della pagina (la cifra grande, i quadrati dei posti, "3/5", "posto 4 di 5"). Il testo che scrive "cinque" in lettere resta com'è.
- In `src/Booking/`: `Slot` è una fascia (orario, posti, posti presi), `SlotCatalog` è l'interfaccia che dà le fasce alla pagina, `ExampleSlotCatalog` la implementa con le fasce di `booking.example_slots`.
- Con le prenotazioni vere, in `Application::create()` un catalogo che legge il database prenderà il posto di `ExampleSlotCatalog`: controller e template restano come sono.

## Requisiti

- PHP 8.3 o più recente (in produzione 8.3), con le estensioni openssl e mbstring.
- Composer 2.
- Node.js 26, la versione LTS indicata in `.nvmrc`, con npm.

## Preparare il progetto su Windows

1. PHP. Il PHP installato con winget non ha un `php.ini`: senza, mancano openssl e mbstring e Composer non funziona. Crea un `php.ini`, per esempio in `.tools/` (cartella ignorata da git):

   ```ini
   extension_dir = "C:\percorso\di\php\ext"
   extension=openssl
   extension=mbstring
   extension=curl
   extension=fileinfo
   extension=intl
   extension=zip
   memory_limit = 1G
   ```

   Poi indica a PHP la cartella del file con la variabile `PHPRC`, in ogni terminale:
   - PowerShell: `$env:PHPRC = "$PWD\.tools"`
   - Git Bash: `export PHPRC="$PWD/.tools"`

   Controllo: `php -m` deve elencare `openssl` e `mbstring`.

2. Composer. Va bene anche un `composer.phar` locale, per esempio in `.tools/`. Dove questa guida scrive `composer`, usa `php .tools/composer.phar`.

3. Dipendenze:

   ```sh
   composer install
   npm install
   ```

   `npm install` attiva anche gli hook git (husky).

4. Avvio:

   ```sh
   npm run build
   npm run serve
   ```

   Il sito è su http://localhost:8000. Mentre lavori tieni aperto `npm run dev` in un altro terminale: ricostruisce CSS e JavaScript a ogni modifica.

## Comandi

### npm

| Comando                | Cosa fa                                                                     |
| ---------------------- | --------------------------------------------------------------------------- |
| `npm run dev`          | Ricostruisce CSS e JavaScript a ogni modifica.                              |
| `npm run serve`        | Server PHP di sviluppo: `php -S localhost:8000 -t public public/index.php`. |
| `npm run build`        | CSS (Tailwind) e JavaScript (esbuild) minificati in `public/assets/`.       |
| `npm run build:css`    | Solo il CSS.                                                                |
| `npm run build:js`     | Solo il JavaScript.                                                         |
| `npm run lint`         | ESLint sul JavaScript.                                                      |
| `npm run format`       | Prettier sistema JavaScript, CSS, JSON, YAML e Markdown.                    |
| `npm run format:check` | Prettier controlla senza modificare.                                        |
| `npm test`             | Test JavaScript con `node --test` (file `*.test.js` in `resources/js/`).    |
| `npm run check`        | Lint, formattazione, test e build: i controlli front-end della CI.          |
| `npm run assemble`     | Build e pacchetto di deploy in `build/`.                                    |
| `npm run smoke`        | Prova `build/` con il server PHP integrato, come andrà in produzione.       |

`npm run assemble` usa Composer. Il comando si sceglie con la variabile `COMPOSER_BIN` (predefinito `composer`). Con il phar locale:

- PowerShell: `$env:COMPOSER_BIN = "php .tools/composer.phar"; npm run assemble`
- Git Bash: `COMPOSER_BIN="php .tools/composer.phar" npm run assemble`

### Composer

| Comando            | Cosa fa                                                  |
| ------------------ | -------------------------------------------------------- |
| `composer lint`    | PHP-CS-Fixer controlla lo stile e mostra le differenze.  |
| `composer format`  | PHP-CS-Fixer sistema lo stile.                           |
| `composer analyse` | PHPStan al livello massimo.                              |
| `composer test`    | PHPUnit.                                                 |
| `composer check`   | Stile, analisi statica e test: i controlli PHP della CI. |

Prima di un commit: `npm run check` e `composer check`.

## Rami

- `develop` è il ramo di lavoro. Ogni push su `develop` esegue i controlli e non pubblica nulla.
- `main` è il sito in produzione. `develop` si porta su `main` solo dopo il controllo del proprietario.
- Un push su `main`, se tutti i controlli passano, pubblica il sito e crea la release.
- Le pull request eseguono gli stessi controlli e non pubblicano mai.

## Messaggi di commit

I commit seguono [Conventional Commits](https://www.conventionalcommits.org/it/): semantic-release li legge per decidere la versione. Il tipo è in inglese, la descrizione in italiano, senza punto finale.

```text
tipo(ambito facoltativo): Descrizione
```

| Tipo                                                        | Quando                             | Versione |
| ----------------------------------------------------------- | ---------------------------------- | -------- |
| `feat`                                                      | una novità per chi visita il sito  | minor    |
| `fix`                                                       | la correzione di un errore         | patch    |
| `perf`                                                      | un miglioramento delle prestazioni | patch    |
| `refactor`, `style`, `test`, `docs`, `build`, `ci`, `chore` | lavoro interno                     | nessuna  |

Una modifica incompatibile si segna con `!` dopo il tipo, oppure con `BREAKING CHANGE:` nel corpo del messaggio: la versione sale di major.

Esempi:

```text
feat: Aggiungi la pagina delle prenotazioni
fix(countdown): Riallinea i secondi al ritorno sulla scheda
refactor(php): Separa il router dai controller
ci: Esegui i test anche con PHP 8.4
chore(deps): update npm dev tooling
feat!: Sposta la pagina dei link su /link/
```

L'hook `commit-msg` controlla il messaggio con commitlint. L'hook `pre-commit` passa ESLint e Prettier sui file front-end in stage (lint-staged). La CI ricontrolla i messaggi dei commit di ogni push e di ogni pull request.

Se una pull request si unisce con "Squash and merge", il suo titolo diventa il messaggio del commit: anche il titolo deve seguire la convenzione.

## Dipendenze: Renovate

Renovate (app GitHub) tiene aggiornate le dipendenze npm, Composer e le GitHub Actions:

- apre le pull request verso `develop`, il lunedì prima delle 6, ora di Roma;
- raggruppa gli aggiornamenti in tre PR: strumenti di sviluppo npm, strumenti di sviluppo Composer, GitHub Actions; gli aggiornamenti major hanno una PR a parte;
- una volta alla settimana aggiorna i lock file (`package-lock.json`, `composer.lock`);
- fissa ogni GitHub Action al suo digest (SHA), con la versione in commento;
- propone un pacchetto npm solo quando è pubblicato da almeno tre giorni;
- scrive commit `chore(deps): ...`, che da soli non creano mai una release;
- non unisce nulla da solo: ogni PR la rivede e la unisce il proprietario.

La issue "Dependency Dashboard" elenca gli aggiornamenti in attesa. Renovate legge `renovate.json` dal ramo principale (`main`): le regole valgono da quando il file è su `main`.

## Versioni: semantic-release

A ogni push su `main`, dopo i controlli, semantic-release:

- legge i commit dall'ultimo tag;
- decide la versione: `fix` e `perf` patch, `feat` minor, `!` o `BREAKING CHANGE:` major; con soli commit interni non rilascia;
- crea il tag `vX.Y.Z` e la GitHub Release, con le note in italiano (Novità, Correzioni, Prestazioni).

Non fa commit nella repo: niente changelog e niente versione in `package.json`. La versione vive nei tag e nelle GitHub Release, così ogni commit della repo resta del proprietario. La prima release sarà la 1.0.0, al primo `feat` o `fix` che arriva su `main`. Se il rilascio fallisce, semantic-release apre una issue con il motivo.

## CI/CD

Un solo workflow: `.github/workflows/ci.yml`.

| Job                 | Quando                                                     | Cosa fa                                                                                                        |
| ------------------- | ---------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| PHP 8.3 e 8.4       | ogni push su `develop` e `main`, ogni pull request         | `composer validate --strict`, dipendenze, `composer lint`, `composer analyse`, `composer test`                 |
| Front-end           | come sopra                                                 | `npm ci`, lint, formattazione, test, build                                                                     |
| Messaggi di commit  | come sopra                                                 | commitlint sui commit del push o della pull request                                                            |
| Pacchetto di deploy | come sopra                                                 | `npm run assemble` con PHP 8.3, `npm run smoke`, artefatto `site-build` con il contenuto di `build/`           |
| Deploy su Hostinger | push su `main` o avvio manuale su `main`, dopo i job sopra | sync FTPS dell'artefatto in `public_html`, poi controlla che `/`, `/links/` e `/calendario.php` rispondano 200 |
| Release             | push su `main`, dopo i job sopra                           | semantic-release                                                                                               |

Il deploy carica lo stesso pacchetto già provato dal job "Pacchetto di deploy". È incrementale: un file di stato sul server (`.ftp-deploy-sync-state.json`) ricorda cosa è stato caricato. Si caricano solo i file nuovi o cambiati e si cancellano solo quelli caricati dal deploy e poi tolti dal pacchetto. Il resto di `public_html` non si tocca e la cartella non viene mai svuotata.

Avvio manuale: Actions, workflow "CI/CD", "Run workflow" sul ramo `main`. L'opzione "Prova a vuoto" mostra cosa verrebbe caricato senza modificare il server.

### Secret e ambiente su GitHub

In Settings, "Secrets and variables", Actions:

| Secret         | Valore                                              |
| -------------- | --------------------------------------------------- |
| `FTP_SERVER`   | host FTP di Hostinger                               |
| `FTP_USERNAME` | utente FTP, che entra direttamente in `public_html` |
| `FTP_PASSWORD` | password dell'utente FTP                            |
| `FTP_PORT`     | porta FTP, di solito 21                             |

Il job di deploy usa l'ambiente `production` (Settings, Environments), con l'indirizzo https://fourstreet.studio. Si possono aggiungere regole all'ambiente, per esempio l'approvazione del proprietario prima di ogni deploy.

semantic-release usa il `GITHUB_TOKEN` del workflow: non serve un altro secret.

Le credenziali non vanno mai nella repo. `.env.example` elenca le variabili FTP; la copia locale `.env` è ignorata da git.
