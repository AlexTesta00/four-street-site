<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Http;

use FourStreet\Http\Response;
use FourStreet\Http\SecurityHeaders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityHeaders::class)]
#[UsesClass(Response::class)]
final class SecurityHeadersTest extends TestCase
{
    public function testAddsTheSecurityHeaders(): void
    {
        $response = SecurityHeaders::forSite()->applyTo(Response::html('<p>ok</p>'));

        self::assertSame('nosniff', $response->header('X-Content-Type-Options'));
        self::assertSame('DENY', $response->header('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->header('Referrer-Policy'));
        self::assertSame('camera=(), microphone=(), geolocation=(), payment=(), usb=()', $response->header('Permissions-Policy'));
        self::assertSame('same-origin', $response->header('Cross-Origin-Opener-Policy'));
        self::assertSame('text/html; charset=utf-8', $response->header('Content-Type'));
    }

    public function testStrictTransportSecurityOnlyWhenTheSiteIsInHttps(): void
    {
        $https = SecurityHeaders::forSite(https: true)->applyTo(new Response());

        // Un anno, solo per questo host: niente includeSubDomains né preload.
        self::assertSame('max-age=31536000', $https->header('Strict-Transport-Security'));
        self::assertFalse(SecurityHeaders::forSite(https: false)->applyTo(new Response())->hasHeader('Strict-Transport-Security'));
        self::assertFalse(SecurityHeaders::forSite()->applyTo(new Response())->hasHeader('Strict-Transport-Security'));
    }

    public function testTheContentSecurityPolicy(): void
    {
        $policy = self::directives(SecurityHeaders::forSite()->applyTo(new Response())->header('Content-Security-Policy'));

        self::assertSame([
            'default-src' => "'self'",
            'script-src' => "'self'",
            'style-src' => "'self' https://fonts.googleapis.com",
            'font-src' => "'self' https://fonts.gstatic.com",
            'img-src' => "'self'",
            'frame-src' => "'none'",
            'object-src' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'none'",
        ], $policy);
    }

    public function testNoInlineOrRemoteScripts(): void
    {
        $policy = self::directives(SecurityHeaders::forSite()->applyTo(new Response())->header('Content-Security-Policy'));

        self::assertArrayHasKey('script-src', $policy);
        self::assertSame("'self'", $policy['script-src']);
    }

    public function testNothingInlineIsAllowed(): void
    {
        $policy = (string) SecurityHeaders::forSite()->applyTo(new Response())->header('Content-Security-Policy');

        // Né <style> né attributi style né script scritti nella pagina: le direttive specifiche non servono.
        self::assertStringNotContainsString('unsafe-inline', $policy);
        self::assertStringNotContainsString('unsafe-eval', $policy);
        self::assertStringNotContainsString('style-src-attr', $policy);
        self::assertStringNotContainsString('style-src-elem', $policy);
    }

    public function testNoThirdPartyFrames(): void
    {
        $policy = self::directives(SecurityHeaders::forSite()->applyTo(new Response())->header('Content-Security-Policy'));

        self::assertArrayHasKey('frame-src', $policy);
        self::assertSame("'none'", $policy['frame-src']);
    }

    public function testDoesNotOverrideWhatTheControllerDecided(): void
    {
        $response = SecurityHeaders::forSite()->applyTo(new Response(200, ['X-Frame-Options' => 'SAMEORIGIN']));

        self::assertSame('SAMEORIGIN', $response->header('X-Frame-Options'));
        self::assertTrue($response->hasHeader('Content-Security-Policy'));
    }

    public function testCustomHeaders(): void
    {
        $response = (new SecurityHeaders(['X-Test' => 'a']))->applyTo(new Response());

        self::assertSame(['X-Test' => ['a']], $response->headers());
    }

    /**
     * @return array<string, string>
     */
    private static function directives(?string $policy): array
    {
        self::assertNotNull($policy);
        $directives = [];
        foreach (explode('; ', $policy) as $directive) {
            $parts = explode(' ', $directive, 2);
            $directives[$parts[0]] = $parts[1] ?? '';
        }

        return $directives;
    }
}
