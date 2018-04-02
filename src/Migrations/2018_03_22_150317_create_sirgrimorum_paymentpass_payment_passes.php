<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSirgrimorumPaymentpassPaymentPasses extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('payment_passes', function($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('referenceCode', 255)->nullable();
            $table->string('type', 3)->nullable();
            $table->string('state', 3)->default('reg');
            $table->string('payment_method', 255)->nullable();
            $table->string('reference', 255)->nullable();
            $table->string('response', 255)->nullable();
            $table->datetime('response_date')->nullable();
            $table->datetime('confirmation_date')->nullable();
            $table->string('payment_state', 255)->nullable();
            $table->longtext('response_data')->nullable();
            $table->longtext('confirmation_data')->nullable();
            $table->longtext('creation_data')->nullable();
            $table->integer('process_id')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::drop('payment_passes');
    }

}
