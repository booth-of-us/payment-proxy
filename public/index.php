<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\BasePath\BasePathMiddleware;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

// Add Slim routing middleware
$app->addRoutingMiddleware();

// Set the base path to run the app in a subdirectory.
// This path is used in urlFor().
$app->add(new BasePathMiddleware($app));

$app->addErrorMiddleware(true, true, true);

$apiKey = 'api_key_anda';
$host = 'https://tripay.co.id/api-sandbox';

// Define app routes
$app->get('/', function (Request $request, Response $response) {
  $response->getBody()->write('Hello, World!');
  return $response;
})->setName('root');

$app->get('/merchant/fee-calculator', function (Request $request, Response $response) {

  $payload = [
    'code' => $request->getQueryParams()['code'],
    'amount' => $request->getQueryParams()['amount'],
  ];

  $curl = curl_init();

  curl_setopt_array($curl, [
    CURLOPT_FRESH_CONNECT  => true,
    CURLOPT_URL            => $this->host . '/merchant/fee-calculator?' . http_build_query($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->apiKey],
    CURLOPT_FAILONERROR    => false,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
  ]);

  $response = curl_exec($curl);
  $error = curl_error($curl);

  curl_close($curl);

  if (!empty($error)) return $error;

  return $response;
})->setName('merchant-fee-calculator');

$app->post('/transaction/create', function (Request $request, Response $response) {

  $curl = curl_init();

  curl_setopt_array($curl, [
    CURLOPT_FRESH_CONNECT  => true,
    CURLOPT_URL            => $this->host . '/transaction/create',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->apiKey],
    CURLOPT_FAILONERROR    => false,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($request->getBody()),
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
  ]);

  $response = curl_exec($curl);
  $error = curl_error($curl);

  curl_close($curl);

  if (!empty($error)) return $error;

  return $response;
})->setName('transaction-create');

$app->get('/merchant/payment-channel', function (Request $request, Response $response) {

  $payload = ['code' => $request->getQueryParams()['code']];

  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_FRESH_CONNECT  => true,
    CURLOPT_URL            => $this->host . '/merchant/payment-channel?' . http_build_query($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->apiKey],
    CURLOPT_FAILONERROR    => false,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
  ));

  $response = curl_exec($curl);
  $error = curl_error($curl);

  curl_close($curl);

  if (!empty($error)) return $error;

  return $response;
})->setName('merchant-payment-channel');

$app->get('/payment/instruction', function (Request $request, Response $response) {

  $payload = ['code' => $request->getQueryParams()['code']];

  $curl = curl_init();

  curl_setopt_array($curl, [
    CURLOPT_FRESH_CONNECT  => true,
    CURLOPT_URL            => $this->host . '/payment/instruction?' . http_build_query($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $this->apiKey],
    CURLOPT_FAILONERROR    => false,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
  ]);

  $response = curl_exec($curl);
  $error = curl_error($curl);

  curl_close($curl);

  if (!empty($error)) return $error;

  return $response;
})->setName('payment-instruction');

// Run app
$app->run();
