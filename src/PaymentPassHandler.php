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
                    return ($este->generateResponseCode([], $paymentAux) == $referencia);
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
     * @return \Sirgrimorum\PaymentPass\Models\PaymentPass the saved transaction request
     */
    public function store($process_id, array $data) {
        if ($this->payment) {
            $payment = $this->payment;
        } else {
            $payment = new PaymentPass();
        }
        $payment->process_id = $process_id;
        $payment->save();
        $this->payment = $payment;
        $this->payment->referenceCode = $this->generateResponseCode($data);
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
    public function handleResponse(string $responseType, Request $request, string $service = "") {
        if (in_array($service, config("sirgrimorum.paymentpass.available_services"))) {
            $this->service = $service;
            $this->config = $this->buildConfig();
        }
        if ($responseType = "") {
            if ($request->isMethod('get')) {
                $responseType = 'response';
            } else {
                $responseType = 'confirmation';
            }
        } else {
            $responseType = strtolower($responseType);
        }
        $curConfig = $this->config;
        $curConfig = $this->translateConfig([], $curConfig);
        if ($this->getByReferencia($request->get(array_get($curConfig, "service.$responseType.referenceCode")))) {
            $this->payment->state = $request->get(array_get($curConfig, "service.$responseType.state"));
            $this->payment->payment_method = $request->get(array_get($curConfig, "service.$responseType.payment_method"));
            $this->payment->reference = $request->get(array_get($curConfig, "service.$responseType.reference"));
            $this->payment->response = $request->get(array_get($curConfig, "service.$responseType.response"));
            $this->payment->payment_state = $request->get(array_get($curConfig, "service.$responseType.payment_state"));
            if (array_get($curConfig, "service.$responseType.save_data", "__all__") == "__all__" || !is_array(array_get($curConfig, "service.$responseType.save_data"))) {
                $save_data = json_encode($request->except('_token'));
            } else {
                $save_data = json_encode($request->only(array_get($curConfig, "service.$responseType.save_data")));
            }
            if ($responseType == 'response') {
                $this->payment->response_date = now();
                $this->payment->response_data = $save_data;
            } else {
                $this->payment->confirmation_date = now();
                $this->payment->confirmation_data = $save_data;
            }
            $this->payment->save();

            if (in_array($this->payment->state, array_get($curConfig, "service.state_codes.failure"))) {
                $callbackFunc = array_get($curConfig, "service.callbacks.failure", "");
            } elseif (in_array($this->payment->state, array_get($curConfig, "service.state_codes.success"))) {
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
                return $this->payment->payment_state;
            }
        } else {
            if ($request->isMethod('get')) {
                Session::flash(array_get($curConfig, "error_messages_key"), str_replace([":referenceCode"], [$request->get(array_get($curConfig, "service.$responseType.referenceCode"))], trans("paymentpass::messages.not_found")));
            } else {
                return 'not_found';
            }
        }
        return view(array_get($curConfig, "result_template", "paymentpass.result"), [
            'user' => $request->user(),
            'request' => $request,
            'paymentPass' => $this->payment
        ]);
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

        return view('paymentpass::redirect', [
            'config' => $curConfig,
            'datos' => $data,
        ]);
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
            foreach (array_get($curConfig, "service.referenceCode.fields", "~") as $parameter) {
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
            $col = collect($serviceProd);
            $col2 = $col->merge(collect($serviceTest));
            $serviceProd = $col->toArray();
        }
        $config['service'] = $serviceProd;
        return $config;
    }

    /**
     *  Evaluate functions inside the config array, such as trans(), route(), url() etc.
     * 
     * @param array $data Optional The data as extra parameters
     * @param array $config Optional The config array to translate
     * @return array The operated config array
     */
    public function translateConfig(array $data = [], array $config = [], array $configComplete=[]) {
        if (count($config) > 0) {
            $array = $config;
        } else {
            $array = $this->config;
			//echo "<p>array</p><pre>" . print_r($data,true) . "</pre>";
        }
		if (count($configComplete)==0){
			$configComplete = $this->config;
		}
        $result = [];
        foreach ($array as $key => $item) {
            if (gettype($item) != "Closure Object") {
                if (is_array($item)) {
                    $result[$key] = $this->translateConfig($data,$item,$configComplete);
                } elseif (is_string($item)) {
                    $item = str_replace(config("sirgrimorum.crudgenerator.locale_key"), \App::getLocale(), $item);
                    $item = $this->translateString($item, "__route__", "route");
                    $item = $this->translateString($item, "__url__", "url");
                    if (function_exists('trans_article')) {
                        $item = $this->translateString($item, "__trans_article__", "trans_article");
                    }
                    $item = $this->translateString($item, "__data__", "data", $data);
                    $item = $this->translateString($item, "__trans__", "trans");
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
                            $datos = explode("|",$textPiece);
                            if (count($datos) > 2) {
                                $datosParameters = explode(",",$datos[1]);
                                $datosFields = explode(",",$datos[2] );
                            } elseif (count($datos) > 1) {
                                $datosParameters = explode(",",$datos[1]);
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
                                            $piece = number_format(($valor / (1 + $impuesto)),$datosParameters[2],".","");
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
                                            $piece = number_format(($base * $impuesto),$datosParameters[2],".","");
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
                                            $piece = number_format($base * (1 + $impuesto),$datosParameters[2],".","");
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
