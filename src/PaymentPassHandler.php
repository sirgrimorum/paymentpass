<?php

namespace Sirgrimorum\PaymentPass;

use DateTime;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sirgrimorum\PaymentPass\Models\PaymentPass;
use Sirgrimorum\PaymentPass\Traits\RandomStringGenerator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use ReflectionMethod;
use Sirgrimorum\PaymentPass\Jobs\RunCallableAfterPayment;

class PaymentPassHandler
{
    use RandomStringGenerator;

    protected $service;
    public $config;
    public $configSrc;
    protected $payment = null;

    function __construct($service = "", $config = "")
    {
        $this->cargarConfig($service, $config);
        if (!in_array($service, $this->configSrc["available_services"])) {
            $service = $this->configSrc["available_services"][0];
        }
        $this->service = $service;
        $this->config = $this->buildConfig();
    }

    private function cargarConfig($service = "", $config = "")
    {
        if ($config == "" || !is_array($config) || (is_array($config) && !Arr::has($config, "available_services.$service"))) {
            $this->configSrc = config("sirgrimorum.paymentpass");
        } else {
            $this->configSrc = $config;
        }
    }

    /**
     * Get a payment by its ReferenceCode and set it for the handler class.
     * @param string $referencia ReferenceCode to lookfor
     * @return \Sirgrimorum\PaymentPass\Models\PaymentPass
     */
    public function getByReferencia($referencia)
    {
        $este = $this;
        $this->payment = PaymentPass::all()->filter(function ($paymentAux) use ($referencia, $este) {
            if ($paymentAux->referenceCode == $referencia) {
                return true;
            } else {
                if ($este->isJsonString($paymentAux->creation_data)) {
                    $data = json_decode($paymentAux->creation_data, true);
                    if (!is_array($data)) {
                        $data = [];
                    }
                } else {
                    $data = [];
                }
                return ($este->generateResponseCode($data, $paymentAux) == $referencia);
            }
        })->first();
        return $this->payment;
    }

    /**
     * Get a payment by its id and set it for the handler class.
     * @param string $id Id to lookfor
     * @return \Sirgrimorum\PaymentPass\Models\PaymentPass
     */
    public function getById($id)
    {
        $this->payment = PaymentPass::find($id);
        return $this->payment;
    }

    /**
     * Store a transaction request
     * @param int $process_id unsigned, the id of the parent process for the transaction request
     * @param array $data The data information needed to process the store
     * @param string $type Optional The type of the transaction
     * @return \Sirgrimorum\PaymentPass\Models\PaymentPass the saved transaction request
     */
    public function store($process_id, array $data, $type = "")
    {
        if ($this->payment) {
            $payment = $this->payment;
        } else {
            $payment = new PaymentPass();
        }
        if ($type != "") {
            $payment->type = substr($type, 0, 3);
        }
        $payment->process_id = $process_id;
        $payment->save();
        $this->payment = $payment;
        $this->payment->referenceCode = $this->generateResponseCode($data);
        $this->payment->creation_data = json_encode($data);
        $this->payment->save();
        return $this->payment;
    }

    /**
     * Get the status and status code of a given sendedStatus form the config array
     *
     * @param array $curConfig Current service config array
     * @param string $sendedState The status received in the webhook
     * @param string $default Optional The $state in case the $sendedState is not recogniced, default is "failure"
     * @param string $config_camp Optional The camp to look for codes in curConfig array, default for states is 'state_codes'
     * @return array [$state|$default, $stateCode|$sendedState]
     */
    private function getResponseStatus($curConfig, $sendedState, $default = "failure", $config_camp = 'service.state_codes')
    {
        foreach (Arr::get($curConfig, $config_camp ?? 'service.state_codes', []) as $state => $subState) {
            if (is_array($subState)) {
                if (Arr::has($subState, $sendedState)) {
                    $stateCode = Arr::get($subState, $sendedState, "");
                    return [$state, $stateCode];
                } else if (in_array($sendedState, $subState)) {
                    return [$state, $sendedState . "1"];
                }
            } elseif ($subState == $sendedState) {
                return [$state, $sendedState . "0"];
            }
        }
        return [$default, $sendedState];
    }

