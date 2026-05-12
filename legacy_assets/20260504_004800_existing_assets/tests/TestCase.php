<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $basePath = Application::inferBasePath();
        $testingDatabase = $basePath.'/database/testing.sqlite';

        if (! file_exists($testingDatabase)) {
            touch($testingDatabase);
        }

        $testingAppKey = 'base64:lPP0cLIJQzly+5SHiIrNUafPQEQIdK53hvASczg1nAY=';

        putenv('APP_ENV=testing');
        putenv('APP_KEY='.$testingAppKey);
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE='.$testingDatabase);
        putenv('CACHE_STORE=array');
        putenv('SESSION_DRIVER=array');
        putenv('QUEUE_CONNECTION=sync');

        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_KEY'] = $testingAppKey;
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $testingDatabase;
        $_ENV['CACHE_STORE'] = 'array';
        $_ENV['SESSION_DRIVER'] = 'array';
        $_ENV['QUEUE_CONNECTION'] = 'sync';

        $_SERVER['APP_ENV'] = 'testing';
        $_SERVER['APP_KEY'] = $testingAppKey;
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = $testingDatabase;
        $_SERVER['CACHE_STORE'] = 'array';
        $_SERVER['SESSION_DRIVER'] = 'array';
        $_SERVER['QUEUE_CONNECTION'] = 'sync';

        $app = require $basePath.'/bootstrap/app.php';
        $app->loadEnvironmentFrom(file_exists($basePath.'/.env.testing') ? '.env.testing' : '.env.testing.example');
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
