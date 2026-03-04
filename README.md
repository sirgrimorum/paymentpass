# PaymentPass

![Latest Version on Packagist](https://img.shields.io/packagist/v/sirgrimorum/paymentpass.svg?style=flat-square)
![PHP Version](https://img.shields.io/packagist/php-v/sirgrimorum/paymentpass.svg?style=flat-square)
![Total Downloads](https://img.shields.io/packagist/dt/sirgrimorum/paymentpass.svg?style=flat-square)
![License](https://img.shields.io/packagist/l/sirgrimorum/paymentpass.svg?style=flat-square)

A payment gateway abstraction layer for Laravel. Configure multiple payment providers (PayU, MercadoPago, and others) through a unified interface — with parameter mapping, signature generation, webhook handling, state management, and PHP callbacks — all driven by config files, no custom gateway code required.

## Features

- **Multi-provider support** — PayU, MercadoPago, and any custom provider via configuration
- **Two integration modes** — redirect forms (`normal`) and SDK-based flows (`sdk`)
- **Declarative parameter mapping** — map your data to payment provider fields using prefix notation; no custom code for each provider
- **Automatic signature / reference generation** — md5, sha1, or sha256 hash generation from configurable field sets
- **Webhook handling** — `confirmation`, `response`, `notification`, `success`, `failure` endpoints registered automatically
- **State management** — transaction records stored in `payment_pass` table with full request/response JSON
- **PHP callbacks** — register closures for `success`, `failure`, `other` outcomes
- **Conditional mapping** — only process webhook fields when conditions are met
- **Tax auto-calculation** — `taxReturnBase` and `tax` computed from percentage + amount config

## Requirements

- PHP >= 8.2
- Laravel >= 9.0
- guzzlehttp/guzzle ^7.0

## Installation

```bash
composer require sirgrimorum/paymentpass
```

### Run migrations

```bash
php artisan migrate
```

Creates the `payment_pass` table.

### Publish configuration

```bash
php artisan vendor:publish --provider="Sirgrimorum\PaymentPass\PaymentPassServiceProvider" --tag=config
```

Publishes:
- `config/sirgrimorum/paymentpass.php` — main config
- `config/sirgrimorum/paymentpass_services/` — per-provider config files

### Publish views (optional)

```bash
php artisan vendor:publish --provider="Sirgrimorum\PaymentPass\PaymentPassServiceProvider" --tag=views
```

## Configuration

### Main config — `config/sirgrimorum/paymentpass.php`

```php
return [
    'route_prefix'      => 'payment',
    'production'        => env('PAYMENT_PRODUCTION', false),
    'available_services' => ['payu'],  // list of active service names

    // Session keys for flash messages
    'status_messages_key' => 'status',
    'error_messages_key'  => 'error',

    // Result view
    'result_template' => 'paymentpass::result',
];
```

### Service config — `config/sirgrimorum/paymentpass_services/payu.php`

Each provider has its own config file:

```php
return [
    'type'   => 'normal',  // 'normal' (form redirect) or 'sdk'
    'action' => 'https://checkout.payulatam.com/ppp-web-gateway-payu/',
    'method' => 'post',

    // Credentials (test / production)
    'merchantId' => env('PAYU_MERCHANT_ID'),
    'accountId'  => env('PAYU_ACCOUNT_ID'),
    'ApiKey'     => env('PAYU_API_KEY'),

    // Map your data to provider fields using prefix notation
    'parameters' => [
        'merchantId'    => '__config_paymentpass__merchantId',
        'accountId'     => '__config_paymentpass__accountId',
        'description'   => '__data__order.description',
        'amount'        => '__data__order.total',
        'currency'      => '__data__order.currency',
        'buyerEmail'    => '__data__user.email',
        'buyerFullName' => '__data__user.name',
        'tax'           => '__auto__tax|19,__data__order.total,2',
        'taxReturnBase' => '__auto__taxReturnBase|19,__data__order.total,2',
        'language'      => '__trans__app.locale',
        'responseUrl'   => '__route__payment.response',
    ],

    // Reference code generation
    'referenceCode' => [
        'send'       => true,
        'field_name' => 'referenceCode',
        'separator'  => '~',
        'fields'     => ['merchantId', '__data__order.id'],
        'encryption' => 'md5',
    ],

    // Security signature
    'signature' => [
        'send'       => true,
        'field_name' => 'signature',
        'separator'  => '~',
        'fields'     => ['ApiKey', 'merchantId', 'referenceCode', 'amount', 'currency'],
        'encryption' => 'md5',
    ],

    // Webhook handlers
    'webhooks' => [
        'confirmation' => [
            'url'            => '__route__payment.webhook.confirmation',
            'url_field_name' => 'confirmationUrl',
            'field_mapping'  => [
                'state'          => 'state_pol',
                'payment_method' => 'payment_method_name',
                'reference'      => 'reference_sale',
                'response_data'  => '*',  // store full payload
            ],
        ],
        'response' => [
            'url'            => '__route__payment.webhook.response',
            'url_field_name' => 'responseUrl',
            'field_mapping'  => [
                'state'   => 'transactionState',
                'reference' => 'referenceCode',
            ],
            'if' => [
                'transactionState' => ['4', '6', '7'],  // only map on these states
            ],
        ],
    ],

    // State code mapping
    'state_codes' => [
        'success' => ['4', 'APPROVED'],
        'failure' => ['6', '5', 'DECLINED', 'ERROR'],
        'pending' => ['7', 'PENDING'],
    ],

    // PHP callbacks on outcome
    'callbacks' => [
        'success' => function ($paymentPass, $data) {
            // Mark order as paid, send confirmation email, etc.
            Order::find($paymentPass->process_id)?->markPaid();
        },
        'failure' => function ($paymentPass, $data) {
            // Log failed attempt, notify user
        },
        'other' => function ($paymentPass, $data) {
            // Handle pending state
        },
    ],
];
```

## Parameter Prefix Reference

| Prefix | Resolves to |
|--------|-------------|
| `__config_paymentpass__key` | Value from the service config array |
| `__data__dotted.key` | Value from the `$data` array passed to `store()` |
| `__trans__key` | `trans('key')` |
| `__trans_article__scope.key` | TransArticles content |
| `__asset__path` | `asset('path')` |
| `__route__name` | `route('name')` |
| `__url__path` | `url('path')` |
| `__auto__tax\|pct,amount,dec` | Calculates `amount * pct/100` |
| `__auto__taxReturnBase\|pct,amount,dec` | Calculates `amount / (1 + pct/100)` |

## Usage

### 1. Store a payment record

```php
use Sirgrimorum\PaymentPass\PaymentPassHandler;

$handler = new PaymentPassHandler('payu');

$payment = $handler->store($orderId, [
    'order' => [
        'id'          => $order->id,
        'description' => $order->description,
        'total'       => $order->total,
        'currency'    => 'COP',
    ],
    'user' => [
        'email' => $user->email,
        'name'  => $user->name,
    ],
]);
```

### 2. Render the payment form

```blade
{{-- PaymentPass generates the redirect form with all mapped + signed parameters --}}
{!! $payment->getForm() !!}
```

### 3. Handle webhooks

Routes are registered automatically:

```
POST /payment/response/{service}/{responseType}
GET  /payment/response/{service}/{responseType}
```

The `CrudController`-style `handleResponse()` method processes the incoming webhook, maps fields, evaluates conditions, executes callbacks, and updates the `payment_pass` record.

### 4. Retrieve a transaction

```php
$payment = $handler->getByReferencia($referenceCode);
$payment = $handler->getById($paymentId);

echo $payment->state;        // 'success', 'failure', 'pending'
echo $payment->response_data; // JSON of full gateway response
```

## API Reference

### `new PaymentPassHandler()`

```php
new PaymentPassHandler(
    string $service = '',  // Service name (must be in 'available_services')
    mixed  $config  = ''   // Optional config override
)
```

### `store()`

```php
$handler->store(
    int   $process_id,  // Your order/process ID
    array $data,        // Data passed to parameter prefixes
    string $type = ''   // Optional transaction type label
): PaymentPass
```

Creates or updates a `PaymentPass` record and evaluates all parameter mappings.

### `getByReferencia()`

```php
$handler->getByReferencia(string $referenceCode): ?PaymentPass
```

### `getById()`

```php
$handler->getById(int $id): ?PaymentPass
```

### `handleResponse()`

```php
$handler->handleResponse(
    \Illuminate\Http\Request $request,
    string $service,
    string $responseType  // 'confirmation', 'response', 'success', 'failure', 'notification'
): \Illuminate\View\View
```

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
