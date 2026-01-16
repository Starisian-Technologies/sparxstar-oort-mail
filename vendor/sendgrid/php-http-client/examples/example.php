<?php

// If running this outside of this context, use the following include and
// comment out the two includes below
// require __DIR__ . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/lib/Client.php';
require dirname( __DIR__ ) . '/lib/Response.php';
// This gets the parent directory, for your current directory use getcwd()
$path_to_config = dirname( __DIR__ );
$apiKey         = getenv( 'SENDGRID_API_KEY' );
$headers        = array( 'Authorization: Bearer ' . $apiKey );
$client         = new SendGrid\Client( 'https://api.sendgrid.com', $headers, '/v3' );

// GET /v3/api_keys - retrieve all API Keys that belong to the user
$queryParams    = array(
	'limit'  => 100,
	'offset' => 0,
);
$requestHeaders = array( 'X-Mock: 200' );
$response       = $client->api_keys()->get( null, $queryParams, $requestHeaders );
echo $response->statusCode();
echo $response->body();
echo $response->headers();

// GET /v3/api_keys - retrieve all API Keys that belong to the user
$queryParams    = array(
	'limit'  => 100,
	'offset' => 0,
);
$requestHeaders = array( 'X-Mock: 200' );
$retryOnLimit   = true; // with auto retry on rate limit
$response       = $client->api_keys()->get( null, $queryParams, $requestHeaders, $retryOnLimit );

// POST /v3/api_keys - create a new user API Key
$requestBody  = array(
	'name'   => 'My PHP API Key',
	'scopes' => array(
		'mail.send',
		'alerts.create',
		'alerts.read',
	),
);
$response     = $client->api_keys()->post( $requestBody );
$responseBody = json_decode( $response->body(), true );
$apiKeyId     = $responseBody['api_key_id'];

// GET /v3/api_keys/{api_key_id} - retrieve a single API Key
$response = $client->api_keys()->_( $apiKeyId )->get();

// PATCH /v3/api_keys/{api_key_id} - update the name of an existing API Key
$requestBody = array(
	'name' => 'A New Hope',
);
$response    = $client->api_keys()->_( $apiKeyId )->patch( $requestBody );

// PUT /v3/api_keys/{api_key_id} - update the name and scopes of a given API Key
$requestBody = array(
	'name'   => 'A New Hope',
	'scopes' => array(
		'user.profile.read',
		'user.profile.update',
	),
);
$response    = $client->api_keys()->_( $apiKeyId )->put( $requestBody );

// DELETE /v3/api_keys/{api_key_id} - revoke an existing API Key
$response = $client->api_keys()->_( $apiKeyId )->delete();
