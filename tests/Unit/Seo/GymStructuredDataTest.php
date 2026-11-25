<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Seo;

use FourStreet\Booking\ExampleSlotCatalog;
use FourStreet\Booking\Slot;
use FourStreet\Config\ConfigValues;
use FourStreet\Config\SiteConfig;
use FourStreet\Content\PendingContent;
use FourStreet\Seo\GymStructuredData;
use FourStreet\Tests\Support\SiteFixture;
use FourStreet\View\Html;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GymStructuredData::class)]
#[UsesClass(SiteConfig::class)]
#[UsesClass(ConfigValues::class)]
#[UsesClass(PendingContent::class)]
#[UsesClass(ExampleSlotCatalog::class)]
#[UsesClass(Slot::class)]
#[UsesClass(Html::class)]
final class GymStructuredDataTest extends TestCase
{
    public function testOnlyTheKnownFactsOfTheGym(): void
    {
        self::assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'ExerciseGym',
            'name' => '4Street',
            'url' => 'https://fourstreet.studio/',
            'address' => 'Via del Bando 22, San Marino',
            'sameAs' => ['https://www.instagram.com/4street_sw'],
        ], GymStructuredData::fromSite(SiteFixture::config())->toArray());
    }

    public function testWithoutSiteUrlOrInstagramThoseFactsAreLeftOut(): void
    {
        $data = GymStructuredData::fromSite(SiteFixture::config(['site_url' => '', 'links' => ['instagram' => '']]));

        self::assertSame(
            ['@context' => 'https://schema.org', '@type' => 'ExerciseGym', 'name' => '4Street', 'address' => 'Via del Bando 22, San Marino'],
            $data->toArray(),
        );
    }

    public function testCompactJsonWithReadableCharactersAndSlashes(): void
    {
        self::assertSame(
            '{"@context":"https://schema.org","@type":"ExerciseGym","name":"4Street","address":"Piazza Città · 1"}',
            (new GymStructuredData('4Street', null, 'Piazza Città · 1'))->toJson(),
        );
    }

    public function testTheJsonCannotBreakOutOfTheScriptBlock(): void
    {
        $json = (new GymStructuredData('4Street', null, '</script><script>alert("x") & \'y\'</script>'))->toJson();

        self::assertStringNotContainsString('<', $json);
        self::assertStringNotContainsString('>', $json);
        self::assertStringNotContainsString('&', $json);
        self::assertStringNotContainsString("'", $json);
        self::assertStringContainsString('\u003C/script\u003E\u003Cscript\u003Ealert(\u0022x\u0022) \u0026 \u0027y\u0027\u003C/script\u003E', $json);
        self::assertSame(
            ['@context' => 'https://schema.org', '@type' => 'ExerciseGym', 'name' => '4Street', 'address' => '</script><script>alert("x") & \'y\'</script>'],
            json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTheScriptBlockForTheHead(): void
    {
        self::assertSame(
            '<script type="application/ld+json">{"@context":"https://schema.org","@type":"ExerciseGym","name":"4Street","url":"https://fourstreet.studio/","address":"Via del Bando 22, San Marino","sameAs":["https://www.instagram.com/4street_sw"]}</script>',
            (string) GymStructuredData::fromSite(SiteFixture::config())->script(),
        );
    }
}
