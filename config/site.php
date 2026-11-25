<?php

declare(strict_types=1);

// Contenuti del sito: tutto ciò che il cliente può voler cambiare sta qui.
// Un valore non valido ferma il sito con un messaggio che dice cosa correggere.
return [
    'name' => '4Street',
    'timezone' => 'Europe/Rome',

    // Data e ora dell'inaugurazione (ora locale di San Marino), nel formato AAAA-MM-GG HH:MM:
    // il countdown scade qui.
    'opening' => '2026-12-06 15:00',
    // Metti true quando l'orario qui sopra è quello vero: solo allora viene mostrato in pagina
    // e inserito nell'evento del calendario (altrimenti l'evento dura tutto il giorno).
    'opening_time_confirmed' => true,

    'address' => 'Via del Bando 22, San Marino',
    // Link esatto della scheda Google Maps della palestra. Se vuoto, si usa la ricerca per indirizzo.
    'maps_url' => '',

    // I link vuoti non vengono pubblicati.
    'links' => [
        'instagram' => 'https://www.instagram.com/4street_sw',
        'whatsapp' => 'https://wa.me/393662546495',
    ],

    // Indirizzo pubblico del sito, senza barra finale (serve per le anteprime social).
    'site_url' => 'https://fourstreet.studio',
];
