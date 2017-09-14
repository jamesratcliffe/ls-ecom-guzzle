<?php

require 'vendor/autoload.php';

use LightspeedHQ\Ecom\EcomClient;

session_start();

$cluster = 'us1';     // eu1 or us1
$language = 'us';     // Shop language
$key = 'xxxx';        // API key
$secret = 'xxxx';     // API secret

$client = new EcomClient($cluster, $language, $key, $secret);

// GET request with some URL paramters.
$query = ['since_id', 1];
$response = $client->get('customers', ['query' => $query]);
$customers = json_decode($response->getBody(), true)['Item'];
var_dump($customers[0]);

// POST request to create a discount code
$payload = [
    'discount' => [
        'discount' => 5,
        'isActive' => true,
        'minumumAmount' => 50,
        'applyTo' => 'productscategories',
        'endDate' => '2018-01-01',
        'type' => 'percentage',
        'code' => '5PERCENT',
        'startDate' => '2017-01-01',
        'usageLimit' => 9999,
    ]
];
$response = $client->post('discounts', ['json' => $payload]);
var_dump(json_decode($response->getBody(), true));
