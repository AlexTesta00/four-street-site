<?php

declare(strict_types=1);

namespace FourStreet\Mail;

use FourStreet\View\View;

/**
 * Le email del sito nascono da una coppia di template: templates/emails/<nome>/text.php (testo semplice)
 * e templates/emails/<nome>/html.php (HTML con gli stili in linea, come vogliono i programmi di posta).
 */
final readonly class MailTemplates
{
    public function __construct(
        private View $view,
    ) {}

    /**
     * @param string $template nome della cartella in templates/emails/, per esempio "verification"
     * @param array<string, mixed> $data variabili dei due template
     */
    public function message(Mailbox $to, string $subject, string $template, array $data): Message
    {
        $text = (string) $this->view->render('emails/' . $template . '/text', $data);
        $html = (string) $this->view->render('emails/' . $template . '/html', $data);

        return new Message($to, $subject, rtrim($text) . "\n", $html);
    }
}
