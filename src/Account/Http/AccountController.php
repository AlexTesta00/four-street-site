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
use FourStreet\View\Html;

/**
 * /account/: la pagina dell'account, solo dopo l'accesso. Saluto, le sezioni degli altri moduli
 * (le prenotazioni), i dati dell'account e l'uscita.
 */
final readonly class AccountController implements Controller
{
    /**
     * @param list<DashboardSection> $sections nell'ordine in cui compaiono, prima dei dati dell'account
     */
    public function __construct(
        private AccountPages $pages,
        private SiteConfig $site,
        private ItalianDateFormatter $dates,
        private array $sections = [],
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = Visitor::of($request)->user();

        return $this->pages->render('dashboard', [
            'csrf' => CsrfToken::of($request)->value,
            'user' => $user,
            'createdOn' => $this->dates->format($user->createdAt->setTimezone($this->site->timezone)),
            'alert' => Notice::fromRequest($request)?->alert(),
            'sections' => array_map(static fn(DashboardSection $section): Html => $section->render($request, $user), $this->sections),
        ]);
    }
}
