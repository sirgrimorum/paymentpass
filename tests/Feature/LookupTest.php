<?php

namespace Sirgrimorum\PaymentPass\Tests\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use Sirgrimorum\PaymentPass\Tests\TestCase;
use Sirgrimorum\PaymentPass\PaymentPassHandler;
use Sirgrimorum\PaymentPass\Models\PaymentPass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

#[CoversClass(PaymentPassHandler::class)]
class LookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('sirgrimorum.paymentpass', [
            'available_services' => ['testservice'],
            'production' => true,
            'services_production' => [
                'testservice' => [
                    'type' => 'normal',
                    'referenceCode' => [
                        'type'       => 'auto',
                        'fields'     => [],
                        'separator'  => '-',
                        'encryption' => 'md5',
                        'send'       => false,
                    ],
                ],
            ],
            'services_test' => [],
        ]);
    }

    /** Create a PaymentPass bypassing mass-assignment protection. */
    private function makePayment(array $attrs): PaymentPass
    {
        $payment = new PaymentPass();
        foreach ($attrs as $key => $value) {
            $payment->{$key} = $value;
        }
        $payment->save();
        return $payment;
    }

    public function test_getByReferencia_with_matching_code_returns_correct_payment(): void
    {
        $payment = $this->makePayment([
            'referenceCode' => 'mycode123',
            'process_id'    => 1,
            'state'         => 'reg',
        ]);

        $handler = new PaymentPassHandler('testservice');
        $found   = $handler->getByReferencia('mycode123');

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
        $this->assertEquals('mycode123', $found->referenceCode);
    }

    public function test_getByReferencia_with_no_match_returns_null(): void
    {
        $handler = new PaymentPassHandler('testservice');
        $found   = $handler->getByReferencia('nonexistent_code');

        $this->assertNull($found);
    }

    public function test_getByReferencia_finds_payment_via_generated_code(): void
    {
        // Payment has no explicit referenceCode; the handler re-generates it from
        // creation_data + payment id. With empty fields the code is md5(payment_id).
        $payment = $this->makePayment([
            'process_id'    => 42,
            'creation_data' => json_encode(['amount' => 999]),
        ]);
        $expectedCode = md5((string) $payment->id);

        $handler = new PaymentPassHandler('testservice');
        $found   = $handler->getByReferencia($expectedCode);

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
    }

    public function test_getByReferencia_returns_null_when_table_is_empty(): void
    {
        $handler = new PaymentPassHandler('testservice');
        $this->assertNull($handler->getByReferencia(md5('anything')));
    }

    public function test_getById_with_valid_id_returns_correct_model(): void
    {
        $payment = $this->makePayment([
            'referenceCode' => 'abc',
            'process_id'    => 5,
        ]);

        $handler = new PaymentPassHandler('testservice');
        $found   = $handler->getById($payment->id);

        $this->assertNotNull($found);
        $this->assertEquals($payment->id, $found->id);
    }

    public function test_getById_with_invalid_id_returns_null(): void
    {
        $handler = new PaymentPassHandler('testservice');
        $found   = $handler->getById(99999);

        $this->assertNull($found);
    }
}
