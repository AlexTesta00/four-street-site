<?php

declare(strict_types=1);

namespace FourStreet\Tests\Support;

use Closure;
use FourStreet\Mail\Mailer;
use FourStreet\Mail\Message;

/**
 * Una casella che risponde piano, per provare il tetto di tempo degli invii senza aspettare davvero: ogni invio fa
 * avanzare l'orologio finto di tanti secondi, poi passa alla posta in memoria (che può anche fallire). Con $during
 * fa qualcosa proprio mentre il primo invio è in corso, una volta sola: per esempio un secondo invio del modulo, nello
 * stesso istante.
 */
final class SlowMailer implements Mailer
{
    /** @var (Closure(Message): void)|null */
    public ?Closure $during = null;

    public function __construct(
        public readonly InMemoryMailer $inner,
        private readonly MovableClock $clock,
        private readonly int $secondsPerEmail,
    ) {}

    public function send(Message $message): void
    {
        $this->clock->travel(sprintf('+%d seconds', $this->secondsPerEmail));
        $during = $this->during;
        if ($during !== null) {
            $this->during = null;
            $during($message);
        }
        $this->inner->send($message);
    }
}
