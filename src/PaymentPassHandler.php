<?php

namespace Sirgrimorum\PaymentPass;

use Exception;
use Sirgrimorum\PaymentPass\Models\PaymentPass;
use App;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Support\Facades\Session;

class PaymentPassHandler {

    protected $service;
    protected $config;
    protected $payment = null;

    function __construct($service = "") {
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
    public function getByReferencia($referencia) {
        $este = $this;
        $this->payment = PaymentPass::all()->filter(function($paymentAux) use ($referencia, $este) {
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
    public function getById($id) {
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
    public function store($process_id, array $data, $type = "") {
        if ($this->payment) {
            $payment = $this->payment;
        } else {
            $payment = new PaymentPass();
        }
        if ($type != "") {
            $payment->type = substr($type, 0,3);
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
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param string $service Optional The payment service to use
     * @return \Illuminate\View\View | \Illuminate\Contracts\View\Factory
     */
    public function handleResponse(Request $request, string $service = "", string $responseType) {
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
        if (!array_has($curConfig, "service.responses." . $responseType)) {
            $responseType = "error";
        }
        if ($responseType == "error") {
            $error = str_replace([":referenceCode", ":error"], [$request->get(array_get($curConfig, "service.$responseType.referenceCode")), $request->get("error", "Unknown error")], trans("paymentpass::messages.error"));
            $callbackFunc = array_get($curConfig, "service.callbacks.failure", "");
            if (is_callable($callbackFunc)) {
                call_user_func($callbackFunc, $error);
            }
            if ($request->isMethod('get')) {
                Session::flash(array_get($curConfig, "error_messages_key"), $error);
            } else {
                return $error;
            }
            return response()->view(array_get($curConfig, "result_template", "paymentpass.result"), [
                        'user' => $request->user(),
                        'request' => $request,
                        'paymentPass' => $this->payment,
                        'config' => $curConfig,
                            ], 200);
        } else {
            $datos = $request->all();
            $configResponse = array_get($curConfig, "service.responses.$responseType");
            foreach (array_get($configResponse, "_pre", []) as $reference => $class_datos) {
                if ($this->conditionsFunction(array_get($class_datos, "if", []), $curConfig, $datos)) {
                    $typeClass = $class_datos['type'];
                    $className = $class_datos['class'];
                    $functionName = $class_datos['name'];
                    $createParameters = array_get($class_datos, "create_parameters", "");
                    $callParameters = array_get($class_datos, "call_parameters", "");
                    if (array_get($class_datos, "key_name", "") != "") {
                        $auxDatos = $this->callSdkFunction($className, $functionName, $typeClass, $createParameters, $callParameters, $curConfig, $datos);
                        if (is_array($auxDatos) || is_object($auxDatos)) {
                            $auxDatos = json_decode(json_encode($auxDatos), true);
                            if (array_has($auxDatos, "body")) {
                                $auxDatos = array_get($auxDatos, "body");
                            }
                        }
                        data_set($datos, $class_datos['key_name'], $auxDatos);
                    } else {
                        $this->callSdkFunction($className, $functionName, $typeClass, $createParameters, $callParameters, $curConfig, $datos);
                    }
                }
            }
            $referenceCode = $this->getResponseParameter(array_get($configResponse, "referenceCode", ""), $curConfig, $datos);
            $payment = $this->getByReferencia($referenceCode);
            if (!array_get($curConfig, "production", false) && array_get($curConfig, "mostrarEchos", false)) {
                if ($request->isMethod('get')) {
                    echo "<p>prueba</p><pre>" . print_r(["datos" => $datos, "referenceCode" => $referenceCode, "Payment" => $this->payment], true) . "</pre>";
                }
            }
            if (!$payment) {
                $noexiste = true;
            } else {
                $noexiste = false;
            }
            if ($payment || (!array_get($curConfig, "production", false))) {
                if ($noexiste) {
                    $this->payment = new PaymentPass();
                } else {
                    if ($this->isJsonString($this->payment->creation_data)) {
                        $curConfig = $this->translateConfig(json_decode($this->payment->creation_data, true), $curConfig, [], false);
                    }
                }
                if (!array_get($curConfig, "production", false)) {
                    $datos['_responseType'] = $responseType;
                    $datos['_service'] = $this->service;
                }
                if (array_get($configResponse, "state", "") != "_notthistime") {
                    $state = $this->getResponseParameter(array_get($configResponse, "state", ""), $curConfig, $datos);
                    $stateAux = $state;
                    if (array_has(array_get($curConfig, "service.state_codes.failure"), $stateAux)) {
                        $stateAux = array_get($curConfig, "service.state_codes.failure." . $stateAux);
                    } elseif (array_has(array_get($curConfig, "service.state_codes.pending"), $stateAux)) {
                        $stateAux = array_get($curConfig, "service.state_codes.pending." . $stateAux);
                    } elseif (array_has(array_get($curConfig, "service.state_codes.success"), $stateAux)) {
                        $stateAux = array_get($curConfig, "service.state_codes.success." . $stateAux);
                    }
                    if (strlen($stateAux) > 3) {
                        $stateAux = substr($stateAux, 0, 3);
                    }
                    $this->payment->state = $stateAux;
                }
                if (array_get($configResponse, "payment_method", "_notthistime") != "_notthistime") {
                    $this->payment->payment_method = $this->getResponseParameter(array_get($configResponse, "payment_method", ""), $curConfig, $datos);
                }
                if (array_get($configResponse, "reference", "_notthistime") != "_notthistime") {
                    $this->payment->reference = $this->getResponseParameter(array_get($configResponse, "reference", ""), $curConfig, $datos);
                }
                if (array_get($configResponse, "response", "_notthistime") != "_notthistime") {
                    $this->payment->response = $this->getResponseParameter(array_get($configResponse, "response", ""), $curConfig, $datos);
                }
                if (array_get($configResponse, "payment_state", "_notthistime") != "_notthistime") {
                    $this->payment->payment_state = $this->getResponseParameter(array_get($configResponse, "payment_state", ""), $curConfig, $datos);
                }
                if (array_get($configResponse, "save_data", "__all__") == "__all__" || !is_array(array_get($configResponse, "save_data"))) {
                    $save_data = json_encode($datos);
                } else {
                    $save_data = json_encode(array_only($datos, array_get($configResponse, "save_data")));
                }
                if (!$request->isMethod('get')) {
                    $this->payment->response_date = now();
                    if (array_get($configResponse, "save_data", "_notthistime") != "_notthistime") {
                        if ($this->payment->response_data != null){
                            $auxData = json_decode($this->payment->response_data, true);
                            if (is_array($auxData)){
                                $save_data = json_encode(array_merge($auxData,  json_decode($save_data,true)));
                            }
                        }
                        $this->payment->response_data = $save_data;
                    }
                } else {
                    $this->payment->confirmation_date = now();
                    if (array_get($configResponse, "save_data", "_notthistime") != "_notthistime") {
                        if ($this->payment->confirmation_data != null){
                            $auxData = json_decode($this->payment->confirmation_data, true);
                            if (is_array($auxData)){
                                $save_data = json_encode(array_merge($auxData,  json_decode($save_data,true)));
                            }
                        }
                        $this->payment->confirmation_data = $save_data;
                    }
                }
                if (!$noexiste) {
                    $this->payment->save();
                } else {
                    if (array_get($curConfig, "saveAll", false)) {
                        $this->payment->save();
                    }
                }
                if (!array_get($curConfig, "production", false) && array_get($curConfig, "mostrarEchos", false)) {
                    if ($request->isMethod('get')) {
                        echo "<p></p><pre>" . print_r(["datos" => $datos, "Payment" => $this->payment], true) . "</pre>";
                    }
                }

                if (in_array($state, array_get($curConfig, "service.state_codes.failure")) || array_has(array_get($curConfig, "service.state_codes.failure"), $state)) {
                    $callbackFunc = array_get($curConfig, "service.callbacks.failure", "");
                } elseif (in_array($state, array_get($curConfig, "service.state_codes.success")) || array_has(array_get($curConfig, "service.state_codes.success"), $state)) {
                    $callbackFunc = array_get($curConfig, "service.callbacks.success", "");
                } else {
                    $callbackFunc = array_get($curConfig, "service.callbacks.other", "");
                }
                if (is_callable($callbackFunc)) {
                    call_user_func($callbackFunc, $this->payment);
                }
                if ($request->isMethod('get')) {
                    if (in_array($this->payment->state, array_get($curConfig, "service.state_codes.failure"))) {
                        Session::flash(array_get($curConfig, "error_messages_key"), str_replace([":referenceCode"], [$this->payment->referenceCode], trans("paymentpass::services.{$this->service}.messages.{$this->payment->state}")));
                    } else {
                        Session::flash(array_get($curConfig, "status_messages_key"), str_replace([":referenceCode"], [$this->payment->referenceCode], trans("paymentpass::services.{$this->service}.messages.{$this->payment->state}")));
                    }
                } else {
                    return response()->json($this->payment->payment_state, 201);
                }
            } else {
                if ($request->isMethod('get')) {
                    Session::flash(array_get($curConfig, "error_messages_key"), str_replace([":referenceCode"], [$request->get(array_get($curConfig, "service.$responseType.referenceCode"))], trans("paymentpass::messages.not_found")));
                } else {
                    return response()->json('not_found', 200);
                }
            }
            if ($request->isMethod('get')) {
                return response()->view(array_get($curConfig, "result_template", "paymentpass.result"), [
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
    private function conditionsFunction($ifs, $config, $datos) {
        if (is_array($ifs)) {
            foreach ($ifs as $if) {
                $value1 = array_get($if, "value1", "");
                $value1 = $this->translate_parameters($value1, $config, $datos);
                $value2 = array_get($if, "value2", "");
                $value2 = $this->translate_parameters($value2, $config, $datos);
                $condition = array_get("if", "condition", "=");
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
    private function getResponseParameter($parameter, $config, $datos) {
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
     * Call a view with a redirection to the payment website
     * @param array $data The data information needed to process the redirection
     * @return \Illuminate\View\View | \Illuminate\Contracts\View\Factory
     */
    public function redirect(array $data) {
        $curConfig = $this->config;
        $curConfig['service']['referenceCode']['value'] = $this->generateResponseCode($data);
        $curConfig = $this->translateConfig($data, $curConfig, $curConfig);
        if (array_get($curConfig, "service.signature.active", false)) {
            $strHash = "";
            $preHash = "";
            foreach (array_get($curConfig, "service.signature.fields", "~") as $parameter) {
                $strHash .= $preHash . $parameter;
                $preHash = array_get($curConfig, "service.signature.separator", "~");
            }
            switch (array_get($curConfig, "service.signature.encryption", "md5")) {
                case "sha256":
                    $curConfig['service']['signature']['value'] = Hash::make($strHash);
                    break;
                case "sha1":
                    $curConfig['service']['signature']['value'] = sha1($strHash);
                    break;
                default:
                case "md5":
                    $curConfig['service']['signature']['value'] = md5($strHash);
                    break;
            }
        }
        $curConfig = $this->translateConfig($data, $curConfig);
        foreach (array_get($curConfig, "service.responses", []) as $responseName => $responseData) {
            $responseUrl = array_get($responseData, "url", "");
            if ($responseUrl == "") {
                $curConfig['service']['responses'][$responseName]['url'] = route("paymentpass::response", ["service" => $this->service, "responseType" => $responseName]);
            }
        }
        if ($curConfig['service']['type'] == "sdk") {
            $redirectUrl = route("paymentpass::response", ['service' => $this->service, 'responseType' => "error"]);
            foreach (array_get($curConfig, "service.responses", []) as $responseName => $responseData) {
                $responseUrl = array_get($responseData, "url", "");
                if ($responseUrl != "") {
                    data_set($curConfig, 'service.parameters.' . array_get($curConfig, "service.responses." . $responseName . ".url_field_name"), array_get($curConfig, "service.responses." . $responseName . ".url", ""));
                }
            }
            if (array_get($curConfig, "service.referenceCode.send", false)) {
                data_set($curConfig, 'service.parameters.' . array_get($curConfig, "service.referenceCode.field_name"), array_get($curConfig, "service.referenceCode.value", ""));
            }
            if (array_get($curConfig, "service.signature.send", false)) {
                data_set($curConfig, 'service.parameters.' . array_get($curConfig, "service.signature.field_name"), array_get($curConfig, "service.signature.value", ""));
            }
            if (!array_get($curConfig, "production", false) && array_get($curConfig, "mostrarEchos", false)) {
                echo "<p>Lats_configuration pre_sdk</p><pre>" . print_r($curConfig, true) . "</pre>";
            }
            if (array_has($curConfig['service'], "pre_sdk")) {
                foreach (array_get($curConfig, "service.pre_sdk", []) as $refName => $class_datos) {
                    $this->callSdkFunction(
                            $class_datos['class'], $class_datos['name'], $class_datos['type'], array_get($class_datos, 'create_parameters', ""), array_get($class_datos, 'call_parameters', ""), $curConfig, $data
                    );
                }
            }
            if (array_has($curConfig, "service.sdk_call")) {
                $class_datos = $curConfig['service']['sdk_call'];
                $className = $class_datos['class'];
                $typeCall = $class_datos['type'];
                $createParameters = array_get($class_datos, 'create_parameters', "");
                $objeto = $this->getInstanceSdk($className, $typeCall, $createParameters, $curConfig, $data);
                if (array_has($class_datos, "pre_functions")) {
                    foreach (array_get($class_datos, "pre_functions", []) as $functionName => $callParameters) {
                        $this->callInstanceSdk($objeto, "function", $functionName, $callParameters, $curConfig, $data);
                    }
                }
                if (array_has($curConfig, "service.sdk_call.name")) {
                    $functionName = $class_datos['name'];
                    $callParameters = array_get($class_datos, 'call_parameters', "");
                    $redirectUrl = $this->callInstanceSdk($objeto, $typeCall, $functionName, $callParameters, $curConfig, $data);
                }
            }
            $curConfig['service']['action'] = $redirectUrl;
        }
        if (!array_get($curConfig, "production", false) && array_get($curConfig, "mostrarEchos", false)) {
            if (!request()->wantsJson()) {
                echo "<p>Last_configuration</p><pre>" . print_r($curConfig, true) . "</pre>";
            }
        }
        if (!request()->wantsJson()) {
            return view('paymentpass::redirect', [
                'config' => $curConfig,
                'datos' => $data,
            ]);
        } else {
            $result = new \stdClass;
            $result->redirect = json_decode($this->getJsonView($curConfig, $data));
            $result->data = $data;
            $result->config = json_decode(json_encode($curConfig));
            return response()->json($result, 200);
        }
    }

    /**
     * Get the data from the view as a json
     * @param array $config Configuration array
     * @param array $datos Data
     * @return string
     */
    private function getJsonView($config, $datos) {
        $return = [
            "action" => array_get($config, "service.action"),
            "method" => array_get($config, "service.method"),
        ];
        $return["parameters"] = [];
        if (array_get($config, "service.method") != "url") {
            if (array_get($config, "service.referenceCode.send", false)) {
                $return["parameters"][array_get($config, "service.referenceCode.field_name")] = array_get($config, "service.referenceCode.value");
            }
            if (array_get($config, "service.signature.send", false)) {
                $return["parameters"][array_get($config, "service.signature.field_name")] = array_get($config, "service.signature.value");
            }
            foreach (array_get($config, "service.responses", []) as $response_name => $response_datos) {
                $return["parameters"][array_get($response_datos, "url_field_name", "")] = array_get($response_datos, "url", "");
            }
            foreach (array_get($config, "service.parameters", []) as $parameter => $value) {
                if (is_array($value)) {
                    $return["parameters"][$parameter] = json_encode($value);
                } else {
                    $return["parameters"][$parameter] = $value;
                }
            }
        } else {
            $parts = parse_url(array_get($config, "service.action"));
            if (array_has($parts, "query")) {
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
     * @param string $className Name of the class
     * @param string $functionName Name of the function/attribute
     * @param string $callType type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'sdk_call.class' object with 'parameters' parameters), 'attribute' (calls an attribute of the current 'sdk_call.class' object)
     * @param array|string $createParameters parameters for the creation of the class, for 'function' types,
     * @param array|string $callParameters parameters to pass to the function, could be an array, use '__servic_parameters__fild_name' for fields in the proccesed service parameters array or '__service_parameters_all__' to pass the array, could use the / __data__ will get the value from the data array passed could also be __trans__ for trans() or __trans_article__ for sirgrimorum/transarticles package
     * @param array $curConfig Current configuration file
     * @param array $data A data array to take as reference
     */
    private function callSdkFunction($className, $functionName, $callType, $createParameters, $callParameters, $curConfig, $data) {
        $objeto = $this->getInstanceSdk($className, $callType, $createParameters, $curConfig, $data);
        return $this->callInstanceSdk($objeto, $callType, $functionName, $callParameters, $curConfig, $data);
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
    private function getInstanceSdk($className, $callType, $createParameters, $curConfig, $data) {
        if ($callType == 'function' || $callType == 'attribute') {
            if ($createParameters == "") {
                $objeto = new $className();
            } else {
                $createParameters = $this->translate_parameters($createParameters, $curConfig, $data);
                $reflection = new \ReflectionClass($className);
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
     * @return mixed
     */
    private function callInstanceSdk($objeto, $callType, $functionName, $callParameters, $curConfig, $data) {
        if ($callType == 'static' || $callType == 'function') {
            if ($callParameters == "") {
                return call_user_func([$objeto, $functionName]);
            } else {
                $callParameters = $this->translate_parameters($callParameters, $curConfig, $data);
                return call_user_func([$objeto, $functionName], $callParameters);
            }
        } else {
            return $objeto->{$functionName};
        }
    }

    /**
     * Translate parameters given a configuration
     * @param array|string $param_config_array Array of parameters to process
     * @param array $config Current configuration file
     * @param array $data Data to take as reference
     * @return array
     */
    private function translate_parameters($param_config_array, $config, $data = []) {
        $service = $config['service'];
        if (!is_array($param_config_array)) {
            if ($param_config_array == '__service_parameters_all__') {
                return $service['parameters'];
            } elseif (stripos($param_config_array, '__service_parameters__') !== false) {
                $aux = str_replace('__service_parameters__', '', $param_config_array);
                return array_get($service['parameters'], $aux, $aux);
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
    public function getConfig() {
        return $this->config;
    }

    /**
     * Set a parameter for the service in the configuration array
     * @param string $key
     * @param mix $value
     */
    public function setParameters($key, $value) {
        $this->config['service']['parameters'][$key] = $value;
    }

    /**
     * Set a parameter for the confirmation response of the service in the configuration array
     * @param string $key
     * @param mix $value
     */
    public function setConfirmation($key, $value) {
        $this->config['service']['confirmation'][$key] = $value;
    }

    /**
     * Set a parameter for the response of the service in the configuration array
     * @param string $key
     * @param mix $value
     */
    public function setResponse($key, $value) {
        $this->config['service']['response'][$key] = $value;
    }

    /**
     * Generate a ResponseCode
     * @param array $data Optional The information needed to generate de code
     * @param PaymentPass $payment The payment
     * @return string
     */
    private function generateResponseCode(array $data = [], PaymentPass $payment = null) {
        $curConfig = $this->translateConfig($data);
        if ($payment) {
            $referenceCode = $payment->id;
        } elseif ($this->payment) {
            $referenceCode = $this->payment->id;
        } else {
            $referenceCode = "";
        }
        if (array_get($curConfig, "service.referenceCode.type", "auto") == "auto") {
            $strHash = $referenceCode;
            $preHash = "";
            foreach (array_get($curConfig, "service.referenceCode.fields", []) as $parameter) {
                $strHash .= $preHash . $parameter;
                $preHash = array_get($curConfig, "service.referenceCode.separator", "~");
            }
            switch (array_get($curConfig, "service.referenceCode.encryption", "md5")) {
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
            $referenceCode = array_get($curConfig, "service.referenceCode.type");
        }
        return $referenceCode;
    }

    /**
     * Build the configuration array
     */
    private function buildConfig() {
        $auxConfig = config("sirgrimorum.paymentpass");
        $config = array_except($auxConfig, ['services_production', 'services_test']);
        $serviceProd = array_get($auxConfig, 'services_production.' . $this->service, []);
        if (!array_get($auxConfig, 'production', false)) {
            $serviceTest = array_get($auxConfig, 'services_test.' . $this->service, []);
            $serviceProd = $this->smartMergeConfig($serviceProd, $serviceTest);
        }
        $config['service'] = $serviceProd;
        if (!array_has($config['service'], "type")) {
            $config['service']['type'] = "normal";
        }
        return $config;
    }

    /**
     * Evaluate if a string is a json
     * @param string $json_string
     * @return boolean
     */
    public function isJsonString($json_string) {
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
    private function smartMergeConfig($config, $preConfig) {
        if (is_array($preConfig)) {
            if (is_array($config)) {
                foreach ($preConfig as $key => $value) {
                    if (!array_has($config, $key)) {
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
    public function translateConfig(array $data = [], array $config = [], array $configComplete = [], $request = false) {
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
                    $item = str_replace(config("sirgrimorum.crudgenerator.locale_key"), \App::getLocale(), $item);
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
    private function translateString($item, $prefix, $function, $data = [], $config = [], $close = "__") {
        $result = "";
        if (str_contains($item, $prefix)) {
            if (($left = (stripos($item, $prefix))) !== false) {
                while ($left !== false) {
                    if (($right = stripos($item, $close, $left + strlen($prefix))) === false) {
                        $right = strlen($item);
                    }
                    $textPiece = substr($item, $left + strlen($prefix), $right - ($left + strlen($prefix)));
                    $piece = $textPiece;
                    if (str_contains($textPiece, "{")) {
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
                                        $impuesto = (double) $this->getValorDesde($datosParameters[0], $data, $config);
                                        $valor = (double) $this->getValorDesde($datosParameters[1], $data, $config);
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
                                        $impuesto = (double) $this->getValorDesde($datosParameters[0], $data, $config);
                                        $base = (double) $this->getValorDesde($datosParameters[1], $data, $config);
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
                                        $impuesto = (double) $this->getValorDesde($datosParameters[0], $data, $config);
                                        $base = (double) $this->getValorDesde($datosParameters[1], $data, $config);
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
    private function getValorDesde($dato, $config1, $config2) {
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
    private function getValor($dato, $data) {
        if (!is_array($data)) {
            return $dato;
        } elseif (array_has($data, $dato)) {
            return array_get($data, $dato, $dato);
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
