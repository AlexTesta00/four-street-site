<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Account\Notice;
use FourStreet\Account\Session\Visitor;
use FourStreet\Config\SiteConfig;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;
use FourStreet\Security\Csrf\CsrfToken;
use FourStreet\Text\ItalianDateFormatter;

/**
 * /account/: la pagina dell'account, solo dopo l'accesso. Saluto, dati dell'account, uscita
 * e lo spazio delle prenotazioni (vuoto finché la prenotazione dal sito non c'è).
 */
final readonly class AccountController implements Controller
{
    public function __construct(
        private AccountPages $pages,
        private SiteConfig $site,
        private ItalianDateFormatter $dates,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = Visitor::of($request)->user();

        return $this->pages->render('dashboard', [
            'csrf' => CsrfToken::of($request)->value,
            'user' => $user,
            'createdOn' => $this->dates->format($user->createdAt->setTimezone($this->site->timezone)),
            'alert' => Notice::fromRequest($request)?->alert(),
        ]);
    }
}
