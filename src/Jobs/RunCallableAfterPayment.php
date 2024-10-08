<?php

namespace Sirgrimorum\PaymentPass\Jobs;

use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Sirgrimorum\PaymentPass\Models\PaymentPass;
use Sirgrimorum\PaymentPass\PaymentPassHandler;

class RunCallableAfterPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $paymentpass;

    protected $state;
    protected $service;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($state, $service, PaymentPass $paymentpass)
    {
        $this->paymentpass = $paymentpass;
        $this->state = $state;
        $this->service = $service;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (in_array($this->service, config("sirgrimorum.paymentpass.available_services"))) {
            $curConfig = (new PaymentPassHandler($this->service))->config;
            $callbackFunc = Arr::get($curConfig, "service.callbacks.{$this->state}", Arr::get($curConfig, "service.callbacks.other", ""));
            if (is_callable($callbackFunc)) {
                call_user_func($callbackFunc, $this->paymentpass);
            }
        }
    }
}
