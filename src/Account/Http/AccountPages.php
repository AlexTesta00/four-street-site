<?php

declare(strict_types=1);

namespace FourStreet\Account\Http;

use FourStreet\Clock\Clock;
use FourStreet\Config\SiteConfig;
use FourStreet\Http\Response;
use FourStreet\Security\RateLimit\RateLimitStatus;
use FourStreet\View\Alert;
use FourStreet\View\View;

/**
 * Le pagine dell'area clienti (templates/pages/account/): ognuna riceve i suoi dati più quelli comuni
 * al layout compatto (configurazione del sito, anno corrente). Le pagine delle prenotazioni hanno lo stesso
 * layout e gli stessi avvisi, con i template in un'altra cartella (templates/pages/booking/).
 */
final readonly class AccountPages
{
    /**
     * @param string $directory la cartella dei template, dentro templates/
     */
    public function __construct(
        private View $view,
        private SiteConfig $site,
        private Clock $clock,
        private string $directory = 'pages/account',
    ) {}

    /**
     * @param string $page nome del template nella cartella, per esempio "login"
     * @param array<string, mixed> $data
     */
    public function render(string $page, array $data = [], int $status = 200): Response
    {
        return Response::html($this->view->render($this->directory . '/' . $page, [
            'site' => $this->site,
            'year' => $this->clock->now()->format('Y'),
            ...$data,
        ]), $status);
    }

    /**
     * Il modulo di nuovo, con l'avviso dei troppi tentativi: 429 e Retry-After.
     *
     * @param array<string, mixed> $data
     */
    public function tooManyAttempts(string $page, array $data, int $retryAfter): Response
    {
        $minutes = RateLimitStatus::minutes($retryAfter);
        $alert = Alert::error(sprintf('Troppi tentativi in poco tempo. Riprova tra %d %s.', $minutes, $minutes === 1 ? 'minuto' : 'minuti'));

        return $this->render($page, [...$data, 'alert' => $alert], 429)
            ->withHeader('Retry-After', (string) max(1, $retryAfter));
    }
}
