<?php

return [
    'config' => [ // the same as paymentpass config root, except available_services, services_production and services_test
        'production' => true,
        'mostrarEchos' => false,
        'mostrarJsonEchos' => false,
    ],
    'service' => [ // original configuration for the services, test mode will overwrite this with the services_test configurations
        'type' => 'normal', // type of service, default is 'normal', options are 'normal' or 'sdk' (need the sdk already installed)
        'public_key' => 'becc6f01a81612d64a8b8e0f034a535b',
        'private_key' => '6935527cbe5e8c70dd3867bf4949eb01',
        'p_key' => '6f59ca2470871b25f1372f67ffe8aa1dec0f8e3f',
        'p_cust_id_cliente' => '498295',
        //'_booleanAsStr' => true, // if the boolean in parameters should be treated as string or boolean, default true. Use it on service or action level config
        'test' => false,
        'pre_actions' => [ // things to do before any sdk call for type 'sdk', works in the same way as actions of type sdk
            'setKeys' => [
                'type' => 'http', // type of service, 'http' (make an http request) or 'sdk' (need the sdk already installed)
                'action' => 'https://api.secure.payco.co/v1/auth/login', // the url of the call
                'method' => 'post', // method of the redirection, options are 'get', 'post', 'put', 'patch', 'delete'
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ], // Headers to send in the Http request, leave empty if not needed
                'call_parameters' => [
                    'public_key' => '__config_paymentpass__public_key',
                    'private_key' => '__config_paymentpass__private_key',
                ], // parameters to pass to the http call, use '__service_parameters__fild_name' for fields in the proccesed service parameters array, '__service_parameters_all__' pass the proccesed parameters array including the webhooks urls
                'field_name' => [ // How to map the result. Set to false to not map the result, 'result_fieldname' => 'response_fieldname', use __all__ for all the response
                    'bearer' => 'bearer_token',
                ],
            ],
        ],
        'actions' => [
            'sdk_action' => [
                'type' => 'sdk', // type of service, options are 'normal' (generate a redirection view), 'http' (make an http request) or 'sdk' (need the sdk already installed)
                'if' => [ //conditional, only executes if is all the conditions are fullfilled
                    [
                        'value1' => '__data__topic', //first value to compare
                        'condition' => '=', // comparision, default =, options are '=', '!=', '<', '>', '<=', '>=', 'is_array', 'is_not_array', 'is_null', 'is_not_null'
                        'value2' => 'algo'
                    ], // second value to compare, default ""
                ],
                'signature' => [ // for security if it applies for the service
                    'active' => false, // if it shoud be calculated
                    'send' => false, // if it shoud be sent
                    'field_name' => 'signature',
                    'separator' => '~',
                    'fields' => [ // fields in order that conform the signature
                        '__data__value',
                    ],
                    'encryption' => 'md5', // could be md5, base64, sha1 or sha256
                ],
                'class' => '\SDK\Class_Name', // The base class for the call
                'ejecutar_pre_actions' => true, // if the pre_actions should be executed or not for this action, default is true
                'call_type' => 'function', // type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'action.class' object with 'call_parameters' parameters),'attribute' (calls an attribute of the current 'action.class' object)
                'create_parameters' => ['__service_parameters__client_id'], // parameters for the creation of the class, for 'function' types, use '__service_parameters__fild_name' for fields in the proccesed service parameters array '__service_parameters_all__' pass the proccesed parameters array including the webhooks urls
                'pre_functions' => [ //functions to call, in order, previus to the redirec function. 'name' is the name of the function, for non 'static' types
                    'function_name' => null, // leave parameter null for no parameters passed
                    'function_name' => 'parameter', // // parameters to pass to the function, could be an array, use '__service_parameters__fild_name' for fields in the proccesed service parameters array or '__service_parameters_all__' to pass the array
                    'function_name' => '__service_parameters_all__', // parameters to pass to the function, could be an array, use '__service_parameters__fild_name' for fields in the proccesed service parameters array or '__service_parameters_all__' to pass the array
                    'function_name' => [ // parameters to pass to the function, use '__service_parameters__fild_name' for fields in the proccesed service parameters array
                        '__service_parameters__client_id',
                    ],
                ],
                'name' => 'init_point', // name of the function/attribute to call
                'call_parameters' => ['parameter'], // parameters to pass to the function for the 'function' type option, use '__service_parameters__fild_name' for fields in the proccesed service parameters array, '__service_parameters_all__' pass the proccesed parameters array including the webhooks urls
                'field_name' => [ // How to map the result. Set to false to not map the result, 'result_fieldname' => 'response_fieldname', use __all__ for all the response
                    'field_name' => 'field_name_origin', // boolean|field 1 to compare, field 2 to compare or auto (using data and result) or auto_config (using config and result) is __auto__[type, options are taxReturnBase|tax,value,number of decimals ; tax|tax,base,number of decimals ; valueToPay|tax,base,number of decimals ; boolean|field that must be true ; boolean|field to compare 1, field to compare 2 ]|[parameters separates with ,]|[fields separates with ,] - auto evaluates after everithing else
                ],
            ],
            'http_action' => [
                'type' => 'http', // type of service, options are 'normal' (generate a redirection view), 'http' (make an http request) or 'sdk' (need the sdk already installed)
                'action' => 'https://algo.algo.com/algo', // the url of the call
                'method' => 'post', // method of the redirection, options are 'get', 'post', 'put', 'patch', 'delete'
                'ejecutar_pre_actions' => true, // if the pre_actions should be executed or not for this action, default is true
                'if' => [ //conditional, only executes if is all the conditions are fullfilled
                    [
                        'value1' => '__data__topic', //first value to compare
                        'condition' => '=', // comparision, default =, options are '=', '!=', '<', '>', '<=', '>=', 'is_array', 'is_not_array', 'is_null', 'is_not_null'
                        'value2' => 'algo'
                    ], // second value to compare, default ""
                ],
                'signature' => [ // for security if it applies for the service
                    'active' => false, // if it shoud be calculated
                    'send' => false, // if it shoud be sent
                    'field_name' => 'signature',
                    'separator' => '~',
                    'fields' => [ // fields in order that conform the signature
                        '__data__value',
                    ],
                    'encryption' => 'md5', // could be md5, base64, sha1 or sha256
                ],
                'headers' => [], // Headers to send in the Http request, leave empty if not needed
                'authentication' => [ // Headers to send in the Http request, leave empty if not needed
                    'type' => '', // the authentication type, options are 'basic', 'digest', 'token', 'aws_sign_v4'
                    'user' => '', // for the 'basic' and 'digest' types
                    'secret' => '', // for the 'basic' and 'digest' types
                    'token' => '', // for the 'token' type,
                    'region' => '', // for the 'aws_sign_v4' type,
                    'service_name' => '', // for the 'aws_sign_v4' type,
                    'key' => '', // for the 'aws_sign_v4' type,
                    'secret' => '', // for the 'aws_sign_v4' type,
                    'token' => null, // for the 'aws_sign_v4' or 'token' types, '' or null is default for 'aws_sign_v4'
                    'token_type' => 'Bearer', // for the 'token' Type of token (the string before the token), default is Bearer
                ],
                'call_parameters' => ['parameter'], // parameters to pass to the http call, use '__service_parameters__fild_name' for fields in the proccesed service parameters array, '__service_parameters_all__' pass the proccesed parameters array including the webhooks urls
                'field_name' => [ // How to map the result. Set to false to not map the result, 'result_fieldname' => 'response_fieldname', use __all__ for all the response
                    'field_name' => 'field_name_origin' , // boolean|field 1 to compare, field 2 to compare or auto (using data and result) or auto_config (using config and result) is __auto__[type, options are taxReturnBase|tax,value,number of decimals ; tax|tax,base,number of decimals ; valueToPay|tax,base,number of decimals ; boolean|field that must be true ; boolean|field to compare 1, field to compare 2 ]|[parameters separates with ,]|[fields separates with ,] - auto evaluates after everithing else
                ],
            ],
        ],
        'parameters' => [
            'items' => [
                0 => [
                    'id' => '__data__id_product',
                    'title' => '__data__product_name',
                    'description' => '__data__description',
                    'quantity' => '__data__numero',
                    'currency' => 'USD',
                    'unit_price' => '__data__product_value',
                    //'picture_url' => '__asset____data__product_picture', //first evaluate '__data__product_picture' and then asset of that
                    'category_id' => '__data__product_category',
                    'taxReturnBase' => '__auto__taxReturnBase|0.19,__data__value__,2', // auto (using data and result) or auto_config (using config and result) is __auto__[type, options are taxReturnBase|tax,value,number of decimals ; tax|tax,base,number of decimals ; valueToPay|tax,base,number of decimals ; boolean|field that must be true ; boolean|field to compare 1, field to compare 2 ]|[parameters separates with ,]|[fields separates with ,] - auto evaluates after everithing else
                    'tax' => '__auto__tax|0.19,taxReturnBase,2',
                ]
            ],
            //'items' => '__data__items', // other option
            'payer' => [
                'name' => '__data__name',
                'surname' => '__data__surname',
                'email' => '__data__email',
                'phone' => [
                    'area_code' => '',
                    'number' => '__data__telefono',
                ],
                'identification' => [
                    'type' => 'DNI',
                    'number' => '__data__cedula',
                ],
            ],
        ],
        'form' => [
            'action' => '/tarjeta', // url of the action, if is ajax, should be a response url extracted from paramas, ej: '__service_parameters__card_token_url'
            'method' => 'POST', // id_of the form
            'type' => 'script', // options are 'script' (everithing is handled by a script), 'ajax' (must be handled by ajax)
            'id' => '', // id of the form
            'class' => '', // for the form
            'button_label' => 'Guardar', // text of the button
            'button_class' => 'btn btn-primary', // class of the button
            'button_div_class' => 'col-xs-offset-0 col-sm-offset-4 col-md-offset-2 col-xs-12 col-sm-12 col-md-12', // class of the button div container
            'ejecutar_pre_actions' => false, // if the pre_actions should be executed or not for this action, default is true
            'fields' => [ // List of inputs
                [
                    'label' => '', // label for the input
                    'description' => '', // description under the label
                    'help' => '', // help under the input
                    'pre' => '', // prefix of the input could be an array with the id and the text or a text
                    'post' => ['id_value' => 'content_value'], // post of the input could be an array with the id and the text or a text
                    'type' => 'text', // type of the field, options are 'text', 'hidden', 'number', 'checkbox', 'div', 'script', 'style'
                    'div_class' => 'form-group row', // class for the container div
                    'div_input_group_class' => '', // class for the input group div when post or pre are present
                    'div_input_class' => 'col-xs-12 col-sm-12 col-md-12', // class for the div containing just the input
                    'div_label_class' => 'col-xs-12 col-sm-12 col-md-12', // class for the div containing just the label
                    'label_class' => 'mb-0 col-form-label font-weight-bold mb-0 pt-0', // class for the label
                    'content' => null, // content inside the tag, leave null for none
                    'attributes' => [ //list of attributes
                        'class="form-control"',
                        'size="30"',
                    ],
                ],
            ],
            'include_scripts' => [
                'https://checkout.epayco.co/epayco.min.js', // url of the scripts to include
                '__asset__vendor/sirgrimorum/checkeador/changeOnHidden.js',
                '__asset__js/paymentcard_functions.js',
            ],
            'pre_functions' => [ // mainly for the 'script' type, script funcitons to do at the page load
                'ePayco.setPublicKey' => '__config_paymentpass__public_key', // use the same instructions for parameters
                'setCardNumberFilter' => 'epayco_card_number',
                'setCardNumberFilter' => 'epayco_cvc',
                'setCardExpYearFilter' => 'epayco_exp_year',
                'setCardExpMonthFilter' => 'epayco_exp_month',
            ],
            'function_call' => '
                $form.find("button").append($("<i/>",{"class":"fa fa-spinner fa-pulse"}));
                $form.find(".class-errors").text("");
                $("#epayco_card_number").removeClass("is-invalid");
                $("#epayco_exp_year").removeClass("is-invalid");
                $("#epayco_exp_month").removeClass("is-invalid");
                $("#:formId").find("input[name=email]").removeClass("is-invalid");
                var seguir = true;
                if (typeof window["payU"] !== "undefined") {
                    if (!payU.validateCard($("#epayco_card_number").val())){
                        $("#epayco_card_number").addClass("is-invalid");
                        $form.find(".class-errors").text("El número de tarjeta es inválido");
                        $form.find("button").prop("disabled", false).find("i").last().remove();
                        seguir = false;
                    }else if(!payU.validateExpiry($("#epayco_exp_year").val(),$("#epayco_exp_month").val())){
                        $("#epayco_exp_year").addClass("is-invalid");
                        $("#epayco_exp_month").addClass("is-invalid");
                        $form.find(".class-errors").text("La fecha de expiración es inválida");
                        $form.find("button").prop("disabled", false).find("i").last().remove();
                        seguir = false;
                    }
                }
                if(seguir){
                    $("#epayco_card_email").val($("#:formId").find("input[name=email]").val());
                    ePayco.token.create($form, function(error, token) {
                        console.log("estamos en el create", token, error);
                        $form.find("button").prop("disabled", false).find("i").last().remove();
                        if(!error) {
                            $("input[name=card_token]").val(token);
                            //form.get(0).submit();
                        } else {
                            var errorStr = "";
                            if (typeof error === "string"){
                                errorStr = error;
                            }else if(typeof error.data.description === "string"){
                                errorStr = error.data.description;
                            }
                            if (errorStr.indexOf("email")>0){
                                $("#:formId").find("input[name=email]").addClass("is-invalid");
                                $([document.documentElement, document.body]).animate({
                                    scrollTop: $("#:formId").find("input[name=email]").offset().top - 200
                                }, 2000);
                            }
                            $form.find(".class-errors").text(errorStr);
                        }
                    });
                }' // for the 'script' type, the code of the function call, the form is in var '$form', remember to re-enable buttons with '$form.find("button").prop("disabled", false);'
        ],
        'referenceCode' => [ //referenceCode of the transaction intent
            'send' => true, // if it should be send to the payment service
            'field_name' => 'external_reference',
            'separator' => '~',
            'fields' => [ // fields in order that conform the code
                '__data__cedula',
                '__data__email',
            ],
            'type' => 'auto', // 'auto' (PaymentPass builds using separator, fields and encryption), '__config__[key]', '__trans__[key]', '__data__[key using dot notation]'
            'encryption' => 'md5', // could be md5, base64, sha1 or sha256
        ],
        'webhooks' => [ // type of webhooks to handle
            'confirmation' => [ // how to map the PaymentPass model with the post or get data for this specifc response call
                'url' => "", // url of the callback to this response, if blank or not present it will be route("paymentpass::response",["service"=>"this service","responseType"=>"this response type"], use: __route__ will evaluate route() and __url__ will evaluate url(), use , to separate parameters and json notation for array parameters
                'url_field_name' => "notification_url", //name of the field name for the url to send in parameters, leave empty for not adding it to parameters
                'es_jwt' => false, // if the payload comes as an JWT, default false
                'pre_actions' => [ // things to do before processing the response, works in the same way as actions of types sdk and http
                    'sdk_action' => [
                        'field_name' => '', //name of the key to save the returned values, leave blank for not saving the returned value
                        'type' => 'sdk', // type of service, options are 'normal' (generate a redirection view), 'http' (make an http request) or 'sdk' (need the sdk already installed)
                        'signature' => [ // for security if it applies for the service
                            'active' => false, // if it shoud be calculated
                            'send' => false, // if it shoud be sent
                            'field_name' => 'signature',
                            'separator' => '~',
                            'fields' => [ // fields in order that conform the signature
                                '__data__value',
                            ],
                            'encryption' => 'md5', // could be md5, base64, sha1 or sha256
                        ],
                        'if' => [ //conditional, only executes if is all the conditions are fullfilled
                            [
                                'value1' => '__data__topic', //first value to compare
                                'condition' => '=', // comparision, default =, options are '=', '!=', '<', '>', '<=', '>=', 'is_array', 'is_not_array', 'is_null', 'is_not_null'
                                'value2' => 'algo'
                            ], // second value to compare, default ""
                        ],
                        'class' => '\SDK\Class_Name', // The base class for the call
                        'ejecutar_pre_actions' => true, // if the pre_actions should be executed or not for this action, default is true
                        'call_type' => 'function', // type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'action.class' object with 'call_parameters' parameters),'attribute' (calls an attribute of the current 'action.class' object)
                        'create_parameters' => ['__service_parameters__client_id'], // parameters for the creation of the class, for 'function' types, use '__service_parameters__fild_name' for fields in the proccesed service parameters array '__service_parameters_all__' pass the proccesed parameters array including the webhooks urls
                        'pre_functions' => [ //functions to call, in order, previus to the redirec function. 'name' is the name of the function, for non 'static' types
                            'function_name' => null, // leave parameter null for no parameters passed
                            'function_name' => 'parameter', // // parameters to pass to the function, could be an array, use '__service_parameters__fild_name' for fields in the proccesed service parameters array or '__service_parameters_all__' to pass the array
                            'function_name' => '__service_parameters_all__', // parameters to pass to the function, could be an array, use '__service_parameters__fild_name' for fields in the proccesed service parameters array or '__service_parameters_all__' to pass the array
                            'function_name' => [ // parameters to pass to the function, use '__service_parameters__fild_name' for fields in the proccesed service parameters array
                                '__service_parameters__client_id',
                            ],
                        ],
                        'name' => 'init_point', // name of the function/attribute to call
                        'call_parameters' => ['parameter'], // parameters to pass to the function for the 'function' type option, use '__service_parameters__fild_name' for fields in the proccesed service parameters array, '__service_parameters_all__' pass the proccesed parameters array including the webhooks urls
                        'if' => [ //conditional, only executes if is all the conditions are fullfilled
                            [
                                'value1' => '__request__type', //first value to compare, default ""
                                'condition' => '=', // comparision, default "=", options are: "=","!=","<",">","<=",">="
                                'value2' => 'payment'
                            ], // second value to compare, default ""
                        ]
                    ],
                    'http_action' => [
                        'field_name' => '', //name of the key to save the returned values, leave blank for not saving the returned value
                        'type' => 'http', // type of service, options are 'normal' (generate a redirection view), 'http' (make an http request) or 'sdk' (need the sdk already installed)
                        'action' => 'https://algo.algo.com/algo', // the url of the call
                        'method' => 'post', // method of the redirection, options are 'get', 'post', 'put', 'patch', 'delete'
                        'ejecutar_pre_actions' => true, // if the pre_actions should be executed or not for this action, default is true
                        'signature' => [ // for security if it applies for the service
                            'active' => false, // if it shoud be calculated
                            'send' => false, // if it shoud be sent
                            'field_name' => 'signature',
                            'separator' => '~',
                            'fields' => [ // fields in order that conform the signature
                                '__data__value',
                            ],
                            'encryption' => 'md5', // could be md5, base64, sha1 or sha256
                        ],
                        'if' => [ //conditional, only executes if is all the conditions are fullfilled
                            [
                                'value1' => '__data__topic', //first value to compare
                                'condition' => '=', // comparision, default =, options are '=', '!=', '<', '>', '<=', '>=', 'is_array', 'is_not_array', 'is_null', 'is_not_null'
                                'value2' => 'algo'
                            ], // second value to compare, default ""
                        ],
                        'headers' => [], // Headers to send in the Http request, leave empty if not needed
                        'authentication' => [ // Headers to send in the Http request, leave empty if not needed
                            'type' => '', // the authentication type, options are 'basic', 'digest', 'token', 'aws_sign_v4'
                            'user' => '', // for the 'basic' and 'digest' types
                            'region' => 'us-east-1', // for the 'aws_sign_v4' type,
                            'service_name' => 'execute-api', // for the 'aws_sign_v4' type,
                            'key' => '__config_paymentpass__client_id', // for the 'aws_sign_v4' type,
                            'secret' => '__config_paymentpass__client_secret', // for the 'aws_sign_v4' type,
                            'token' => null, // for the 'aws_sign_v4' or 'token' types, '' or null is default for 'aws_sign_v4'
                            'token_type' => 'Bearer', // for the 'token' Type of token (the string before the token), default is Bearer
                        ],
                        'call_parameters' => ['parameter'], // parameters to pass to the http call, use '__service_parameters__fild_name' for fields in the proccesed service parameters array, '__service_parameters_all__' pass the proccesed parameters array including the webhooks urls
                        'if' => [ //conditional, only executes if is all the conditions are fullfilled
                            [
                                'value1' => '__request__type', //first value to compare, default ""
                                'condition' => '=', // comparision, default "=", options are: "=","!=","<",">","<=",">="
                                'value2' => 'payment'
                            ], // second value to compare, default ""
                        ]
                    ],
                ],
                //now, the map to save the thata returned
                'referenceCode' => '__data__merchant_order_info.external_reference', // the reference code of the transaction, internal
                'state' => '__data__payment_info.status',
                'payment_method' => '__data__payment_info.payment_type_id__ - __data__payment_info.payment_method_id',
                'reference' => '__request__data_id',
                'response' => '__data__merchant_order_info.preference_id',
                'payment_state' => '__data__payment_info.status_detail',
                 // valor que retorna el request al webhook
                 'fail_not_found' => true, // si no encuentra un registro en payment_passes con ese referenceCode, falla, por defeto true para evitar ataques, si está en producción no se tiene en cuenta y es false
                 'create_not_found' => false, // si no encuentra el registro y no falla, guardar el registro nuevo, por defecto es false
                'save_data' => '__all__' // __all__ will save all the request data in the response_data field or specify an array of the request fields to use,
            ],
        ],
        'state_codes' => [ // the ones returned from the paymen services asigned to 'state' field
            'success' => [
                "approved" => "apr", // the state field in the bd is 3 varchar long, so the data must be truncated, the key is the value returned and the value is the one saved to the bd
                "authorized" => "aut"
            ],
            'failure' => [
                "rejected" => "rej",
                "cancelled" => "can",
                "refunded" => "ref",
                "charged_back" => "cha",
                "in_mediation" => "inm"
            ],
            'pending' => [
                'in_process' => "inp",
                "pending" => "pen"
            ]
        ],
        'callbacks' => [ //to be called in the response and/or confirmation calls from the payment service depending on the state_code results
            'success' => function ($paymentpass) {
            },
            'failure' => function ($paymentpass) { //if is an internal error, $paymentpass will be a string with the error

            },
            'other' => function ($paymentpass) {
            }
        ]
    ],
    'test' => [ //This will overwrite any production service parameter and leave the others
        'test' => true,
    ],
];
