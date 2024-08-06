<?php

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\BasePath\BasePathMiddleware;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$configuration = [
  'tripay_host' => 'https://tripay.co.id/api-sandbox'
];

// Create the logger
$logger = new Logger('app_logger');

// Add RotatingFileHandler to log files and rotate every 7 days
$logger->pushHandler(new RotatingFileHandler(__DIR__ . '/../logs/app.log', 7, Logger::DEBUG));

$app = AppFactory::create();

// Add Slim routing middleware
$app->addRoutingMiddleware();

// Set the base path to run the app in a subdirectory.
// This path is used in urlFor().
$app->add(new BasePathMiddleware($app));

$app->addErrorMiddleware(true, true, true);

// Define app routes
$app->get('/', function (Request $request, Response $response) use ($logger) {
  $logger->info('Health Check route accessed');
  $response->getBody()->write('Health Check: OK');
  return $response;
})->setName('root');

$app->get('/merchant/fee-calculator', function (Request $request, Response $response) use ($configuration, $logger) {

  $payload = [];

  try {
    $logger->info('Merchant fee calculator route accessed', ['query' => $request->getQueryParams()]);
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
      $logger->error('Curl error', ['error' => $error]);
      $response->withStatus(500)->getBody()->write($error);
      return $response->withHeader('Content-Type', 'application/json');
    }

    $resultData = json_decode($curlResult, true);
    if ($httpcode !== 200) {
      $logger->error('API call unsuccessful', ['response' => $resultData, 'httpcode' => $httpcode]);
      $response->getBody()->write($curlResult);
      return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write($curlResult);
    return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
  } catch (Exception $e) {
    $logger->error('Exception caught', ['exception' => $e->getMessage()]);
    $response->getBody()->write($e->getMessage());
    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
  }
})->setName('merchant-fee-calculator');

$app->post('/transaction/create', function (Request $request, Response $response) use ($configuration, $logger) {

  try {
    $logger->info('Transaction create route accessed', ['body' => $request->getBody()->getContents()]);
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
      $logger->error('Curl error', ['error' => $error]);
      $response->getBody()->write($error);
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $resultData = json_decode($curlResult, true);
    if ($httpcode >= 400) {
      $logger->error('API call unsuccessful', ['response' => $resultData, 'httpcode' => $httpcode]);
      $response->getBody()->write($curlResult);
      return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    }
    
    $logger->info('API call successful', ['response' => $resultData]);
    $response->getBody()->write($curlResult);
      return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
  } catch (Exception $e) {
    $logger->error('Exception caught', ['exception' => $e->getMessage()]);
    $response->getBody()->write($e->getMessage());
    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
  }
})->setName('transaction-create');

$app->get('/merchant/payment-channel', function (Request $request, Response $response) use ($configuration, $logger) {

  $payload = [];

  try {
    $logger->info('Merchant payment channel route accessed', ['query' => $request->getQueryParams()]);
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
      $logger->error('Curl error', ['error' => $error]);
      $response->getBody()->write($error);
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $resultData = json_decode($curlResult, true);
    if ($httpcode != 200) {
      $logger->error('API call unsuccessful', ['response' => $resultData, 'httpcode' => $httpcode]);
      $response->getBody()->write($curlResult);
      return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write($curlResult);
    return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
  } catch (Exception $e) {
    $logger->error('Exception caught', ['exception' => $e->getMessage()]);
    $response->getBody()->write($e->getMessage());
    return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
  }
})->setName('merchant-payment-channel');

$app->get('/payment/instruction', function (Request $request, Response $response) use ($configuration, $logger) {

  $payload = [];

  try {
    $logger->info('Payment instruction route accessed', ['query' => $request->getQueryParams()]);
    if (array_key_exists('code', $request->getQueryParams())) {
      $payload['code'] = $request->getQueryParams()['code'];
    }

    $auth = isset($request->getHeader('Authorization')[0]) ? $request->getHeader('Authorization')[0] : '';

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_FRESH_CONNECT  => true,
      CURLOPT_URL            => $configuration['tripay_host'] . '/payment/instruction?' . http_build_query($payload),
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
      $logger->error('Curl error', ['error' => $error]);
      $response->getBody()->write($error);
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $resultData = json_decode($curlResult, true);
    if ($httpcode != 200) {
      $logger->error('API call unsuccessful', ['response' => $resultData, 'httpcode' => $httpcode]);
      $response->getBody()->write($curlResult);
      return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write($curlResult);
    return $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
  } catch (Exception $e) {
    $logger->error('Exception caught', ['exception' => $e->getMessage()]);
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
