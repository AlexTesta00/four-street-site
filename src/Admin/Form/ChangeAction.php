<?php

declare(strict_types=1);

namespace FourStreet\Admin\Form;

/**
 * Che cosa chiede uno dei moduli delle pagine dell'orario: il campo nascosto "azione". Ogni pagina accetta solo le sue
 * (la settimana tipo aggiunge e toglie un orario; una settimana precisa anche chiude e riapre un giorno, lo riporta
 * alla settimana tipo e si copia nella successiva; le chiusure chiudono e riaprono): un'azione che la pagina non
 * conosce vuol dire un modulo manomesso.
 */
enum ChangeAction: string
{
    case Add = 'aggiungi';
    case Remove = 'togli';
    case Close = 'chiudi';
    case Reopen = 'riapri';
    case BackToWeek = 'settimana-tipo';
    case Copy = 'copia';
}
