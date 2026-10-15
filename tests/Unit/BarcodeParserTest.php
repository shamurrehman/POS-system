<?php

namespace Tests\Unit;

use App\Support\BarcodeParser;
use PHPUnit\Framework\TestCase;

class BarcodeParserTest extends TestCase
{
    public function test_plain_ean_is_returned_unchanged(): void
    {
        $r = BarcodeParser::parse(' 8901234567890 ');
        $this->assertSame('8901234567890', $r['codes'][0]);
        $this->assertContains('08901234567890', $r['codes'], 'also matches products stored as GTIN-14');
        $this->assertNull($r['gtin']);
        $this->assertSame(['ABC-123'], BarcodeParser::parse('ABC-123')['codes']);
    }

    public function test_upc_and_ean_forms_of_the_same_code_match(): void
    {
        // Scanners send UPC-A either as 12 digits or as EAN-13 with a leading zero.
        $this->assertContains('012345678905', BarcodeParser::parse('0012345678905')['codes']);
        $this->assertContains('0012345678905', BarcodeParser::parse('012345678905')['codes']);
        $this->assertContains('96385074', BarcodeParser::parse('00000096385074')['codes']);
    }

    public function test_normalize_stores_only_the_gtin_of_a_pack_code(): void
    {
        $this->assertSame('8901234567890', BarcodeParser::normalize("]d2010890123456789017270600\x1D10LOT42\x1D21SER9"));
        $this->assertSame('8901234567890', BarcodeParser::normalize(' 8901234567890 '));
        $this->assertSame('ABC-123', BarcodeParser::normalize('ABC-123'));
    }

    public function test_gs1_datamatrix_with_separators(): void
    {
        $r = BarcodeParser::parse("]d2010890123456789017270600\x1D10LOT42\x1D21SER9");
        $this->assertSame('08901234567890', $r['gtin']);
        $this->assertSame('LOT42', $r['batch']);
        $this->assertSame('SER9', $r['serial']);
        $this->assertSame('2027-06-30', $r['expiry'], 'day 00 means end of month');
        $this->assertContains('8901234567890', $r['codes']);
    }

    public function test_human_readable_gs1(): void
    {
        $r = BarcodeParser::parse('(01)08901234567890(17)280115(10)B-77');
        $this->assertSame('08901234567890', $r['gtin']);
        $this->assertSame('2028-01-15', $r['expiry']);
        $this->assertSame('B-77', $r['batch']);
    }
}
