<?php

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Selective\BasePath\BasePathMiddleware;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/utils/variable.php';
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Configurations
$configuration = [
  'tripay_host' => $_ENV['TRIPAY_HOST'] ?? 'https://tripay.co.id/api-sandbox',
  'server_host' => $_ENV['SERVER_HOST'] ?? '',
  'tripay_auths' => $_ENV['TRIPAY_AUTHS'] ?? '',
  'return_url_host' => $_ENV['TRIPAY_RETURN_HOST'] ?? 'https://boothofus.online/proxy',
  'callback_url_host' => $_ENV['TRIPAY_CALLBACK_HOST'] ?? 'https://boothofus.online/proxy',
  'curl_ssl_verify_peer' => isset($_ENV['CURL_SSL_VERIFY_PEER']) ? str_to_bool($_ENV['CURL_SSL_VERIFY_PEER']) : false,
];

$configuration['callback_url_offline_mode'] = $configuration['callback_url_host'] . "/payment/callback";
$configuration['return_url_offline_mode'] = $configuration['return_url_host'] . "/offline/token/generate";

// Authentication keys
$auth_keys = explode(',', $configuration['tripay_auths']);
$auth_keys_map = array();

foreach ($auth_keys as $key) {
  $data_keys = explode('::', $key);
  if (count($data_keys) < 3) {
    continue;
  }

  $auth_keys_map[$data_keys[0]] = (object) array(
    'api_key' => $data_keys[1],
    'private_key' => $data_keys[2]
  );
}

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

$beforeMiddleware = function (Request $request, RequestHandler $handler) use ($logger) {
  $logger->info('[Middleware][Start] Request Received', [
    'method' => $request->getMethod(),
    'uri'    => (string)$request->getUri()
  ]);

  return $handler->handle($request);
};

$afterMiddleware = function (Request $request, RequestHandler $handler) use ($logger) {
  $response = $handler->handle($request);

  $contentType = $response->getHeaderLine('Content-Type');

  // If it's an image, skip logging
  if (strpos($contentType, 'image/') === 0) {
    return $response;
  }

  $logger->info('[Middleware][End] Request Completed', [
    'status' => $response->getStatusCode(),
    'uri'    => (string)$request->getUri()
  ]);

  return $response;
};

$app->add($afterMiddleware);
$app->add($beforeMiddleware);

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

    $auth = $request->getHeaderLine('Authorization');

    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_FRESH_CONNECT  => true,
      CURLOPT_URL            => $configuration['tripay_host'] . '/merchant/fee-calculator?' . http_build_query($payload),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
      CURLOPT_FAILONERROR    => false,
      CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
      CURLOPT_SSL_VERIFYPEER => $configuration['curl_ssl_verify_peer'] === true,
      CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
    ]);

    $curlResult = curl_exec($curl);
    $error = curl_error($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    $logger->info('[CURL] Tripay merchant/fee-calculator response', [
      'http_code' => $httpcode,
      'error'     => $error ?: null,
      'response'  => $curlResult
    ]);

    if (!empty($error)) {
      $logger->error('Curl error', ['error' => $error]);
      $response->withStatus(500)->getBody()->write($error);
      $response = $response->withHeader('Content-Type', 'application/json');
      return $response;
    }

    $resultData = json_decode($curlResult, true);
    if ($httpcode !== 200) {
      $logger->error('API call unsuccessful', ['response' => $resultData, 'httpcode' => $httpcode]);
      $response->getBody()->write($curlResult);
      $response = $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
      return $response;
    }

    $response->getBody()->write($curlResult);
    $response = $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    return $response;
  } catch (Exception $e) {
    $logger->error('Exception caught', ['exception' => $e->getMessage()]);
    $response->getBody()->write($e->getMessage());
    $response = $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    return $response;
  }
})->setName('merchant-fee-calculator');

$app->post('/transaction/create', function (Request $request, Response $response) use ($configuration, $logger) {
  try {
    $body = $request->getBody()->getContents();
    $logger->info('Transaction create route accessed', ['body' => $body]);
    $auth = $request->getHeaderLine('Authorization');

    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_FRESH_CONNECT  => true,
      CURLOPT_URL            => $configuration['tripay_host'] . '/transaction/create',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
      CURLOPT_FAILONERROR    => false,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => http_build_query(json_decode($body, true)),
      CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
      CURLOPT_SSL_VERIFYPEER => $configuration['curl_ssl_verify_peer'] === true,
      CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
    ]);

    $curlResult = curl_exec($curl);
    $error = curl_error($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    $logger->info('[CURL] Tripay /transaction/create response', [
      'http_code' => $httpcode,
      'error'     => $error ?: null,
      'response'  => $curlResult
    ]);

    if (!empty($error)) {
      $logger->error('Curl error', ['error' => $error]);
      $response->getBody()->write($error);
      $response = $response->withStatus(500)->withHeader('Content-Type', 'application/json');
      return $response;
    }

    $resultData = json_decode($curlResult, true);
    if ($httpcode >= 400) {
      $logger->error('API call unsuccessful', ['response' => $resultData, 'httpcode' => $httpcode]);
      $response->getBody()->write($curlResult);
      $response = $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
      return $response;
    }

    $logger->info('API call successful', ['response' => $resultData]);
    $response->getBody()->write($curlResult);
    $response = $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    return $response;
  } catch (Exception $e) {
    $logger->error('Exception caught', ['exception' => $e->getMessage()]);
    $response->getBody()->write($e->getMessage());
    $response = $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    return $response;
  }
})->setName('transaction-create');

