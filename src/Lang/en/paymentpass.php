<?php

return [
    'labels' => [
        'close' => 'Close',
    ],
    'messages'=>[
        'not_found' => 'No transaction found with the reference code <strong>:referenceCode</strong>. Please contact the administrator.'
    ],
    'services' => [
        'payu' => [
            'messages' => [
                "4" => "<h3 class='mt-0'>The transaction has been approved.</h3>Your reference code is <strong>:referenceCode</strong>",
                "5" => "<h3 class='mt-0'>The transaction has expired.</h3>Your reference code is <strong>:referenceCode</strong>",
                "6" => "<h3 class='mt-0'>The transaction has been declined.</h3>Your reference code is <strong>:referenceCode</strong>",
                "7" => "<h3 class='mt-0'>The transaction is pending.</h3>SYour reference code is <strong>:referenceCode</strong>",
                "104" => "<h3 class='mt-0'>An error has occur.</h3>Your reference code is <strong>:referenceCode</strong>",
            ],
            "selects" => [
                "state" => [
                    "reg" => "Registered",
                    "4" => "Approved",
                    "5" => "Expired",
                    "6" => "Declined",
                    "7" => "Pending",
                    "104" => "Error",
                ],
                "payment_method" => [
                    "2" => "Credit Card",
                    "4" => "PSE",
                    "5" => "ACH Debit",
                    "6" => "Debit Card",
                    "7" => "Cash",
                    "8" => "Referenced",
                    "10" => "Bank pay",
                ],
            ],
        ],
    ],
];
