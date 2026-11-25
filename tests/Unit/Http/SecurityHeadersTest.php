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

    public function testTheContentSecurityPolicy(): void
    {
        $policy = self::directives(SecurityHeaders::forSite()->applyTo(new Response())->header('Content-Security-Policy'));

        self::assertSame([
            'default-src' => "'self'",
            'script-src' => "'self'",
            'style-src-elem' => "'self' https://fonts.googleapis.com",
            'style-src-attr' => "'unsafe-inline'",
            'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com",
            'font-src' => "'self' https://fonts.gstatic.com",
            'img-src' => "'self'",
            'frame-src' => 'https://www.google.com',
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
        self::assertArrayHasKey('style-src-elem', $policy);
        self::assertSame("'self'", $policy['script-src']);
        self::assertStringNotContainsString('unsafe-inline', $policy['style-src-elem']);
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
