# NETOPIA Payments API

Mirroring the [MobilPay](https://github.com/mobilpay/PHP_CARD) library with Composer PSR-4 autoloading.

## Requirements

- PHP `>= 8.3`
- `ext-openssl`

> **OpenSSL 3 note.** OpenSSL 3.0 removed RC4 from the default provider. The
> library detects this and seals payloads with `aes-256-cbc` instead, which
> requires an **initialization vector (IV)**. The cipher and IV must be relayed
> to the gateway and back from the IPN — see the wiring below. On OpenSSL < 3
> the legacy `RC4` cipher is used and no IV is produced (`getIv()` returns
> `null`).

## Install

```bash
composer require birkof/netopia-mobilpay
```

## Sending a payment request

```php
use Mobilpay\Payment\Request\Card;
use Mobilpay\Payment\Invoice;

$request = new Card();
$request->signature  = 'YOUR-NETOPIA-SIGNATURE';
$request->orderId    = 'ORDER-123';
$request->confirmUrl = 'https://example.com/ipn';     // IPN endpoint (server-to-server)
$request->returnUrl  = 'https://example.com/return';  // browser redirect

$invoice = new Invoice();
$invoice->currency = 'RON';
$invoice->amount   = '49.99';
$invoice->details  = 'Order #123';
$request->invoice  = $invoice;

// Seal the request with NETOPIA's public certificate.
$request->encrypt('/path/to/sandbox.YOUR-SIGNATURE.public.cer');
```

### Posting to the gateway (cipher + IV)

`encrypt()` exposes the sealed envelope through four getters. **All four must be
posted** so the gateway can open the envelope:

```php
$endpoint = 'http://sandboxsecure.mobilpay.ro'; // live: https://secure.mobilpay.ro

$fields = [
    'env_key' => $request->getEnvKey(),
    'data'    => $request->getEncData(),
    'cipher'  => $request->getCipher(), // e.g. "aes-256-cbc" (or "RC4" on OpenSSL < 3)
    'iv'      => $request->getIv(),     // base64 IV; null when the cipher is a stream cipher
];
?>
<form id="mobilpay" method="post" action="<?= htmlspecialchars($endpoint) ?>">
<?php foreach ($fields as $name => $value): ?>
    <?php if ($value !== null): ?>
        <input type="hidden" name="<?= $name ?>" value="<?= htmlspecialchars($value) ?>">
    <?php endif; ?>
<?php endforeach; ?>
</form>
<script>document.getElementById('mobilpay').submit();</script>
```

> Skip the `iv` field when `getIv()` returns `null` (legacy RC4), as shown above.

## Handling the IPN (confirm URL)

NETOPIA posts the encrypted response back to your `confirmUrl`. Forward the
`cipher` and `iv` it sends into `factoryFromEncrypted()` so block-cipher
envelopes decrypt correctly:

```php
use Mobilpay\Payment\Request\RequestAbstract;

$request = RequestAbstract::factoryFromEncrypted(
    $_POST['env_key'],
    $_POST['data'],
    '/path/to/sandbox.YOUR-SIGNATURE.private.key',
    null,                  // private key password, if any
    $_POST['cipher'] ?? null, // forward gateway-provided cipher
    $_POST['iv'] ?? null      // forward gateway-provided IV
);

$notify = $request->objPmNotify;

if ($notify->errorCode == 0) {
    switch ($notify->action) {
        case 'confirmed':         // funds captured
        case 'confirmed_pending':
        case 'paid_pending':
        case 'paid':              // authorized, awaiting capture
        case 'canceled':
        case 'credit':            // refund
            // update your order using $notify->action / $notify->processedAmount
            break;
    }
    $errorType    = RequestAbstract::CONFIRM_ERROR_TYPE_NONE;
    $errorCode    = 0;
    $errorMessage = $notify->action;
} else {
    $errorType    = RequestAbstract::CONFIRM_ERROR_TYPE_TEMPORARY;
    $errorCode    = $notify->errorCode;
    $errorMessage = $notify->errorMessage;
}

// Acknowledge to NETOPIA.
header('Content-Type: application/xml');
echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
echo "<crc error_type=\"{$errorType}\" error_code=\"{$errorCode}\">{$errorMessage}</crc>";
```

## Low-level query-string API

`Mobilpay\Payment\Request::buildAccessParameters()` returns the same values by
reference, including the new cipher/IV outputs:

```php
$cipher = null;
$iv     = null;
$request->buildAccessParameters($publicKey, $envKey, $encData, $cipher, $iv);
// $envKey, $encData, $cipher, $iv are now populated (base64 where applicable)
```

## License

MIT
