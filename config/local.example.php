<?php

declare(strict_types=1);

// Configurazione locale: ciò che dipende dal server e non va mai nella repo (è pubblica).
// Sul server copia questo file in config/local.php (nel pacchetto: public_html/_app/config/local.php)
// e compila i valori. config/local.php è ignorato da git e il deploy non lo tocca mai.
//
// Senza config/local.php:
// - in sviluppo (repo) valgono valori che funzionano da soli: SQLite in var/4street.sqlite,
//   email scritte come file .eml in var/mail/, link verso http://localhost:8000;
// - sul server l'area clienti risponde "non ancora attiva"; home, link e calendario funzionano lo stesso.
//
// Un valore sbagliato ferma il sito con un messaggio che dice quale chiave correggere.
return [
    // Indirizzo pubblico del sito, solo l'origine (senza percorso): i link delle email partono da qui.
    // Se manca vale site_url di config/site.php. Fuori da localhost deve essere https://.
    'base_url' => 'https://fourstreet.studio',

    // Chiave segreta di almeno 32 caratteri, diversa per ogni server, per le impronte degli indirizzi IP
    // e delle email nei contatori dei tentativi. Generala con:
    //   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    // Se cambia, i contatori dei tentativi ripartono da zero (nient'altro).
    'secret' => '',

    // Database MySQL o MariaDB (su Hostinger: hPanel, Database, MySQL; host 127.0.0.1).
    // Per SQLite: 'dsn' => 'sqlite:var/4street.sqlite' (percorso relativo alla radice dell'applicazione).
    'database' => [
        'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=NOME_DEL_DATABASE',
        'username' => 'UTENTE_DEL_DATABASE',
        'password' => '',
    ],

    // Posta in uscita.
    // transport "smtp": una casella email vera (su Hostinger: smtp.hostinger.com, porta 465).
    //   Porta 465: TLS dalla connessione. Porta 587: STARTTLS obbligatorio.
    // transport "file": ogni email diventa un file .eml in 'directory' (per le prove, nessun invio).
    'mail' => [
        'transport' => 'smtp',
        'host' => 'smtp.hostinger.com',
        'port' => 465,
        'username' => 'prenotazioni@fourstreet.studio',
        'password' => '',
        // Il mittente: di solito la stessa casella di username.
        'from' => 'prenotazioni@fourstreet.studio',
        'from_name' => '4Street',
    ],

    // Una sessione scade dopo questi giorni senza visite (ogni visita la allunga).
    'session' => [
        'idle_days' => 30,
    ],
];
