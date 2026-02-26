<?php

namespace Sirgrimorum\PaymentPass\Tests\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Sirgrimorum\PaymentPass\PaymentPassHandler;
use Sirgrimorum\PaymentPass\Tests\Fixtures\StubSdkClass;
use Sirgrimorum\PaymentPass\Tests\TestCase;

/**
 * Tests for PaymentPassHandler::execAction() via the public action() wrapper — §1.5.
 *
 * The service config intentionally has NO webhooks so that actualizarParametros()
 * never calls route(), keeping tests self-contained.
 */
#[CoversClass(PaymentPassHandler::class)]
class ExecActionTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /** Build a minimal config around a given set of service actions. */
    private function configWith(array $actions): array
    {
        return [
            'available_services'  => ['testservice'],
            'production'          => true,
            'services_production' => [
                'testservice' => [
                    'type'          => 'normal',
                    'referenceCode' => [
                        'type'       => 'auto',
                        'fields'     => [],
                        'encryption' => 'md5',
                        'send'       => false,
                    ],
                    'actions' => $actions,
                ],
            ],
            'services_test' => [],
        ];
    }

    // ---------------------------------------------------------------------------
    // Guards
    // ---------------------------------------------------------------------------

    public function test_action_not_found_returns_default(): void
    {
        Config::set('sirgrimorum.paymentpass', $this->configWith([]));
        $handler = new PaymentPassHandler('testservice');
        $result  = $handler->action('does_not_exist', [], 'my_default');

        $this->assertEquals('my_default', $result);
    }

    public function test_conditionsFunction_false_returns_default(): void
    {
        Config::set('sirgrimorum.paymentpass', $this->configWith([
            'guarded_action' => [
                'type'   => 'http',
                'action' => 'https://api.example.com',
                'method' => 'post',
                'if'     => [['value1' => 'a', 'value2' => 'b', 'condition' => '=']],
            ],
        ]));
        $handler = new PaymentPassHandler('testservice');
        $result  = $handler->action('guarded_action', [], ['fallback' => true]);

        $this->assertEquals(['fallback' => true], $result);
    }

    // ---------------------------------------------------------------------------
    // §1.5 — action type: normal
    // ---------------------------------------------------------------------------

    public function test_normal_action_wantsJson_true_returns_json_with_redirect_info(): void
    {
        Config::set('sirgrimorum.paymentpass', $this->configWith([
            'redirect_action' => [
                'type'   => 'normal',
                'action' => 'https://pay.example.com?amount=100',
                'method' => 'url',
            ],
        ]));

        // Make the container's request report wantsJson()
        $req = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->app->instance('request', $req);

        $handler  = new PaymentPassHandler('testservice');
        $response = $handler->action('redirect_action', []);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData();
        $this->assertEquals('https://pay.example.com?amount=100', $data->redirect->action);
        $this->assertEquals('url', $data->redirect->method);
    }

    public function test_normal_action_wantsJson_false_returns_view(): void
    {
        Config::set('sirgrimorum.paymentpass', $this->configWith([
            'redirect_action' => [
                'type'   => 'normal',
                'action' => 'https://pay.example.com',
                'method' => 'post',
            ],
        ]));

        $req = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);
        $this->app->instance('request', $req);

        $handler = new PaymentPassHandler('testservice');
        $result  = $handler->action('redirect_action', []);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('paymentpass::redirect', $result->getName());
    }

    // ---------------------------------------------------------------------------
    // §1.5 — action type: http — authentication variants
    // ---------------------------------------------------------------------------

    public function test_http_action_no_auth_successful_response(): void
    {
        Http::fake(['https://api.example.com/*' => Http::response(['status' => 'ok'], 200)]);

        Config::set('sirgrimorum.paymentpass', $this->configWith([
            'plain_http' => [
                'type'            => 'http',
                'action'          => 'https://api.example.com/endpoint',
                'method'          => 'post',
                'call_parameters' => ['foo' => 'bar'],
            ],
        ]));

        $handler = new PaymentPassHandler('testservice');
        $result  = $handler->action('plain_http', []);

        $this->assertEquals(['status' => 'ok'], $result);
    }

    public function test_http_action_basic_auth_sends_authorization_header(): void
    {
        Http::fake(['https://api.example.com/*' => Http::response(['ok' => true], 200)]);

        Config::set('sirgrimorum.paymentpass', $this->configWith([
            'basic_auth_http' => [
                'type'            => 'http',
                'action'          => 'https://api.example.com/secure',
                'method'          => 'post',
                'call_parameters' => [],
                'authentication'  => ['type' => 'basic', 'user' => 'myuser', 'secret' => 'mypass'],
            ],
        ]));

        $handler = new PaymentPassHandler('testservice');
        $handler->action('basic_auth_http', []);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization')
                && str_starts_with($request->header('Authorization')[0], 'Basic ');
        });
    }

    public function test_http_action_token_auth_sends_bearer_header(): void
    {
        Http::fake(['https://api.example.com/*' => Http::response(['ok' => true], 200)]);

        Config::set('sirgrimorum.paymentpass', $this->configWith([
            'token_auth_http' => [
                'type'            => 'http',
                'action'          => 'https://api.example.com/token',
                'method'          => 'get',
                'call_parameters' => [],
                'authentication'  => ['type' => 'token', 'token' => 'mytoken123'],
            ],
        ]));

        $handler = new PaymentPassHandler('testservice');
        $handler->action('token_auth_http', []);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization')
                && str_starts_with($request->header('Authorization')[0], 'Bearer ');
        });
    }

    public function test_http_action_aws_sign_v4_authorization_header_is_non_empty_and_correct_format(): void
    {
        // Test the private getHeadersForAWSSign() via reflection.
        // The HTTP-sending path is already proven by the basic/token auth tests above;
        // what's unique to aws_sign_v4 is the header it generates.
        $actionConfig = [
            'type'   => 'http',
            'action' => 'https://execute-api.us-east-1.amazonaws.com/prod/resource',
            'method' => 'post',
            'authentication' => [
                'type'         => 'aws_sign_v4',
                'key'          => 'AKIAIOSFODNN7EXAMPLE',
                'secret'       => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
                'region'       => 'us-east-1',
                'service_name' => 'execute-api',
            ],
        ];

        Config::set('sirgrimorum.paymentpass', $this->configWith(['dummy' => $actionConfig]));
        $handler = new PaymentPassHandler('testservice');

        // getHeadersForAWSSign receives a PendingRequest but doesn't call any
        // methods on it (the parameter is only type-hinted). We supply a real one.
        $pendingRequest = Http::withOptions([]);

        $method = new \ReflectionMethod($handler, 'getHeadersForAWSSign');
        $method->setAccessible(true);
        $headers = $method->invoke($handler, $pendingRequest, ['data' => 'value'], $actionConfig);

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('authorization', $headers);
        $this->assertNotEmpty($headers['authorization']);
        $this->assertStringStartsWith('AWS4-HMAC-SHA256 ', $headers['authorization']);
        $this->assertArrayHasKey('x-amz-date', $headers);
    }

    public function test_http_action_500_response_returns_default_with_error_key(): void
    {
        Http::fake(['*' => Http::response(null, 500)]);

        Config::set('sirgrimorum.paymentpass', $this->configWith([
            'failing_http' => [
                'type'            => 'http',
                'action'          => 'https://api.example.com/fail',
                'method'          => 'post',
                'call_parameters' => [],
            ],
        ]));

        $handler = new PaymentPassHandler('testservice');
        $result  = $handler->action('failing_http', [], []);  // default is array so error key is set

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_http_action_with_field_name_mapping(): void
    {
        Http::fake(['*' => Http::response(['token' => 'abc123', 'expires' => 3600], 200)]);

        Config::set('sirgrimorum.paymentpass', $this->configWith([
            'mapped_http' => [
                'type'            => 'http',
                'action'          => 'https://api.example.com/auth',
                'method'          => 'post',
                'call_parameters' => [],
                'field_name'      => 'authResult',
            ],
        ]));

        $handler = new PaymentPassHandler('testservice');
        $result  = $handler->action('mapped_http', []);

        // mapearRespuesta with preFieldsName="" stores the response directly at
        // field_name key in the result array (not nested under call_parameters).
        $this->assertIsArray($result);
        $this->assertArrayHasKey('authResult', $result);
        $this->assertEquals(['token' => 'abc123', 'expires' => 3600], $result['authResult']);
    }

    // ---------------------------------------------------------------------------
    // §1.5 — action type: sdk
    // ---------------------------------------------------------------------------

    public function test_sdk_action_call_type_function_returns_method_result(): void
    {
        Config::set('sirgrimorum.paymentpass', $this->configWith([
            'sdk_func' => [
                'type'            => 'sdk',
                'class'           => StubSdkClass::class,
                'call_type'       => 'function',
                'name'            => 'compute',
                'call_parameters' => null,
            ],
        ]));

        $handler = new PaymentPassHandler('testservice');
        // action() always uses con_mapearRespuesta=true, but with no field_name
        // mapearRespuesta passes the raw result through unchanged.
        $result  = $handler->action('sdk_func', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('compute', $result);
        $this->assertEquals('function_result', $result['compute']);
    }

    public function test_sdk_action_call_type_static_returns_static_result(): void
    {
        Config::set('sirgrimorum.paymentpass', $this->configWith([
            'sdk_static' => [
                'type'            => 'sdk',
                'class'           => StubSdkClass::class,
                'call_type'       => 'static',
                'name'            => 'staticCompute',
                'call_parameters' => null,
            ],
        ]));

        $handler = new PaymentPassHandler('testservice');
        $result  = $handler->action('sdk_static', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('staticCompute', $result);
        $this->assertEquals('static_result', $result['staticCompute']);
    }

    public function test_sdk_action_call_type_attribute_returns_attribute_value(): void
    {
        Config::set('sirgrimorum.paymentpass', $this->configWith([
            'sdk_attr' => [
                'type'      => 'sdk',
                'class'     => StubSdkClass::class,
                'call_type' => 'attribute',
                'name'      => 'myAttribute',
            ],
        ]));

        $handler = new PaymentPassHandler('testservice');
        $result  = $handler->action('sdk_attr', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('myAttribute', $result);
        $this->assertEquals('attribute_value', $result['myAttribute']);
    }
}
