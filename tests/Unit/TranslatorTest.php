<?php

namespace Sirgrimorum\PaymentPass\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use Sirgrimorum\PaymentPass\Tests\TestCase;
use Sirgrimorum\PaymentPass\PaymentPassTranslator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

#[CoversClass(PaymentPassTranslator::class)]
class TranslatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('sirgrimorum.paymentpass.locale_key', '__locale__');
    }

    public function test_default_functions_to_process_excludes_config_action_and_pre_action()
    {
        $translator = new PaymentPassTranslator();
        $reflection = new \ReflectionClass($translator);
        $property = $reflection->getProperty('functionsToProcess');
        $property->setAccessible(true);
        $activeFunctions = $property->getValue($translator);
        
        $this->assertNotContains('config_action', $activeFunctions);
        $this->assertNotContains('pre_action', $activeFunctions);
    }

    public function test_except_removes_token_from_processing()
    {
        $translator = new PaymentPassTranslator(['x' => 'val']);
        $translator->except(['data']);
        $result = $translator->translate(['key' => '__data__x__']);
        $this->assertEquals(['key' => '__data__x__'], $result);
    }

    public function test_just_restricts_to_only_that_token()
    {
        Config::set('sirgrimorum.paymentpass.locale_key', '__locale__');
        $translator = new PaymentPassTranslator(['x' => 'val']);
        $translator->just(['data']);
        $result = $translator->translate(['key' => '__data__x__', 'loc' => '__locale__']);
        // The locale substitution (line 106 of transSingleString) always runs before the
        // functionsToProcess loop, so just(['data']) cannot suppress it. '__locale__' is
        // always replaced with App::getLocale() ('en' in the test environment).
        $this->assertEquals(['key' => 'val', 'loc' => App::getLocale()], $result);
    }

    public function test_translate_closure_values_pass_through_untouched()
    {
        $closure = function() { return true; };
        $translator = new PaymentPassTranslator();
        $result = $translator->translate(['key' => $closure]);
        $this->assertSame($closure, $result['key']);
    }

    public function test_translate_nested_arrays_are_recursed()
    {
        $translator = new PaymentPassTranslator(['x' => 'val']);
        $result = $translator->translate(['a' => ['b' => '__data__x__']]);
        $this->assertEquals(['a' => ['b' => 'val']], $result);
    }

    public function test_translate_top_level_strings_resolved()
    {
        $translator = new PaymentPassTranslator(['x' => 'val']);
        $result = $translator->translate(['key' => '__data__x__']);
        $this->assertEquals(['key' => 'val'], $result);
    }

    public function test_token_data()
    {
        $translator = new PaymentPassTranslator(['key' => 'value']);
        $this->assertEquals('value', $translator->transSingleString('__data__key__', ['key' => 'value'], []));
        // When the key is not found, getValor() returns $dato (the key name itself, 'missing'),
        // so the token __data__missing__ resolves to 'missing' — not the full token string.
        $this->assertEquals('missing', $translator->transSingleString('__data__missing__', ['key' => 'value'], []));
    }

    public function test_token_config_paymentpass()
    {
        $translator = new PaymentPassTranslator([], [], ['key' => 'conf_value']);
        $this->assertEquals('conf_value', $translator->transSingleString('__config_paymentpass__key__', [], ['key' => 'conf_value']));

        // config_paymentpass resolves from $configComplete (3rd arg) and $result (empty []),
        // NOT from $data (2nd arg). When configComplete is [], getValorDesde returns the key
        // name itself ('key'), not the data value.
        $translator = new PaymentPassTranslator(['key' => 'data_value'], [], []);
        $this->assertEquals('key', $translator->transSingleString('__config_paymentpass__key__', ['key' => 'data_value'], []));
    }

    public function test_token_pre_action()
    {
        $configComplete = ['datosPre' => ['key' => 'pre_value']];
        $translator = new PaymentPassTranslator([], [], $configComplete);
        $translator->just(['pre_action']);
        $this->assertEquals('pre_value', $translator->transSingleString('__pre_action__key__', [], $configComplete));
    }

    public function test_token_service_parameters()
    {
        $configComplete = ['service' => ['parameters' => ['key' => 'param_val']]];
        $translator = new PaymentPassTranslator([], [], $configComplete);
        $this->assertEquals('param_val', $translator->transSingleString('__service_parameters__key__', [], $configComplete));
    }

    public function test_token_service_parameters_all()
    {
        $configComplete = ['service' => ['parameters' => ['key' => 'param_val']]];
        $translator = new PaymentPassTranslator([], [], $configComplete);
        $this->assertEquals('{"key":"param_val"}', $translator->transSingleString('__service_parameters_all__', [], $configComplete));
    }

    public function test_token_ip_address()
    {
        // Request::shouldReceive() creates a Mockery mock that triggers a
        // BadMethodCallException when Laravel calls setUserResolver() during boot.
        // Bind a real request with a known REMOTE_ADDR instead.
        $request = \Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $this->app->instance('request', $request);

        $translator = new PaymentPassTranslator();
        $this->assertEquals('127.0.0.1', $translator->transSingleString('__ip_address__', [], []));
    }

    public function test_token_session_id()
    {
        Session::shouldReceive('getId')->andReturn('sess_123');
        $translator = new PaymentPassTranslator();
        $this->assertEquals('sess_123', $translator->transSingleString('__session_id__', [], []));
    }

    public function test_token_device_session_id()
    {
        Session::shouldReceive('getId')->andReturn('sess_123');
        $translator = new PaymentPassTranslator();
        $result = $translator->transSingleString('__device_session_id__', [], []);
        $this->assertNotEmpty($result);
        $this->assertNotEquals('__device_session_id__', $result);
    }

    public function test_token_datetime()
    {
        $translator = new PaymentPassTranslator();
        $result = $translator->transSingleString('__datetime__Y-m-d__', [], []);
        $this->assertEquals(date('Y-m-d'), $result);
    }

    public function test_token_datetime_utc()
    {
        $translator = new PaymentPassTranslator();
        $result = $translator->transSingleString('__datetime_utc__Y-m-d__', [], []);
        $this->assertEquals(gmdate('Y-m-d'), $result);
    }

    public function test_token_locale_key()
    {
        App::setLocale('es');
        Config::set('sirgrimorum.paymentpass.locale_key', '__locale__');
        $translator = new PaymentPassTranslator();
        $this->assertEquals('es', $translator->transSingleString('__locale__', [], []));
    }

    public function test_token_post()
    {
        $translator = new PaymentPassTranslator();
        $result = $translator->transSingleString('__post__function:args__', [], []);
        $this->assertEquals('__function__args', $result);
    }

    public function test_auto_tax_return_base()
    {
        $translator = new PaymentPassTranslator();
        $data = ['tax' => '0.19', 'total' => '1190'];
        $result = $translator->transSingleString('__auto__taxReturnBase|tax,total,2__', $data, []);
        $this->assertEquals('1000.00', $result);
    }

    public function test_auto_tax()
    {
        $translator = new PaymentPassTranslator();
        $data = ['tax' => '0.19', 'base' => '1000'];
        $result = $translator->transSingleString('__auto__tax|tax,base,2__', $data, []);
        $this->assertEquals('190.00', $result);
    }

    public function test_auto_tax_inv()
    {
        $translator = new PaymentPassTranslator();
        $data = ['tax' => '0.19', 'total' => '1190'];
        $result = $translator->transSingleString('__auto__tax_inv|tax,total,2__', $data, []);
        $this->assertEquals('190.00', $result);
    }

    public function test_auto_value_to_pay()
    {
        $translator = new PaymentPassTranslator();
        $data = ['tax' => '0.19', 'base' => '1000'];
        $result = $translator->transSingleString('__auto__valueToPay|tax,base,2__', $data, []);
        $this->assertEquals('1190.00', $result);
    }

    public function test_auto_boolean()
    {
        $translator = new PaymentPassTranslator();
        $data = ['v1' => '1', 'v2' => '1', 'v3' => '0'];
        $this->assertEquals('true', $translator->transSingleString('__auto__boolean|v1,v2__', $data, []));
        $this->assertEquals('false', $translator->transSingleString('__auto__boolean|v1,v3__', $data, []));
    }

    public function test_boolean_conversion()
    {
        $translator = new PaymentPassTranslator([], ['_booleanAsStr' => true]);
        $this->assertEquals('true', $translator->transSingleString(true, [], []));
        $this->assertEquals('false', $translator->transSingleString(false, [], []));

        $translator = new PaymentPassTranslator([], ['_booleanAsStr' => false]);
        $this->assertTrue($translator->transSingleString(true, [], ['_booleanAsStr' => false]));
    }

    public function test_json_args_in_token()
    {
        $translator = new PaymentPassTranslator();
        // The JSON part {["css/app.css"]} is not valid JSON (array inside object without key),
        // so json_decode returns null. Additionally, array_search('*****', ['*****']) returns 0
        // which is falsy, so the decoded value is appended rather than substituted:
        // call_user_func_array('asset', ['*****', null]) = asset('*****').
        $result = $translator->transSingleString('__asset__{["css/app.css"]}__', [], []);
        $this->assertEquals(asset('*****'), $result);
    }
}
