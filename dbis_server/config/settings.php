<?php

// phpcs:ignoreFile

$IS_PRODUCTIVE = filter_var(getenv('PRODUCTIVE'),FILTER_VALIDATE_BOOLEAN);


if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . "/../");
}

error_reporting(E_ALL & ~E_USER_DEPRECATED);

ini_set('display_errors', '0');
date_default_timezone_set('Europe/Berlin');

$settings['error'] = [
    'display_error_details' => false,
    'log_errors' => true,
    'log_error_details' => true,
];

/**
 * Settings CONFIG
 */
if ($IS_PRODUCTIVE) {
    $settings['config']['translation_dir'] = getenv('PRODUCTIVE_DBIS_SERVER_TRANSLATION_DIR');
    $settings['elasticsearch']['user'] =  getenv('ELASTICSEARCH_USER');
    $settings['elasticsearch']['password'] =  getenv('ELASTICSEARCH_PASSWORD');
    $settings['elasticsearch']['host'] =  getenv('ELASTICSEARCH_HOST');
} else {
    $settings['config']['translation_dir'] = getenv('DBIS_SERVER_TRANSLATION_DIR');
}
/**
 * Settings DBIS DATABASE
 */
if ($IS_PRODUCTIVE) {
    $settings['db']['host'] = getenv('PRODUCTIVE_DBIS_DB_HOST');
} else {
    $settings['db']['host'] = getenv('DBIS_DB_HOST');
}

$settings['db']['user'] = getenv('DBIS_DB_USER');
$settings['db']['driver'] = 'DBIS_pdo_pgsql';
$settings['db']['pass'] = getenv('DBIS_DB_PASSWORD');
$settings['db']['dbname'] = getenv('DBIS_DB_DBNAME');
$settings['db']['port'] = getenv("DBIS_DB_PORT");
$settings['db']['url'] = 'pgsql://' . getenv('DBIS_DB_USER') .
    ':' . getenv('DBIS_DB_PASSWORD') .
    '@' . getenv('DBIS_DB_HOST') .
    ':' . getenv('DBIS_DB_PORT') .
    '/' . getenv('DBIS_DB_NAME');
$settings['db']['dns'] = "pgsql:host=" . $settings['db']['host'] . ";port=" . getenv('DBIS_DB_PORT') .
    ";dbname=" . getenv('DBIS_DB_DBNAME') . ";" .
    "user=" . getenv('DBIS_DB_USER') . ";password=" . getenv('DBIS_DB_PASSWORD') . ";";

$settings['db']['flags'] = [
    // Turn off persistent connections
    // PDO::ATTR_PERSISTENT => false,
    // Enable exceptions
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    // Emulate prepared statements
    // PDO::ATTR_EMULATE_PREPARES => true,
    // Set default fetch mode to array
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Set character set
];
/**
 * Settings UBR DATABASE
 */
$settings['db_ubr']['host'] = getenv('UBR_DB_HOST');
$settings['db_ubr']['user'] = getenv('UBR_DB_USER');
$settings['db_ubr']['driver'] = 'pdo_mysql';
$settings['db_ubr']['pass'] = getenv('UBR_DB_PASSWORD');
$settings['db_ubr']['dbname'] = getenv('UBR_DB_DBNAME');
$settings['db_ubr']['port'] = getenv("UBR_DB_PORT");
$settings['db_ubr']['url'] = 'mysql://' . getenv('UBR_DB_USER') .
    ':' . getenv('UBR_DB_PASSWORD') .
    '@' . getenv('UBR_DB_HOST') .
    ':' . getenv('UBR_DB_PORT') .
    '/' . getenv('UBR_DB_NAME');
$settings['db_ubr']['dns'] = "mysql:host=" . getenv('UBR_DB_HOST') . ";port=" . getenv('UBR_DB_PORT') .
    ";dbname=" . getenv('UBR_DB_DBNAME') . ";" .
    "user=" . getenv('UBR_DB_USER') . ";password=" . getenv('UBR_DB_PASSWORD') . ";";
$settings['db_ubr']['flags'] = [
    // Turn off persistent connections
    // PDO::ATTR_PERSISTENT => false,
    // Enable exceptions
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    // Emulate prepared statements
    // PDO::ATTR_EMULATE_PREPARES => true,
    // Set default fetch mode to array
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Set character set
];

/**
 * Settings TWIG
 */
$settings['twig'] = [
    'paths' => [
        __DIR__ . '/../templates',
    ],
    'options' => [
        // Should be set to true in production
        'cache_enabled' => false,
        'cache_path' => __DIR__ . '/../tmp/twig'
    ],
];

// echo(getenv('PRODUCTIVE_DBIS_SERVER_PUBLIC_FOLDER'));

/**
 * Settings APACHE
 */
if ($IS_PRODUCTIVE) {
    $settings['public'] = "/opt/dbis-projekt/dbis_server/public";
} else {
    $settings['public'] = "/var/www/public";
}

return $settings;