    /**
     * Handle a response from the payment source. update the payment and show a view with the result.
     *
     * @param string $responseType Name of the response type, options are 'response' and 'confirmation'
     * @param Illuminate\Http\Request $request
     * @param string $service Optional The payment service to use
     * @return \Illuminate\View\View | \Illuminate\Contracts\View\Factory
     */
    public function handleResponse(Request $request, string $service = "", string $responseType)
    {
        $this->cargarConfig($service);
        if (in_array($service, $this->configSrc["available_services"])) {
            $this->service = $service;
            $this->config = $this->buildConfig();
        }
        if ($responseType == "") {
            if ($request->isMethod('get')) {
                $responseType = 'response';
            } else {
                $responseType = 'confirmation';
            }
        } else {
            $responseType = strtolower($responseType);
        }
        $curConfig = $this->config;
        $curConfig = (new PaymentPassTranslator([], $curConfig, $this->config, true))->translate();
        if (!Arr::has($curConfig, "service.webhooks." . $responseType)) {
            $responseType = "error";
        }
        if ($responseType == "error") {
            $error = str_replace([":referenceCode", ":error"], [$request->get(Arr::get($curConfig, "service.webhooks.$responseType.referenceCode")), $request->get("error", "Unknown error")], trans("paymentpass::messages.error"));
            $callbackFunc = Arr::get($curConfig, "service.callbacks.failure", "");
            if (is_callable($callbackFunc)) {
                call_user_func($callbackFunc, $error);
            }
            if ($request->isMethod('get')) {
                Session::flash(Arr::get($curConfig, "error_messages_key"), $error);
            } else {
                return response()->json($error, 400);
            }
            return response()->view(Arr::get($curConfig, "result_template", "paymentpass.result"), [
                'user' => $request->user(),
                'request' => $request,
                'paymentPass' => $this->payment,
                'config' => $curConfig,
            ], 200);
        } else {
            $configResponse = Arr::get($curConfig, "service.webhooks.$responseType");
            if (Arr::get($configResponse, "es_jwt", false)){
                $datos = json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $request->getContent())[1]))), true);
                $request->merge($datos);
            }else{
                $datos = $request->all();
            }
            if (!$this->conditionsFunction(Arr::get($configResponse, "if", []), $curConfig, $datos)) {
                return response()->json('no_aplica', Arr::get($configResponse, "final_response_code", 200));
            }
            $hacerTranslateConPre = false;
            foreach (Arr::get($configResponse, "pre_actions", []) as $refName => $preActionConfig) {
                if (is_int($refName) && is_string($preActionConfig)) {
                    if (Arr::has($curConfig, "service.actions.$preActionConfig")) {
                        $resultadoPre = $this->execAction($preActionConfig, Arr::get($curConfig, "service.actions.$preActionConfig", null), $curConfig, $datos, null, false);
                        $configResponse['datosPre'][$preActionConfig] = $resultadoPre ?? "devolvio null";
                        $hacerTranslateConPre = !$hacerTranslateConPre ? ($resultadoPre !== null) : true;
                    } else {
                        $configResponse['datosPre'][$preActionConfig] = null;
                    }
                } elseif (is_string($refName) && is_array($preActionConfig)) {
                    $resultadoPre = $this->execAction($refName, $preActionConfig, $curConfig, $datos, null, false);
                    $configResponse['datosPre'][$refName] = $resultadoPre;
                    $hacerTranslateConPre = !$hacerTranslateConPre ? ($resultadoPre !== null) : true;
                }
            }
            if ($hacerTranslateConPre) {
                $configResponse = (new PaymentPassTranslator($datos, $configResponse, $configResponse))->just(['pre_action'])->translate();
            }
            $referenceCode = $this->getResponseParameter(Arr::get($configResponse, "referenceCode", ""), $curConfig, $datos);
            $payment = $this->getByReferencia($referenceCode);
            $this->lanzarDump([
                'prueba' => ["datos" => $datos, "referenceCode" => $referenceCode, "Payment" => $this->payment],
            ]);
            if (!$payment) {
                $noexiste = true;
            } else {
                $noexiste = false;
            }
            if ($payment || (!Arr::get($configResponse, "fail_not_found", true)) || (!Arr::get($curConfig, "production", false))) {
                if ($noexiste) {
                    $this->payment = new PaymentPass();
                } else {
                    if (isset($this->payment->creation_data) && $this->isJsonString($this->payment->creation_data)) {
                        $curConfig = (new PaymentPassTranslator(json_decode($this->payment->creation_data, true), $curConfig, $this->config, false))->translate();
                    }
                }
                if (!Arr::get($curConfig, "production", false)) {
                    $datos['_responseType'] = $responseType;
                    $datos['_service'] = $this->service;
                }
                $state = "";
                if (Arr::get($configResponse, "state", "") != "_notthistime") {
                    $state = $this->getResponseParameter(Arr::get($configResponse, "state", ""), $curConfig, $datos);
                    [$state, $stateAux] = $this->getResponseStatus($curConfig, $state);
                    if (strlen($stateAux) > 3) {
                        $stateAux = substr($stateAux, 0, 3);
                    }
                    $this->payment->state = $stateAux;
                }
                if (Arr::get($configResponse, "type", "") != "_notthistime") {
                    $typePayment = $this->getResponseParameter(Arr::get($configResponse, "type", ""), $curConfig, $datos);
                    [$typePayment, $typeAux] = $this->getResponseStatus($curConfig, $typePayment, $responseType, 'service.type_codes');
                    if (strlen($typePayment) > 3) {
                        $typePayment = substr($typePayment, 0, 3);
                    }
                    $this->payment->type = $typePayment;
                }
                if (Arr::get($configResponse, "payment_method", "_notthistime") != "_notthistime") {
                    $this->payment->payment_method = $this->getResponseParameter(Arr::get($configResponse, "payment_method", ""), $curConfig, $datos);
                }
                if (Arr::get($configResponse, "reference", "_notthistime") != "_notthistime") {
                    $this->payment->reference = $this->getResponseParameter(Arr::get($configResponse, "reference", ""), $curConfig, $datos);
                }
                if (Arr::get($configResponse, "response", "_notthistime") != "_notthistime") {
                    $this->payment->response = $this->getResponseParameter(Arr::get($configResponse, "response", ""), $curConfig, $datos);
                }
                if (Arr::get($configResponse, "payment_state", "_notthistime") != "_notthistime") {
                    $this->payment->payment_state = $this->getResponseParameter(Arr::get($configResponse, "payment_state", ""), $curConfig, $datos);
                }
                if (Arr::get($configResponse, "save_data", "__all__") == "__all__" || !is_array(Arr::get($configResponse, "save_data"))) {
                    $save_data = $datos;
                } else {
                    $save_data = Arr::only($datos, Arr::get($configResponse, "save_data"));
                }
                $save_data = json_encode(array_merge($save_data, ['datosPre' => Arr::get($configResponse, "datosPre", [])]));
                if (!$request->isMethod('get')) {
                    $this->payment->response_date = now();
                    if (Arr::get($configResponse, "save_data", "_notthistime") != "_notthistime") {
                        if ($this->payment->response_data != null) {
                            $auxData = json_decode($this->payment->response_data, true);
                            if (is_array($auxData)) {
                                $save_data = json_encode(array_merge($auxData,  json_decode($save_data, true)));
                            }
                        }
                        $this->payment->response_data = $save_data;
                    }
                } else {
                    $this->payment->confirmation_date = now();
                    if (Arr::get($configResponse, "save_data", "_notthistime") != "_notthistime") {
                        if ($this->payment->confirmation_data != null) {
                            $auxData = json_decode($this->payment->confirmation_data, true);
                            if (is_array($auxData)) {
                                $save_data = json_encode(array_merge($auxData,  json_decode($save_data, true)));
                            }
                        }
                        $this->payment->confirmation_data = $save_data;
                    }
                }
                if (!$noexiste) {
                    $this->payment->save();
                } else {
                    if (Arr::get($configResponse, "create_not_found", Arr::get($curConfig, "saveAll", false))) {
                        $this->payment->save();
                    }
                }
                $this->lanzarDump([
                    "after save" => ["datos" => $datos, "Payment" => $this->payment]
                ]);
                if (Arr::get($configResponse, "state", "") != "_notthistime") {
                    if (Arr::get($curConfig, "queue_callbacks", false)) {
                        RunCallableAfterPayment::dispatch($state, $this->service, $this->payment);
                    } else {
                        $callbackFunc = Arr::get($curConfig, "service.callbacks.$state", Arr::get($curConfig, "service.callbacks.other", ""));
                        if (is_callable($callbackFunc)) {

                            call_user_func($callbackFunc, $this->payment);
                        }
                    }
                }
                if ($request->isMethod('get')) {
                    if (in_array($this->payment->state, Arr::get($curConfig, "service.state_codes.failure"))) {
                        Session::flash(Arr::get($curConfig, "error_messages_key"), str_replace([":referenceCode"], [$this->payment->referenceCode], trans("paymentpass::services.{$this->service}.messages.{$this->payment->state}")));
                    } else {
                        Session::flash(Arr::get($curConfig, "status_messages_key"), str_replace([":referenceCode"], [$this->payment->referenceCode], trans("paymentpass::services.{$this->service}.messages.{$this->payment->state}")));
                    }
                } else {
                    return response()->json($this->payment->payment_state, Arr::get($configResponse, "final_response_code", 201));
                }
            } else {
                if ($request->isMethod('get')) {
                    Session::flash(Arr::get($curConfig, "error_messages_key"), str_replace([":referenceCode"], [$request->get(Arr::get($curConfig, "service.webhooks.$responseType.referenceCode"))], trans("paymentpass::messages.not_found")));
                } else {
                    return response()->json('not_found', Arr::get($configResponse, "fail_not_found_code", 200));
                }
            }
            if ($request->isMethod('get')) {
                return response()->view(Arr::get($curConfig, "result_template", "paymentpass.result"), [
                    'user' => $request->user(),
                    'request' => $request,
                    'paymentPass' => $this->payment,
                    'config' => $curConfig,
                ], Arr::get($configResponse, "final_response_code", 201));
            } else {
                return response()->json('not_found', Arr::get($configResponse, "fail_not_found_code", 200));
            }
        }
    }

    /**
     * Evaluates the conditions of a function given an array of conditions
     * @param array $ifs Conditions
     * @param array $config Configuration array
     * @param array $datos Data to evlauate
     * @return boolean
     */
    private function conditionsFunction($ifs, $config, $datos)
    {
        if (is_array($ifs)) {
            foreach ($ifs as $if) {
                $value1 = Arr::get($if, "value1", "");
                $value1 = $this->translate_parameters($value1, $config, $datos);
                $value2 = Arr::get($if, "value2", "");
                $value2 = $this->translate_parameters($value2, $config, $datos);
                $condition = Arr::get($if, "condition", "=");
                switch ($condition) {
                    case "=":
                        if ($value1 != $value2) {
                            return false;
                        }
                        break;
                    case "!=":
                        if ($value1 == $value2) {
                            return false;
                        }
                        break;
                    case ">":
                        if ($value1 <= $value2) {
                            return false;
                        }
                        break;
                    case "<":
                        if ($value1 >= $value2) {
                            return false;
                        }
                        break;
                    case ">=":
                        if ($value1 < $value2) {
                            return false;
                        }
                        break;
                    case "<=":
                        if ($value1 > $value2) {
                            return false;
                        }
                        break;
                    case "in_array":
                        if (is_array($value2)) {
                            $valueArray = $value2;
                        } else {
                            $valueArray = [$value2];
                        }
                        if (!in_array($value1, $valueArray)) {
                            return false;
                        }
                        break;
                    case "is_array":
                        if (!is_array($value1)) {
                            return false;
                        }
                        break;
                    case "is_not_array":
                        if (is_array($value1)) {
                            return false;
                        }
                        break;
                    case "is_int":
                        if (!is_int($value1)) {
                            return false;
                        }
                        break;
                    case "is_not_in":
                        if (is_int($value1)) {
                            return false;
                        }
                        break;
                    case "is_string":
                        if (!is_string($value1)) {
                            return false;
                        }
                        break;
                    case "is_not_string":
                        if (is_string($value1)) {
                            return false;
                        }
                        break;
                    case "is_null":
                        if ($value1 !== null) {
                            return false;
                        }
                        break;
                    case "is_not_null":
                        if ($value1 === null) {
                            return false;
                        }
                        break;
                }
            }
        }
        return true;
    }

    /**
     * Get the parameters from a response
     * @param string $parameter Parameter, could nedd translation
     * @param array $config Configuration array
     * @param arrya $datos Data to evaluate
     * @return string
     */
    private function getResponseParameter($parameter, $config, $datos)
    {
        /* if (stripos($parameter, "__request__") !== false) {
          $parameter = str_replace("__request__", "", $parameter);
          }
          $data = data_get($datos, $parameter, $parameter); */
        $data = $this->translate_parameters($parameter, $config, $datos);
        if (is_array($data) || is_object($data)) {
            return json_encode($data);
        } else {
            return $data;
        }
        return $parameter;
    }

    /**
     * Execute an action from the configuration array, it could be an http request or an SDK call
     *
     * @param string $action The name of the action in the configuration array
     * @param array $data The data information needed to process the action
     * @param mix $default Optional The default value in case someting goes wrong, default is null
     * @return mix The response from the request or the call, or the $default value if something goes wrong
     */
    public function action($action, array $data, $default = null)
    {
        if (Arr::has($this->config, "service.actions.$action")) {
            return $this->execAction($action, Arr::get($this->config, "service.actions.$action", null), $this->config, $data, $default);
        } else {
            if (is_array($default)) {
                data_set($default, "error", [
                    "mensaje" => "Action '$action' no encontrada",
                ]);
            }
        }
        return $default;
    }

    /**
     * Get the headers for AWS Sign v4
     *
     * Thanks to https://stackoverflow.com/users/6906592/pravesh-khatana
     * and https://stackoverflow.com/users/2979377/avik-das
     *
     * in https://stackoverflow.com/a/42816847/9229515
     *
     * @param PendingRequest $request The request so far
     * @param array $parameters The parameters (payload)
     * @param array $actionConfig The configuration array for the action
     * @return array The headers to use
     */
    private function getHeadersForAWSSign(PendingRequest $request, $parameters, $actionConfig)
    {
        $action = $actionConfig['action'];
        $method = Arr::get($actionConfig, 'method', 'post');
        $host = Str::before(Str::after($action, '//'), '/');
        $uri = Str::after(Str::before($action, '?'), $host . '/');
        $json = json_encode($parameters);
        $obj = json_decode($json);
        $secretKey = Arr::get($actionConfig, 'authentication.secret', '');
        $accessKey = Arr::get($actionConfig, 'authentication.key', '');
        $token = Arr::get($actionConfig, 'authentication.token', '');
        if ($token == '') {
            $token = null;
        }
        $region = Arr::get($actionConfig, 'authentication.region', '');
        $service = Arr::get($actionConfig, 'authentication.service_name', '');
        $headers = Arr::get($actionConfig, 'headers', ['content-type' => 'application/json']);
        if (Arr::has($headers, 'Content-Type')) {
            $headers['content-type'] = $headers['Content-Type'];
            unset($headers['Content-Type']);
        }
        if (Arr::has($headers, 'Content-type')) {
            $headers['content-type'] = $headers['Content-type'];
            unset($headers['Content-type']);
        }

        $terminationString = 'aws4_request';
        $algorithm = 'AWS4-HMAC-SHA256';
        $phpAlgorithm = 'sha256';
        $canonicalURI = $uri;
        $canonicalQueryString = '';
        $signedHeaders = 'content-type;host;x-amz-date';
        if (isset($token)) {
            $signedHeaders .= ';x-amz-security-token';
        }

        $currentDateTime = new DateTime('UTC');
        $reqDate = $currentDateTime->format('Ymd');
        $reqDateTime = $currentDateTime->format('Ymd\THis\Z');

        // Create signing key
        $kSecret = $secretKey;
        $kDate = hash_hmac($phpAlgorithm, $reqDate, 'AWS4' . $kSecret, true);
        $kRegion = hash_hmac($phpAlgorithm, $region, $kDate, true);
        $kService = hash_hmac($phpAlgorithm, $service, $kRegion, true);
        $kSigning = hash_hmac($phpAlgorithm, $terminationString, $kService, true);

        // Create canonical headers
        $canonicalHeaders = [];
        $canonicalHeaders[] = 'content-type:' . Arr::get($headers, "content-type", Arr::get($headers, "Content-Type", "application/json"));
        $canonicalHeaders[] = 'host:' . $host;
        $canonicalHeaders[] = 'x-amz-date:' . $reqDateTime;
        if (isset($token)) {
            $canonicalHeaders[] = 'x-amz-security-token:' . $token;
        }
        $canonicalHeadersStr = implode("\n", $canonicalHeaders);

        // Create request payload
        $requestHasedPayload = hash($phpAlgorithm, $json);

        // Create canonical request
        $canonicalRequest = [];
        $canonicalRequest[] = $method;
        $canonicalRequest[] = $canonicalURI;
        $canonicalRequest[] = $canonicalQueryString;
        $canonicalRequest[] = $canonicalHeadersStr . "\n";
        $canonicalRequest[] = $signedHeaders;
        $canonicalRequest[] = $requestHasedPayload;
        $requestCanonicalRequest = implode("\n", $canonicalRequest);
        $requestHasedCanonicalRequest = hash($phpAlgorithm, utf8_encode($requestCanonicalRequest));

        // Create scope
        $credentialScope = [];
        $credentialScope[] = $reqDate;
        $credentialScope[] = $region;
        $credentialScope[] = $service;
        $credentialScope[] = $terminationString;
        $credentialScopeStr = implode('/', $credentialScope);

        // Create string to signing
        $stringToSign = [];
        $stringToSign[] = $algorithm;
        $stringToSign[] = $reqDateTime;
        $stringToSign[] = $credentialScopeStr;
        $stringToSign[] = $requestHasedCanonicalRequest;
        $stringToSignStr = implode("\n", $stringToSign);

        $this->lanzarDump(["llamando awsSing de {$this->service}" => [
            "canonical" => $canonicalRequest,
            "method" => $method,
            "headers" => $headers,
            "uri" => $uri,
            "host" => $host,
            "data" => $parameters,
            "caninicalHeaders" => [$canonicalHeaders, $requestCanonicalRequest],
            "String to Sign" => [$stringToSign, $stringToSignStr],
        ]]);

        // Create signature
        $signature = hash_hmac($phpAlgorithm, $stringToSignStr, $kSigning);

        // Create authorization header
        $authorizationHeader = [];
        $authorizationHeader[] = 'Credential=' . $accessKey . '/' . $credentialScopeStr;
        $authorizationHeader[] = 'SignedHeaders=' . $signedHeaders;
        $authorizationHeader[] = 'Signature=' . ($signature);
        $authorizationHeaderStr = $algorithm . ' ' . implode(', ', $authorizationHeader);


        // Request headers
        //$headers = [];
        $headers = array_merge($headers, [
            'authorization' => $authorizationHeaderStr,
            'content-length' => strlen($json),
            'host' => $host,
            'x-amz-date' => $reqDateTime,
        ]);
        if (isset($token)) {
            $headers[] = 'x-amz-security-token: ' . $token;
        }

        $this->lanzarDump(["headers de awsSing de {$this->service}" => [
            "headers" => $headers,
        ]]);
        return $headers;

        if (isset($obj->method)) {
            $m = explode("|", $obj->method);
            $method = $m[0];
            $uri .= $m[1];
        }

        $secretKey = Arr::get($actionConfig, 'authentication.secret', '');
        $access_key = Arr::get($actionConfig, 'authentication.key', '');
        $token = Arr::get($actionConfig, 'authentication.token', '');
        if ($token == '') {
            $token = null;
        }
        $region = Arr::get($actionConfig, 'authentication.region', '');
        $service = Arr::get($actionConfig, 'authentication.service_name', '');

        $headers = [];

        $alg = 'sha256';
        $date = new DateTime('UTC');
        $dd = $date->format('Ymd\THis\Z');

        $amzdate2 = new DateTime('UTC');
        $amzdate2 = $amzdate2->format('Ymd');
        $amzdate = $dd;

        $algorithm = 'AWS4-HMAC-SHA256';

        if ($parameters == null || empty($parameters)) {
            $param = "";
        } else {
            $param = $json;
            if ($param == "{}") {
                $param = "";
            }
        }

        $requestPayload = strtolower($param);
        $hashedPayload = hash($alg, $requestPayload);

        $canonical_uri = $uri;
        $canonical_querystring = '';

        $canonical_headers = "content-type:" . "application/json" . "\n" . "host:" . $host . "\n" . "x-amz-date:" . $amzdate . "\n" . "x-amz-security-token:" . $token . "\n";
        $signed_headers = 'content-type;host;x-amz-date;x-amz-security-token';
        $canonical_request = "" . $method . "\n" . $canonical_uri . "\n" . $canonical_querystring . "\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $hashedPayload;


        $credential_scope = $amzdate2 . '/' . $region . '/' . $service . '/' . 'aws4_request';
        $string_to_sign  = "" . $algorithm . "\n" . $amzdate . "\n" . $credential_scope . "\n" . hash('sha256', $canonical_request) . "";
        //string_to_sign is the answer..hash('sha256', $canonical_request)//

        $kSecret = 'AWS4' . $secretKey;
        $kDate = hash_hmac($alg, $amzdate2, $kSecret, true);
        $kRegion = hash_hmac($alg, $region, $kDate, true);
        $kService = hash_hmac($alg, $service, $kRegion, true);
        $kSigning = hash_hmac($alg, 'aws4_request', $kService, true);
        $signature = hash_hmac($alg, $string_to_sign, $kSigning);
        $authorization_header = $algorithm . ' ' . 'Credential=' . $access_key . '/' . $credential_scope . ', ' .  'SignedHeaders=' . $signed_headers . ', ' . 'Signature=' . $signature;

        $headers = [
            'content-type' => 'application/json',
            'x-amz-security-token' => $token,
            'x-amz-date' => $amzdate,
            'Authorization' => $authorization_header
        ];
        return $headers;
    }

    /**
     * Execute an action from the configuration array, it could be an http request or an SDK call
     *
     * @param string $action The name of the action in the configuration array
     * @param array $curConfig Current configuration, its updated
     * @param array $actionConfig Configuration of the current action
     * @param array $data The data information needed to process the action
     * @param mix $default Optional The default value in case someting goes wrong, default is null
     * @param boolean $con_preactions Optional If should run pre_actions or not, default true
     * @param boolean $con_mapearRespuesta Optional If should map the response in actionConfig with the 'field_name' field fo the action, default true
     * @return mix The response from the request or the call, or the $default value if something goes wrong
     */
    private function execAction($action, array $actionConfig, array $curConfig, array $data, $default = null, $con_preactions = null, $con_mapearRespuesta = true)
    {
        if ($actionConfig != null && is_array($actionConfig) && count($actionConfig) > 0) {
            $this->actualizarParametros($curConfig, $actionConfig, $data);
            if ($this->conditionsFunction(Arr::get($actionConfig, "if", []), $curConfig, $data) === true) {
                if ($con_preactions === null) {
                    $con_preactions = Arr::get($actionConfig, 'ejecutar_pre_actions', true);
                }
                if ($actionConfig['type'] == "sdk") {
                    if ($con_preactions && Arr::has($curConfig['service'], "pre_actions")) {
                        foreach (Arr::get($curConfig, "service.pre_actions", []) as $refName => $preActionConfig) {
                            $resultadoPre = $this->execAction($refName, $preActionConfig, $curConfig, $data, null, false);
                            $actionConfig['datosPre'][$refName] = $resultadoPre;
                        }
                        $actionConfig = (new PaymentPassTranslator($data, $actionConfig, $actionConfig))->just(['pre_action'])->translate();
                    }
                    $this->lanzarDump(["llamando $action de {$this->service}" => [
                        "actionConfig" => $actionConfig
                    ]], true);
                    $resultadoAction = $this->callSdkFunction($actionConfig, $curConfig, $data);
                    if ($con_mapearRespuesta) {
                        $datosDevolver = [];
                        $this->mapearRespuesta($curConfig, $datosDevolver, $resultadoAction, $data, $actionConfig, "");
                    } else {
                        $datosDevolver = $resultadoAction;
                    }
                    $this->lanzarDump(["resultado $action de {$this->service}" => [
                        "resultadoAction" => $resultadoAction,
                        "datosDevolver" => $datosDevolver
                    ]], true);
                    return $datosDevolver;
                } elseif ($actionConfig['type'] == "http") {
                    if ($con_preactions && Arr::has($curConfig['service'], "pre_actions")) {
                        foreach (Arr::get($curConfig, "service.pre_actions", []) as $refName => $preActionConfig) {
                            $resultadoPre = $this->execAction($refName, $preActionConfig, $curConfig, $data, null, false);
                            $actionConfig['datosPre'][$refName] = $resultadoPre;
                        }
                        $actionConfig = (new PaymentPassTranslator($data, $actionConfig, $actionConfig))->just(['pre_action'])->translate();
                    }
                    if (!Arr::has($curConfig, "_booleanAsStr") && Arr::has($actionConfig, "_booleanAsStr")){
                        $curConfig["_booleanAsStr"] = $actionConfig["_booleanAsStr"];
                    }
                    $callParameters = $this->translate_parameters(Arr::get($actionConfig, 'call_parameters', []), $curConfig, $data);
                    $httpRequest = Http::retry(3, 100);
                    if (count(Arr::get($actionConfig, 'headers', [])) > 0 && Arr::get($actionConfig, 'authentication.type', 'nada') != 'aws_sign_v4') {
                        $httpRequest = $httpRequest->withHeaders($actionConfig['headers']);
                    }
                    if (Arr::get($actionConfig, 'asForm', false) || Arr::get($actionConfig, 'headers.Content-Type', "") == "application/x-www-form-urlencoded") {
                        $httpRequest = $httpRequest->asForm();
                    }
                    if (Arr::get($actionConfig, 'authentication.type', 'nada') == 'basic' && Arr::has($actionConfig, ['authentication.user', 'authentication.secret'])) {
                        $httpRequest = $httpRequest->withBasicAuth(Arr::get($actionConfig, 'authentication.user', ''), Arr::get($actionConfig, 'authentication.secret', ''));
                    } elseif (Arr::get($actionConfig, 'authentication.type', 'nada') == 'digest' && Arr::has($actionConfig, ['authentication.user', 'authentication.secret'])) {
                        $httpRequest = $httpRequest->withDigestAuth(Arr::get($actionConfig, 'authentication.user', ''), Arr::get($actionConfig, 'authentication.secret', ''));
                    } elseif (Arr::get($actionConfig, 'authentication.type', 'nada') == 'token' && Arr::has($actionConfig, 'authentication.token')) {
                        $httpRequest = $httpRequest->withToken(Arr::get($actionConfig, 'authentication.token', ''), Arr::get($actionConfig, 'authentication.token_type', 'Bearer'));
                    } elseif (Arr::get($actionConfig, 'authentication.type', 'nada') == 'aws_sign_v4' && Arr::has($actionConfig, 'authentication.region') && Arr::has($actionConfig, 'authentication.service_name') && Arr::has($actionConfig, 'authentication.key') && Arr::has($actionConfig, 'authentication.secret')) {
                        $httpRequest = $httpRequest->withHeaders($this->getHeadersForAWSSign($httpRequest, $callParameters, $actionConfig));
                    }
                    if (in_array(Arr::get($actionConfig, 'method', ''), ['get', 'post', 'put', 'patch', 'delete']) && Arr::get($actionConfig, 'action', '') != '') {
                        try {
                            $this->lanzarDump(["llamando $action de {$this->service}" => [
                                "actionConfig" => $actionConfig,
                                "callParameters" => $callParameters,
                            ]], true);
                            $response = $httpRequest->{$actionConfig['method']}($actionConfig['action'], $callParameters);
                            if ($response->successful()) {
                                if ($con_mapearRespuesta) {
                                    $datosDevolver = [];
                                    $this->mapearRespuesta($curConfig, $datosDevolver, $response->json(), $data, $actionConfig, "");
                                } else {
                                    $datosDevolver = $response->json();
                                }
                                $this->lanzarDump(["resultado $action de {$this->service}" => [
                                    "response" => $response->json(),
                                    "datosDevolver" => $datosDevolver
                                ]], true);
                                return $datosDevolver;
                            } else {
                                $response->throw();
                            }
                        } catch (RequestException $e) {
                            $this->lanzarDump(["request error en {$action} de {$this->service}" => [
                                "mensaje" => $e->getMessage(),
                                "response" => $e->response->json(),
                                "body" => $e->response->body(),
                                "actionConfig" => $actionConfig,
                            ]]);
                            if (is_array($default)) {
                                data_set($default, "error", [
                                    "mensaje" => $e->getMessage(),
                                    "response" => $e->response->json(),
                                    "body" => $e->response->body(),
                                ]);
                            }
                        } catch (Exception $e) {
                            $this->lanzarDump(["error en http {$action} de {$this->service}" => [
                                "mensaje" => $e->getMessage(),
                                "actionConfig" => $actionConfig,
                            ]]);
                            if (is_array($default)) {
                                data_set($default, "error", [
                                    "mensaje" => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                } elseif ($actionConfig['type'] == "normal") {
                    if (strpos(Arr::get($actionConfig, 'action', '__service_action__sdk_redirect'), '__service_action__')) {
                        $redirectUrl = route("paymentpass::response", ['service' => $this->service, 'responseType' => "error"]);
                        $redirectAction = str_replace('__service_action__', '', Arr::get($actionConfig, 'action', '__service_action__sdk_redirect'));
                        if ($auxRedirect = $this->execAction($redirectAction, Arr::get($curConfig, "service.actions.$redirectAction", null), $curConfig, $data) != null) {
                            $redirectUrl = $auxRedirect;
                        }
                        $actionConfig['action'] = $redirectUrl;
                    }
                    if (!request()->wantsJson()) {
                        return view('paymentpass::redirect', [
                            'config' => $curConfig,
                            'actionConfig' => $actionConfig,
                            'datos' => $data,
                        ]);
                    } else {
                        $result = new \stdClass;
                        $result->redirect = json_decode($this->getJsonView($actionConfig));
                        $result->data = $data;
                        $result->config = json_decode(json_encode($curConfig));
                        return response()->json($result, 200);
                    }
                }
            }
        }
        return $default;
    }

    /**
     * Map a response to load in the call_parameters of an action config
     *
     * Edit $actionConfig call_parameters and then run translateConfig on it
     *
     * @param array $curConfig Current configuration, its updated
     * @param array $actionConfig Configuration of the current action
     * @param mix $result The response to map
     * @param array $data The data information needed to process the update
     * @param array $curActionConfig Optional The configuration of the action executed in order to get the response, in case is diferente from $actionConfig
     * @param string $preFieldsName Optional, the prefix to use in the data_set to save the field_name info in $actionConfig
     *
     */
    private function mapearRespuesta($curConfig, &$actionConfig, $result = null, $data = [], $curActionConfig = null, $preFieldsName = "call_parameters.")
    {
        if (is_string($result) && $this->isJsonString($result)) {
            $result = json_decode($result, true);
        }
        if ($curActionConfig === null) {
            $curActionConfig == $actionConfig;
        }
        $reRevisar = false;
        if (($field_name = Arr::get($curActionConfig, "field_name", false))) {
            if (is_string($field_name)) {
                data_set($actionConfig, $preFieldsName . $field_name, $result);
                $actionConfig = (new PaymentPassTranslator($data, $actionConfig, $curConfig, false, false))->translate();
                $reRevisar = true;
            } elseif (is_array($field_name)) {
                $resultado = $this->subMapear($field_name, $result, $reRevisar);
                if ($preFieldsName != "") {
                    data_set($actionConfig, $preFieldsName, $resultado);
                } elseif (count($actionConfig) == 0) {
                    $actionConfig = $resultado;
                }
            }
        } elseif (count($actionConfig) == 0 && $result != null) {
            $actionConfig = $result;
        }
        if ($reRevisar) {
            $actionConfig = (new PaymentPassTranslator($data, $actionConfig, $curConfig, false, false))->translate($actionConfig);
        }
    }

    private function subMapear($field_name, $result, &$reRevisar)
    {
        if (is_array($field_name) && count($field_name) > 0) {
            $procesado = [];
            foreach ($field_name as $keyField => $valueField) {
                if (is_int($keyField) && is_string($valueField)) {
                    data_set($procesado, $valueField, $result);
                    $reRevisar = true;
                } else {
                    data_set($procesado, $keyField, $this->subMapear($valueField, $result, $reRevisar));
                }
            }
            return $procesado;
        } elseif (is_string($field_name)) {
            $reRevisar = true;
            if ($field_name == "__all__") {
                return $result;
            } elseif (Str::startsWith($field_name, 'boolean|')) {
                $datosParameters = explode(",", str_replace("boolean|", "", $field_name));
                if (count($datosParameters) == 1) {
                    $piece = $this->getValorDesde($result, $datosParameters[0]) == true;
                } elseif (count($datosParameters) == 2) {
                    $piece = $this->getValorDesde($result, $datosParameters[0]) == $this->getValorDesde($result, $datosParameters[1]);
                } else {
                    $piece = false;
                }
                return $piece;
            } elseif (($field_name == 'true' || $field_name == 'false')) {
                return $field_name == 'true';
            } elseif ($field_name !== null) {
                return $this->getValorDesde($result, $field_name);
            }
        }
        $reRevisar = false;
        return $field_name;
    }

    /**
     * Get the value of a field from an array or object
     * @param array|object $data The place to take the field from
     * @param string $field_name The key or property name
     * @return mix The result
     */
    private function getValorDesde($data, $field_name)
    {
        if (is_array($data) && $data !== null) {
            return Arr::get($data, $field_name, $field_name);
        } elseif (is_object($data) && $data !== null) {
            if (property_exists($data, $field_name)) {
                if (is_callable([$data, $field_name])) {
                    return $data->{$field_name}();
                } else {
                    return $data->{$field_name};
                }
            } elseif (strpos($field_name, ".") !== false) {
                return Arr::get((array) $data, $field_name, $field_name);
            }
        } else {
            return $data;
        }
    }

    /**
     * Update the parameters values in a configuration array.
     * Mainly referenceCode, Signature of an action and webhooks urls
     *
     * It updates the parameters $curConfig and $actionConfig
     * @param array $curConfig Current configuration, its updated
     * @param array $actionConfig Configuration of the current action
     * @param array $data The data information needed to process the update
     *
     */
    private function actualizarParametros(&$curConfig, &$actionConfig, $data = [])
    {
        if (!Arr::get($curConfig, 'service.referenceCode.ya_procesado', false)) {
            $curConfig['service']['referenceCode']['value'] = $this->generateResponseCode($data);
            $curConfig['service']['referenceCode']['ya_procesado'] = true;
            $curConfig = (new PaymentPassTranslator($data, $curConfig, $curConfig))->translate();
        }
        foreach (Arr::get($curConfig, "service.webhooks", []) as $responseName => $responseData) {
            $responseUrl = Arr::get($responseData, "url", "");
            if ($responseUrl == "") {
                $curConfig['service']['webhooks'][$responseName]['url'] = route("paymentpass::response", ["service" => $this->service, "responseType" => $responseName]);
            }
            if (Arr::get($curConfig, "service.webhooks." . $responseName . ".url_field_name", "") != "") {
                data_set($curConfig, 'service.parameters.' . Arr::get($curConfig, "service.webhooks." . $responseName . ".url_field_name"), Arr::get($curConfig, "service.webhooks." . $responseName . ".url", ""));
            }
        }
        if ($actionConfig != null) {
            $actionConfig = (new PaymentPassTranslator($data, $actionConfig, $curConfig))->translate();
            if (!Arr::get($actionConfig, 'signature.ya_procesado', false)) {
                if (Arr::get($actionConfig, "signature.active", false)) {
                    $strHash = "";
                    $preHash = "";
                    foreach (Arr::get($actionConfig, "signature.fields", "~") as $parameter) {
                        $strHash .= $preHash . $parameter;
                        $preHash = Arr::get($actionConfig, "signature.separator", "~");
                    }
                    switch (Arr::get($actionConfig, "signature.encryption", "md5")) {
                        case "sha256":
                            $actionConfig['signature']['value'] = Hash::make($strHash);
                            break;
                        case "sha1":
                            $actionConfig['signature']['value'] = sha1($strHash);
                            break;
                        case "base64":
                            $actionConfig['signature']['value'] = base64_encode($strHash);
                            break;
                        default:
                        case "md5":
                            $actionConfig['signature']['value'] = md5($strHash);
                            break;
                    }
                    $actionConfig['signature']['ya_procesado'] = true;
                }
                if (Arr::get($actionConfig, "signature.send", false)) {
                    data_set($actionConfig, 'call_parameters.' . Arr::get($actionConfig, "signature.field_name"), Arr::get($actionConfig, "signature.value", ""));
                }
                $actionConfig = (new PaymentPassTranslator($data, $actionConfig, $actionConfig))->except(['pre_action'])->translate();
            }
            if (Arr::get($actionConfig, "signature.send", false)) {
                data_set($actionConfig, 'call_parameters.' . Arr::get($actionConfig, "signature.field_name"), Arr::get($actionConfig, "signature.value", ""));
            }
        }
        if (Arr::get($curConfig, "service.referenceCode.send", false)) {
            data_set($curConfig, 'service.parameters.' . Arr::get($curConfig, "service.referenceCode.field_name"), Arr::get($curConfig, "service.referenceCode.value", ""));
        }
        $this->lanzarDump(['Last Configuration' => $curConfig]);
    }

    /**
     * Call a view with a redirection to the payment website.
     * Uses the 'redirect' action of the service configuration array
     *
     * @param array $data The data information needed to process the redirection
     * @return \Illuminate\View\View | \Illuminate\Contracts\View\Factory
     */
    public function redirect(array $data)
    {
        $curConfig = $this->config;
        $redirectResult = $this->execAction('redirect', Arr::get($curConfig, "service.actions.redirect", null), $curConfig, $data);
        if (!request()->wantsJson()) {
            return $redirectResult ?? redirect()->route("paymentpass::response", ['service' => $this->service, 'responseType' => "error"]);
        } elseif ($redirectResult == null) {
            $error = str_replace([":referenceCode", ":error"], ["Unknown", "Unknown error"], trans("paymentpass::messages.error"));
            $callbackFunc = Arr::get($this->config, "service.callbacks.failure", "");
            if (is_callable($callbackFunc)) {
                call_user_func($callbackFunc, $error);
            }
            return response()->json($error, 400);
        } else {
            return $redirectResult;
        }
    }

    /**
     * Call a view with a form to tokenize information.
     * Uses the form array of the service configuration array
     *
     * @param array $data The data information needed to process the redirection
     * @param boolean $pre_renderView If the view should be rendered before returning it. The result would be a string. Default, true
     * @return \Illuminate\View\View | \Illuminate\Contracts\View\Factory | string
     */
    public function loadTokenForm(array $data = [], $pre_renderView = true)
    {
        $curConfig = $this->config;
        $actionConfig = null;
        $this->actualizarParametros($curConfig, $actionConfig, $data);
        $formConfig = Arr::get($curConfig, "service.form", []);
        if (Arr::get($formConfig, 'ejecutar_pre_actions', true)) {
            foreach (Arr::get($curConfig, "service.pre_actions", []) as $refName => $preActionConfig) {
                $resultadoPre = $this->execAction($refName, $preActionConfig, $curConfig, $data, null, false, false);
                $formConfig['datosPre'][$refName] = $resultadoPre;
                //$this->mapearRespuesta($curConfig, $curConfig, $resultadoPre, $data, $preActionConfig, "service.parameters.");
            }
        }
        $formConfig = (new PaymentPassTranslator($data, $formConfig, $curConfig))->translate();
        $formConfig = (new PaymentPassTranslator($data, $formConfig, $formConfig))->just(['pre_action'])->translate();

        if (Arr::get($formConfig, "id", "") == "") {
            $formConfig["id"] = "paymentpass_{$this->service}";
        }
        $this->lanzarDump(["para form" => [
            'config' => Arr::except($curConfig, ["service.actions", "service.form", "service.webhooks"]),
            'formConfig' => $formConfig,
            'service' => $this->service,
            'datos' => $data,
        ]]);
        $view = view('paymentpass::form', [
            'config' => $curConfig,
            'formConfig' => $formConfig,
            'service' => $this->service,
            'datos' => $data,
        ]);
        if ($pre_renderView) {
            return $view->render();
        }
        return $view;
    }

    /**
     * Get the data from the view as a json
     * @param array $actionConfig Configuration array for the action
     * @return string
     */
    private function getJsonView($actionConfig)
    {
        $return = [
            "action" => Arr::get($actionConfig, "action"),
            "method" => Arr::get($actionConfig, "method"),
        ];
        $return["parameters"] = [];
        if (Arr::get($actionConfig, "method") != "url") {
            foreach (Arr::get($actionConfig, "call_parameters", []) as $parameter => $value) {
                if (is_array($value)) {
                    $return["parameters"][$parameter] = json_encode($value);
                } else {
                    $return["parameters"][$parameter] = $value;
                }
            }
        } else {
            $parts = parse_url(Arr::get($actionConfig, "action"));
            if (Arr::has($parts, "query")) {
                parse_str($parts['query'], $query);
                if (is_array($query)) {
                    foreach ($query as $parameter => $value) {
                        $return["parameters"][$parameter] = $value;
                    }
                }
            }
        }
        return json_encode($return);
    }

    /**
     * Call a function form a class
     *
     * @param array $actionConfig Configuration of the action to call
     * @param array $curConfig Current configuration file
     * @param array $data A data array to take as reference
     */
    private function callSdkFunction($actionConfig, $curConfig, $data)
    {
        if (Arr::has($actionConfig, "name")) {
            $className = $actionConfig['class'];
            $callType = Arr::get($actionConfig, 'call_type', "");
            $createParameters = Arr::get($actionConfig, 'create_parameters', "");
            $objeto = $this->getInstanceSdk($className, $callType, $createParameters, $curConfig, $data);
            $functionName = $actionConfig['name'];
            $resultado = [];
            if (Arr::has($actionConfig, "pre_functions")) {
                foreach (Arr::get($actionConfig, "pre_functions", []) as $preFunctionName => $preCallParameters) {
                    $resultado[$preFunctionName] = $this->callInstanceSdk($objeto, "", $preFunctionName, $preCallParameters, $curConfig, $data);
                }
            }
            $callParameters = Arr::get($actionConfig, 'call_parameters', null);
            $resultado[$functionName] = $this->callInstanceSdk($objeto, $callType, $functionName, $callParameters, $curConfig, $data);
            return $resultado;
        }
        return null;
    }

    /**
     * Return the instance object to call
     * @param string $className Name of the class
     * @param string $callType type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'sdk_call.class' object with 'parameters' parameters), 'attribute' (calls an attribute of the current 'sdk_call.class' object)
     * @param array|string $createParameters parameters for the creation of the class, for 'function' types,
     * @param array $curConfig Current configuration file
     * @param aray $data A data array to take as reference
     * @return string|Object
     */
    private function getInstanceSdk($className, $callType, $createParameters, $curConfig, $data)
    {
        if ($callType == 'function' || $callType == 'attribute') {
            if ($createParameters == "") {
                $objeto = new $className();
            } else {
                $createParameters = $this->translate_parameters($createParameters, $curConfig, $data);
                if (!is_array($createParameters)) {
                    $createParameters = [$createParameters];
                }
                $this->lanzarDump([
                    'llendo a crear' => [$className, $callType, $createParameters]
                ]);
                $reflection = new ReflectionClass($className);
                $objeto = $reflection->newInstanceArgs($createParameters);
            }
        } else {
            $objeto = $className;
        }
        return $objeto;
    }

    /**
     * Call a function or atribute of an instance
     *
     * @param mixed $objeto The object instance or class name to call
     * @param string $callType type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'sdk_call.class' object with 'parameters' parameters), 'attribute' (calls an attribute of the current 'sdk_call.class' object)
     * @param string $functionName Name of the function/attribute
     * @param array|string $callParameters parameters to pass to the function, could be an array, use '__servic_parameters__fild_name' for fields in the proccesed service parameters array or '__service_parameters_all__' to pass the array, could use the / __data__ will get the value from the data array passed could also be __trans__ for trans() or __trans_article__ for sirgrimorum/transarticles package
     * @param array $curConfig Current configuration file
     * @param aray $data A data array to take as reference
     * @return mixed Null if something goes wrong;
     */
    private function callInstanceSdk($objeto, $callType, $functionName, $callParameters, $curConfig, $data)
    {
        $confirmado = false;
        $method = null;
        if ($callType == '') {
            if (method_exists($objeto, $functionName) && is_callable([$objeto, $functionName])) {
                $method = new ReflectionMethod($objeto, $functionName);
                if ($method->isStatic()) {
                    $callType = 'static';
                } else {
                    $callType = 'function';
                }
                $confirmado = true;
            } elseif (property_exists($objeto, $functionName) && !is_callable([$objeto, $functionName])) {
                $callType = 'attribute';
            }
        }
        if ($callType == 'static' || $callType == 'function') {
            if ($confirmado || (method_exists($objeto, $functionName) && is_callable([$objeto, $functionName]))) {
                try {
                    if ($method === null) {
                        $method = new ReflectionMethod($objeto, $functionName);
                    }
                    $result = null;
                    if ($callParameters === null || $callParameters == "" && $method->getNumberOfParameters() == 0) {
                        $result = $method->invoke(($callType != 'static') ? $objeto : null);
                    } else {
                        $callParameters = $this->translate_parameters($callParameters, $curConfig, $data);
                        if (!is_array($callParameters) && $method->getNumberOfParameters() == 1) {
                            $result = $method->invoke(($callType != 'static') ? $objeto : null, $callParameters);
                        } elseif (is_array($callParameters) && $method->getNumberOfParameters() <= count($callParameters)) {
                            $result = $method->invokeArgs(($callType != 'static') ? $objeto : null, $callParameters);
                        }
                    }
                    return $result;
                } catch (Exception $e) {
                    if (!Arr::get($curConfig, "production", false) && Arr::get($curConfig, "mostrarEchos", false)) {
                        if (!request()->wantsJson()) {
                            if (is_object($objeto)) {
                                $objeto_name = get_class($objeto);
                            } else {
                                $objeto_name = $objeto;
                            }
                            $this->lanzarDump([
                                "error calling {$functionName} in {$objeto_name} con argumentos" => $callParameters,
                                'exception' => $e
                            ]);
                        }
                    }
                    return null;
                }
            }
        } elseif ($callParameters !== null && $callType == 'attribute') {
            return $objeto->{$functionName} = $callParameters;
        } else {
            return $objeto->{$functionName};
        }
        return null;
    }

    /**
     * Translate parameters given a configuration
     * @param array|string $param_config_array Array of parameters to process
     * @param array $config Current configuration file
     * @param array $data Data to take as reference
     * @return mix
     */
    private function translate_parameters($param_config_array, $config, $data = [])
    {
        $service = $config['service'];
        if (!is_array($param_config_array)) {
            $item = (new PaymentPassTranslator())->transSingleString($param_config_array, $data, $config);
            return $item;
        } elseif (count($param_config_array) > 0) {
            $return_params = [];
            foreach ($param_config_array as $keyParameter => $config_parameter) {
                $return_params[$keyParameter] = $this->translate_parameters($config_parameter, $config, $data);
            }
            return $return_params;
        }
        return $param_config_array;
    }

    /**
     * Get the current configuration array
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Set a parameter for the service in the configuration array
     * @param string $key
     * @param mix $value
     */
    public function setParameters($key, $value)
    {
        $this->config['service']['parameters'][$key] = $value;
    }

    /**
     * Set a parameter for the confirmation response of the service in the configuration array
     * @param string $key
     * @param mix $value
     */
    public function setConfirmation($key, $value)
    {
        $this->config['service']['confirmation'][$key] = $value;
    }

    /**
     * Set a parameter for the response of the service in the configuration array
     * @param string $key
     * @param mix $value
     */
    public function setResponse($key, $value)
    {
        $this->config['service']['response'][$key] = $value;
    }

    /**
     * Generate a ResponseCode
     * @param array $data Optional The information needed to generate de code
     * @param PaymentPass $payment The payment
     * @return string
     */
    public function generateResponseCode(array $data = [], PaymentPass $payment = null)
    {
        $curConfig = (new PaymentPassTranslator($data, $this->config, $this->config))->translate();
        if ($payment) {
            $referenceCode = $payment->id;
        } elseif ($this->payment) {
            $referenceCode = $this->payment->id;
        } else {
            $referenceCode = "";
        }
        if (Arr::get($curConfig, "service.referenceCode.type", "auto") == "auto") {
            $strHash = $referenceCode;
            $preHash = "";
            foreach (Arr::get($curConfig, "service.referenceCode.fields", []) as $parameter) {
                $strHash .= $preHash . $parameter;
                $preHash = Arr::get($curConfig, "service.referenceCode.separator", "~");
            }
            switch (Arr::get($curConfig, "service.referenceCode.encryption", "md5")) {
                case "sha256":
                    $referenceCode = Hash::make($strHash);
                    break;
                case "sha1":
                    $referenceCode = sha1($strHash);
                    break;
                case "base64":
                    $referenceCode = base64_encode($strHash);
                    break;
                default:
                case "md5":
                    $referenceCode = md5($strHash);
                    break;
            }
        } else {
            $referenceCode = Arr::get($curConfig, "service.referenceCode.type");
        }
        return $referenceCode;
    }

    /**
     * Build the configuration array
     */
    private function buildConfig()
    {
        $auxConfig = $this->configSrc;
        $config = Arr::except($auxConfig, ['services_production', 'services_test']);
        if (($configServicio = config("sirgrimorum.paymentpass_services.{$this->service}.config", false)) !== false) {
            if (is_array($configServicio)) {
                $config = $this->smartMergeConfig($config, $configServicio);
            }
        }
        $serviceProd = Arr::get($auxConfig, 'services_production.' . $this->service, config("sirgrimorum.paymentpass_services.{$this->service}.service", config("sirgrimorum.paymentpass_services.{$this->service}", [])));
        if (!Arr::get($auxConfig, 'production', false)) {
            $serviceTest = Arr::get($auxConfig, 'services_test.' . $this->service, config("sirgrimorum.paymentpass_services.{$this->service}.test", []));
            $serviceProd = $this->smartMergeConfig($serviceProd, $serviceTest);
        }
        $config['service'] = $serviceProd;
        if (!Arr::has($config['service'], "type")) {
            $config['service']['type'] = "normal";
        }
        return $config;
    }

    /**
     * Evaluate if a string is a json
     * @param string $json_string
     * @return boolean
     */
    public function isJsonString($json_string)
    {
        return !preg_match('/[^,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t]/', preg_replace('/"(\\.|[^"\\\\])*"/', '', $json_string));
    }

    /**
     * Merge 2 configuration arrays, with $config as base and using $preConfig to overwrite.
     *
     * A value of "notThisTime" in a field would mean that the field must be deleted
     *
     * @param array $config The base configuration array
     * @param array $preConfig The principal configuration array
     * @return boolean|array The new configuration file
     */
    private function smartMergeConfig($config, $preConfig)
    {
        if (is_array($preConfig)) {
            if (is_array($config)) {
                foreach ($preConfig as $key => $value) {
                    if (!Arr::has($config, $key)) {
                        if (is_array($value)) {
                            if ($auxValue = $this->smartMergeConfig("", $value)) {
                                $config[$key] = $auxValue;
                            }
                        } elseif (is_object($value)) {
                            $config[$key] = $value;
                        } elseif (strtolower($value) !== "notthistime") {
                            $config[$key] = $value;
                        }
                    } else {
                        if (is_array($value)) {
                            if ($auxValue = $this->smartMergeConfig($config[$key], $value)) {
                                $config[$key] = $auxValue;
                            } else {
                                unset($config[$key]);
                            }
                        } elseif (is_object($value)) {
                            $config[$key] == $value;
                        } elseif (strtolower($value) === "notthistime") {
                            unset($config[$key]);
                        } else {
                            $config[$key] = $value;
                        }
                    }
                }
                if (count($config) > 0) {
                    return $config;
                } else {
                    return false;
                }
            } else {
                $config = [];
                foreach ($preConfig as $key => $value) {
                    if (is_array($value)) {
                        if ($auxValue = $this->smartMergeConfig("", $value)) {
                            $config[$key] = $auxValue;
                        }
                    } elseif (is_object($value)) {
                        $config[$key] = $value;
                    } elseif (strtolower($value) !== "notthistime") {
                        $config[$key] = $value;
                    }
                }
                if (count($config) > 0) {
                    return $config;
                } else {
                    return false;
                }
            }
        } elseif (is_object($preConfig)) {
            return $preConfig;
        } elseif (strtolower($preConfig) === "notthistime") {
            return false;
        } elseif (!$preConfig) {
            return false;
        } else {
            return $preConfig;
        }
    }

    private function lanzarDump($datos, $mostrarJson = false)
    {
        if (!Arr::get($this->config, "production", false) && Arr::get($this->config, "mostrarEchos", false)) {
            if (!request()->wantsJson()) {
                dump($datos);
                if (Arr::get($this->config, "mostrarJsonEchos", false) && $mostrarJson) {
                    echo "<pre><code>" . json_encode($datos, JSON_PRETTY_PRINT) . "</code></pre>";
                }
            }
        }
    }
}
