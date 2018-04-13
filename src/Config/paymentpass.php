<?php

return [
    'route_prefix' => 'payment',
    'redirect_blade_extend' => 'layouts.app', // blade template to extend in the redirect view
    'redirect_blade_content_section' => 'content',
    'result_blade_extend' => 'layouts.app', // blade template to extend in the result view
    'result_blade_content_section' => 'content',
    'js_section' => 'selfjs', // fstag to insert the scripts
    'status_messages_key' => 'status', // for flash and sessions
    'error_messages_key' => 'error', // for flash and sessions
    'redirect_container_style' => 'padding-top: 200px; padding-bottom: 200px;',
    'redirect_container_class' => '',
    'redirect_pre_html' => '', // html to insert before the container div in the redirect view
    'redirect_post_html' => '', // html to insert after the container div in the redirect view
    'result_container_style' => 'padding-top: 200px; padding-bottom: 200px;',
    'result_container_class' => '',
    'result_pre_html' => '', // html to insert before the container div in the result view
    'result_post_html' => '', // html to insert after the container div in the result view
    'available_services' => ['payu'],
    'production' => false,
    'result_template' => 'paymentpass::result', // blade template to show in the result
    'services_production' => [ // original configuration for the services, test mode will overwrite this with the services_test configurations
        'mercadopago' => [
            'type' => 'sdk', // type of service, default is 'normal', options are 'normal' or 'sdk' (need the sdk already installed)
            'action' => '', // where to redirect, if sdk type, ti will be overwritten
            'method' => 'url', // method of the redirection, use url if no form is used
            'client_id' => '', // if necesary to be called using __config_paymentpass__ directive
            'client_secret' => '', // if necesary to be called using __config_paymentpass__ directive
            'pre_sdk'=>[ // things to do before in the sdk for type 'sdk'
                'setClientId'=>[ // only as reference
                    'class' => '\MercadoPago\SDK', // class name as key
                    'type' => 'static', // type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'sdk_call.class' object with 'parameters' parameters), 'attribute' (calls an attribute of the current 'sdk_call.class' object)
                    'name' => 'setClientId', // name of the function/attribute to call
                    'call_parameters' => '__config_paymentpass__client_id', // parameters to pass to the function, could be an array
                ],
                'setClientSecret'=>[
                    'class' => '\MercadoPago\SDK', // class name as key
                    'type' => 'static', // type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'sdk_call.class' object with 'parameters' parameters), 'attribute' (calls an attribute of the current 'sdk_call.class' object)
                    'name' => 'setClientSecret', // name of the function/attribute to call
                    'call_parameters' => '__config_paymentpass__client_secret', // parameters to pass to the function, could be an array
                ],
                'other'=>[ //other calls
                    'class' => '\Class\Name', // class name as key
                    'type' => 'static', // type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'sdk_call.class' object with 'parameters' parameters), 'attribute' (calls an attribute of the current 'sdk_call.class' object)
                    'name' => 'function', // name of the function/attribute to call
                    'create_parameters' => ['parameter'], // parameters for the creation of the class, for 'function' types,
                    'call_parameters' => ['parameter'], // parameters to pass to the function, could be an array
                ],
            ],
            'sdk_call'=>[ // get the redirect from the sdk for the 'sdk' type
                'type' => 'attribute', // type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'sdk_call.class' object with 'parameters' parameters),'attribute' (calls an attribute of the current 'sdk_call.class' object)
                'class' => '\MercadoPago\Preference',
                'create_parameters' => ['__service_parameters_all__'], // parameters to pass to de create, '__service_parameters_all__' pass the proccesed parameters array including the responses urls
                'pre_functions' =>[ //functions to call, in order, previus to the redirec function. 'name' is the name of the function, for non 'static' types
                    'save'=>'', // leave parameter empty for no parameters passed
                    'name'=>'parameter', // leave parameter empty for no parameters passed
                    'name'=>'__service_parameters__parameters.currency', // parameters to pass to the function, could be an array, use '__servic_parameters__fild_name' for fields in the proccesed service parameters array or '__service_parameters_all__' to pass the array
                    'name'=>[
                        '__service_parameters__client_id', // parameters to pass to the function, use '__servic_parameters__fild_name' for fields in the proccesed service parameters array 
                        ],
                ],
                'name' => 'init_point', // name of the function/attribute to call
                'call_parameters'=> ['parameter'], // for the 'function' option, operate same as pre_function parameters
            ],
            'parameters' => [
                'items' => [
                    0 => [
                        'id' => '__data__id_product',
                        'title' => '__data__product_name',
                        'description' => '__data__description',
                        'quantity' => '__data__numero',
                        'currency' => 'COP',
                        'unit_price' => '__data__product_value',
                        'picture_url' => '__asset____data__product_picture', //first evaluate '__data__product_picture' and then asset of that
                        'category_id' => '__data__product_category',
                    ]
                ],
                'items' => '__data__items', // other option 
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
            'referenceCode' => [ //referenceCode of the transaction intent
                'send' => true, // if it should be send to the payment service
                'field_name' => 'external_reference',
                'separator' => '~',
                'fields' => [ // fields in order that conform the code
                    '__data__cedula',
                    '__data__email',
                ],
                'type' => 'auto', // 'auto' (PaymentPass builds using separator, fields and encryption), '__config__[key]', '__trans__[key]', '__data__[key using dot notation]'
                'encryption' => 'md5', // could be md5, sha1 or sha256
            ],
            'signature' => [ // for security if it applies for the service
                'active' => false, // if it shoud be calculated 
                'send' => false, // if it shoud be sent
                'field_name' => 'signature',
                'separator' => '~',
                'fields' => [ // fields in order that conform the signature
                    '__config_paymentpass__ApiKey',
                    '__config_paymentpass__merchantId',
                    '__config_paymentpass__referenceCode.value',
                    '__data__value',
                    '__config_paymentpass__parameters.currency',
                ],
                'encryption' => 'md5', // could be md5, sha1 or sha256
            ],
            'responses' => [ // type of responses to handle
                'notification' => [ // how to map the PaymentPass model with the post or get data for this specifc response call
                    'url' => "", // url of the callback to this response, if blank or not present it will be route("paymentpass::response",["service"=>"this service","responseType"=>"this response type"], use: __route__ will evaluate route() and __url__ will evaluate url(), use , to separate parameters and json notation for array parameters
                    'url_field_name' => "notification_url", //name of the field name for the url to send in parameters
                    '_pre'=>[ //things to do before to the save data, it will save each element acording to its name in order to be processed
                        'setClientId' => [ //for reference, not used
                            'key_name' => '', //name of the key to save the returned values, leave blank for not saving the returned value
                            'type' => 'static', // type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'sdk_call.class' object with 'parameters' parameters), 'attribute' (calls an attribute of the current 'sdk_call.class' object)
                            'class' => '\MercadoPago\SDK', // class name of the instance
                            'name' => 'setClientId', // name of the function/attribute to call
                            'call_parameters' => '__config_paymentpass__client_id', // parameters to pass to the function, could be an array
                        ],
                        'setClientSecret' => [ //for reference, not used
                            'key_name' => '', //name of the key to save the returned values, leave blank for not saving the returned value
                            'type' => 'static', // type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'sdk_call.class' object with 'parameters' parameters), 'attribute' (calls an attribute of the current 'sdk_call.class' object)
                            'class' => '\MercadoPago\SDK', // class name of the instance
                            'name' => 'setClientSecret', // name of the function/attribute to call
                            'call_parameters' => '__config_paymentpass__client_secret', // parameters to pass to the function, could be an array
                        ],
                        'payment_info' => [ //for reference, not used
                            'key_name' => 'payment_info', //name of the key to save the returned values, leave blank for not saving the returned value
                            'type' => 'function', // type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'sdk_call.class' object with 'parameters' parameters), 'attribute' (calls an attribute of the current 'sdk_call.class' object)
                            'class' => '\MercadoPago\Payment', // class name of the instance
                            'name' => 'get', // name of the function/attribute to call
                            'create_parameters' => '', // parameters for the creation of the class, for 'function' types, could be an array, use __request__ for request data
                            'call_parameters' => '__request__id', // parameters to pass to the function, could be an array, use __data__ for request data
                        ],
                        'order_info' => [ //for reference, not used
                            'key_name' => 'merchant_order_info', //name of the key to save the returned values, leave blank for not saving the returned value
                            'type' => 'function', // type of the call, options are: 'static' (nedds a class name), 'function' (calls a function to the current 'sdk_call.class' object with 'parameters' parameters), 'attribute' (calls an attribute of the current 'sdk_call.class' object)
                            'class' => '\MercadoPago\MerchantOrder', // class name of the instance
                            'name' => 'get', // name of the function/attribute to call
                            'create_parameters' => '', // parameters for the creation of the class, for 'function' types, could be an array, use __request__ for request data
                            'call_parameters' => '__request__id', // parameters to pass to the function, could be an array, use __request__ for request data
                        ],
                    ],
                    //now, the map to save the thata returned
                    'referenceCode' => '__config_paymentpass__referenceCode.value', // the reference code of the transaction, internal
                    'state' => 'nose',
                    'payment_method' => 'nose',
                    'reference' => '__request__id',
                    'response' => '__request__merchant_order_info.response',
                    'payment_state' => '__request__merchant_order_info.response.payments',
                    'save_data' => '__all__' // __all__ will save all the request data in the response_data field or specify an array of the request fields to use,
                ],
                'success' => [ // how to map the PaymentPass model with the post or get data for this specifc response call
                    'url' => "", // url of the callback to this response, if blank or not present it will be route("paymentpass::response",["service"=>"this service","responseType"=>"this response type"], use: __route__ will evaluate route() and __url__ will evaluate url(), use , to separate parameters and json notation for array parameters
                    'url_field_name' => "back_urls.success", //name of the field name for the url to send in parameters
                    'referenceCode' => '__config_paymentpass__referenceCode.value', // the reference code of the transaction, internal
                    'state' => '4',
                    'payment_method' => 'payment_method_type',
                    'reference' => '__config_paymentpass__referenceCode.value',
                    'response' => 'response_message_pol',
                    'payment_state' => 'response_code_pol',
                    'save_data' => '__all__' // __all__ will save all the request data in the response_data field or specify an array of the request fields to use,
                ],
                'failure' => [ // how to map the PaymentPass model with the post or get data
                    'url' => "", // url of the callback to this response,recomended, leave blank. if blank or not present it will be route("paymentpass::response",["service"=>"this service","responseType"=>"this response type"]), use: __route__ will evaluate route() and __url__ will evaluate url(), use , to separate parameters and json notation for array parameters
                    'url_field_name' => "back_urls.failure", //name of the field name for the url to send in parameters
                    'referenceCode' => '__config_paymentpass__referenceCode.value', // the reference code of the transaction, internal
                    'state' => '5',
                    'payment_method' => 'polPaymentMethodType',
                    'reference' => '__config_paymentpass__referenceCode.value',
                    'response' => 'lapResponseCode',
                    'payment_state' => 'lapTransactionState',
                    'save_data' => '__all__' // __all__ will save all the request data in the response_data field or specify an array of the request fields to use,
                ],
            ],
            'state_codes' => [ // the ones returned from the paymen services asigned to 'state' field
                'success' => ["4"],
                'failure' => ["5","6","104"],
            ],
            'callbacks' => [ //to be called in the response and/or confirmation calls from the payment service depending on the state_code results
                'success' => function($paymentpass){
    
                },
                'failure' => function($paymentpass){ //if is an internal error, $paymentpass will be a string with the error
                    
                },
                'other' => function($paymentpass){
                    
                }
            ]
        ],
        'payu' => [
            'type' => 'normal', // type of service, default is 'normal', options are 'normal' or 'sdk' (need the sdk already installed)
            'action' => 'https://checkout.payulatam.com/ppp-web-gateway-payu/', // where to redirectwhere to redirect, if sdk type, ti will be overwritten
            'method' => 'post', // method of the redirection
            'merchantId' => '', // if necesary to be called using __config_paymentpass__ directive
            'accountId' => '', // if necesary to be called using __config_paymentpass__ directive
            'ApiKey' => '', // if necesary to be called using __config_paymentpass__ directive
            'ApiLogin' => '', // if necesary to be called using __config_paymentpass__ directive
            'PublicKey' => '', // if necesary to be called using __config_paymentpass__ directive
            'parameters' => [
                'merchantId' => '__config_paymentpass__merchantId', // __config_paymentpass__ will get the value from this array
                'accountId' => '__config_paymentpass__accountId',
                'currency' => 'COP',
                'test' => '0',
                'description' => '__data__description', // __data__ will get the value from the data array passed could also be __trans__ for trans() or __trans_article__ for sirgrimorum/transarticles package
                'amount' => '__data__value',
                'buyerEmail' => '__data__email',
                'payerEmail' => '__data__email',
                'buyerFullName' => '__data__name',
                'payerFullName' => '__data__name',
                'payerDocument' => '__data__cedula',
                'mobilePhone' => '__data__telefono',
                'payerPhone' => '__data__telefono',
                'taxReturnBase' => '__auto__taxReturnBase|0.19,__data__value__,2', // auto is __auto__[type, options are taxReturnBase|tax,value,number of decimals ; tax|tax,base,number of decimals ; valueToPay|tax,base,number of decimals]|[parameters separates with ,]|[fields separates with ,] - auto evaluates after everithing else
                'tax' => '__auto__tax|0.19,taxReturnBase,2',
            ],
            'referenceCode' => [ //referenceCode of the transaction intent
                'send' => true, // if it should be send to the payment service
                'field_name' => 'referenceCode', 
                'separator' => '~',
                'fields' => [ // fields in order that conform the code
                    '__data__cedula',
                    '__data__email',
                ],
                'type' => 'auto', // 'auto' (PaymentPass builds using separator, fields and encryption), '__config__[key]', '__trans__[key]', '__data__[key using dot notation]'
                'encryption' => 'md5', // could be md5, sha1 or sha256
            ],
            'signature' => [ // for security if it applies for the service
                'active' => true, // if it shoud be calculated 
                'send' => true, // if it shoud be sent
                'field_name' => 'signature',
                'separator' => '~',
                'fields' => [ // fields in order that conform the signature
                    '__config_paymentpass__ApiKey',
                    '__config_paymentpass__merchantId',
                    '__config_paymentpass__referenceCode.value',
                    '__data__value',
                    '__config_paymentpass__parameters.currency',
                ],
                'encryption' => 'md5', // could be md5, sha1 or sha256
            ],
            'responses' => [ // type of responses to handle
                'confirmation' => [ // how to map the PaymentPass model with the post or get data for this specifc response call
                    'url' => "__route__paymentpass::response,{'service':'payu','responseType':'confirmation'}", // url of the callback to this response, if blank or not present it will be route("paymentpass::response",["service"=>"this service","responseType"=>"this response type"], use: __route__ will evaluate route() and __url__ will evaluate url(), use , to separate parameters and json notation for array parameters
                    'url_field_name' => "confirmationUrl", //name of the field name for the url to send in parameters
                    'referenceCode' => '__request__reference_sale',  // the reference code of the transaction, internal
                    'state' => '__request__state_pol',
                    'payment_method' => '__request__payment_method_type',
                    'reference' => '__request__reference_pol',
                    'response' => '__request__response_message_pol',
                    'payment_state' => '__request__response_code_pol',
                    'save_data' => '__all__' // __all__ will save all the request data in the response_data field or specify an array of the request fields to use,
                ],
                'response' => [ // how to map the PaymentPass model with the post or get data
                    'url' => "__route__paymentpass::response,{'service':'payu','responseType':'response'}", // url of the callback to this response,recomended, leave blank. if blank or not present it will be route("paymentpass::response",["service"=>"this service","responseType"=>"this response type"]), use: __route__ will evaluate route() and __url__ will evaluate url(), use , to separate parameters and json notation for array parameters
                    'url_field_name' => "responseUrl", //name of the field name for the url to send in parameters
                    'referenceCode' => '__request__referenceCode', // the reference code of the transaction, internal
                    'state' => '__request__transactionState',
                    'payment_method' => '__request__polPaymentMethodType',
                    'reference' => '__request__reference_pol',
                    'response' => '__request__lapResponseCode',
                    'payment_state' => '__request__lapTransactionState',
                    'save_data' => '__all__' // __all__ will save all the request data in the response_data field or specify an array of the request fields to use,
                ],
            ],
            'state_codes' => [ // the ones returned from the paymen services asigned to 'state' field
                'success' => ["4"],
                'failure' => ["5","6","104"],
            ],
            'callbacks' => [ //to be called in the response and/or confirmation calls from the payment service depending on the state_code results
                'success' => function($paymentpass){
    
                },
                'failure' => function($paymentpass){ //if is an internal error, $paymentpass will be a string with the error
                    
                },
                'other' => function($paymentpass){
                    
                }
            ]
        ]
    ],
    'services_test' => [ //This will overwrite any production service parameter and leave the others
        'payu' => [
            'merchantId' => '508029',
            'accountId' => '512321',
            'ApiKey' => '4Vj8eK4rloUd272L48hsrarnUA',
            'ApiLogin' => 'pRRXKOl8ikMmt9u',
            'PublicKey' => 'PK644bj0e7J4g9609566gT130i',
            'action' => 'https://sandbox.checkout.payulatam.com/ppp-web-gateway-payu/',
            'parameters' => [
                'test' => '1',
            ],
        ],
        'mercadopago' => [
            'sdk_call' => [
                'name' => 'sandbox_init_point'
            ],
        ]
    ],
];
