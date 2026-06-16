<?php

declare(strict_types=1);

namespace Tests;

use Mobilpay\MobilpayGlobal;
use PHPUnit\Framework\TestCase;

final class MobilpayGlobalTest extends TestCase
{
    public function testBuildCrcExcludesCrcKeyAndReturnsMd5(): void
    {
        $params = ['signature' => 'ABC', 'amount' => '100', 'crc' => 'stale'];

        $crc = MobilpayGlobal::buildCRC($params);

        $this->assertSame(md5('signature=ABC&amount=100'), $crc);
        $this->assertSame(32, strlen($crc));
    }

    public function testBuildCrcExclusionIsCaseInsensitive(): void
    {
        $this->assertSame(md5('a=1'), MobilpayGlobal::buildCRC(['a' => '1', 'CRC' => 'x']));
    }

    public function testBuildQueryStringJoinsPairsWithAmpersand(): void
    {
        $this->assertSame('a=1&b=2', MobilpayGlobal::buildQueryString(['a' => '1', 'b' => '2']));
    }

    public function testAttachQueryStringReturnsUrlUnchangedForEmptyQuery(): void
    {
        $this->assertSame('https://x.test/cb', MobilpayGlobal::attachQueryString('https://x.test/cb', ''));
    }

    public function testAttachQueryStringAddsQuestionMarkThenAmpersand(): void
    {
        $this->assertSame('https://x.test/cb?a=1', MobilpayGlobal::attachQueryString('https://x.test/cb', 'a=1'));
        $this->assertSame('https://x.test/cb?z=0&a=1', MobilpayGlobal::attachQueryString('https://x.test/cb?z=0', 'a=1'));
    }

    public function testIsValidMsisdnAcceptsRomanianMobile(): void
    {
        $this->assertTrue(MobilpayGlobal::isValidMsisdn('0712345678'));
        $this->assertTrue(MobilpayGlobal::isValidMsisdn('40712345678'));
    }

    public function testIsValidMsisdnRejectsInvalidNumbers(): void
    {
        $this->assertFalse(MobilpayGlobal::isValidMsisdn('0612345678'));
        $this->assertFalse(MobilpayGlobal::isValidMsisdn('07123'));
        $this->assertFalse(MobilpayGlobal::isValidMsisdn('not-a-number'));
    }

    public function testGenerateSellerSignatureMatchesFormatAndExcludedCharset(): void
    {
        $signature = MobilpayGlobal::generateSellerSignature();

        // five groups of four chars; uppercase letters minus O/I, digits minus 0.
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z1-9]{4}(-[A-HJ-NP-Z1-9]{4}){4}$/', $signature);
        $this->assertSame(24, strlen($signature));
    }

    public function testGeneratePasswordReturnsRequestedLengthWithinCharset(): void
    {
        $password = MobilpayGlobal::generatePassword(
            ['first' => '0', 'last' => '9', 'exclude' => []],
            ['first' => 'A', 'last' => 'Z', 'exclude' => []],
            12,
        );

        $this->assertSame(12, strlen($password));
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{12}$/', $password);
    }
}
