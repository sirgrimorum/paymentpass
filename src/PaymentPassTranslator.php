<?php

namespace Sirgrimorum\PaymentPass;

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
        "user_agent",
        "config_action",
        "pre_action",
        "config_paymentpass",
        "auto",
    ];

    /**
     * All the functions to process
     *
     * @var array
     */
    private $functionsToProcess;

    function __construct(array $data = [], array $config = [], array $configComplete = [], $request = false)
    {
        $this->data = $data;
        $this->config = $config;
        $this->configComplete = $configComplete;
        $this->request = $request;
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
                    $item = str_replace(config("sirgrimorum.crudgenerator.locale_key"), App::getLocale(), $item);

                    $item = $this->transString($item, "__route__", "route");
                    $item = $this->transString($item, "__url__", "url");
                    if (function_exists('trans_article')) {
                        $item = $this->transString($item, "__trans_article__", "trans_article");
                    }
                    if (!$request) {
                        $item = $this->transString($item, "__data__", "data", $data);
                    } else {
                        $item = $this->transString($item, "__request__", "data", $data);
                    }
                    $item = $this->transString($item, "__trans__", "trans");
                    $item = $this->transString($item, "__asset__", "asset");
                    $item = $this->transString($item, "__session_id__", "session_id");
                    $item = $this->transString($item, "__device_session_id__", "device_session_id");
                    $item = $this->transString($item, "__ip_address__", "ip_address");
                    $item = $this->transString($item, "__user_agent__", "user_agent");
                    $item = $this->transString($item, "__config_action__", "config_action", $configComplete, $result);
                    $item = $this->transString($item, "__pre_action__", "pre_action", Arr::get($configComplete, "datosPre", []), $result);
                    $item = $this->transString($item, "__config_paymentpass__", "config_paymentpass", $configComplete, $result);
                    $item = $this->transString($item, "__auto__", "auto", $data, $result);
                    $item = $this->transString($item, "__auto__", "auto", $data, $result);
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
    private function transString($item, $prefix, $function, $data = [], $config = [], $close = "__")
    {
        if (in_array($function, $this->functionsToProcess)) {
            return PaymentPassTranslator::translateString($item, $prefix, $function, $data, $config, $close);
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
     * @return string The string with the results of the evaluations
     */
    public static function translateString($item, $prefix, $function, $data = [], $config = [], $close = "__")
    {
        if (isset($item)) {
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
                            } elseif ($function == 'user_agent') {
                                $piece = $_SERVER['HTTP_USER_AGENT'];
                            } elseif ($function == 'session_id') {
                                $piece = session()->getId();
                            } elseif ($function == 'device_session_id') {
                                $piece = md5(session()->getId() . microtime());
                            } elseif ($function == 'data') {
                                $piece = PaymentPassTranslator::getValor($textPiece, $data);
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
                        } elseif (is_bool($piece)) {
                            $piece = ($piece) ? "true" : "false";
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
