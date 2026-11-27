# 4Street · sito

Sito di 4Street, palestra a San Marino: [fourstreet.studio](https://fourstreet.studio).

Oggi il sito ha tre indirizzi pubblici, l'area clienti, la prenotazione delle fasce e il pannello del titolare:

- `/`: la landing. La regola dei cinque posti per fascia oraria, come si prenota (con le fasce vere del primo giorno con un posto libero, quando le prenotazioni sono attive), lo spazio, dove siamo con la mappa illustrata, le domande frequenti e l'invito a venire. Su mobile e tablet una barra in fondo allo schermo tiene a portata "Vieni a trovarci" e WhatsApp. Fino all'inaugurazione la pagina ha il conto alla rovescia; allo zero passa allo stato "aperti" senza ricaricare, e dal giorno dell'apertura il server la scrive già così. Per provare gli stati: `/?anteprima=mappa` (la pagina come se fossimo aperti) e `/?anteprima=zero` (il conto arriva a zero dopo 12 secondi).
- `/links/`: la pagina dei link.
- `/calendario.php`: l'evento dell'inaugurazione da aggiungere al calendario (file `.ics`).
- `/account/…`: l'area clienti, cioè registrazione con email confermata, accesso, password dimenticata e pagina dell'account con le prenotazioni (vedi [Area clienti](#area-clienti)). La landing la collega con un link fisso ("Area clienti"), uguale per tutti.
- `/prenota/…`: la scelta del giorno e della fascia, il riepilogo e la conferma; la disdetta sta nell'account (vedi [Prenotazioni](#prenotazioni)).
- `/gestione/…`: il pannello del titolare, solo per lui: chi è prenotato e in che fascia (un giorno, o il mese a colpo d'occhio), una prenotazione nuova o una disdetta, l'orario (la settimana tipo, una settimana precisa, le chiusure) e le regole delle prenotazioni (vedi [Pannello del titolare](#pannello-del-titolare)). Nessuna pagina pubblica lo collega.

Stack: HTML, Tailwind CSS, JavaScript e PHP, senza framework. Produzione: hosting condiviso Hostinger, PHP 8.3, Apache/LiteSpeed con `.htaccess`, deploy via FTPS in `public_html`.

## Architettura in breve

```text
config/site.php      contenuti e impostazioni del sito (nella repo)
config/local.php     database, posta, chiave segreta del server: ignorato da git, si crea da local.example.php
public/              radice web: solo ciò che il browser può raggiungere
  index.php          unico punto d'ingresso PHP (front controller)
  .htaccess          porta http su https, rimanda gli indirizzi a index.php, blocca file nascosti e _app/, cache degli asset
  assets/            immagini; css/app.css e js/app.js li genera la build
bin/console          console dell'applicazione: migrazioni e manutenzione
database/migrations/ migrazioni dello schema, una per file, in ordine di nome
resources/css/       sorgenti Tailwind: app.css importa token, base, componenti (uno per file) e movimento
resources/js/        sorgenti JavaScript: app.js collega le funzioni di features/, la logica pura sta in lib/
src/                 classi PHP, namespace FourStreet\
templates/           viste PHP: layouts/, partials/ (partials/landing/: le sezioni della home), pages/, emails/
tests/               test PHPUnit
scripts/             script Node: build del JavaScript, pacchetto di deploy, prova del pacchetto
var/                 solo in sviluppo, ignorata da git: database SQLite e posta in file .eml
.github/workflows/   CI/CD
```

Ogni richiesta passa da `public/index.php`. Il router in `src/` sceglie il controller, il controller prepara i dati, il template produce l'HTML. `pages/home.php` compone soltanto le sezioni della landing, una per partial. `public/index.php` e `bin/console` sono gli unici punti in cui si leggono superglobali, argomenti e ambiente del processo; da lì in giù tutto arriva come parametro, compresi l'ora (`Clock`) e la configurazione.

Le pagine non hanno nulla in linea: niente attributi `style`, niente `<style>`, niente script scritti nella pagina. Per questo la Content-Security-Policy (`src/Http/SecurityHeaders.php`) ammette solo stili e script del sito (più i fogli di Google Fonts) e nessun sito incorporato: i ritardi delle animazioni stanno nelle classi CSS, la mappa è un disegno SVG. Lo script si aggancia agli attributi `data-*`, mai alle classi di stile, e senza JavaScript la pagina è completa.

`public/index.php` trova l'applicazione così: se accanto a sé c'è la cartella `_app/` usa quella, altrimenti la radice della repo. In sviluppo l'applicazione è la repo stessa. In produzione è `public_html/_app/`, perché l'utente FTP non può scrivere sopra `public_html`.

Il pacchetto di deploy è la cartella `build/`, creata da `npm run assemble`:

```text
build/               copia di public/, con CSS e JavaScript costruiti
build/_app/          src, templates, config, database, bin/console, composer.json, composer.lock
build/_app/vendor/   dipendenze di Composer, senza pacchetti di sviluppo
build/_app/.htaccess nega ogni accesso dal browser
```

La CI carica `build/` dentro `public_html`. Il pacchetto non contiene mai `config/local.php` né `var/`: `npm run assemble` si ferma se ci finissero, e anche il passo FTP li esclude.

Gli indirizzi di CSS, JavaScript e logo hanno `?v=` seguito dalla data di modifica del file: dopo un deploy il browser scarica la versione nuova.

## Contenuti da confermare

Orari, prezzi, cosa c'è in sala, le tre foto della sezione "Lo spazio", l'informativa privacy e i dati della società non sono ancora noti. Stanno nella sezione `content` di `config/site.php`, vuoti finché il cliente non li conferma.

- Un contenuto vuoto diventa il suo segnaposto tra parentesi quadre: "[Orari da confermare]", "[Prezzi da confermare]", "[Foto: la sala, bianco e nero]".
- `show_placeholders` è l'interruttore. Con `false` la pagina toglie tutto ciò che conterrebbe solo un segnaposto: la riga degli orari, le domande su orari e prezzi, la frase sulla sala, le foto che mancano, i dati legali. Così il sito può andare online senza segnaposto prima che arrivino i contenuti.
- Un contenuto compilato va in pagina com'è: le foto come immagini (file in `public/assets/img/`, con larghezza, altezza e testo alternativo), l'informativa come link.

"Mappa illustrativa" e "[Fasce illustrative]" non sono segnaposto: dicono il vero (la mappa è un disegno, le fasce sono esempi) e restano finché le prenotazioni dal sito non sono attive.

## Prenotazioni

Il cliente sceglie una fascia, accede (o si registra e conferma l'email) e conferma: gli arriva un'email con i dettagli e l'evento da aggiungere al calendario. Massimo cinque persone per fascia. La prenotazione si vede e si disdice dall'account; anche la disdetta manda un'email. Le pagine funzionano senza JavaScript e su schermi da 320px; lo script aggiunge solo il riepilogo della fascia scelta sulla landing e il blocco del doppio invio.

### Le regole (provvisorie, da confermare con il titolare)

Stanno nella sezione `booking` di `config/site.php`, scritte perché le possa cambiare anche chi non programma. Un valore sbagliato ferma il sito con un messaggio che nomina la chiave ("`booking.schedule.sabato` ha due fasce che si sovrappongono, alle 18:00 e alle 18:30").

Il file è il valore iniziale: orario, chiusure e alcune regole il titolare le cambia dal suo pannello, e da allora valgono quelle salvate nel database (vedi [L'orario nel database](#lorario-nel-database)). Restano soltanto nel file `capacity`, `slot_minutes`, gli esempi della landing e l'interruttore generale `online`: con `false` le prenotazioni dal sito sono spente qualunque cosa salvi il titolare, e nessuna pagina pubblica legge il database. Con `true` il titolare le spegne e le riaccende dal pannello.

| Chiave                          | Oggi                                                        | Cosa                                                                                                                             | Dal pannello                       |
| ------------------------------- | ----------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------- |
| `online`                        | `true`                                                      | l'interruttore delle prenotazioni dal sito (servono anche database e posta in `config/local.php`)                                | le spegne e riaccende, se è `true` |
| `capacity`                      | `5`                                                         | posti per fascia: da qui tutti i numeri delle pagine (la cifra grande, i quadrati, "3/5", "posto 4 di 5")                        | no                                 |
| `schedule`                      | da lunedì a sabato 07:00, 09:30, 12:30, 17:30, 18:30, 20:00 | per ogni giorno della settimana gli orari d'inizio; `[]` è un giorno chiuso (oggi la domenica)                                   | sì: settimana tipo e giorni        |
| `slot_minutes`                  | `60`                                                        | quanto dura una fascia: serve all'evento del calendario; le fasce di un giorno non si sovrappongono e finiscono entro mezzanotte | no                                 |
| `days_ahead`                    | `14`                                                        | si prenota da oggi fino a quattordici giorni dopo (0: solo oggi)                                                                 | sì, da 0 a 62                      |
| `closed_dates`                  | `[]`                                                        | giorni di chiusura, `AAAA-MM-GG`                                                                                                 | sì: chiusure                       |
| `booking_closes_minutes_before` | `0`                                                         | la prenotazione chiude questi minuti prima dell'inizio (0: all'inizio)                                                           | sì, fino a una settimana           |
| `cancel_closes_minutes_before`  | `0`                                                         | la disdetta chiude questi minuti prima dell'inizio (0: all'inizio)                                                               | sì, fino a una settimana           |
| `max_upcoming`                  | `6`                                                         | prenotazioni future attive per cliente                                                                                           | sì, da 1 a 62                      |
| `example_slots`, `default_slot` | come prima                                                  | le fasce di esempio della landing, finché le prenotazioni non sono attive                                                        | no                                 |

Regole fisse: una sola prenotazione attiva per cliente al giorno; nessuna fascia prima dell'inaugurazione (`opening`): il primo giorno prenotabile è quello dell'apertura, dalle fasce che iniziano dall'ora d'apertura in poi (oggi il 6 dicembre è domenica, chiusa: si comincia lunedì 7).

Giorni e orari delle fasce sono ora di San Marino (`timezone`). Una fascia è un giorno e un orario d'inizio; i confronti con "adesso" si fanno sugli istanti, e i giorni si contano sul calendario, mai sommando ore: il cambio dell'ora non sposta nulla. Il giorno del passaggio all'ora legale l'ora che manca (02:00-03:00) non ha fasce; nel giorno del ritorno all'ora solare un'ora doppia vale una volta sola. La durata di una fascia è tempo reale.

### Il percorso

| Indirizzo                                  | Cosa fa                                                                                                                                                          |
| ------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `/` (sezione "Come funziona")              | le fasce del primo giorno con un posto libero, con i posti veri; "Prenota il posto" è un modulo GET verso il riepilogo della fascia scelta. Resta WhatsApp       |
| `/prenota/?giorno=AAAA-MM-GG`              | pubblica: i giorni prenotabili (link, senza JavaScript) e le fasce del giorno scelto; una fascia libera è un link al riepilogo. Riconosce chi ha fatto l'accesso |
| `/prenota/conferma/?giorno=…&fascia=HH:MM` | solo dopo l'accesso: il riepilogo (giorno, orario, posto, indirizzo) e "Conferma la prenotazione"; con POST prenota e torna all'account (303)                    |
| `/account/`                                | le prossime prenotazioni (giorno, orario, posto) con "Disdici"; quella in corso resta fino alla fine della fascia                                                |
| `/account/prenotazioni/{id}/disdici/`      | GET chiede conferma, POST disdice e torna all'account (303)                                                                                                      |

- Chi non ha fatto l'accesso passa dalla pagina di accesso (o dalla registrazione e dal link dell'email) e torna da solo al riepilogo: la destinazione viaggia nel parametro `ritorno`.
- Gli esiti hanno pagine chiare e i codici giusti: fascia piena (anche nel frattempo), già prenotato quel giorno, troppe prenotazioni future, fascia chiusa (iniziata o passata): 409; fascia o giorno inesistenti e parametri manomessi: 404; troppi tentativi: 429 con `Retry-After`; modulo senza token CSRF: 403. Lo stesso modulo inviato due volte porta all'account con la prenotazione che c'è già.
- Una disdetta vale solo per una prenotazione propria, attiva e non ancora iniziata (secondo `cancel_closes_minutes_before`): tutto il resto è 404, senza dire di chi sia la prenotazione.
- Limiti dei tentativi, sul database come per l'area clienti: prenotazioni e disdette contano insieme, 12 ogni 15 minuti per account e 60 per indirizzo IP (largo: chi è in palestra esce su internet con lo stesso indirizzo).
- Le email partono subito, non dopo la risposta: la prenotazione vale comunque, e se l'email non parte l'errore va nel log e l'avviso sull'account lo dice. La conferma ha in allegato `prenotazione-4street.ics` (con l'UID `prenotazione-{id}@` più l'host del sito, stabile per prenotazione); la disdetta non ha allegati.

### Posti e gare

La capienza la garantisce il database, non un conteggio. Ogni prenotazione attiva ha un posto da 1 a `capacity`, con un vincolo `UNIQUE (giorno, orario, posto)`: chi prenota prende il primo posto libero; se due richieste scelgono insieme lo stesso, il database ne fa passare una e l'altra riprova con il successivo, in una transazione nuova; finiti i posti, "fascia completa". Una prenotazione disdetta libera il posto (`seat` e `active` a NULL: i NULL non si scontrano nei vincoli UNIQUE, né su SQLite né su MySQL) e resta come storico. Allo stesso modo `UNIQUE (cliente, giorno, active)` tiene una sola prenotazione attiva per cliente al giorno. Il controllo "al massimo `max_upcoming` future" vale anche con due richieste insieme: la prenotazione blocca prima la riga del cliente in `users`, così le sue prenotazioni passano una alla volta. Una persona senza account, inserita dal titolare, blocca allo stesso modo la riga del titolare: lo stesso modulo inviato due volte trova la prima prenotazione, e in ogni caso `UNIQUE (fascia, guest_name, active)` non ne lascia scrivere una seconda.

Abbassare `capacity` non cancella nessuna prenotazione: una fascia con più prenotazioni dei posti nuovi resta piena finché non se ne liberano.

### La landing e il database

La landing non dipende mai dal database: lo legge poche volte (le regole e l'orario salvati dal titolare, poi i posti dei giorni prenotabili), senza sessione né riconoscimento del visitatore, dentro un guscio che a qualunque errore lo registra nel log e mostra gli orari senza numeri con il link a `/prenota/`; se anche quelli non si potessero calcolare, le fasce di esempio. Gli orari senza numeri sono quelli di `config/site.php`, la riserva: se il titolare li ha cambiati possono non essere quelli veri, ma si vedono solo finché il database non risponde (e allora non si prenota comunque). Un database spento non dà mai 500 sulla home, su `/links/` o su `/calendario.php` (`tests/Feature/Account/DatabaseIndependenceTest.php`); link e calendario non lo aprono nemmeno. Con le prenotazioni attive la home risponde `Cache-Control: no-cache` (il browser la richiede ogni volta, i posti sono sempre quelli veri); senza, resta com'era.

Una connessione a MySQL che non risponde rinuncia dopo 5 secondi, così un database bloccato non tiene ferma la pagina.

### Attive, spente, senza server

- `booking.online` a `true` e `config/local.php` sul server: tutto come sopra.
- Spente dal titolare nelle impostazioni del pannello (con `booking.online` a `true`): `/prenota/` e il riepilogo rispondono "Prenotazioni presto" (503) prima ancora di chiedere l'accesso, la landing torna alle fasce di esempio, l'account non propone di prenotare e le email non hanno più il link per prenotare. La pagina dei link non legge mai il database: resta com'è, e il suo link porta a "Prenotazioni presto". Chi ha già una prenotazione la vede e la disdice.
- `booking.online` a `false`: la landing mostra le fasce di esempio con il bottone "Presto", la pagina dei link dice "Presto", `/prenota/` e il riepilogo rispondono "Prenotazioni presto" (503, una pagina cortese), senza leggere il database. Chi ha già una prenotazione la vede nell'account e la può disdire.
- Senza `config/local.php` (il pacchetto appena caricato): come con `false`, e la disdetta risponde come il resto dell'area clienti ("non ancora attiva").

### L'orario nel database

Dal pannello il titolare cambia la settimana tipo, l'orario di un giorno preciso, le chiusure e cinque regole. `BookingCalendar` legge orario e regole da una fonte sola (`Booking\Schedule\ScheduleSource`): `FileSchedule` (il file, senza database: senza Backend e nei test puri) oppure `PdoSchedule` (il database, con il file come valori iniziali). Application ne crea una per richiesta e la dà a tutti (landing, `/prenota/`, account, email, pannello): ogni pagina vede lo stesso orario.

- **Finché il titolare non salva nulla vale il file.** La prima modifica dell'orario copia nel database la settimana tipo e i `closed_dates` del file, nella stessa transazione, e scrive la riga `schedule` = `database` fra le impostazioni: da lì l'orario del file non conta più (resta la riserva della landing). Le regole valgono chiave per chiave: una regola mai salvata vale com'è nel file.
- **Poche letture, una volta per richiesta.** Le impostazioni in una query (e prima che l'orario passi nel database basta questa), la settimana tipo in una, le eccezioni un mese alla volta: mai una query per giorno. Ciò che si è letto resta in memoria fino alla fine della richiesta; ogni scrittura lo dimentica.
- **La settimana tipo cambia da domani.** Ogni orario della settimana tipo è una riga con il periodo in cui vale (`valid_from` compreso, `valid_until` escluso): una modifica chiude le righe di prima e ne apre di nuove da domani, così oggi e i giorni passati restano com'erano (anche nel calendario e nel giorno del pannello).
- **Un giorno con un orario suo** (un'eccezione: chiuso, oppure con le sue fasce) vale al posto della settimana tipo. Un giorno con lo stesso orario della settimana tipo torna a seguirla: nessuna eccezione che non serve.

| Tabella                 | Cosa                                                                                                                                                                                                                     |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `booking_settings`      | le regole salvate dal titolare (`online`, `days_ahead`, `booking_closes_minutes_before`, `cancel_closes_minutes_before`, `max_upcoming`) e la riga `schedule`; chi e quando (`updated_by`, `updated_at`)                 |
| `schedule_weekly_slots` | la settimana tipo: giorno della settimana (1 lunedì, 7 domenica), orario, `valid_from` e `valid_until` (NULL: da sempre, per sempre)                                                                                     |
| `schedule_days`         | le eccezioni, una per giorno: `closed` (0 o 1), `note` (a uso interno del titolare, da 1 a 100 caratteri, mai mostrata ai clienti né nelle email), `updated_by` (cancellato l'account resta senza autore) e `updated_at` |
| `schedule_day_slots`    | gli orari di un giorno con un orario suo (si cancellano con il giorno)                                                                                                                                                   |

Se cambi `slot_minutes` nel file, controlla nel pannello che gli orari salvati non si sovrappongano: il file controlla solo i suoi.

### La tabella `bookings`

`id`, `user_id` (il cliente registrato; cancellato l'account, se ne vanno anche le sue prenotazioni), `guest_name` (il nome di una persona senza account, scritto dal titolare: da 2 a 100 caratteri), `slot_date` e `slot_time` (la fascia, ora di San Marino), `starts_at` (lo stesso inizio in UTC, per i confronti con adesso), `seat` (il posto, NULL se disdetta), `active` (1, NULL se disdetta), `created_at` e `created_by` (chi l'ha inserita: `customer` oppure `admin`), `cancelled_at` e `cancelled_by` (`customer` oppure `admin`). Vincoli: `UNIQUE (slot_date, slot_time, seat)`, `UNIQUE (user_id, slot_date, active)`, `UNIQUE (slot_date, slot_time, guest_name, active)` (la stessa persona senza account una volta per fascia: il nome si confronta esatto, già normalizzato negli spazi) e i `CHECK` che tengono coerenti posto, stato e disdetta, e la persona: esattamente uno fra `user_id` e `guest_name`, e una persona senza account la inserisce solo `admin`.

La migrazione `0005_create_bookings` è stata corretta sul posto nello Sprint 3 (persona senza account e `created_by`), perché non era mai stata applicata a un database vero: su `main` non era arrivata e non c'era stato nessun deploy. Chi ha un database di sviluppo dello Sprint 2 lo ricrea: cancella `var/4street.sqlite` e lancia `npm run migrate`. Da qui in avanti ogni cambio di schema è una migrazione nuova.

### Privacy

Una prenotazione dice chi si allena in palestra, quando: è un dato personale. Nella tabella ci sono solo il riferimento all'account (o, per chi non ce l'ha, il nome scritto dal titolare), la fascia, il posto, chi l'ha inserita e gli istanti di prenotazione e disdetta; niente indirizzi IP (i limiti dei tentativi ne tengono solo l'impronta con chiave, e la cancellano alla scadenza). Le prenotazioni restano come storico anche dopo la fascia o la disdetta, finché non si cancella l'account: quanto tenerle (per esempio un anno), comprese quelle con il solo nome, e come dirlo nell'informativa va deciso con il titolare. Le vede il cliente, nel suo account, e il titolare nel suo pannello, con il nome e l'email: nomi ed email non compaiono mai in pagine pubbliche né negli indirizzi (dopo un redirect il pannello porta solo il numero della prenotazione), e gli errori che il sito scrive nel log non li ripetono (il testo di un server di posta che rifiuta un'email, riportato nel log, può però citare il destinatario).

### Da confermare con il titolare

- Gli orari delle fasce, la domenica chiusa, i giorni di chiusura (festività): ora li sistema lui dal pannello.
- La durata delle fasce (60 minuti); i giorni prenotabili in anticipo (14) e il massimo di prenotazioni future (6) li cambia dal pannello.
- Fino a quando si prenota e si disdice (oggi fino all'inizio della fascia, cambiabile dal pannello): un preavviso di disdetta libera i posti per gli altri.
- I posti per fascia (5) e cosa fare di chi non si presenta (oggi nulla).
- Per quanto tenere lo storico delle prenotazioni e l'informativa privacy.

### Moduli

```text
src/Booking/            prenotazioni: regole, calendario, prenotazione e disdetta, email
  BookingRules          la sezione booking di config/site.php, validata (WeeklySchedule, Weekday, LocalDate, SlotTime)
  BookingCalendar       quali fasce esistono e fino a quando si prenotano e si disdicono, dall'orario di una ScheduleSource
  Schedule/             l'orario e le regole del titolare: ScheduleSource (FileSchedule, PdoSchedule), ScheduleEditor
                        (le modifiche, la conferma, le disdette), RulesEditor (le impostazioni), DayTimes (le regole di un giorno)
  SlotBooking           check() e book(): la prenotazione di un cliente, con il limite dei tentativi e l'email
  BookingCancellation   cancellable() e cancel(): la disdetta del cliente
  SlotBoard, BookingAgenda  le fasce con i posti (pagina /prenota/, landing) e le prenotazioni di un cliente
  BookingRepository     l'interfaccia; PdoBookingRepository la implementa (SQLite e MySQL/MariaDB)
  Booking, RosterEntry  una prenotazione (di un cliente o di una persona senza account, CreatedBy) e la stessa con la persona
  Staff/                le regole del titolare, senza HTTP: StaffBooking, StaffCancellation, DayRoster (il giorno con chi c'è)
  Http/                 controller, avvisi dell'account, la sezione "Le tue prenotazioni" (DashboardSection)
  Landing/              le fasce della landing: vere, solo orari, esempi, e il guscio che le protegge
  BookingModule.php     collega tutto; dà ad Application rotte, sezione dell'account e fasce della landing
```

L'area clienti non conosce le prenotazioni: mette in pagina le `DashboardSection` che riceve (`AccountModule::create()`).

## Pannello del titolare

Il titolare della palestra vede chi si è prenotato e in che fascia, giorno per giorno o il mese intero, e da lì disdice o crea una prenotazione; cambia l'orario (la settimana tipo, i giorni di una settimana, le chiusure) e le regole delle prenotazioni. Posti per fascia e durata della fascia restano in `config/site.php`, i ruoli nella console. Le pagine sono pensate per il telefono in palestra (da 320px, orario grande, posti a colpo d'occhio, nomi leggibili, bottoni alti almeno 44px), usano il layout dell'area clienti e funzionano senza JavaScript: lo script blocca solo il doppio invio, porta il focus sul riepilogo degli errori e, sul telefono, porta in vista la voce corrente della navigazione.

In cima a ogni pagina la navigazione del pannello: Oggi, Calendario, Orari, Chiusure, Impostazioni. Sul telefono è una riga che scorre di lato.

| Indirizzo                                             | Cosa fa                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| ----------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `/gestione/`, `/gestione/?giorno=AAAA-MM-GG`          | il giorno: una scheda per fascia con l'orario, i posti presi su 5 e chi c'è (posto, nome, email se ha l'account, "inserita dalla palestra"); "Disdici" su ogni prenotazione e "Aggiungi" sulle fasce con un posto, finché la fascia non è iniziata. Il giorno prima e quello dopo (senza limiti, anche nel passato, da guardare soltanto), "Oggi" e la scelta rapida dei giorni prenotabili come in `/prenota/`, dentro un `details` chiuso. Se l'orario del giorno è suo lo dice (chiuso con la nota, orario cambiato), e porta a "Cambia l'orario di questo giorno" |
| `/gestione/prenotazioni/nuova/?giorno=…&fascia=HH:MM` | GET il modulo, per chi è il posto: l'email di un cliente registrato oppure il nome di chi non ha l'account, uno solo dei due; POST prenota e torna al giorno con l'avviso (303)                                                                                                                                                                                                                                                                                                                                                                                       |
| `/gestione/prenotazioni/{id}/disdici/`                | GET chiede conferma (chi, quando, se gli arriva un'email); POST disdice e torna al giorno con l'avviso (303)                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `/gestione/calendario/?mese=AAAA-MM`                  | il mese, per settimane: ogni giorno chiuso o aperto (con la nota), quante fasce, quante persone, un segno per le fasce piene e uno per le prenotazioni fuori dall'orario; ogni giorno porta al suo giorno nel pannello. Mese prima e dopo, "Questo mese". Le prenotazioni del mese si leggono in una query sola, già contate per fascia                                                                                                                                                                                                                               |
| `/gestione/orari/`                                    | la settimana tipo: gli orari di ogni giorno della settimana, da togliere uno per uno o da aggiungere (POST, 303). Vale da domani, per i giorni senza un orario loro                                                                                                                                                                                                                                                                                                                                                                                                   |
| `/gestione/orari/settimana/?da=AAAA-MM-GG`            | una settimana, da lunedì a domenica: per ogni giorno le fasce con le persone prenotate; si aggiunge o toglie un orario, si chiude il giorno (con una nota), si riapre, si riporta alla settimana tipo; "Copia questa settimana nella prossima"                                                                                                                                                                                                                                                                                                                        |
| `/gestione/chiusure/`                                 | le chiusure in programma (i giorni di fila con la stessa nota insieme), con "Riapri"; il modulo per chiudere un giorno o un intervallo (al più 62 giorni), con una nota facoltativa                                                                                                                                                                                                                                                                                                                                                                                   |
| `/gestione/impostazioni/`                             | prenotazioni dal sito accese o spente, giorni prenotabili in anticipo, fino a quando si prenota e si disdice, prenotazioni future per cliente; ogni campo dice l'effetto con i valori veri (POST, 303)                                                                                                                                                                                                                                                                                                                                                                |

Senza giorno il pannello apre oggi, oppure, se oggi non ha fasce (prima dell'inaugurazione, la domenica), il primo giorno prenotabile che ne ha. La scelta rapida sta chiusa perché sul telefono le fasce del giorno restino nel primo schermo. Il pannello arriva a dieci anni prima e dopo quello di oggi (dal 1° gennaio al 31 dicembre): oltre, la pagina non c'è (404), e il titolare non prenota.

Chi entra:

- Solo il ruolo `admin` (`AccountGuards::admin()`). Un ospite passa dalla pagina di accesso e torna dove voleva andare; chi è entrato senza essere titolare riceve la stessa pagina 404 di un indirizzo che non esiste (stesso codice e stesso contenuto; le intestazioni di una pagina privata, come `no-store`, restano). Che il pannello esista non è un segreto: la repo è pubblica, e un ospite viene mandato alla pagina di accesso; non escono dati. Il ruolo si rilegge a ogni pagina: tolto, vale dalla pagina successiva.
- Dopo l'accesso, senza `ritorno`, il titolare arriva su `/gestione/`; nella pagina del suo account c'è il link "Gestione prenotazioni". Nessuna pagina pubblica né di un cliente collega il pannello.
- Pagine `no-store` e `noindex`, moduli con il token CSRF, come l'area clienti. Senza `config/local.php` rispondono "Arriva presto" (503), come il resto dell'area clienti. Il pannello funziona anche con `booking.online` a `false`.

Le regole del titolare (`src/Booking/Staff/`, senza HTTP):

- I posti valgono anche per lui: la prenotazione passa dallo stesso `place()` del cliente (o dal suo gemello `placeGuest()`), con lo stesso vincolo del database.
- Prenota qualsiasi fascia che esiste nel calendario e non è ancora iniziata, anche oltre `days_ahead` e senza `booking_closes_minutes_before`; mai prima dell'inaugurazione né nei giorni chiusi, dove le fasce non esistono.
- Per un cliente registrato resta una prenotazione attiva al giorno; `max_upcoming` non vale.
- Disdice qualsiasi prenotazione attiva non ancora iniziata, senza `cancel_closes_minutes_before`.
- Un cliente registrato si cerca per email esatta, solo se l'ha confermata. Gli arriva subito la stessa conferma con il file `.ics` (e la prenotazione è nel suo account); se la palestra la disdice, un'email con un testo suo ("la palestra ha disdetto…", senza "se non sei stato tu", con il link per un'altra fascia e il WhatsApp). Una persona senza account non riceve nulla: la avvisa il titolare. Un'email che non parte non annulla nulla, e l'avviso del pannello lo dice.
- Lo stesso modulo inviato due volte non scrive due prenotazioni. Per un cliente c'è "una al giorno"; per una persona senza account lo stesso nome nella stessa fascia (spazi a parte, maiuscole comprese) è la stessa persona, "già inserita", garantito anche dal database. Due persone diverse con lo stesso nome nella stessa fascia si distinguono con l'iniziale del cognome.
- Limite dei tentativi: 60 prenotazioni e disdette ogni 15 minuti per titolare, con un contatore suo. Largo: ferma solo un uso fuori misura. Si conta prima di ogni controllo, compresa la ricerca dell'email: oltre il limite un'email nota e una sconosciuta hanno la stessa risposta, così il pannello non serve a scoprire chi ha un account. Non contano solo i moduli scritti male (campi vuoti, un'email senza chiocciola), che non leggono il database.
- Se il titolare inserisce un cliente quando per lui la disdetta è già chiusa (`cancel_closes_minutes_before`), la conferma non gli promette una disdetta: gli dice di avvisare la palestra.
- Una prenotazione in una fascia che l'orario non ha più (orario cambiato dopo) resta visibile nella sua fascia, "Fuori dall'orario": si disdice, ma lì non si aggiunge nessuno. Il calendario del mese segna il giorno.

Gli esiti: email sconosciuta o non confermata e modulo sbagliato 422, cliente già prenotato quel giorno 409 (si torna nel modulo, con l'errore sul campo); fascia piena o iniziata 409, inesistente o parametri manomessi 404 (una pagina con il bottone per tornare al giorno); troppi tentativi 429 con `Retry-After`; modulo senza token CSRF 403. Dopo un redirect l'avviso porta nell'indirizzo solo il numero della prenotazione: nome e fascia li legge la pagina dal database.

Le regole dell'orario (`src/Booking/Schedule/ScheduleEditor`, senza HTTP):

- Il passato non si cambia: né i giorni passati né, oggi, le fasce già cominciate (oggi si chiude solo se nessuna fascia è cominciata; riaprire oggi riporta solo le fasce che devono ancora cominciare). La settimana tipo cambia da domani.
- Mai fasce prima dell'inaugurazione, mai due fasce dello stesso giorno che si sovrappongono (data la durata), mai oltre la mezzanotte, al più 24 al giorno, mai un'ora che quel giorno non esiste (il cambio dell'ora). Gli orari si scrivono a 24 ore ("18:30"; vanno bene anche "18.30" e "9:30").
- Togliere una fascia (o chiudere un giorno) dove ci sono prenotazioni attive non ancora iniziate non le cancella di nascosto: prima una pagina di conferma con quante e quali persone (nome; email se registrate), poi, nella stessa transazione, la modifica e la disdetta di quelle prenotazioni (`cancelled_by` = `admin`). Ai clienti registrati parte l'email "disdetta dalla palestra" con il motivo ("Quel giorno la palestra resta chiusa.", "Quel giorno la fascia delle 18:30 non c'è più."; mai la nota del titolare); le persone senza account sono elencate nell'avviso finale, perché le avvisa il titolare; un'email che non parte non annulla nulla e l'avviso lo dice (dopo due invii falliti di fila le altre non si provano: risultano da avvisare). Togliere un orario dalla settimana tipo tocca tutte le date future che la seguono, con la stessa conferma.
- Si disdicono solo le prenotazioni che il titolare ha visto: la conferma porta i loro numeri, e se nel frattempo qualcuno ha prenotato dove la fascia sparisce la conferma si ripete. Le modifiche dell'orario passano una alla volta (la transazione comincia bloccando l'orario e solo dopo lo rilegge): due tocchi in fretta o due telefoni non si cancellano a vicenda.
- La gara con un cliente che prenota proprio mentre il titolare chiude: chi prenota legge l'orario e poi scrive la prenotazione; se la chiusura cade in mezzo (la durata di una transazione), la prenotazione resta attiva in una fascia che non c'è più. Non sparisce di nascosto: il giorno la mostra "Fuori dall'orario", il calendario del mese la segna, e si disdice dal giorno. Bloccare l'orario a ogni prenotazione di ogni cliente costerebbe più del caso che evita.
- Dopo una modifica l'avviso porta nell'indirizzo solo numeri e giorni (`?avviso=…&disdette=3&senza-account=12,15&senza-email=18`): nomi ed email li legge la pagina dal database, e una nota non va mai in un indirizzo (la conferma la rimanda nel modulo). Limite dei tentativi: 150 modifiche dell'orario e delle regole ogni 15 minuti per titolare, con un contatore suo, separato da quello delle prenotazioni.

### Dare il ruolo al titolare

Dalla console, mai da una pagina web, e solo a un account che esiste già con l'email confermata: il titolare si registra dal sito come un cliente, conferma l'email, poi

```sh
php bin/console admin:grant <email>    # dà il ruolo; rilanciato non cambia nulla
php bin/console admin:list             # i titolari
php bin/console admin:revoke <email>   # lo toglie e chiude le sue sessioni su ogni dispositivo
```

Sul server lo stesso comando, da `public_html`: `php _app/bin/console admin:grant <email>` (vedi [Prima di andare online](#prima-di-andare-online)).

### Moduli del pannello

```text
src/Admin/                 il pannello: solo HTTP, le regole stanno in src/Booking/Staff/ e src/Booking/Schedule/
  AdminUrls                gli indirizzi (mai un nome, un'email né una nota: dopo un redirect solo numeri e giorni)
  Form/                    i moduli: la prenotazione (due campi alternativi), le impostazioni, gli orari, le chiusure
  Http/                    il giorno (RosterController), il mese, la settimana tipo, la settimana, le chiusure, le impostazioni,
                           la prenotazione nuova, la disdetta, la conferma delle modifiche (ScheduleChanges), gli avvisi
  AdminModule.php          collega tutto; dà ad Application le rotte e il link nella pagina dell'account
src/Booking/Staff/         StaffBooking, StaffCancellation, StaffThrottle, DayRoster (il giorno con le sue fasce),
                           MonthOverview (il mese), ScheduleBoard (l'orario come lo cambia il titolare)
src/Booking/Schedule/      l'orario e le regole nel database e le loro modifiche (vedi L'orario nel database)
src/Account/AdminRoles     dare e togliere il ruolo (i comandi admin:grant, admin:revoke, admin:list)
```

## Area clienti

L'account del cliente, con l'email confermata. Le pagine hanno un layout compatto (`templates/layouts/account.php`), funzionano senza JavaScript e su schermi da 320px; lo script aggiunge solo il bottone "Mostra" della password, il blocco del doppio invio e il focus sul riepilogo degli errori.

| Indirizzo                                           | Cosa fa                                                                                                                                  |
| --------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `/account/registrati/`                              | nome e cognome, email, password, informativa sulla privacy; poi "Controlla la posta"                                                     |
| `/account/registrati/controlla-la-posta/`           | la pagina dopo la registrazione e dopo un nuovo link di conferma                                                                         |
| `/account/verifica/?token=…`                        | il link dell'email: conferma, apre la sessione e porta alla destinazione; fuori dal browser della registrazione chiede prima la password |
| `/account/verifica/nuovo-link/`                     | un nuovo link di conferma                                                                                                                |
| `/account/accedi/`                                  | email e password                                                                                                                         |
| `/account/esci/`                                    | POST: chiude la sessione; GET: chiede conferma (un link non fa mai uscire)                                                               |
| `/account/password-dimenticata/`                    | chiede il link per una nuova password                                                                                                    |
| `/account/password-dimenticata/controlla-la-posta/` | la pagina dopo la richiesta                                                                                                              |
| `/account/nuova-password/?token=…`                  | la password nuova: chiude le sessioni su ogni dispositivo, fa entrare e manda un'email di avviso                                         |
| `/account/`                                         | solo dopo l'accesso: saluto, le prossime prenotazioni con "Disdici", dati dell'account, uscita                                           |

Come funziona:

- Nessuna enumerazione: registrazione, nuovo link e password dimenticata rispondono sempre allo stesso modo, che l'email abbia un account o no, con gli stessi cookie. Cambia solo l'email che arriva nella casella (per un account già confermato: "Hai già un account", con i link per accedere e per una nuova password; per un account mai confermato la password dimenticata manda "Completa la registrazione"). Nuovo link e password dimenticata mandano l'email dopo la risposta (`Outbox`, svuotata da `Application::finish()` quando `index.php` ha già chiuso la connessione): né il tempo né un invio fallito cambiano la pagina. La registrazione invece manda subito, e se l'email non parte la pagina lo dice (vale uguale per account nuovi ed esistenti).
- L'accesso ha una risposta unica per email sconosciuta, password sbagliata ed email non ancora confermata, anche con la password giusta: chiunque può registrare un indirizzo non suo, e una risposta diversa rivelerebbe se quell'indirizzo aveva già un account. La password si verifica sempre, su un hash finto se l'account non c'è. Dopo un accesso non riuscito la pagina ricorda a tutti di confermare l'email e offre il bottone per un nuovo link.
- Il link di conferma basta da solo solo nel browser che ha inviato la registrazione (cookie `__Host-signup`, la sua impronta sta sull'account in sospeso). Altrove (un altro dispositivo, il browser dell'app di posta, un programma che controlla i link) mostra una pagina che chiede la password scelta registrandosi e, finché non la riceve, non cambia nulla. Così il destinatario di una registrazione fatta da un altro non attiva l'account aprendo il link, e un link mandato da un altro non fa cambiare account a chi lo apre. I tentativi di password su quella pagina contano come gli accessi.
- Una registrazione mai confermata si sostituisce con quella nuova (nessuno ha ancora dimostrato di possedere l'email); un link nuovo annulla i precedenti. Se la sostituzione arriva mentre si apre il link di prima, la conferma non passa: l'account si conferma solo con la password letta insieme al link.
- La password dimenticata vale solo per gli account con l'email confermata. Un account in sospeso ha il nome e il consenso di chi ha compilato la registrazione, magari un estraneo: invece del link per la nuova password riceve "Completa la registrazione", con il link per registrarsi di nuovo.
- Il nome di un account non confermato non compare nelle email (né nel destinatario né nel saluto, che diventa "Ciao,"): l'ha scritto chi ha compilato il modulo, non per forza il padrone della casella.
- Link delle email monouso, nel database solo l'impronta SHA-256: conferma 48 ore, nuova password un'ora. Un link di conferma già usato su un account confermato non è un errore: la pagina dice di accedere.
- Sessioni sul database (niente `$_SESSION`): un token casuale nel cookie, nel database solo la sua impronta. Cookie `HttpOnly`, `SameSite=Lax`, `Path=/`; in HTTPS anche `Secure` con il prefisso `__Host-`, su `http://localhost` senza. Token nuovo a ogni accesso, cancellato all'uscita; la sessione scade dopo 30 giorni senza visite (`session.idle_days` in `config/local.php`). Sessioni, link e contatori scaduti si cancellano a ogni accesso (e con `bin/console prune`).
- Ogni modulo, anche prima dell'accesso, ha il token CSRF (double-submit cookie `__Host-csrf`, confronto a tempo costante) e il controllo di `Sec-Fetch-Site` o `Origin`. Un modulo respinto riceve una pagina 403 che invita a ricaricare.
- Limiti dei tentativi a finestre fisse, sul database: per l'accesso 20 tentativi ogni 15 minuti per indirizzo IP, 8 per la stessa email dallo stesso indirizzo e 40 per email da tutti gli indirizzi insieme. Si contano prima di verificare la password (tante richieste in parallelo non passano il limite) e uno dopo l'altro: oltre il limite dell'indirizzo non si toccano i contatori dell'email, oltre quello della coppia non si tocca il tetto dell'email, così da un indirizzo solo non si blocca l'accesso di un altro. Un accesso riuscito azzera i tentativi di quella email da quell'indirizzo. Poi 10 registrazioni l'ora per IP; 10 richieste di link l'ora per IP; al massimo 5 email dell'account l'ora allo stesso indirizzo, contate a ogni richiesta anche quando l'indirizzo non ha un account (oltre, non parte nulla, in silenzio). Indirizzi IP ed email non si salvano mai: solo la loro impronta HMAC con la chiave segreta. Un IPv6 conta per la sua rete /64.
- Password da 8 a 128 caratteri, nessuna regola di composizione. Argon2id se PHP lo offre, altrimenti bcrypt (e allora al massimo 72 byte). Se i parametri cambiano, l'hash si rifà al prossimo accesso.
- Destinazione dopo l'accesso: il parametro `ritorno` (o il campo nascosto omonimo) vale solo se è un percorso interno scritto in modo pulito e non è una pagina dell'accesso; altrimenti si va a `/account/`. Sopravvive anche al giro registrazione → email → conferma (resta nel database con il link).
- Le pagine dell'area clienti rispondono `Cache-Control: no-store`, `X-Robots-Tag: noindex` e hanno `<meta name="robots" content="noindex">`; quelle con un token nell'indirizzo anche `Referrer-Policy: no-referrer`. POST riuscito: redirect 303. Modulo con errori: 422, con il riepilogo degli errori, `aria-invalid`, `aria-describedby` e i valori ricompilati (mai la password).
- Un'email che non parte non è mai un errore 500: si registra nel log; dopo la registrazione la pagina propone di chiedere un nuovo link.
- Senza `config/local.php` sul server, ogni pagina dell'area clienti risponde 503 con una pagina cortese; link e calendario non aprono mai il database, e la home lo legge solo per i posti delle fasce, dentro un guscio che non la fa mai cadere (vedi [Prenotazioni](#la-landing-e-il-database)).

## Configurazione locale

`config/site.php` sono i contenuti, nella repo. `config/local.php` è ciò che dipende dal server, ignorato da git e mai nel pacchetto di deploy: si crea copiando `config/local.example.php`, che spiega ogni chiave.

| Chiave              | Cosa                                                                                                                 |
| ------------------- | -------------------------------------------------------------------------------------------------------------------- |
| `base_url`          | origine pubblica del sito (`https://fourstreet.studio`), da cui nascono i link delle email; se manca vale `site_url` |
| `secret`            | chiave casuale di almeno 32 caratteri per le impronte di IP ed email: `php -r "echo bin2hex(random_bytes(32));"`     |
| `database`          | `dsn`, `username`, `password` di PDO: `mysql:host=127.0.0.1;port=3306;dbname=…` oppure `sqlite:percorso`             |
| `mail`              | `transport` `smtp` (`host`, `port`, `username`, `password`) oppure `file` (`directory`); `from` e `from_name`        |
| `session.idle_days` | giorni senza visite dopo cui una sessione scade (predefinito 30)                                                     |

Un valore sbagliato ferma il sito con un messaggio che nomina il file e la chiave da correggere; password e chiave segreta non compaiono mai nei messaggi. I link delle email nascono sempre da `base_url`, mai dall'intestazione `Host` della richiesta. Fuori da `localhost` `base_url` deve essere in `https://`. Con l'origine pubblica in https (`base_url`, oppure `site_url` senza `config/local.php`) le risposte di PHP hanno `Strict-Transport-Security: max-age=31536000`, solo per questo host (niente `includeSubDomains` né `preload`); il `.htaccess` porta ogni richiesta http su https con un 301, tranne `/.well-known/` e `localhost`, senza cicli dietro un proxy (`X-Forwarded-Proto: https`). Gli errori di PHP non compaiono mai nella pagina: `index.php` li manda solo nel log, anche quelli all'avvio.

Senza `config/local.php`:

- in sviluppo (applicazione avviata dalla radice della repo) valgono valori che funzionano da soli: SQLite in `var/4street.sqlite`, email scritte come file in `var/mail/`, link verso `http://localhost:8000`;
- nel pacchetto di deploy (applicazione in `_app/`) l'area clienti risulta non configurata (503), il resto del sito funziona come sempre.

La variabile d'ambiente `FOURSTREET_LOCAL_CONFIG` indica un altro file al posto di `config/local.php`; se il file indicato non c'è, è un errore. La usano lo smoke test e il job MariaDB della CI.

## Database e migrazioni

SQLite in sviluppo e nei test, MySQL 8 o MariaDB 10.6+ in produzione: lo stesso codice gira su entrambi (dove l'SQL cambia, come negli upsert e nello schema, si sceglie in base al driver). La connessione è pigra: si apre solo quando una pagina legge o scrive dati. Errori come eccezioni, prepared statement veri, chiavi esterne attive anche su SQLite, utf8mb4 e fuso UTC su MySQL. Date e ore nel database sono sempre in UTC; diventano ora di San Marino solo quando si mostrano.

Le migrazioni stanno in `database/migrations/`, una per file, con il nome `NNNN_descrizione.php`, e si applicano in ordine di nome una volta sola: la tabella `schema_migrations` tiene il registro. Su SQLite ogni migrazione è una transazione; su MySQL lo schema non è transazionale, quindi ogni istruzione è scritta per poter girare di nuovo (`CREATE TABLE IF NOT EXISTS`, indici dentro `CREATE TABLE`).

```sh
php bin/console                       # l'elenco dei comandi
php bin/console migrate               # applica le migrazioni che mancano (anche: npm run migrate, composer migrate)
php bin/console migrate:status        # quali sono applicate e quali no
php bin/console prune                 # cancella sessioni, link e contatori scaduti
php bin/console admin:grant <email>   # dà il ruolo di titolare (vedi Pannello del titolare)
php bin/console admin:revoke <email>  # lo toglie e chiude le sessioni dell'account
php bin/console admin:list            # i titolari
```

In sviluppo, prima di provare l'area clienti: `npm run migrate` (crea `var/4street.sqlite`). Un database di sviluppo creato prima dello Sprint 3 va ricreato: la migrazione `0005` è cambiata sul posto (vedi [la tabella](#la-tabella-bookings)).

Tabelle: `users` (account; email unica e già in minuscolo; ruolo `customer` o `admin`, che si dà con `admin:grant`), `sessions`, `account_tokens` (link delle email), `rate_limits` (contatori dei tentativi), `bookings` (prenotazioni, vedi [la tabella](#la-tabella-bookings)), `booking_settings`, `schedule_weekly_slots`, `schedule_days` e `schedule_day_slots` (l'orario e le regole del titolare, migrazione `0006_create_schedule`, vedi [L'orario nel database](#lorario-nel-database)).

## Posta

Ogni email è un messaggio multipart testo + HTML, da due template in `templates/emails/<nome>/` (`text.php` e `html.php`, con gli stili in linea); il testo semplice si legge da solo. Nessuna immagine né font remoti: il marchio è testo.

- In sviluppo, senza `config/local.php`, le email non partono: ognuna diventa un file `.eml` in `var/mail/`, che si apre con un programma di posta. Il link di conferma è lì dentro.
- In produzione partono dalla casella SMTP di `config/local.php` (symfony/mailer). Porta 465: TLS dalla connessione; porta 587: STARTTLS obbligatorio.
- Nei test restano in memoria (`tests/Support/InMemoryMailer.php`).
- Le email che non devono far aspettare la pagina vanno nella `Outbox` (`src/Mail/Outbox.php`): `index.php` manda la risposta, chiude la connessione con `litespeed_finish_request()` (LiteSpeed, Hostinger) o `fastcgi_finish_request()` (PHP-FPM) e poi chiama `Application::finish()`, che le spedisce. Un invio fallito lì resta solo nel log. Con il server di sviluppo di PHP la connessione resta aperta fino alla fine: la pagina aspetta l'invio, ma si comporta allo stesso modo.

## Moduli e punti d'aggancio

```text
src/Account/          area clienti: utenti, registrazione, conferma, accesso, nuova password, email, ruolo di titolare
  Http/               controller e rendering delle pagine
  Form/               moduli e loro validazione
  Session/            sessioni sul database, Visitor, middleware StartSession, RequireLogin e RequireAdmin
  Token/              link monouso delle email
  AccountModule.php   collega servizi, controller e rotte; espone guards() e sessions() agli altri moduli
src/Admin/            il pannello del titolare (vedi Pannello del titolare)
src/Booking/          le prenotazioni (vedi Moduli, sotto Prenotazioni)
src/Config/           SiteConfig (config/site.php), LocalConfig (config/local.php), Environment
src/Console/          bin/console e i suoi comandi
src/Database/         Database (PDO pigro), DatabaseConfig, Row, UtcDateTime, Migration/
src/Http/             Request, Response, Cookie, CookiePolicy, Origin, Middleware/, Form/, Routing/
src/Mail/             Mailer, Message, Attachment, SmtpMailer, FileMailer, MailTemplates, Outbox
src/Security/         Token, Fingerprinter, IpAddress, Csrf/, Password/, RateLimit/
src/Backend.php       database, posta, password e impostazioni locali, passati ad Application::create()
```

`Application::create()` resta l'unico punto in cui le dipendenze si collegano: ogni modulo porta le sue rotte già pronte (le prenotazioni sono costruite così, in `BookingModule`). Per i moduli che verranno:

- **Una rotta riservata a chi ha fatto l'accesso**: `Route::form('/prenota/', $controller)->through(...$accountModule->guards()->member())`. `guards()->page()` dà le stesse protezioni (no-store, CSRF, visitatore riconosciuto) senza obbligare all'accesso; `guards()->tokenPage()` aggiunge `Referrer-Policy: no-referrer`.
- **Una rotta solo per il titolare**: `->through(...$accountModule->guards()->admin())`: come `member()`, e per chi non è titolare la pagina 404 degli indirizzi che non esistono (quella che `Application` passa ad `AccountModule::create()`).
- **Dove va il titolare dopo l'accesso**: l'ultimo argomento di `AccountModule::create()` (oggi `AdminUrls::HOME`); senza, all'account come tutti.
- **L'utente corrente**: `Visitor::of($request)->user` (null per un ospite), oppure `Visitor::of($request)->user()` nelle rotte con `member()`.
- **Il token CSRF per un modulo**: `CsrfToken::of($request)->value`, nel partial `partials/form/hidden`.
- **La destinazione dopo l'accesso**: `AccountUrls::to(AccountUrls::LOGIN, ReturnPath::fromInput('/prenota/…'))`; `RequireLogin` la mette da solo quando rimanda alla pagina di accesso.
- **Una sezione nella pagina dell'account**: una classe che implementa `FourStreet\Account\Http\DashboardSection`, passata ad `AccountModule::create()` (come "Le tue prenotazioni").
- **Un'email con un allegato**: `$mailTemplates->message($user->mailbox(), 'Oggetto', 'nome-template', $dati)->withAttachment(new Attachment('prenotazione.ics', 'text/calendar; charset=utf-8; method=PUBLISH', $ics))`, poi `$backend->mailer->send($message)`, che lancia `MailNotSent` se non parte; oppure `$outbox->queue($message)` (la `Outbox` che `Application::create()` passa ai moduli) per mandarla dopo la risposta, quando la pagina non deve aspettare né dire se è partita. `$user->mailbox()` mette il nome solo a email confermata.
- **Una migrazione**: un file nuovo in `database/migrations/` (per esempio `0006_….php`) che restituisce un oggetto `Migration` con le istruzioni per SQLite e per MySQL, come le altre.
- **Un comando**: una classe che implementa `FourStreet\Console\Command` e una riga in `ConsoleApplication::create()` (più il suo nome in `DATABASE_COMMANDS`, se usa il database).

Dalle prenotazioni, per altre pagine della palestra:

- **Chi è prenotato in un giorno**: `BookingRepository::roster($giorno)`, una lettura sola con `JOIN users` (nome ed email dei clienti, il nome di chi non ha l'account); `DayRoster` la mette nelle fasce del calendario, con cosa si può ancora fare.
- **Prenotare e disdire per la palestra**: `StaffBooking` (`check()`, `bookCustomer()` con l'email, `bookGuest()` con il nome) e `StaffCancellation` (`cancellable()`, `cancel()`), con le regole del titolare, il limite dei tentativi e le email. Sotto, `BookingRepository::place(…, CreatedBy::Admin)` e `placeGuest()`.
- **Il calendario delle fasce**: `BookingCalendar` (`days()`, `slotsOn()`, `slot()`, `seating()`, `openingDay()`) e `SlotBoard` sono senza HTTP.

## Prima di andare online

Cosa serve sul server Hostinger perché l'area clienti funzioni (fino ad allora risponde "non ancora attiva" e il resto del sito va benissimo):

1. **Database MySQL**: hPanel, Database, Database MySQL. Crea database e utente (password lunga e casuale). Dal sito l'host è `127.0.0.1`, porta 3306.
2. **Casella email** per il mittente (per esempio `prenotazioni@fourstreet.studio`): hPanel, Email. SMTP `smtp.hostinger.com`, porta 465. Controlla che il dominio abbia SPF, DKIM e DMARC (hPanel, Email, autenticazione), altrimenti le email finiscono nello spam.
3. **`config/local.php`**: dal File Manager di hPanel copia `public_html/_app/config/local.example.php` in `public_html/_app/config/local.php` e compila `secret`, `database` e `mail`. Il file non è raggiungibile dal browser (`_app/` è chiusa) e il deploy non lo tocca mai.
4. **Migrazioni**: via SSH (hPanel, Avanzate, Accesso SSH) da `public_html`: `php _app/bin/console migrate`. Senza SSH: un cron job di una volta sola in hPanel con lo stesso comando, poi cancellalo. Da ripetere dopo ogni deploy che aggiunge una migrazione; `php _app/bin/console migrate:status` dice se ne manca qualcuna.
5. **Informativa privacy**: la registrazione chiede di averla letta. Pubblicala e metti il suo indirizzo in `content.privacy_url` di `config/site.php` prima di aprire la registrazione. Deve parlare anche delle prenotazioni (vedi [Privacy](#privacy)).
6. **Prenotazioni**: conferma con il titolare i posti per fascia e la durata della fascia (stanno solo in `config/site.php`) e i valori iniziali della sezione `booking` (orari, chiusure, giorni in anticipo, limiti): dopo, orari, chiusure e regole li cambia lui dal pannello. Le pagine per prenotare si accendono con `booking.online` a `true` (oggi è già così: senza `config/local.php` rispondono "Prenotazioni presto"). Dopo il deploy che porta una migrazione nuova (`0005_create_bookings`, `0006_create_schedule`) va lanciato subito `migrate`: fino ad allora la home mostra gli orari del file senza i posti, l'account dice che le prenotazioni non si possono mostrare, `/prenota/` e le pagine del pannello rispondono con un errore, e ogni volta l'errore finisce nel log.
7. **Prova**: registrati con un'email vera, apri il link, entra, prenota una fascia (arriva l'email con l'evento da aggiungere al calendario), disdicila, esci, chiedi una nuova password.
8. **Titolare**: il titolare si registra dal sito con la sua email e la conferma; poi, via SSH da `public_html` (o con un cron di una volta sola), `php _app/bin/console admin:grant <email>`. Al prossimo accesso arriva nel pannello, `/gestione/`. `php _app/bin/console admin:list` dice chi è titolare; `admin:revoke <email>` toglie il ruolo e chiude le sue sessioni.
9. Facoltativo: un cron giornaliero `php _app/bin/console prune` (la pulizia avviene comunque a ogni accesso).

Se il sito passa dietro un CDN o un proxy (Cloudflare, il CDN di Hostinger), l'indirizzo del client non è più `REMOTE_ADDR`: i limiti per IP vanno rivisti prima di attivarlo.

## Requisiti

- PHP 8.3 o più recente (in produzione 8.3), con le estensioni openssl, mbstring, pdo e pdo_mysql; in sviluppo anche pdo_sqlite.
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
   extension=pdo_mysql
   extension=pdo_sqlite
   extension=sodium
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

5. Area clienti: `npm run migrate` crea il database di sviluppo (`var/4street.sqlite`). Le email finiscono in `var/mail/`: il link di conferma è dentro il file `.eml` più recente.

## Comandi

### npm

| Comando                | Cosa fa                                                                     |
| ---------------------- | --------------------------------------------------------------------------- |
| `npm run dev`          | Ricostruisce CSS e JavaScript a ogni modifica.                              |
| `npm run serve`        | Server PHP di sviluppo: `php -S localhost:8000 -t public public/index.php`. |
| `npm run migrate`      | Applica le migrazioni al database (in sviluppo `var/4street.sqlite`).       |
| `npm run console`      | La console dell'applicazione: `npm run console -- migrate:status`.          |
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

| Comando                  | Cosa fa                                                             |
| ------------------------ | ------------------------------------------------------------------- |
| `composer lint`          | PHP-CS-Fixer controlla lo stile e mostra le differenze.             |
| `composer format`        | PHP-CS-Fixer sistema lo stile.                                      |
| `composer analyse`       | PHPStan al livello massimo.                                         |
| `composer test`          | PHPUnit.                                                            |
| `composer test:database` | Solo i test del database (gruppo `database`).                       |
| `composer check`         | Stile, analisi statica e test: i controlli PHP della CI.            |
| `composer migrate`       | Applica le migrazioni al database.                                  |
| `composer console`       | La console dell'applicazione: `composer console -- migrate:status`. |

I test del database girano su SQLite in memoria. Con `FOURSTREET_TEST_DATABASE_DSN`, `FOURSTREET_TEST_DATABASE_USERNAME` e `FOURSTREET_TEST_DATABASE_PASSWORD` girano su un MySQL o MariaDB vero (con le migrazioni applicate e le tabelle svuotate prima di ogni test), come nel job "Database MariaDB" della CI:

```sh
FOURSTREET_TEST_DATABASE_DSN="mysql:host=127.0.0.1;port=3306;dbname=fourstreet_test" \
FOURSTREET_TEST_DATABASE_USERNAME=fourstreet FOURSTREET_TEST_DATABASE_PASSWORD=… composer test:database
```

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

| Job                 | Quando                                                     | Cosa fa                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| ------------------- | ---------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PHP 8.3 e 8.4       | ogni push su `develop` e `main`, ogni pull request         | `composer validate --strict`, dipendenze, `composer lint`, `composer analyse`, `composer test` (database SQLite in memoria)                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| Database MariaDB    | come sopra                                                 | con un servizio MariaDB 10.11: `bin/console migrate` due volte (la seconda non deve fare nulla), poi i test del gruppo `database` su MariaDB                                                                                                                                                                                                                                                                                                                                                                                                                     |
| Front-end           | come sopra                                                 | `npm ci`, lint, formattazione, test, build                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| Messaggi di commit  | come sopra                                                 | commitlint sui commit del push o della pull request                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| Pacchetto di deploy | come sopra                                                 | `npm run assemble` con PHP 8.3, `npm run smoke` (anche area clienti, prenotazioni e pannello del titolare: 503 senza configurazione, poi un giro completo con una configurazione temporanea, dalla registrazione alla prenotazione con l'email e il file `.ics`, fino alla disdetta; poi `admin:grant`, il pannello con una persona senza account e un cliente inseriti e disdetti, il mese, la settimana tipo e la settimana, una chiusura con la conferma, la disdetta e la sua email, la riapertura, le impostazioni, `admin:revoke`), artefatto `site-build` |
| Deploy su Hostinger | push su `main` o avvio manuale su `main`, dopo i job sopra | sync FTPS dell'artefatto in `public_html` (mai `config/local.php` né `var/`), poi controlla che `/`, `/links/` e `/calendario.php` rispondano 200                                                                                                                                                                                                                                                                                                                                                                                                                |
| Release             | push su `main`, dopo i job sopra                           | semantic-release                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |

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
