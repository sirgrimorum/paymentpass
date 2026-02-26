<?php

namespace Sirgrimorum\PaymentPass\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use Sirgrimorum\PaymentPass\Tests\TestCase;
use Sirgrimorum\PaymentPass\PaymentPassHandler;
use Sirgrimorum\PaymentPass\Models\PaymentPass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

#[CoversClass(PaymentPassHandler::class)]
class HandlerStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('sirgrimorum.paymentpass', [
            'available_services' => ['payu', 'testservice'],
            'services_production' => [
                'payu' => [
                    'type' => 'normal',
                    'action' => 'http://test',
                ],
                'testservice' => [
                    'type' => 'sdk',
                    'referenceCode' => [
                        'type' => 'auto',
                        'fields' => ['__data__amount'],
                        'separator' => '-',
                        'encryption' => 'md5'
                    ]
                ]
            ],
            'services_test' => [],
            'production' => true,
        ]);
    }

    public function test_cargarConfig_loads_config_file_and_buildConfig_merges()
    {
        $handler = new PaymentPassHandler('payu');
        $this->assertNotEmpty($handler->config);
        $this->assertEquals('normal', $handler->config['service']['type']);
    }

    public function test_unknown_service_name_falls_back_to_first_available()
    {
        // Actual behavior: if service not in available_services, it defaults to index 0 (payu)
        $handler = new PaymentPassHandler('unknown_service');
        $this->assertEquals('normal', $handler->config['service']['type']);
    }

    public function test_store_new_payment_creates_record()
    {
        $handler = new PaymentPassHandler('testservice');
        $data = ['amount' => 100, 'user' => 1];

        $payment = $handler->store(123, $data);

        $this->assertNotNull($payment->id);
        $this->assertEquals(123, $payment->process_id);
        // store() saves the model first (assigning an auto-increment id), THEN calls
        // generateResponseCode($data). At that point $this->payment is set, so
        // generateResponseCode starts the hash string with $this->payment->id.
        // With fields=['__data__amount'] → '100', separator='-', and one field only:
        // strHash = payment_id . '' . '100' → md5(payment_id . '100').
        $this->assertEquals(md5($payment->id . '100'), $payment->referenceCode);
        $this->assertJsonStringEqualsJsonString(json_encode($data), $payment->creation_data);
    }

    public function test_store_reuses_payment_record_if_already_set_in_handler()
    {
        $handler = new PaymentPassHandler('testservice');
        $data = ['amount' => 100];
        
        $payment1 = $handler->store(123, $data);
        $payment2 = $handler->store(123, $data);
        
        $this->assertEquals($payment1->id, $payment2->id);
        $this->assertEquals(1, PaymentPass::count());
    }

    public function test_generateResponseCode_is_deterministic()
    {
        $handler = new PaymentPassHandler('testservice');
        $data = ['amount' => 500];
        
        $code1 = $handler->generateResponseCode($data);
        $code2 = $handler->generateResponseCode($data);
        
        $this->assertEquals(md5('500'), $code1);
        $this->assertEquals($code1, $code2);
    }

    public function test_creation_data_stored_as_json()
    {
        $handler = new PaymentPassHandler('testservice');
        $data = ['foo' => 'bar'];
        
        $payment = $handler->store(999, $data);
        
        $this->assertTrue($handler->isJsonString($payment->creation_data));
        $this->assertEquals($data, json_decode($payment->creation_data, true));
    }
}
