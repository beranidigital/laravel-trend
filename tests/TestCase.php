<?php

namespace Flowframe\Trend\Tests;

use Flowframe\Trend\TrendServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . 'test/database/migrations');


        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Flowframe\\Trend\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
        \Flowframe\Trend\Tests\Models\SimpleModel::factory()->count(1000)->create();
    }

    protected function getPackageProviders($app)
    {
        return [
            TrendServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');


        $migration = include __DIR__ . '/database/migrations/create_simple_table.php';
        $migration->up();


    }
}
