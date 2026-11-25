<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\View;

use FourStreet\Asset\AssetUrls;
use FourStreet\Tests\Support\InMemoryMtimeLookup;
use FourStreet\View\Escaper;
use FourStreet\View\Html;
use FourStreet\View\Icons;
use FourStreet\View\Template;
use FourStreet\View\TemplateNotFound;
use FourStreet\View\View;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(View::class)]
#[CoversClass(Template::class)]
#[CoversClass(TemplateNotFound::class)]
#[UsesClass(AssetUrls::class)]
#[UsesClass(Escaper::class)]
#[UsesClass(Html::class)]
#[UsesClass(Icons::class)]
final class ViewTest extends TestCase
{
    public function testRendersAPageInsideItsLayoutWithPartialsAndHelpers(): void
    {
        $html = (string) self::view()->render('pages/greeting', ['name' => 'Ada <3']);

        self::assertSame(
            "<title>Ciao Ada &lt;3</title>\n"
            . "<main><p><b>Ada &lt;3</b>\n</p>\n"
            . "<link href=\"/assets/app.css?v=123\">\n"
            . '<svg class="x" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"'
            . ' stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true"><path d="M7 17 17 7"></path><path d="M8 7h9v9"></path></svg>'
            . "</main>\n",
            $html,
        );
    }

    public function testLayoutsCanBeNested(): void
    {
        self::assertSame(
            "<title>Annidato</title>\n<main><section>corpo\n</section>\n</main>\n",
            (string) self::view()->render('pages/nested'),
        );
    }

    public function testATemplateSeesOnlyItsDataAndTheHelpers(): void
    {
        $html = (string) self::view()->render('pages/scope', ['name' => 'Ada', 'step' => 4]);

        self::assertSame('__template,name,step,view', $html);
    }

    public function testCleansUpOutputBuffersWhenATemplateFails(): void
    {
        $level = ob_get_level();

        try {
            self::view()->render('pages/broken');
            self::fail('Il template doveva lanciare un\'eccezione.');
        } catch (RuntimeException $error) {
            self::assertSame('Template rotto.', $error->getMessage());
        }

        self::assertSame($level, ob_get_level());
    }

    public function testALayoutCanBeChosenOnlyOnce(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Il layout è già impostato');

        self::view()->render('pages/double-layout');
    }

    public function testMissingTemplate(): void
    {
        $this->expectException(TemplateNotFound::class);

        self::view()->render('pages/missing');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'risalita di cartella' => ['../src/Application'];
        yield 'percorso assoluto' => ['/etc/passwd'];
        yield 'con estensione' => ['pages/greeting.php'];
        yield 'vuoto' => [''];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsUnsafeTemplateNames(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::view()->render($name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reservedVariables(): iterable
    {
        yield 'view' => ['view'];
        yield 'this' => ['this'];
        yield 'nome del file' => ['__template'];
        yield 'non è un nome di variabile' => ['1name'];
        yield 'con il trattino' => ['first-name'];
    }

    #[DataProvider('reservedVariables')]
    public function testRejectsDataThatCannotBeATemplateVariable(string $variable): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($variable);

        self::view()->render('pages/scope', [$variable => 'x']);
    }

    public function testTemplateHelpers(): void
    {
        $template = new Template(self::view(), self::assets(), new Icons());

        self::assertSame('&lt;a href=&quot;x&quot;&gt;', $template->e('<a href="x">'));
        self::assertSame('<b>sicuro</b>', $template->e(new Html('<b>sicuro</b>')));
        self::assertSame('/assets/app.css?v=123', (string) $template->asset('/assets/app.css'));
        self::assertSame('/assets/missing.css', (string) $template->asset('/assets/missing.css'));
        self::assertStringStartsWith('<svg class="fs-x"', (string) $template->icon('pin', 'fs-x'));
        self::assertSame("<b>Ada</b>\n", (string) $template->partial('partials/name', ['name' => 'Ada']));
        self::assertNull($template->takeLayout());

        $template->layout('layouts/frame', ['title' => 't']);
        self::assertSame(['name' => 'layouts/frame', 'data' => ['title' => 't']], $template->takeLayout());
        self::assertNull($template->takeLayout());
    }

    private static function view(): View
    {
        return new View(dirname(__DIR__, 2) . '/Fixtures/templates', self::assets(), new Icons());
    }

    private static function assets(): AssetUrls
    {
        return new AssetUrls('/public', new InMemoryMtimeLookup(['/public/assets/app.css' => 123]));
    }
}
