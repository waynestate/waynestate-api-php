waynestate-api-php
==================

PHP wrapper for the Wayne State University API http://api.wayne.edu/

Installation
------------

To install this library, run the command below and you will get the latest version

    composer require nickdenardis/waynestate-api-php

Usage
------------

Create the object

    # start.php

    use Waynestate\Api\Connector;

    ...

    $api = new Waynestate\Api\Connector(API_KEY);
    
    // Set the params
    $params = array(
        'promo_group_id' => 123,
        'is_active' => '1',
        'ttl' => TTL,
    );
    
    // Get promotions from the API
    $promos = $api->sendRequest('cms/promotions/listing', $params);

 Best used with the following packages:
 
 * [ParsePromos](https://github.com/waynestate/parse-promos)
 * [ParseMenu](https://github.com/waynestate/parse-menu)