<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use Closure;
use FourStreet\Http\Controller;
use FourStreet\Http\Request;
use FourStreet\Http\Response;

/**
 * Controller di prova: restituisce una risposta fissa (o quella di una funzione) e ricorda le richieste.
 */
final class StubController implements Controller
{
    /** @var list<Request> */
    public array $requests = [];

    /**
     * @param Closure(Request): Response|null $respond
     */
    public function __construct(
        private readonly string $body = 'ok',
        private readonly ?Closure $respond = null,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->requests[] = $request;

        return $this->respond === null ? Response::text($this->body) : ($this->respond)($request);
    }
}
