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
    'result_template' => 'paymentpass.result', // blade template to show in the result
    'services_production' => [ // original configuration for the services, test mode will overwrite this with the services_test configurations
        'payu' => [
            'merchantId' => '',
            'accountId' => '',
            'ApiKey' => '',
            'ApiLogin' => '',
            'PublicKey' => '',
            'parameters' => [
                'merchantId' => '__config_paymentpass__merchantId', // __config_paymentpass__ will get the value from this array
                'accountId' => '__config_paymentpass__accountId',
                'currency' => 'COP',
                'tax' => 0.19,
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
                'taxReturnBase' => '__auto__taxReturnBase|tax,__data__value__', // auto is __auto__[type, options are taxReturnBase|tax,value, tax|tax,base, valueToPay|tax,base]|[parameters separates with ,]|[fields separates with ,] - auto evaluates after everithing else
                'tax' => '__auto__tax|0.19,taxReturnBase',
            ],
            'referenceCode' => [ //referenceCode of the transaction intent
                'send' => true, // if it should be send to the payment service
                'field_name' => 'referenceCode',
                'separator' => '~',
                'fields' => [ // fields in order that conform the code
                    '__data__cedula',
                    '__data_email',
                ],
                'type' => 'auto', // 'auto' (PaymentPass builds using separator, fields and encryption), '__config__[key]', '__trans__[key]', '__data__[key using dot notation]'
                'encryption' => 'md5', // could be md5, sha1 or sha256
            ],
            'signature' => [ // for security if it applies for the service
                'active' => true,
                'field_name' => 'signature',
                'separator' => '~',
                'fields' => [ // fields in order that conform the signature
                    '__config_paymentpass__ApiKey',
                    '__config_paymentpass__merchantId',
                    '__config_paymentpass__referenceCode.value',
                    '__data__value',
                    '__config_paymentpass__extra_parameters.currency',
                ],
                'encryption' => 'md5', // could be md5, sha1 or sha256
            ],
            'action' => 'https://checkout.payulatam.com/ppp-web-gateway-payu/', // where to redirect
            'method' => 'post', // method of the redirection
            'confirmation' => [ // how to map the PaymentPass model with the post or get data
                'url' => "__route__paymentpass::response,{'service':'payu','responseType':'confirmation}", // __route__ will evaluate route() and __url__ will evaluate url(), use , to separate parameters and json notation for array parameters
                'referenceCode' => 'reference_sale',
                'state' => 'state_pol',
                'payment_method' => 'payment_method_type',
                'reference' => 'reference_pol',
                'response' => 'response_message_pol',
                'payment_state' => 'response_code_pol',
                'save_data' => [
                    '__all__' // __all__ will save all the request data in the confirmation_data field or specify the request fields to use
                ],
            ],
            'response' => [ // how to map the PaymentPass model with the post or get data
                'url' => "__route__paymentpass::response,{'service':'payu','responseType':'response}", // url of the callback to responso
                'referenceCode' => 'referenceCode',
                'state' => 'transactionState',
                'payment_method' => 'polPaymentMethodType',
                'reference' => 'reference_pol',
                'response' => 'lapResponseCode',
                'payment_state' => 'lapTransactionState',
                'save_data' => [
                    '__all__' // __all__ will save all the request data in the response_data field or specify the request fields to use
                ],
            ],
            'state_codes' => [ // the ones returned from the paymen services asigned to 'state' field
                'success' => ["4"],
                'failure' => ["5","6","104"],
            ],
            'callbacks' => [ //to be called in the response and/or confirmation calls from the payment service depending on the state_code results
                'success' => function($paymentpass){
    
                },
                'failure' => function($paymentpass){
                    
                },
                'other' => function($paymentpass){
                    
                }
            ]
        ]
    ],
    'services_test' => [ //This will overwrite any production service parameter
        'payu' => [
            'merchantId' => '508029',
            'accountId' => '512321',
            'ApiKey' => '4Vj8eK4rloUd272L48hsrarnUA',
            'ApiLogin' => 'pRRXKOl8ikMmt9u',
            'PublicKey' => 'PK644bj0e7J4g9609566gT130i',
            'action' => 'https://sandbox.checkout.payulatam.com/ppp-web-gateway-payu/',
            'extra_parameters' => [
                'test' => '1',
            ],
        ]
    ],
];