$app->get('/merchant/payment-channel', function (Request $request, Response $response) use ($configuration, $logger) {

  $payload = [];

  try {
    $logger->info('Merchant payment channel route accessed', ['query' => $request->getQueryParams()]);
    if (array_key_exists('code', $request->getQueryParams())) {
      $payload['code'] = $request->getQueryParams()['code'];
    }

    $auth = $request->getHeaderLine('Authorization');

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_FRESH_CONNECT  => true,
      CURLOPT_URL            => $configuration['tripay_host'] . '/merchant/payment-channel?' . http_build_query($payload),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
      CURLOPT_FAILONERROR    => false,
      CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
      CURLOPT_SSL_VERIFYPEER => $configuration['curl_ssl_verify_peer'] === true,
      CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
    ));

    $curlResult = curl_exec($curl);
    $error = curl_error($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    $logger->info('[CURL] Tripay merchant/payment-channel response', [
      'http_code' => $httpcode,
      'error'     => $error ?: null,
      'response'  => $curlResult
    ]);

    if (!empty($error)) {
      $logger->error('Curl error', ['error' => $error]);
      $response->getBody()->write($error);
      $response = $response->withStatus(500)->withHeader('Content-Type', 'application/json');
      return $response;
    }

    $resultData = json_decode($curlResult, true);
    if ($httpcode != 200) {
      $logger->error('API call unsuccessful', ['response' => $resultData, 'httpcode' => $httpcode]);
      $response->getBody()->write($curlResult);
      $response = $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
      return $response;
    }

    $response->getBody()->write($curlResult);
    $response = $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    return $response;
  } catch (Exception $e) {
    $logger->error('Exception caught', ['exception' => $e->getMessage()]);
    $response->getBody()->write($e->getMessage());
    $response = $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    return $response;
  }
})->setName('merchant-payment-channel');

