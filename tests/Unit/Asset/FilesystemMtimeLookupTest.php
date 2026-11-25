<?php

declare(strict_types=1);

namespace FourStreet\Tests\Unit\Asset;

use FourStreet\Asset\FilesystemMtimeLookup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilesystemMtimeLookup::class)]
final class FilesystemMtimeLookupTest extends TestCase
{
    public function testReadsTheModificationTimeOfAFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'asset');
        self::assertIsString($file);

        try {
            touch($file, 1_700_000_000);
            clearstatcache(true, $file);

            self::assertSame(1_700_000_000, (new FilesystemMtimeLookup())->mtime($file));
        } finally {
            unlink($file);
        }
    }

    public function testMissingFilesAndDirectoriesHaveNoTime(): void
    {
        $lookup = new FilesystemMtimeLookup();

        self::assertNull($lookup->mtime(__DIR__ . '/non-esiste.css'));
        self::assertNull($lookup->mtime(__DIR__));
    }
}
