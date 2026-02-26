<?php

namespace Sirgrimorum\PaymentPass\Tests\Fixtures;

class StubSdkClass
{
    public string $myAttribute = 'attribute_value';

    public function compute(): string
    {
        return 'function_result';
    }

    public static function staticCompute(): string
    {
        return 'static_result';
    }
}
