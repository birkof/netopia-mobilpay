<?php

declare(strict_types=1);

namespace Tests\Payment\Request;

use DOMDocument;
use Mobilpay\Payment\Request\Notify;
use PHPUnit\Framework\TestCase;

final class NotifyTest extends TestCase
{
    public function testCreateXmlElementAndLoadFromXmlRoundTrip(): void
    {
        $notify = new Notify();
        $notify->action = 'confirmed';
        $notify->purchaseId = 'PID-123';
        $notify->originalAmount = '100.00';
        $notify->processedAmount = '100.00';
        $notify->errorCode = '0';
        $notify->errorMessage = 'Approved';

        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->appendChild($notify->createXmlElement($doc));

        $parsed = new Notify();
        $parsed->loadFromXml($doc->documentElement);

        $this->assertSame('confirmed', $parsed->action);
        $this->assertSame('PID-123', $parsed->purchaseId);
        $this->assertSame('100.00', $parsed->originalAmount);
        $this->assertSame('100.00', $parsed->processedAmount);
        $this->assertSame('0', $parsed->errorCode);
        $this->assertSame('Approved', $parsed->errorMessage);
        $this->assertNotEmpty($parsed->getCrc());
    }

    public function testLoadFromXmlParsesWellFormedDiscounts(): void
    {
        $notify = $this->loadNotify(
            '<mobilpay timestamp="20240101000000" crc="abc">'
            . '<action>confirmed</action>'
            . '<discounts><discount id="1" amount="10" currency="RON" third_party="0"/></discounts>'
            . '</mobilpay>'
        );

        $this->assertCount(1, $notify->discounts);
        $this->assertSame('1', $notify->discounts[0]->id);
        $this->assertSame('10', $notify->discounts[0]->amount);
        $this->assertSame('RON', $notify->discounts[0]->currency);
        $this->assertSame('0', $notify->discounts[0]->third_party);
    }

    /**
     * Regression for the null-safe discount parsing fix: a malformed <discount>
     * missing attributes must yield null fields, not a fatal "nodeValue on null".
     */
    public function testLoadFromXmlDoesNotCrashOnDiscountMissingAttributes(): void
    {
        $notify = $this->loadNotify(
            '<mobilpay timestamp="20240101000000" crc="abc">'
            . '<action>confirmed</action>'
            . '<discounts><discount id="1"/></discounts>'
            . '</mobilpay>'
        );

        $this->assertCount(1, $notify->discounts);
        $this->assertSame('1', $notify->discounts[0]->id);
        $this->assertNull($notify->discounts[0]->amount);
        $this->assertNull($notify->discounts[0]->currency);
        $this->assertNull($notify->discounts[0]->third_party);
    }

    public function testLoadFromXmlThrowsWhenCrcAttributeMissing(): void
    {
        $this->expectException(\Exception::class);

        $this->loadNotify('<mobilpay timestamp="20240101000000"><action>confirmed</action></mobilpay>');
    }

    public function testLoadFromXmlThrowsWhenActionElementMissing(): void
    {
        $this->expectException(\Exception::class);

        $this->loadNotify('<mobilpay timestamp="20240101000000" crc="abc"></mobilpay>');
    }

    private function loadNotify(string $xml): Notify
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        $notify = new Notify();
        $notify->loadFromXml($doc->documentElement);

        return $notify;
    }
}
