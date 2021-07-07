<?php

return [

    'migrations' => 'migrations',

    'default' => env('DB_CONNECTION', 'mysql_ec'),

    'connections' => [

        'mysql_cdb' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::ATTR_EMULATE_PREPARES => true
            ]) : [],
        ],

        'mysql_ec' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB2_HOST', '127.0.0.1'),
            'port' => env('DB2_PORT', '3306'),
            'database' => env('DB2_DATABASE', 'forge'),
            'username' => env('DB2_USERNAME', 'forge'),
            'password' => env('DB2_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                PDO::ATTR_EMULATE_PREPARES => true
            ]) : [],
        ],
    ],
];
