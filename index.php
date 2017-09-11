<?php

require 'vendor/autoload.php';
require_once 'ecomClient.php';

use LightspeedHQ\Ecom\EcomClient;

session_start();

$cluster = 'eu1';     // eu1 or us1
$language = 'en';     // Shop language
$key = 'xxxx';        // API key
$secret = 'xxxx';     // API secret

$client = new EcomClient($cluster, $language, $key, $secret);

for ($i=1; $i <= 3500; $i++) {
    $res = $client->get('customers');
    print_r($res->getHeader('X-RateLimit-Remaining')[0]);
    echo(' | ');
    print_r($res->getHeader('X-RateLimit-Reset')[0]);
    echo PHP_EOL;
}