$app->get('/payment/instruction', function (Request $request, Response $response) use ($configuration, $logger) {

  $payload = [];

  try {
    $logger->info('Payment instruction route accessed', ['query' => $request->getQueryParams()]);
    if (array_key_exists('code', $request->getQueryParams())) {
      $payload['code'] = $request->getQueryParams()['code'];
    }

    $auth = $request->getHeaderLine('Authorization');

    $curl = curl_init();

    $curl_opts = array(
      CURLOPT_FRESH_CONNECT  => true,
      CURLOPT_URL            => $configuration['tripay_host'] . '/payment/instruction?' . http_build_query($payload),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER         => false,
      CURLOPT_HTTPHEADER     => ['Authorization: ' . $auth],
      CURLOPT_FAILONERROR    => false,
      CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
      CURLOPT_SSL_VERIFYPEER => $configuration['curl_ssl_verify_peer'] === true,
      CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
    );
    curl_setopt_array($curl, $curl_opts);

    $curlResult = curl_exec($curl);
    $error = curl_error($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    $logger->info('[CURL] Tripay payment/instruction response', [
      'http_code' => $httpcode,
      'error'     => $error ?: null,
      'response'  => $curlResult
    ]);

    if (!empty($error)) {
      $logger->error('Curl error', ['error' => $error]);
      $response->getBody()->write($error);
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

    $resultData = json_decode($curlResult, true);
    if ($httpcode != 200) {
      $logger->error('API call unsuccessful', ['response' => $resultData, 'httpcode' => $httpcode]);
      $response->getBody()->write($curlResult);
      $response = $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
      return $response;
    }

    $response->getBody()->write($curlResult);
    $response = $response->withStatus($httpcode)->withHeader('Content-Type', 'application/json');
    return $response;
  } catch (Exception $e) {
    $logger->error('Exception caught', ['exception' => $e->getMessage()]);
    $response->getBody()->write($e->getMessage());
    $response = $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    return $response;
  }
})->setName('payment-instruction');

$app->post('/payment/callback', function (Request $request, Response $response) use ($configuration) {
  $object = (object) ['success' => true];
  $response->getBody()->write(json_encode($object));
  $response = $response->withStatus(200)->withHeader('Content-Type', 'application/json');
  return $response;
})->setName('payment-callback');

// offline
$app->get('/offline/payment/create', function (Request $request, Response $response) use ($configuration, $logger, $auth_keys_map, $getClosedTransactionDetail) {
  $data = $request->getQueryParams();
  $logger->info('Offline payment create route accessed', ['query' => $data]);

  $payment_methods = ['QRIS', 'QRISC'];
  $expired_time_max = 5 * 60; // 5 minutes

  $errorValidationMessages = array();

  if (empty($data['merchant_ref'])) {
    array_push($errorValidationMessages, "you have not scan the booth properly");
  }

  if (empty($data['amount'])) {
    array_push($errorValidationMessages, "booth price is not setup properly, please ask admin!");
  }

  if (empty($data['merchant_code'])) {
    array_push($errorValidationMessages, "booth code is not setup properly, please ask admin!");
  }

  if (count($errorValidationMessages) > 0) {
    return $response->withStatus(400)->withHeader('Content-Type', 'application/json')->write(json_encode(['error' => $errorValidationMessages[0]]));
  }

  $merchantCode = $data['merchant_code'];
  $merchantRef = $data['merchant_ref'];
  $amount = $data['amount'];
  $secretKey = $auth_keys_map[$merchantCode]->private_key ?? '';

  $default_item = [
    'name' => $data['item_name'] ?? 'Offline Item',
    'price' => $amount,
    'quantity' => 1
  ];

  $payload = [
    'method' => $payment_methods[1],
    'merchant_ref' => $merchantRef,
    'amount' => $amount,
    'customer_name' => $data['customer_name'] ?? 'Offline Token',
    'customer_email' => $data['customer_email'] ?? 'boothofus.payment@gmail.com',
    'customer_phone' => $data['customer_phone'] ?? '+6285213000140',
    'callback_url' => $configuration['callback_url_offline_mode'],
    'return_url' => $configuration['return_url_offline_mode'] . '?merchant_code=' . $merchantCode . '&',
    'order_items' => [$default_item],
    'expired_time' => time() + ($expired_time_max),
    'signature' => hash_hmac('sha256', $merchantCode . $merchantRef . $amount, $secretKey)
  ];

  $payload_json = json_encode($payload, true);

  $logger->info('Offline transaction create route accessed', ['body' => $payload_json]);
  $auth = $auth_keys_map[$merchantCode]->api_key ?? '';

  $curl = curl_init();

  curl_setopt_array($curl, [
    CURLOPT_FRESH_CONNECT  => true,
    CURLOPT_URL            => $configuration['tripay_host'] . '/transaction/create',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $auth],
    CURLOPT_FAILONERROR    => false,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(json_decode($payload_json, true)),
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    CURLOPT_SSL_VERIFYPEER => $configuration['curl_ssl_verify_peer'] === true,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1
  ]);

  $curlResult = curl_exec($curl);
  $error = curl_error($curl);
  $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  curl_close($curl);
  $logger->info('[CURL][OFFLINE] Tripay /transaction/create response', [
    'http_code' => $httpcode,
    'error'     => $error ?: null,
    'response'  => $curlResult
  ]);
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

  $responseData = json_decode($curlResult, true);

  return $response
    ->withHeader('Location', $responseData['data']['checkout_url'])
    ->withStatus(302);
})->setName('offline-payment-create');

$app->get('/offline/token/generate', function (Request $request, Response $response) use ($configuration, $auth_keys_map) {
  $queryParams = $request->getQueryParams();

  $tripayRef = $queryParams['tripay_reference'] ?? null;
  $merchantRef = $queryParams['tripay_merchant_ref'] ?? null;
  $merchantCode = $queryParams['merchant_code'] ?? null;

  if (!$tripayRef || !$merchantRef || !$merchantCode) {
    $response->getBody()->write('Missing required parameters.');
    return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
  }

  $auth = $auth_keys_map[$merchantCode]->api_key ?? '';
  $payload = ['reference' => $tripayRef];

  $curl = curl_init();

  curl_setopt_array($curl, [
    CURLOPT_FRESH_CONNECT  => true,
    CURLOPT_URL            => $configuration['tripay_host'] . '/transaction/detail?' . http_build_query($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $auth],
    CURLOPT_FAILONERROR    => false,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    CURLOPT_SSL_VERIFYPEER => false,
  ]);

  $curlResult = curl_exec($curl);
  $error = curl_error($curl);
  $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  curl_close($curl);

  if (!empty($error)) {
    $response->getBody()->write('Error fetching transaction detail: ' . $error);
    return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
  }

  $transactionDetail = json_decode($curlResult, true);
  if ($httpcode !== 200) {
    $response->getBody()->write('Error fetching transaction detail: ' . $curlResult);
    return $response->withStatus($httpcode)->withHeader('Content-Type', 'text/plain');
  }

  if (isset($transactionDetail['error'])) {
    $response->getBody()->write('Error fetching transaction detail: ' . $transactionDetail['error']);
    return $response->withStatus(500)->withHeader('Content-Type', 'text/plain');
  }

  if (empty($transactionDetail['data']) || $transactionDetail['data']['status'] !== 'PAID') {
    $response->getBody()->write('Transaction is not paid or does not exist.');
    return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
  }

  if ($transactionDetail['data']['expired_time'] < time()) {
    $response->getBody()->write('Transaction has expired.');
    return $response->withStatus(400)->withHeader('Content-Type', 'text/plain');
  }

  $rawData = sprintf(
    'tripay_reference=%s,tripay_merchant_ref=%s,timestamp=%d',
    $tripayRef,
    $merchantRef,
    time()
  );

  $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($rawData);

  return $response
    ->withHeader('Location', $qrApiUrl)
    ->withStatus(302);
})->setName('offline-token-generate');

// Run app
$app->run();
