<?php

namespace Sirgrimorum\PaymentPass\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sirgrimorum\PaymentPass\PaymentPassServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('payment_passes');
        $this->app['db']->connection()->getSchemaBuilder()->create('payment_passes', function ($table) {
            $table->id();
            $table->string('referenceCode')->unique()->nullable();
            $table->string('process_id')->nullable();
            $table->string('state')->nullable();
            $table->string('type')->nullable();
            $table->text('creation_data')->nullable();
            $table->text('response_data')->nullable();
            $table->dateTime('response_date')->nullable();
            $table->text('confirmation_data')->nullable();
            $table->dateTime('confirmation_date')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            PaymentPassServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', [
            'driver'   => 'mysql',
            'host'     => '127.0.0.1',
            'port'     => '3306',
            'database' => 'sirgrimorum_test',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'   => '',
        ]);
    }
}
