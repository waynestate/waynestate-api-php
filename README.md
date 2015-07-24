Wayne State University API wrapper v1
==================

PHP wrapper for the Wayne State University API http://api.wayne.edu/

Installation
------------

To install this library, run the command below and you will get the latest version

    composer require waynestate/waynestate-api

Usage
------------

Create the object

    # start.php

    use Waynestate\Api\Connector;

    ...

    $api = new Connector(API_KEY);

    // Set the params
    $params = array(
        'promo_group_id' => 123,
        'is_active' => '1',
        'ttl' => TTL,
    );

    // Get promotions from the API
    $promos = $api->sendRequest('cms/promotions/listing', $params);
    
Setting a unique endpoint **before** the Connector is instantiated

    define('API_ENDPOINT', 'http://api.domain.com/v1/');
    
Setting a unique endpoint **after** the Connector is instantiated

    $api->cmsREST = 'http://api.domain.com/v1/';
    
Temporarily using the 'production' endpoint

    # Use the production endpoint for only the next $api->sendRequest() call
    $api->nextRequestProduction();

 Best used with the following packages:

 * [ParsePromos](https://github.com/waynestate/parse-promos)
 * [ParseMenu](https://github.com/waynestate/parse-menu)

Contributing
------------

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Make your changes
4. Commit your changes (`git commit -am 'Added some feature'`)
5. Push to the branch (`git push origin my-new-feature`)
6. Create new Pull Request
