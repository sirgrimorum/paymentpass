<?php

return [
    'labels' => [
        'close' => 'Cerrar',
    ],
    'messages'=>[
        'not_found' => 'La transacción con el código de referencia <strong>:referenceCode</strong> no ha sido encontrada. Por favor póngase en contacto con el administrador',
        'error' => ':error. Por favor póngase en contacto con el administrador'
    ],
    'services' => [
        'payu' => [
            'messages' => [
                "4" => "<h3 class='mt-0'>La transacción ha sido aprobada.</h3>Su código de referencia es <strong>:referenceCode</strong>",
                "5" => "<h3 class='mt-0'>La transacción ha expirado.</h3>Su código de referencia es <strong>:referenceCode</strong>",
                "6" => "<h3 class='mt-0'>La transacción ha sido declinada.</h3>Su código de referencia es <strong>:referenceCode</strong>",
                "7" => "<h3 class='mt-0'>La transacción está pendiente.</h3>Su código de referencia es <strong>:referenceCode</strong>",
                "104" => "<h3 class='mt-0'>Ha ocurrido un error en la transacción.</h3>Su código de referencia es <strong>:referenceCode</strong>",
            ],
            "selects" => [
                "state" => [
                    "reg" => "Registrado",
                    "4" => "Aprobada",
                    "5" => "Expirada",
                    "6" => "Declinada",
                    "7" => "Pendiente",
                    "104" => "Error",
                ],
                "payment_method" => [
                    "2" => "Tarjeta de crédito",
                    "4" => "PSE",
                    "5" => "Débito ACH",
                    "6" => "Tarjeta débito",
                    "7" => "Pago en efectivo",
                    "8" => "Pago referenciado",
                    "10" => "Pago en banco",
                ],
            ],
        ],
    ],
];
