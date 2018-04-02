<?php

namespace Sirgrimorum\PaymentPass\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPass extends Model {

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    //The validation rules
    public $rules = [
    ];
    //The validation error messages
    public $error_messages = [
    ];
    //For serialization
    protected $with = [
            //'modalidad',
            //'user',
    ];

    public function _construct() {
        $this->error_messages = [
        ];
    }


}
