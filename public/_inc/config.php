<?php
// Contenuti del sito: tutto ciò che il cliente può voler cambiare sta qui.
return [
    'name' => '4Street',
    'timezone' => 'Europe/Rome',

    // Data e ora dell'inaugurazione (ora locale di San Marino): il countdown scade qui.
    'opening' => '2026-12-06 00:00',
    // Metti true quando l'orario qui sopra è quello vero: solo allora viene mostrato in pagina
    // e inserito nell'evento del calendario (altrimenti l'evento dura tutto il giorno).
    'opening_time_confirmed' => false,

    'address' => 'Via del Bando 22, San Marino',
    // Link esatto della scheda Google Maps della palestra. Se vuoto, si usa la ricerca per indirizzo.
    'maps_url' => '',

    // I link vuoti non vengono pubblicati.
    'links' => [
        'instagram' => 'https://www.instagram.com/4ndrew_sw',
        'whatsapp' => 'https://wa.me/393662546495',
    ],

    // Indirizzo pubblico del sito, senza barra finale (serve per le anteprime social).
    'site_url' => 'https://fourstreet.studio',
];
