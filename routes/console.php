<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('app:setup-testing', function () {
    $connection = config('database.connections.mysql');
    $testDatabase = $connection['database'].'_test';

    if (str_ends_with($connection['database'], '_test')) {
        $testDatabase = $connection['database'];
    }

    // Create the dedicated test database (never touches the main database).
    DB::connection('mysql')->statement(
        "CREATE DATABASE IF NOT EXISTS `{$testDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    if (! file_exists(base_path('.env.testing'))) {
        $env = file_get_contents(base_path('.env'));
        $env = preg_replace('/^APP_ENV=.*$/m', 'APP_ENV=testing', $env);
        $env = preg_replace('/^DB_DATABASE=.*$/m', "DB_DATABASE={$testDatabase}", $env);
        file_put_contents(base_path('.env.testing'), $env);
        $this->info('Created .env.testing');
    }

    $this->info("Test database [{$testDatabase}] is ready.");
})->purpose('Create the MySQL test database and .env.testing');
