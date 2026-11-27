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

    // Segnaposto dei contenuti che mancano ancora (la sezione "content" qui sotto).
    // true: al posto di ogni contenuto vuoto la pagina mostra il suo segnaposto tra parentesi quadre,
    // per esempio "[Orari da confermare]".
    // false: la pagina toglie tutto ciò che conterrebbe solo un segnaposto (la riga degli orari,
    // le domande senza risposta, le foto che mancano, i dati legali). Mettilo a false per andare
    // online senza segnaposto anche prima che arrivino i contenuti.
    'show_placeholders' => true,

    // Contenuti ancora da confermare. Lascia vuoto ('' oppure null) quello che non c'è ancora.
    'content' => [
        // Orari di apertura, su una riga: "Dal lunedì al sabato, 7:00-22:00".
        'hours' => '',
        // Prezzi, su una riga.
        'prices' => '',
        // Cosa trovi in sala (discipline e attrezzatura), su una riga e senza punto finale:
        // la pagina scrive "Cosa trovi in sala: …".
        'room' => '',
        // Le tre foto della sezione "Lo spazio", in bianco e nero, da mettere in public/assets/img/.
        // Per ognuna: ['src' => '/assets/img/sala.jpg', 'width' => 1600, 'height' => 1200,
        // 'alt' => 'Descrizione della foto per chi non la vede'] (larghezza e altezza in pixel).
        'photos' => [
            'room' => null,
            'entrance' => null,
            'detail' => null,
        ],
        // Indirizzo completo (https://…) della pagina con l'informativa privacy.
        'privacy_url' => '',
        // Ragione sociale e COE, su una riga: "4Street S.r.l. · COE SM00000".
        'company' => '',
    ],

    // Prenotazione per fasce orarie. Le regole qui sotto sono provvisorie: vanno confermate con il titolare.
    // Orari e giorni sono ora di San Marino (timezone qui sopra).
    'booking' => [
        // true: si prenota dal sito (serve anche config/local.php sul server, con database e posta).
        // false: la landing mostra le fasce di esempio e /prenota/ risponde "Prenotazioni presto";
        // chi ha già una prenotazione la vede e la può disdire dal suo account.
        'online' => true,
        // Posti per fascia oraria: tutti i numeri della pagina partono da qui.
        'capacity' => 5,
        // L'orario della settimana: per ogni giorno gli orari d'inizio delle fasce (HH:MM, a 24 ore).
        // Un giorno chiuso ha []. Le fasce di uno stesso giorno non si sovrappongono e finiscono entro mezzanotte.
        'schedule' => [
            'lunedì' => ['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'],
            'martedì' => ['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'],
            'mercoledì' => ['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'],
            'giovedì' => ['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'],
            'venerdì' => ['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'],
            'sabato' => ['07:00', '09:30', '12:30', '17:30', '18:30', '20:00'],
            'domenica' => [],
        ],
        // Quanto dura una fascia, in minuti (serve anche all'evento del calendario nell'email).
        'slot_minutes' => 60,
        // Si prenota da oggi fino a questi giorni dopo oggi (0: solo oggi). Mai prima dell'inaugurazione:
        // il primo giorno prenotabile è quello dell'apertura, dalle fasce che iniziano dall'ora d'apertura in poi.
        'days_ahead' => 14,
        // Giorni di chiusura, senza fasce, nel formato AAAA-MM-GG: ['2026-12-25', '2027-01-01'].
        'closed_dates' => [],
        // Fino a quanti minuti prima dell'inizio si prenota e si disdice (0: fino all'inizio della fascia).
        'booking_closes_minutes_before' => 0,
        'cancel_closes_minutes_before' => 0,
        // Prenotazioni future attive per cliente, al massimo (una al giorno, sempre).
        'max_upcoming' => 6,
        // Fasce di esempio, per quando le prenotazioni non sono attive: orario (HH:MM) => posti già presi, da 0 a capacity.
        'example_slots' => [
            '07:00' => 2,
            '09:30' => 4,
            '12:30' => 1,
            '17:30' => 5,
            '18:30' => 3,
            '20:00' => 0,
        ],
        // La fascia di esempio scelta quando si apre la pagina: una delle fasce qui sopra, non piena.
        'default_slot' => '18:30',
    ],
];
