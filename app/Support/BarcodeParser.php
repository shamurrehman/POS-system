<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Parses scanner input. Plain EAN/UPC/Code128 values are returned as-is; GS1 strings
 * (the QR / DataMatrix codes printed on medicine packs) are split into GTIN, batch and expiry.
 */
class BarcodeParser
{
    private const GS = "\x1D";

    /** Fixed-length GS1 application identifiers we may need to skip over. */
    private const FIXED = ['00' => 18, '01' => 14, '02' => 14, '11' => 6, '13' => 6, '15' => 6, '16' => 6, '17' => 6, '20' => 2];

    /** @return array{raw:string, codes:list<string>, gtin:?string, batch:?string, expiry:?string, serial:?string} */
    public static function parse(string $input): array
    {
        $raw = trim($input);
        $result = ['raw' => $raw, 'codes' => [$raw], 'gtin' => null, 'batch' => null, 'expiry' => null, 'serial' => null];

        // Strip symbology identifiers some scanners prepend (]d2 DataMatrix, ]Q3 QR, ]C1 GS1-128).
        $data = preg_replace('/^\](d2|Q3|C1|e0)/', '', $raw);
        // Many keyboard-wedge scanners send the GS separator as a visible substitute.
        $data = str_replace(['<GS>', '{GS}', '~'], self::GS, $data);
        // Human-readable form: (01)12345678901234(17)270131(10)AB12
        if (str_starts_with($data, '(')) {
            $data = preg_replace('/\((\d{2,4})\)/', self::GS.'$1', $data);
            $data = ltrim($data, self::GS);
        }

        if (! preg_match('/^01\d{14}/', $data)) {
            $result['codes'] = array_values(array_unique([$raw, ...self::numericVariants($raw)]));

            return $result;
        }

        $pos = 0;
        $len = strlen($data);
        while ($pos < $len) {
            if ($data[$pos] === self::GS) {
                $pos++;

                continue;
            }
            $ai = substr($data, $pos, 2);
            if (isset(self::FIXED[$ai])) {
                $value = substr($data, $pos + 2, self::FIXED[$ai]);
                $pos += 2 + self::FIXED[$ai];
            } elseif (in_array($ai, ['10', '21'], true)) {
                $end = strpos($data, self::GS, $pos + 2);
                $end = $end === false ? $len : $end;
                $value = substr($data, $pos + 2, $end - $pos - 2);
                $pos = $end;
            } else {
                break; // unknown AI; stop parsing but keep what we have
            }

            match ($ai) {
                '01' => $result['gtin'] = $value,
                '10' => $result['batch'] = $value,
                '21' => $result['serial'] = $value,
                '17' => $result['expiry'] = self::expiryDate($value),
                default => null,
            };
        }

        if ($result['gtin']) {
            // Products may be stored with the 14-digit GTIN, the 13-digit EAN or a 12-digit UPC.
            $result['codes'] = array_values(array_unique([...self::numericVariants($result['gtin']), $raw]));
        }

        return $result;
    }

    /**
     * The value to store in products.barcode. A GS1 pack code carries a batch, expiry and a
     * per-pack serial, so only its GTIN identifies the product; storing the whole string would
     * make every other pack of the same medicine unscannable.
     */
    public static function normalize(string $input): string
    {
        $parsed = self::parse($input);
        if (! $parsed['gtin']) {
            return $parsed['raw'];
        }

        // Most medicine GTINs are an EAN-13 with a leading zero; store the familiar 13-digit form.
        return str_starts_with($parsed['gtin'], '0') ? substr($parsed['gtin'], 1) : $parsed['gtin'];
    }

    /**
     * Every standard length (GTIN-14, EAN-13, UPC-A, EAN-8) the same numeric code can be written in.
     * Scanners differ in whether they add or drop leading zeros (UPC-A sent as EAN-13, and so on).
     *
     * @return list<string>
     */
    public static function numericVariants(string $code): array
    {
        if (! preg_match('/^\d{8,14}$/', $code)) {
            return [];
        }

        $gtin = str_pad($code, 14, '0', STR_PAD_LEFT);
        $variants = [];
        foreach ([14, 13, 12, 8] as $length) {
            if (strspn($gtin, '0') >= 14 - $length) {
                $variants[] = substr($gtin, 14 - $length);
            }
        }

        return $variants;
    }

    private static function expiryDate(string $yymmdd): ?string
    {
        if (! preg_match('/^\d{6}$/', $yymmdd)) {
            return null;
        }
        $year = 2000 + (int) substr($yymmdd, 0, 2);
        $month = (int) substr($yymmdd, 2, 2);
        $day = (int) substr($yymmdd, 4, 2);
        if ($month < 1 || $month > 12) {
            return null;
        }
        $date = Carbon::create($year, $month, 1);

        // GS1 rule: day "00" means the last day of the month.
        return ($day === 0 ? $date->endOfMonth() : $date->setDay(min($day, $date->daysInMonth)))->toDateString();
    }
}
