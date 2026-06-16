<?php

declare(strict_types=1);

namespace Tests\Payment\Request;

use Mobilpay\Payment\Request\RequestAbstract;
use Mobilpay\Payment\Request\Sms;
use PHPUnit\Framework\TestCase;

final class RequestAbstractTest extends TestCase
{
    public function testRequestIdentifierIs32HexChars(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (new Sms())->getRequestIdentifier());
    }

    public function testRequestIdentifiersAreUnique(): void
    {
        $this->assertNotSame(
            (new Sms())->getRequestIdentifier(),
            (new Sms())->getRequestIdentifier(),
        );
    }

    public function testFactoryParsesXmlOrderIntoSms(): void
    {
        $xml = '<order type="sms" id="ORD-1"><signature>SIG-1</signature><service>SVC-1</service></order>';

        $req = RequestAbstract::factory($xml);

        $this->assertInstanceOf(Sms::class, $req);
        $this->assertSame('ORD-1', $req->orderId);
        $this->assertSame('SIG-1', $req->signature);
        $this->assertSame('SVC-1', $req->service);
        $this->assertSame(RequestAbstract::VERSION_XML, $req->getRequestInfo()->reqVersion);
    }

    public function testFactoryFallsBackToQueryStringParsing(): void
    {
        $req = RequestAbstract::factory('signature=SIG-2&service=SVC-2&tran_id=ORD-2');

        $this->assertInstanceOf(Sms::class, $req);
        $this->assertSame('ORD-2', $req->orderId);
        $this->assertSame('SIG-2', $req->signature);
        $this->assertSame(RequestAbstract::VERSION_QUERY_STRING, $req->getRequestInfo()->reqVersion);
    }

    public function testEncryptThenFactoryFromEncryptedRoundTrip(): void
    {
        if (!$this->rc4Available()) {
            $this->markTestSkipped('RC4 cipher unavailable in this OpenSSL build (expected on OpenSSL 3+).');
        }

        $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($pkey);
        openssl_pkey_export($pkey, $privatePem);
        $publicPem = (string) openssl_pkey_get_details($pkey)['key'];

        $keyFile = (string) tempnam(sys_get_temp_dir(), 'mpkey');
        file_put_contents($keyFile, $privatePem);

        try {
            $sms = new Sms();
            $sms->signature = 'SIG-RT';
            $sms->service = 'SVC-RT';
            $sms->orderId = 'ORD-RT';
            $sms->encrypt($publicPem);

            $decoded = RequestAbstract::factoryFromEncrypted($sms->getEnvKey(), $sms->getEncData(), $keyFile);

            $this->assertInstanceOf(Sms::class, $decoded);
            $this->assertSame('ORD-RT', $decoded->orderId);
            $this->assertSame('SIG-RT', $decoded->signature);
            $this->assertSame('SVC-RT', $decoded->service);
        } finally {
            @unlink($keyFile);
        }
    }

    private function rc4Available(): bool
    {
        $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($pkey === false) {
            return false;
        }

        $sealed = null;
        $envKeys = null;
        $publicPem = (string) openssl_pkey_get_details($pkey)['key'];

        return @openssl_seal('probe', $sealed, $envKeys, [$publicPem], 'RC4') !== false;
    }
}
