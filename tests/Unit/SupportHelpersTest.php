<?php

namespace Tests\Unit;

use App\Support\Fwver;
use App\Support\SemVer;
use PHPUnit\Framework\TestCase;

class SupportHelpersTest extends TestCase
{
    public function test_fwver_extracts_key_ending_in_fwver(): void
    {
        $this->assertSame('2.0.8', Fwver::extract([
            'IS-1-0---POW' => 123,
            'XX-9-9---FWVER' => '2.0.8',
        ]));
        $this->assertNull(Fwver::extract(['IS-1-0---POW' => 1]));
        $this->assertNull(Fwver::extract(['XX---FWVER' => '']));
    }

    public function test_semver_compare(): void
    {
        $this->assertSame(-1, SemVer::compare('2.0.8', '2.1.0'));
        $this->assertSame(0, SemVer::compare('2.1.0', '2.1.0'));
        $this->assertSame(1, SemVer::compare('2.1.0', '2.0.8'));
        $this->assertSame(1, SemVer::compare('3.0.0', '2.9.9'));
        $this->assertSame(-1, SemVer::compare('2.0.8', '2.0.9'));
    }

    public function test_semver_valid(): void
    {
        $this->assertTrue(SemVer::isValid('9.9.9'));
        $this->assertFalse(SemVer::isValid('2.1'));
        $this->assertFalse(SemVer::isValid('v2.1.0'));
    }
}
