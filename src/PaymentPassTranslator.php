<?php

namespace Sirgrimorum\PaymentPass;

use DateTime;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class PaymentPassTranslator
{

    /**
     * The data as extra parameters
     *
     * @var  array
     */
    protected $data;

    /**
     * The config so far array to translate
     *
     * @var  array
     */
    protected $config;

    /**
     * The complet config array to translate
     *
     * @var  array
     */
    protected $configComplete;

    /**
     * If is translating with request as data
     *
     * @var  boolean
     */
    protected $request = false;

    /**
     * The functions not to translate
     *
     * @var  array
     */
    protected $except = [
        "config_action",
        "pre_action"
    ];

    /**
     * The only functions to translate
     *
     * @var  array
     */
    protected $just = [];

    /**
     * if change the boolean values for strings, default is true
     *
     * @var  bool
     */
    protected $booleanAsStr = true;

    /**
     * All the functions currently available
     *
     * @var array
     */
    private $functions = [
        "route",
        "url",
        "trans_article",
        "data",
        "trans",
        "asset",
        "session_id",
        "device_session_id",
        "ip_address",
        'datetime_utc',
        'datetime',
        "user_agent",
        "config_action",
        "pre_action",
        "config_paymentpass",
        "service_parameters_all",
        "service_parameters",
        "auto",
        "auto_config",
        "post",
    ];

    /**
     * All the functions to process
     *
     * @var array
     */
    private $functionsToProcess;

    function __construct(array $data = [], array $config = [], array $configComplete = [], $request = false, $booleanAsStr = null)
    {
        $this->data = $data;
        $this->config = $config;
        $this->configComplete = $configComplete;
        $this->request = $request;
        $this->booleanAsStr = true;
        if (is_array($config)) {
            $this->booleanAsStr = Arr::get($config, "_booleanAsStr", true);
            if (is_string($this->booleanAsStr)) {
                $this->booleanAsStr = $this->booleanAsStr != "false";
            }
        }
        if (isset($booleanAsStr)) {
            $this->booleanAsStr = $booleanAsStr;
        }
        $this->except($this->except);
    }

    /**
     * Use all translation functions except fot this ones
     * @param array $exceptThisFunctions List of functions to discard
     * @return PaymentPassTranslator
     */
    public function except(array $exceptThisFunctions)
    {
        $functions = [];
        foreach ($this->functions as $function) {
            if (!in_array($function, $exceptThisFunctions)) {
                $functions[] = $function;
            }
        }
        $this->functionsToProcess = $functions;
        return $this;
    }

    /**
     * Use only this translation functions
     * @param array $justThisFunctions List of functions to use
     * @return PaymentPassTranslator
     */
    public function just(array $justThisFunctions)
    {
        $functions = [];
        foreach ($this->functions as $function) {
            if (in_array($function, $justThisFunctions)) {
                $functions[] = $function;
            }
        }
        $this->functionsToProcess = $functions;
        return $this;
    }

    /**
     * Translate de configuration array
     *
     * @param array $config Optional The config so far array to translate, if null will use the initial one
     * @return array The configuration translated
     */
    public function translate($config = null)
    {
        if ($config == null) {
            $array = $this->config;
        } else {
            $array = $config;
        }
        $configComplete = $this->configComplete;
        $data = $this->data;
        $request = $this->request;
        $result = [];
        foreach ($array as $key => $item) {
            if (gettype($item) != "Closure Object") {
                if (is_array($item) && count($item) > 0) {
                    $result[$key] = $this->translate($item);
                } elseif (is_string($item)) {
                    $item = $this->transSingleString($item, $data, $configComplete, $result, $request);
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
     * @param array $data Optional, The data with optional parameters
     * @param array $configComplete The complete config so far
     * @param array $result Optional, the result of the translate so far
     * @param boolean $request Optional, if the data is request data or not, if different from false, "__request__" will operate with "__data__"
     * @param string $close Optional, the closing string for the prefix, default is '__'
     * @return string The string with the results of the evaluations
     */
    public function transSingleString($item, $data, $configComplete, $result = [], $request = false, $close = "__")
    {
        if (is_array($configComplete)) {
            $booleanAsStr = Arr::get($configComplete, "_booleanAsStr", true);
            if (is_string($booleanAsStr)) {
                $booleanAsStr = $booleanAsStr != "false";
            }
        } else {
            $booleanAsStr = $this->booleanAsStr;
        }
        if (is_bool($item) && $booleanAsStr){
            $item = ($item) ? "true" : "false";
        } elseif ($item === "true" && !$booleanAsStr) {
            $item = true;
        } elseif ($item === "false" && !$booleanAsStr) {
            $item = false;
        }
        if (is_string($item)) {
            $dataParaFunctions = [
                "data" => $data,
                'datetime_utc' => $data,
                'datetime' => $data,
                "config_action" => $configComplete,
                "pre_action" => Arr::get($configComplete, "datosPre", []),
                "config_paymentpass" => $configComplete,
                "auto" => $data,
                "auto_config" => $this->config,
            ];
            $configParaFunctions = [
                "config_action" => $result,
                "pre_action" => $result,
                "config_paymentpass" => $result,
                "auto" => $result,
                "auto_config" => $result,
                "service_parameters" => $configComplete,
                "service_parameters_all" => $configComplete,
            ];
            $functionsToProcess = $this->functionsToProcess;
            if (in_array("auto", $functionsToProcess)) {
                $functionsToProcess[] = "auto";
            }
            $item = str_replace(config("sirgrimorum.crudgenerator.locale_key"), App::getLocale(), $item);
            foreach ($this->functionsToProcess as $function) {
                if ($request !== false && $function == "data") {
                    $item = PaymentPassTranslator::translateString($item, "__request__", $function, Arr::get($dataParaFunctions, $function, []), Arr::get($configParaFunctions, $function, []), $close, $booleanAsStr);
                } else {
                    $item = PaymentPassTranslator::translateString($item, "__{$function}__", $function, Arr::get($dataParaFunctions, $function, []), Arr::get($configParaFunctions, $function, []), $close, $booleanAsStr);
                }
            }
        }
        return $item;
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
     * @param bool $booleanAsStr if change the boolean values for strings, default is true
     * @return string The string with the results of the evaluations
     */
    public static function translateString($item, $prefix, $function, $data = [], $config = [], $close = "__", $booleanAsStr = true)
    {
        if (is_bool($item) && $booleanAsStr){
            $item = ($item) ? "true" : "false";
        } elseif ($item === "true" && !$booleanAsStr) {
            $item = true;
        } elseif ($item === "false" && !$booleanAsStr) {
            $item = false;
        }
        if (isset($item) && is_string($item)) {
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
                            $auxArr = explode(",", str_replace([" ,", ", "], [",", ","], $textPiece));
                            if ($auxIndex = array_search("*****", $auxArr)) {
                                $auxArr[$auxIndex] = json_decode($auxJson, true);
                            } else {
                                $auxArr[] = json_decode($auxJson, true);
                            }
                            $piece = call_user_func_array($function, $auxArr);
                        } else {
                            if ($function == 'config_paymentpass') {
                                $piece = PaymentPassTranslator::getValorDesde($textPiece, $data, $config);
                            } elseif ($function == 'config_action') {
                                $piece = PaymentPassTranslator::getValorDesde($textPiece, $data, $config);
                            } elseif ($function == 'pre_action') {
                                $piece = PaymentPassTranslator::getValorDesde($textPiece, $data, $config);
                            } elseif ($function == 'ip_address') {
                                $piece = $_SERVER['REMOTE_ADDR'];
                            } elseif ($function == 'datetime_utc') {
                                $currentDateTime = new DateTime('UTC');
                                $piece = $currentDateTime->format($textPiece);
                            } elseif ($function == 'datetime') {
                                $currentDateTime = new DateTime();
                                $piece = $currentDateTime->format($textPiece);
                            } elseif ($function == 'user_agent') {
                                $piece = $_SERVER['HTTP_USER_AGENT'];
                            } elseif ($function == 'session_id') {
                                $piece = session()->getId();
                            } elseif ($function == 'device_session_id') {
                                $piece = md5(session()->getId() . microtime());
                            } elseif ($function == 'service_parameters_all') {
                                $piece = Arr::get($config, "service.parameters", []);
                            } elseif ($function == 'service_parameters') {
                                $piece = Arr::get($config, "service.parameters.$textPiece", $textPiece);
                            } elseif ($function == 'data') {
                                $piece = PaymentPassTranslator::getValor($textPiece, $data);
                            } elseif ($function == 'post') {
                                $resto = explode(":", $textPiece);
                                $postFunction = array_shift($resto);
                                if (count($resto) > 1) {
                                    $restoStr = implode(":", $resto);
                                } elseif (count($resto) == 1) {
                                    $restoStr = $resto[0];
                                } else {
                                    $restoStr = "";
                                }
                                $piece = "__{$postFunction}__$restoStr";
                            } elseif ($function == 'auto' || $function == 'auto_config') {
                                $datos = explode("|", $textPiece);
                                if (count($datos) > 2) {
                                    $datosParameters = explode(",", $datos[1]);
                                } elseif (count($datos) > 1) {
                                    $datosParameters = explode(",", $datos[1]);
                                } else {
                                    $datosParameters = [];
                                }
                                switch ($datos[0]) {
                                    case "taxReturnBase":
                                        if (count($datosParameters) == 3) {
                                            $impuesto = (float) PaymentPassTranslator::getValorDesde($datosParameters[0], $data, $config);
                                            $valor = (float) PaymentPassTranslator::getValorDesde($datosParameters[1], $data, $config);
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
                                            $impuesto = (float) PaymentPassTranslator::getValorDesde($datosParameters[0], $data, $config);
                                            $base = (float) PaymentPassTranslator::getValorDesde($datosParameters[1], $data, $config);
                                            try {
                                                $piece = number_format(($base * $impuesto), $datosParameters[2], ".", "");
                                            } catch (Exception $exc) {
                                                $piece = "";
                                            }
                                        } else {
                                            $piece = "";
                                        }
                                        break;
                                    case "tax_inv":
                                        if (count($datosParameters) == 3) {
                                            $impuesto = (float) PaymentPassTranslator::getValorDesde($datosParameters[0], $data, $config);
                                            $valor = (float) PaymentPassTranslator::getValorDesde($datosParameters[1], $data, $config);
                                            try {
                                                $piece = number_format($valor - ($valor / (1 + $impuesto)), $datosParameters[2], ".", "");
                                            } catch (Exception $exc) {
                                                $piece = "";
                                            }
                                        } else {
                                            $piece = "";
                                        }
                                        break;
                                    case "valueToPay":
                                        if (count($datosParameters) == 3) {
                                            $impuesto = (float) PaymentPassTranslator::getValorDesde($datosParameters[0], $data, $config);
                                            $base = (float) PaymentPassTranslator::getValorDesde($datosParameters[1], $data, $config);
                                            try {
                                                $piece = number_format($base * (1 + $impuesto), $datosParameters[2], ".", "");
                                            } catch (Exception $exc) {
                                                $piece = "";
                                            }
                                        } else {
                                            $piece = "";
                                        }
                                        break;
                                    case "boolean":
                                        if (count($datosParameters) == 1) {
                                            $piece = PaymentPassTranslator::getValorDesde($datosParameters[0], $data, $config) == true;
                                        } elseif (count($datosParameters) == 2) {
                                            $piece = PaymentPassTranslator::getValorDesde($datosParameters[0], $data, $config) == PaymentPassTranslator::getValorDesde($datosParameters[1], $data, $config);
                                        } else {
                                            $piece = false;
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
                        if (is_object($piece) || is_array($piece)) {
                            $piece = json_encode($piece);
                        } elseif (is_bool($piece) && $booleanAsStr) {
                            $piece = ($piece) ? "true" : "false";
                        } elseif ($piece === "true" && !$booleanAsStr) {
                            $piece = true;
                        } elseif ($piece === "false" && !$booleanAsStr) {
                            $piece = false;
                        }
                        if (is_string($piece) || is_numeric($piece)) {
                            if ($right <= strlen($item)) {
                                $item = substr($item, 0, $left) . $piece . substr($item, $right + 2);
                            } else {
                                $item = substr($item, 0, $left) . $piece;
                            }
                            $left = (stripos($item, $prefix));
                            if ($item == $piece) {
                                //$item = $textPiece;
                                $left = false;
                            }
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
        return $item;
    }

    /**
     * Get the value of a key inside nested arrays
     * @param string $dato key
     * @param array $config1 firts array to look in
     * @param array $config2 second array to look in
     * @return mix The value found or $dato
     */
    private static function getValorDesde($dato, $config1, $config2)
    {
        $aux = PaymentPassTranslator::getValor($dato, $config1);
        if ($aux == $dato && $config1 != $config2) {
            $aux = PaymentPassTranslator::getValor($dato, $config2);
        }
        return $aux;
    }

    /**
     * Get the value of a key inside a nested array
     * @param string $dato key
     * @param array $data the array to look in
     * @return mix The value found or $dato
     */
    private static function getValor($dato, $data)
    {
        if (!is_array($data) || (is_array($data) && count($data) == 0)) {
            return $dato;
        } elseif (Arr::has($data, $dato)) {
            return Arr::get($data, $dato, $dato);
        } else {
            foreach ($data as $key => $item) {
                if (is_array($item)) {
                    $aux = PaymentPassTranslator::getValor($dato, $item);
                    if ($aux != $dato) {
                        return $aux;
                    }
                }
            }
            return $dato;
        }
    }

    /**
     * Get the parameters for a function call in js
     *
     * @param mix $params The parameters to process
     * @param bool $esPrimero Optional if is the first call (no {}) in the tree or no
     * @return string The str to use in the js
     */
    public static function paramsForJs($params, $esPrimero = false)
    {
        if (is_array($params)) {
            if (count($params) == 0) {
                return "";
            }
            $return = !$esPrimero ? "{" : "";
            $i = 0;
            foreach ($params as $paramKey => $paramValue) {
                if (!is_int($paramKey)) {
                    $return .= "'{$paramKey}':";
                }
                if (is_array($paramValue)) {
                    $return .= PaymentPassTranslator::paramsForJs($paramValue);
                } else {
                    if (Str::startsWith($paramValue, ['function', ' function'])) {
                        $return .= "{$paramValue}";
                    } else {
                        $return .= "'{$paramValue}'";
                    }
                }
                if ($i < count($params) - 1) {
                    $return .= ",";
                }
            }
            $return .= !$esPrimero ? "}" : "";
            return $return;
        } elseif ($params != '' && $params != null) {
            if (Str::startsWith($params, ['funtion', ' funciton'])) {
                return "{$params}";
            }
            return "'{$params}'";
        }
        return "";
    }
}
