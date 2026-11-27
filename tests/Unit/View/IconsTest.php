<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\View;

use FourStreet\View\Escaper;
use FourStreet\View\Html;
use FourStreet\View\Icons;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Icons::class)]
#[UsesClass(Escaper::class)]
#[UsesClass(Html::class)]
final class IconsTest extends TestCase
{
    public function testRendersAnIconWithItsClass(): void
    {
        self::assertSame(
            '<svg class="fs-btn__arrow" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"'
            . ' stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true"><path d="M7 17 17 7"></path><path d="M8 7h9v9"></path></svg>',
            (string) (new Icons())->svg('arrow', 'fs-btn__arrow'),
        );
    }

    public function testWithoutClassThereIsNoClassAttribute(): void
    {
        self::assertStringStartsWith('<svg width="24"', (string) (new Icons())->svg('copy'));
    }

    public function testTheClassIsEscaped(): void
    {
        self::assertStringStartsWith('<svg class="a&quot; onload=&quot;x"', (string) (new Icons())->svg('pin', 'a" onload="x'));
    }

    public function testTheFullSetUsedByThePages(): void
    {
        $icons = new Icons();

        self::assertSame(
            ['arrow', 'calendar', 'navigate', 'copy', 'list', 'plus', 'instagram', 'whatsapp', 'pin', 'clock', 'bag', 'check', 'alert', 'info', 'mail', 'exit', 'user', 'previous', 'next'],
            $icons->names(),
        );
        foreach ($icons->names() as $name) {
            $svg = (string) $icons->svg($name);
            self::assertStringStartsWith('<svg ', $svg);
            self::assertStringEndsWith('</svg>', $svg);
            self::assertStringContainsString('aria-hidden="true"', $svg);
        }
        // L'icona dell'orologio ha la lancetta animata.
        self::assertStringContainsString('class="fs-anim-tick"', (string) $icons->svg('clock'));
        // Nelle domande frequenti, a risposta aperta, sparisce l'ultimo tratto del più: quello verticale.
        self::assertStringEndsWith('<path d="M12 5v14"></path></svg>', (string) $icons->svg('plus'));
    }

    public function testUnknownIconsFailLoudly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Icona sconosciuta: "rocket"');

        (new Icons())->svg('rocket');
    }
}
