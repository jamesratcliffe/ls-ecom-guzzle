Guzzle Client for Lightspeed eCommerce
======================================

The class is an extension of the Guzzle 6 PHP HTTP Client for use with the Lightspeed eCommerce API.

It works the same way as the standard Guzzle Client, but takes care of rate limiting.

**This package was created for demonstration purposes and comes with no waranty.**

## Installation

Use this commmand to install with Composer:

```shell
$ composer require lightspeedhq/ls-ecom-guzzle:~1.0
```

Alternatively, you can add these lines to your `composer.json` file:

```json
    "require": {
        "lightspeedhq/ls-ecom-guzzle": "~1.0"
    }
```

## Usage Example

```php
<?php

require 'vendor/autoload.php';
use LightspeedHQ\Ecom\EcomClient;

$cluster = 'us1';     // eu1 or us1
$language = 'us';     // Shop language
$key = 'xxxx';        // API key
$secret = 'xxxx';     // API secret

$client = new EcomClient($cluster, $language, $key, $secret);

// GET request with some URL paramters.
$query = ['since_id', 1];
$response = $client->get('customers', ['query' => $query]);
$customers = json_decode($response->getBody(), true)['customers'];
echo '<pre>';
echo '<h3>GET Test</h3>';
var_dump($customers[0]);
echo '</pre>';

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
echo '<h3>POST Test</h3>';
echo '<pre>';
var_dump(json_decode($response->getBody(), true));
echo '</pre>';
```
