<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\BasePath\BasePathMiddleware;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$configuration = [
  'tripay_host' => 'https://tripay.co.id/api-sandbox'
];

$app = AppFactory::create();

// Add Slim routing middleware
$app->addRoutingMiddleware();

// Set the base path to run the app in a subdirectory.
// This path is used in urlFor().
$app->add(new BasePathMiddleware($app));

$app->addErrorMiddleware(true, true, true);

// Define app routes
$app->get('/', function (Request $request, Response $response) {
  $response->getBody()->write('Health Check: OK');
  return $response;
})->setName('root');

$app->get('/merchant/fee-calculator', function (Request $request, Response $response) use ($configuration) {

  $payload = [];

  try {
    if (array_key_exists('code', $request->getQueryParams())) {
      $payload['code'] = $request->getQueryParams()['code'];
    }

    if (array_key_exists('amount', $request->getQueryParams())) {
      $payload['amount'] = $request->getQueryParams()['amount'];
    }

    $auth = isset($request->getHeader('Authorization')[0]) ? $request->getHeader('Authorization')[0] : '';

    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_FRESH_CONNECT  => true,
      CURLOPT_URL            => $configuration['tripay_host'] . '/merchant/fee-calculator?' . http_build_query($payload),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
      CURLOPT_FAILONERROR    => false,
      CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
    ]);

    $curlResult = curl_exec($curl);
    $error = curl_error($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if (!empty($error)) {
      $response->withStatus(500)->getBody()->write($error);
      return $response->withHeader('Content-Type', 'application/json');
    }

    $resultData = json_decode($curlResult, true);
    if ($resultData['success'] == false) {
      $response->getBody()->write($curlResult);
      return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write($curlResult);
    return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
  } catch (Exception $e) {
    $response->getBody()->write($e->getMessage());
    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
  }
})->setName('merchant-fee-calculator');

$app->post('/transaction/create', function (Request $request, Response $response) use ($configuration) {

  try {
    $auth = isset($request->getHeader('Authorization')[0]) ? $request->getHeader('Authorization')[0] : '';

    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_FRESH_CONNECT  => true,
      CURLOPT_URL            => $configuration['tripay_host'] . '/transaction/create',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
      CURLOPT_FAILONERROR    => false,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => http_build_query(json_decode($request->getBody()->getContents(), true)),
      CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
    ]);

    $curlResult = curl_exec($curl);
    $error = curl_error($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if (!empty($error)) {
      $response->getBody()->write($error);
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $resultData = json_decode($curlResult, true);
    if ($resultData['success'] == false || $httpcode != 200 || $httpcode != 201) {
      $response->getBody()->write($curlResult);
      return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write($curlResult);
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
  } catch (Exception $e) {
    $response->getBody()->write($e->getMessage());
    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
  }
})->setName('transaction-create');

$app->get('/merchant/payment-channel', function (Request $request, Response $response) use ($configuration) {

  $payload = [];

  try {
    if (array_key_exists('code', $request->getQueryParams())) {
      $payload['code'] = $request->getQueryParams()['code'];
    }

    $auth = isset($request->getHeader('Authorization')[0]) ? $request->getHeader('Authorization')[0] : '';

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_FRESH_CONNECT  => true,
      CURLOPT_URL            => $configuration['tripay_host'] . '/merchant/payment-channel?' . http_build_query($payload),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
      CURLOPT_FAILONERROR    => false,
      CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
    ));

    $curlResult = curl_exec($curl);
    $error = curl_error($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if (!empty($error)) {
      $response->getBody()->write($error);
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $resultData = json_decode($curlResult, true);
    if ($resultData['success'] == false) {
      $response->getBody()->write($curlResult);
      return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write($curlResult);
    return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
  } catch (Exception $e) {
    $response->getBody()->write($e->getMessage());
    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
  }
})->setName('merchant-payment-channel');

$app->get('/payment/instruction', function (Request $request, Response $response) use ($configuration) {

  $payload = [];

  try {
    if (array_key_exists('code', $request->getQueryParams())) {
      $payload['code'] = $request->getQueryParams()['code'];
    }

    $auth = isset($request->getHeader('Authorization')[0]) ? $request->getHeader('Authorization')[0] : '';

    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_FRESH_CONNECT  => true,
      CURLOPT_URL            => $configuration['tripay_host'] . '/payment/instruction?' . http_build_query($payload),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
      CURLOPT_FAILONERROR    => false,
      CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
    ]);

    $curlResult = curl_exec($curl);
    $error = curl_error($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if (!empty($error)) {
      $response->getBody()->write($error);
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $resultData = json_decode($curlResult, true);
    if ($resultData['success'] == false) {
      $response->getBody()->write($curlResult);
      return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    }

    $response->withStatus(200)->getBody()->write($curlResult);
    return $response->withHeader('Content-Type', 'application/json');
  } catch (Exception $e) {
    $response->getBody()->write($e->getMessage());
    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
  }
})->setName('payment-instruction');

$app->post('/payment/callback', function (Request $request, Response $response) use ($configuration) {
  $object = (object) ['success' => true];
  $response->getBody()->write(json_encode($object));
  return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
})->setName('payment-callback');

// Run app
$app->run();
