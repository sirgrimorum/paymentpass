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

    public function getReferencia(){
        $referencia = "";
        
        switch (config("services.payu.mode")) {
            case "sha256":
                $datos['signature'] = Hash::make($strHash);
                break;
            case "sha1":
                $datos['signature'] = sha1($strHash);
                break;
            default:
            case "md5":
                $datos['signature'] = md5($strHash);
                break;
        }
        return md5($this->id . "-" . $this->name);
    }
    
    public static function getByReferencia($referencia){
        return Registro::all()->filter(function($registro) use ($referencia){
            return ($registro->getReferencia()==$referencia);
        })->first();
    }
    
    public function user() {
        return $this->belongsTo('App\User');
    }

}
