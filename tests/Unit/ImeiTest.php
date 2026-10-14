<?php

namespace Tests\Unit;

use App\Support\Imei;
use PHPUnit\Framework\TestCase;

class ImeiTest extends TestCase
{
    public function test_normalize_text_imei(): void
    {
        $this->assertSame('865056070026808', Imei::normalize('865056070026808'));
    }

    public function test_normalize_strips_spaces_and_dashes(): void
    {
        $this->assertSame('865056070026808', Imei::normalize(' 865056-070026808 '));
        $this->assertSame('865056070026808', Imei::normalize('865 056 070 026 808'));
    }

    public function test_normalize_numeric_cell_keeps_all_15_digits(): void
    {
        // Excel stores a 15-digit IMEI as a float and displays 8.65056E+14
        $this->assertSame('865056070026808', Imei::normalize(865056070026808.0));
        $this->assertSame('865056070026808', Imei::normalize(865056070026808));
        $this->assertSame('865056070026808', Imei::normalize('8.65056070026808E+14'));
    }

    public function test_valid_imeis(): void
    {
        $this->assertTrue(Imei::isValid('865056070026808'));
        $this->assertTrue(Imei::isValid('490154203237518'));
    }

    public function test_rejects_wrong_length_and_bad_luhn(): void
    {
        $this->assertFalse(Imei::isValid('8650560700268'));   // 14 digits
        $this->assertFalse(Imei::isValid('865056070026809')); // bad check digit
        $this->assertFalse(Imei::isValid('86505607002680A')); // non-digit
        $this->assertFalse(Imei::isValid(''));
    }
}
