<?php

use Illuminate\Support\Facades\Artisan;

/**
 * php artisan timesheet:create-db
 * Creates the local MySQL database named in DB_DATABASE (if it doesn't exist).
 */
Artisan::command('timesheet:create-db', function () {
    $host = config('database.connections.mysql.host');
    $port = config('database.connections.mysql.port');
    $name = config('database.connections.mysql.database');

    if (! in_array($host, ['127.0.0.1', 'localhost'], true)) {
        $this->error("DB_HOST is {$host}. This project is meant for a LOCAL database only.");

        return 1;
    }

    $pdo = new PDO(
        "mysql:host={$host};port={$port}",
        config('database.connections.mysql.username'),
        config('database.connections.mysql.password'),
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $this->info("Database `{$name}` is ready on {$host}:{$port}.");

    return 0;
})->purpose('Create the local timesheet MySQL database');
