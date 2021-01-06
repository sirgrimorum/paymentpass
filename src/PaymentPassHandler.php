<?php

namespace Sirgrimorum\PaymentPass;

use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sirgrimorum\PaymentPass\Models\PaymentPass;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use ReflectionMethod;

class PaymentPassHandler
{

    protected $service;
    protected $config;
    protected $payment = null;

    function __construct($service = "")
    {
        if (!in_array($service, config("sirgrimorum.paymentpass.available_services"))) {
            $service = config("sirgrimorum.paymentpass.available_services")[0];
        }
        $this->service = $service;
        $this->config = $this->buildConfig();
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
     * Handle a response from the payment source. update the payment and show a view with the result.
     *
     * @param string $responseType Name of the response type, options are 'response' and 'confirmation'
     * @param Illuminate\Http\Request $request
     * @param string $service Optional The payment service to use
     * @return \Illuminate\View\View | \Illuminate\Contracts\View\Factory
     */
    public function handleResponse(Request $request, string $service = "", string $responseType)
    {
        if (in_array($service, config("sirgrimorum.paymentpass.available_services"))) {
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
        $curConfig = $this->translateConfig([], $curConfig, [], false);
        if (!Arr::has($curConfig, "service.responses." . $responseType)) {
            $responseType = "error";
        }
        if ($responseType == "error") {
            $error = str_replace([":referenceCode", ":error"], [$request->get(Arr::get($curConfig, "service.$responseType.referenceCode")), $request->get("error", "Unknown error")], trans("paymentpass::messages.error"));
            $callbackFunc = Arr::get($curConfig, "service.callbacks.failure", "");
            if (is_callable($callbackFunc)) {
                call_user_func($callbackFunc, $error);
            }
            if ($request->isMethod('get')) {
                Session::flash(Arr::get($curConfig, "error_messages_key"), $error);
            } else {
                return $error;
            }
            return response()->view(Arr::get($curConfig, "result_template", "paymentpass.result"), [
                'user' => $request->user(),
                'request' => $request,
                'paymentPass' => $this->payment,
                'config' => $curConfig,
            ], 200);
        } else {
            $datos = $request->all();
            $configResponse = Arr::get($curConfig, "service.responses.$responseType");
            foreach (Arr::get($configResponse, "pre_actions", []) as $reference => $class_datos) {
                if ($this->conditionsFunction(Arr::get($class_datos, "if", []), $curConfig, $datos)) {
                    if (Arr::get($class_datos, "key_name", "") != "") {
                        $auxDatos = $this->execAction($reference, $class_datos, $curConfig, $datos);
                        if (is_array($auxDatos) || is_object($auxDatos)) {
                            $auxDatos = json_decode(json_encode($auxDatos), true);
                            if (Arr::has($auxDatos, "body")) {
                                $auxDatos = Arr::get($auxDatos, "body");
                            }
                        }
                        data_set($datos, $class_datos['key_name'], $auxDatos);
                    } else {
                        $this->execAction($reference, $class_datos, $curConfig, $datos);
                    }
                }
            }
            $referenceCode = $this->getResponseParameter(Arr::get($configResponse, "referenceCode", ""), $curConfig, $datos);
            $payment = $this->getByReferencia($referenceCode);
            if (!Arr::get($curConfig, "production", false) && Arr::get($curConfig, "mostrarEchos", false)) {
                if ($request->isMethod('get')) {
                    echo "<p>prueba</p><pre>" . print_r(["datos" => $datos, "referenceCode" => $referenceCode, "Payment" => $this->payment], true) . "</pre>";
                }
            }
            if (!$payment) {
                $noexiste = true;
            } else {
                $noexiste = false;
            }
            if ($payment || (!Arr::get($curConfig, "production", false))) {
                if ($noexiste) {
                    $this->payment = new PaymentPass();
                } else {
                    if ($this->isJsonString($this->payment->creation_data)) {
                        $curConfig = $this->translateConfig(json_decode($this->payment->creation_data, true), $curConfig, [], false);
                    }
                }
                if (!Arr::get($curConfig, "production", false)) {
                    $datos['_responseType'] = $responseType;
                    $datos['_service'] = $this->service;
                }
                if (Arr::get($configResponse, "state", "") != "_notthistime") {
                    $state = $this->getResponseParameter(Arr::get($configResponse, "state", ""), $curConfig, $datos);
                    $stateAux = $state;
                    if (Arr::has(Arr::get($curConfig, "service.state_codes.failure"), $stateAux)) {
                        $stateAux = Arr::get($curConfig, "service.state_codes.failure." . $stateAux);
                    } elseif (Arr::has(Arr::get($curConfig, "service.state_codes.pending"), $stateAux)) {
                        $stateAux = Arr::get($curConfig, "service.state_codes.pending." . $stateAux);
                    } elseif (Arr::has(Arr::get($curConfig, "service.state_codes.success"), $stateAux)) {
                        $stateAux = Arr::get($curConfig, "service.state_codes.success." . $stateAux);
                    }
                    if (strlen($stateAux) > 3) {
                        $stateAux = substr($stateAux, 0, 3);
                    }
                    $this->payment->state = $stateAux;
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
                    $save_data = json_encode($datos);
                } else {
                    $save_data = json_encode(Arr::only($datos, Arr::get($configResponse, "save_data")));
                }
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
                    if (Arr::get($curConfig, "saveAll", false)) {
                        $this->payment->save();
                    }
                }
                if (!Arr::get($curConfig, "production", false) && Arr::get($curConfig, "mostrarEchos", false)) {
                    if ($request->isMethod('get')) {
                        echo "<p></p><pre>" . print_r(["datos" => $datos, "Payment" => $this->payment], true) . "</pre>";
                    }
                }

                if (in_array($state, Arr::get($curConfig, "service.state_codes.failure")) || Arr::has(Arr::get($curConfig, "service.state_codes.failure"), $state)) {
                    $callbackFunc = Arr::get($curConfig, "service.callbacks.failure", "");
                } elseif (in_array($state, Arr::get($curConfig, "service.state_codes.success")) || Arr::has(Arr::get($curConfig, "service.state_codes.success"), $state)) {
                    $callbackFunc = Arr::get($curConfig, "service.callbacks.success", "");
                } else {
                    $callbackFunc = Arr::get($curConfig, "service.callbacks.other", "");
                }
                if (is_callable($callbackFunc)) {
                    call_user_func($callbackFunc, $this->payment);
                }
                if ($request->isMethod('get')) {
                    if (in_array($this->payment->state, Arr::get($curConfig, "service.state_codes.failure"))) {
                        Session::flash(Arr::get($curConfig, "error_messages_key"), str_replace([":referenceCode"], [$this->payment->referenceCode], trans("paymentpass::services.{$this->service}.messages.{$this->payment->state}")));
                    } else {
                        Session::flash(Arr::get($curConfig, "status_messages_key"), str_replace([":referenceCode"], [$this->payment->referenceCode], trans("paymentpass::services.{$this->service}.messages.{$this->payment->state}")));
                    }
                } else {
                    return response()->json($this->payment->payment_state, 201);
                }
            } else {
                if ($request->isMethod('get')) {
                    Session::flash(Arr::get($curConfig, "error_messages_key"), str_replace([":referenceCode"], [$request->get(Arr::get($curConfig, "service.$responseType.referenceCode"))], trans("paymentpass::messages.not_found")));
                } else {
                    return response()->json('not_found', 200);
                }
            }
            if ($request->isMethod('get')) {
                return response()->view(Arr::get($curConfig, "result_template", "paymentpass.result"), [
                    'user' => $request->user(),
                    'request' => $request,
                    'paymentPass' => $this->payment,
                    'config' => $curConfig,
                ], 200);
            } else {
                return response()->json('not_found', 200);
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
                $condition = Arr::get("if", "condition", "=");
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
     * @param array $curConfig Current configuration, its updated
     * @param array $actionConfig Configuration of the current action
     * @param array $data The data information needed to process the action
     * @param mix $default Optional The default value in case someting goes wrong, default is null
     * @return mix The response from the request or the call, or the $default value if something goes wrong
     */
    public function execAction($action, array $actionConfig, array $curConfig, array $data, $default = null)
    {
        if ($actionConfig != null && is_array($actionConfig) && count($actionConfig) > 0) {
            $this->actualizarParametros($curConfig, $actionConfig, $data);
            if ($this->conditionsFunction(Arr::get($actionConfig, "if", []), $curConfig, $data)) {
                if ($actionConfig['type'] == "sdk") {
                    if (Arr::has($curConfig['service'], "pre_sdk")) {
                        foreach (Arr::get($curConfig, "service.pre_sdk_actions", []) as $refName => $preActionConfig) {
                            $this->callSdkFunction($preActionConfig, $curConfig, $data);
                        }
                    }
                    return $this->callSdkFunction($actionConfig, $curConfig, $data);
                } elseif ($actionConfig['type'] == "http") {
                    $httpRequest = Http::retry(3, 100);
                    if (count(Arr::get($actionConfig, 'headers', [])) > 0) {
                        $httpRequest = $httpRequest->withHeaders($actionConfig['headers']);
                    }
                    if (Arr::get($actionConfig, 'authentication.type', 'nada') == 'basic' && Arr::has($actionConfig, ['authentication.user', 'authentication.secret'])) {
                        $httpRequest = $httpRequest->withBasicAuth(Arr::get($actionConfig, 'authentication.user', ''), Arr::get($actionConfig, 'authentication.secret', ''));
                    } elseif (Arr::get($actionConfig, 'authentication.type', 'nada') == 'digest' && Arr::has($actionConfig, ['authentication.user', 'authentication.secret'])) {
                        $httpRequest = $httpRequest->withDigestAuth(Arr::get($actionConfig, 'authentication.user', ''), Arr::get($actionConfig, 'authentication.secret', ''));
                    } elseif (Arr::get($actionConfig, 'authentication.type', 'nada') == 'token' && Arr::has($actionConfig, 'authentication.token')) {
                        $httpRequest = $httpRequest->withToken(Arr::get($actionConfig, 'authentication.token', ''));
                    }
                    if (in_array(Arr::get($actionConfig, 'method', ''), ['get', 'post', 'put', 'patch', 'delete']) && Arr::get($actionConfig, 'action', '') != '') {
                        $response = $httpRequest->{$actionConfig['method']}($actionConfig['action'], Arr::get($actionConfig, 'call_parameters', []));
                        if ($response->successful()) {
                            return $response->json();
                        }
                        if (!Arr::get($curConfig, "production", false) && Arr::get($curConfig, "mostrarEchos", false) && $response->failed()) {
                            if (!request()->wantsJson()) {
                                echo "<p>error http request {$action}: {$response->status()}</p><pre>" . print_r($response->json(), true) . "</pre>{$response->body()}";
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
     * Update the parameters values in a configuration array.
     * Mainly referenceCode, Signature of an action and responses urls
     *
     * It updates the parameters $curConfig and $actionConfig
     * @param array $curConfig Current configuration, its updated
     * @param array $actionConfig Configuration of the current action
     * @param array $data The data information needed to process the update
     *
     */
    private function actualizarParametros(&$curConfig, &$actionConfig, $data)
    {
        if (!Arr::get($curConfig, 'service.referenceCode.ya_procesado', false)) {
            $curConfig['service']['referenceCode']['value'] = $this->generateResponseCode($data);
            $curConfig['service']['referenceCode']['ya_procesado'] = true;
            $curConfig = $this->translateConfig($data, $curConfig, $curConfig);
        }
        foreach (Arr::get($curConfig, "service.responses", []) as $responseName => $responseData) {
            $responseUrl = Arr::get($responseData, "url", "");
            if ($responseUrl == "") {
                $curConfig['service']['responses'][$responseName]['url'] = route("paymentpass::response", ["service" => $this->service, "responseType" => $responseName]);
            }
            data_set($actionConfig, 'call_parameters.' . Arr::get($curConfig, "service.responses." . $responseName . ".url_field_name"), Arr::get($curConfig, "service.responses." . $responseName . ".url", ""));
        }
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
                    default:
                    case "md5":
                        $actionConfig['signature']['value'] = md5($strHash);
                        break;
                }
                $actionConfig['signature']['ya_procesado'] = true;
            }
            $actionConfig = $this->translateConfig($data, $actionConfig, $curConfig);
        }
        if (Arr::get($curConfig, "service.referenceCode.send", false)) {
            data_set($actionConfig, 'call_parameters.' . Arr::get($curConfig, "service.referenceCode.field_name"), Arr::get($curConfig, "service.referenceCode.value", ""));
        }
        if (Arr::get($actionConfig, "signature.send", false)) {
            data_set($actionConfig, 'call_parameters.' . Arr::get($actionConfig, "signature.field_name"), Arr::get($actionConfig, "signature.value", ""));
        }
        if (!Arr::get($curConfig, "production", false) && Arr::get($curConfig, "mostrarEchos", false)) {
            if (!request()->wantsJson()) {
                echo "<p>Lats_configuration pre_sdk</p><pre>" . print_r($curConfig, true) . "</pre>";
            }
        }
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
            $callType = $actionConfig['call_type'];
            $createParameters = Arr::get($actionConfig, 'create_parameters', "");
            $objeto = $this->getInstanceSdk($className, $callType, $createParameters, $curConfig, $data);
            $functionName = $actionConfig['name'];
            if (Arr::has($actionConfig, "pre_functions")) {
                foreach (Arr::get($actionConfig, "pre_functions", []) as $preFunctionName => $preCallParameters) {
                    $this->callInstanceSdk($objeto, "function", $preFunctionName, $preCallParameters, $curConfig, $data);
                }
            }
            $callParameters = Arr::get($actionConfig, 'call_parameters', "");
            return $this->callInstanceSdk($objeto, $callType, $functionName, $callParameters, $curConfig, $data);
        }
        return null;
    }

    /**
     * Return the instance object to call with a call_user_func
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
        if ($callType == 'static' || $callType == 'function') {
            if (method_exists($objeto, $functionName) && is_callable(array($objeto, $functionName))) {
                try {
                    $method = new ReflectionMethod($objeto, $functionName);
                    $result = null;
                    if ($callParameters == "" && $method->getNumberOfParameters() == 0) {
                        $result = $method->invoke(($callType == 'static') ? $objeto : null);
                    } else {
                        $callParameters = $this->translate_parameters($callParameters, $curConfig, $data);
                        if (!is_array($callParameters) && $method->getNumberOfParameters() == 1) {
                            $result = $method->invoke(($callType == 'static') ? $objeto : null, $callParameters);
                        } elseif (is_array($callParameters) && $method->getNumberOfParameters() <= count($callParameters)) {
                            $result = $method->invokeArgs(($callType == 'static') ? $objeto : null, $callParameters);
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
                            echo "<p>error calling {$functionName} in {$objeto_name} con argumentos:</p><pre>" . print_r($callParameters, true) . "</pre><p>{$e->message}</p>";
                        }
                    }
                    return null;
                }
            }
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
            if ($param_config_array == '__service_parameters_all__') {
                return $service['parameters'];
            } elseif (stripos($param_config_array, '__service_parameters__') !== false) {
                $aux = str_replace('__service_parameters__', '', $param_config_array);
                return Arr::get($service['parameters'], $aux, $aux);
            } else {
                $item = $this->translateString($param_config_array, "__route__", "route");
                $item = $this->translateString($item, "__url__", "url");
                if (function_exists('trans_article')) {
                    $item = $this->translateString($item, "__trans_article__", "trans_article");
                }
                $item = $this->translateString($item, "__data__", "data", $data);
                $item = $this->translateString($item, "__request__", "data", $data);
                $item = $this->translateString($item, "__trans__", "trans");
                $item = $this->translateString($item, "__asset__", "asset");
                $item = $this->translateString($item, "__config_paymentpass__", "config_paymentpass", $data, $config);
                return $item;
            }
        } else {
            $return_params = [];
            foreach ($param_config_array as $config_parameter) {
                $return_params[] = $this->translate_parameters($config_parameter, $config, $data);
            }
            return $return_params;
        }
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
    private function generateResponseCode(array $data = [], PaymentPass $payment = null)
    {
        $curConfig = $this->translateConfig($data);
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
        $auxConfig = config("sirgrimorum.paymentpass");
        $config = Arr::except($auxConfig, ['services_production', 'services_test']);
        $serviceProd = Arr::get($auxConfig, 'services_production.' . $this->service, []);
        if (!Arr::get($auxConfig, 'production', false)) {
            $serviceTest = Arr::get($auxConfig, 'services_test.' . $this->service, []);
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

    /**
     *  Evaluate functions inside the config array, such as trans(), route(), url() etc.
     *
     * @param array $data Optional The data as extra parameters
     * @param array $config Optional The config so far array to translate
     * @param array $configComplete Optional The complet config array to translate
     * @param boolean $request Optional If is translating with request as data
     * @return array The operated config array
     */
    public function translateConfig(array $data = [], array $config = [], array $configComplete = [], $request = false)
    {
        if (count($config) > 0) {
            $array = $config;
        } else {
            $array = $this->config;
            //echo "<p>array</p><pre>" . print_r($data,true) . "</pre>";
        }
        if (count($configComplete) == 0) {
            $configComplete = $this->config;
        }
        $result = [];
        foreach ($array as $key => $item) {
            if (gettype($item) != "Closure Object") {
                if (is_array($item)) {
                    $result[$key] = $this->translateConfig($data, $item, $configComplete, $request);
                } elseif (is_string($item)) {
                    $item = str_replace(config("sirgrimorum.crudgenerator.locale_key"), App::getLocale(), $item);
                    $item = $this->translateString($item, "__route__", "route");
                    $item = $this->translateString($item, "__url__", "url");
                    if (function_exists('trans_article')) {
                        $item = $this->translateString($item, "__trans_article__", "trans_article");
                    }
                    if (!$request) {
                        $item = $this->translateString($item, "__data__", "data", $data);
                    } else {
                        $item = $this->translateString($item, "__request__", "data", $data);
                    }
                    $item = $this->translateString($item, "__trans__", "trans");
                    $item = $this->translateString($item, "__asset__", "asset");
                    $item = $this->translateString($item, "__config_paymentpass__", "config_paymentpass", $configComplete, $result);
                    $item = $this->translateString($item, "__auto__", "auto", $data, $result);
                    $result[$key] = $item;
                } else {
                    $result[$key] = $item;
                }
            } else {
                $result[$key] = $item;
            }
        }
        return $result;
    }

    /**
     * Use the prefixes to change strings in config array to evaluate
     * functions such as route(), trans(), url(), etc.
     *
     * For parameters, use ', ' to separate them inside the prefix and the close.
     *
     * For array, use json notation inside comas
     *
     * @param string $item The string to operate
     * @param string $prefix The prefix for the function
     * @param string $function The name of the function to evaluate
     * @param array $data Optional, The data with optional parameters
     * @param array $config Optional, The complete config so far
     * @param string $close Optional, the closing string for the prefix, default is '__'
     * @return string The string with the results of the evaluations
     */
    private function translateString($item, $prefix, $function, $data = [], $config = [], $close = "__")
    {
        $result = "";
        if (Str::contains($item, $prefix)) {
            if (($left = (stripos($item, $prefix))) !== false) {
                while ($left !== false) {
                    if (($right = stripos($item, $close, $left + strlen($prefix))) === false) {
                        $right = strlen($item);
                    }
                    $textPiece = substr($item, $left + strlen($prefix), $right - ($left + strlen($prefix)));
                    $piece = $textPiece;
                    if (Str::contains($textPiece, "{")) {
                        $auxLeft = (stripos($textPiece, "{"));
                        $auxRight = stripos($textPiece, "}", $left) + 1;
                        $auxJson = substr($textPiece, $auxLeft, $auxRight - $auxLeft);
                        $textPiece = str_replace($auxJson, "*****", $textPiece);
                        $auxJson = str_replace(["'", ", }"], ['"', "}"], $auxJson);
                        $auxArr = explode(",", str_replace([" ,", " ,"], [",", ","], $textPiece));
                        if ($auxIndex = array_search("*****", $auxArr)) {
                            $auxArr[$auxIndex] = json_decode($auxJson, true);
                        } else {
                            $auxArr[] = json_decode($auxJson);
                        }
                        $piece = call_user_func_array($function, $auxArr);
                    } else {
                        if ($function == 'config_paymentpass') {
                            $piece = $this->getValorDesde($textPiece, $data, $config);
                        } elseif ($function == 'data') {
                            $piece = $this->getValor($textPiece, $data);
                        } elseif ($function == 'auto') {
                            $datos = explode("|", $textPiece);
                            if (count($datos) > 2) {
                                $datosParameters = explode(",", $datos[1]);
                                $datosFields = explode(",", $datos[2]);
                            } elseif (count($datos) > 1) {
                                $datosParameters = explode(",", $datos[1]);
                                $datosFields = [];
                            } else {
                                $datosParameters = [];
                                $datosFields = [];
                            }
                            switch ($datos[0]) {
                                case "taxReturnBase":
                                    if (count($datosParameters) == 3) {
                                        $impuesto = (float) $this->getValorDesde($datosParameters[0], $data, $config);
                                        $valor = (float) $this->getValorDesde($datosParameters[1], $data, $config);
                                        try {
                                            $piece = number_format(($valor / (1 + $impuesto)), $datosParameters[2], ".", "");
                                        } catch (Exception $exc) {
                                            $piece = "";
                                        }
                                    } else {
                                        $piece = "";
                                    }
                                    break;
                                case "tax":
                                    if (count($datosParameters) == 3) {
                                        $impuesto = (float) $this->getValorDesde($datosParameters[0], $data, $config);
                                        $base = (float) $this->getValorDesde($datosParameters[1], $data, $config);
                                        try {
                                            $piece = number_format(($base * $impuesto), $datosParameters[2], ".", "");
                                        } catch (Exception $exc) {
                                            $piece = "";
                                        }
                                    } else {
                                        $piece = "";
                                    }
                                    break;
                                case "valueToPay":
                                    if (count($datosParameters) == 3) {
                                        $impuesto = (float) $this->getValorDesde($datosParameters[0], $data, $config);
                                        $base = (float) $this->getValorDesde($datosParameters[1], $data, $config);
                                        try {
                                            $piece = number_format($base * (1 + $impuesto), $datosParameters[2], ".", "");
                                        } catch (Exception $exc) {
                                            $piece = "";
                                        }
                                    } else {
                                        $piece = "";
                                    }
                                    break;
                                default:
                                    $piece = $textPiece;
                                    break;
                            }
                        } else {
                            $piece = call_user_func($function, $textPiece);
                        }
                    }
                    if (is_string($piece)) {
                        if ($right <= strlen($item)) {
                            $item = substr($item, 0, $left) . $piece . substr($item, $right + 2);
                        } else {
                            $item = substr($item, 0, $left) . $piece;
                        }
                        $left = (stripos($item, $prefix));
                    } else {
                        $item = $piece;
                        $left = false;
                    }
                }
            }
            $result = $item;
        } else {
            $result = $item;
        }
        return $result;
    }

    /**
     * Get the value of a key inside nested arrays
     * @param string $dato key
     * @param array $config1 firts array to look in
     * @param array $config2 second array to look in
     * @return mix The value found or $dato
     */
    private function getValorDesde($dato, $config1, $config2)
    {
        $aux = $this->getValor($dato, $config1);
        if ($aux == $dato) {
            $aux = $this->getValor($dato, $config2);
        }
        return $aux;
    }

    /**
     * Get the value of a key inside a nested array
     * @param string $dato key
     * @param array $data the array to look in
     * @return mix The value found or $dato
     */
    private function getValor($dato, $data)
    {
        if (!is_array($data)) {
            return $dato;
        } elseif (Arr::has($data, $dato)) {
            return Arr::get($data, $dato, $dato);
        } else {
            foreach ($data as $key => $item) {
                if (is_array($item)) {
                    $aux = $this->getValor($dato, $item);
                    if ($aux != $dato) {
                        return $aux;
                    }
                }
            }
            return $dato;
        }
    }
}
