<?php

require_once 'vendor/autoload.php';

require_once 'vendor/yandex-money/yandex-money-sdk-php-simplified/lib/api.php';
require_once 'vendor/yandex-money/yandex-money-sdk-php-simplified/lib/exceptions.php';
require_once "constants.php";

use \YandexMoney\API;

$app = new Silex\Application(); 

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// $app->register(new Silex\Provider\MonologServiceProvider(), array(
//     'monolog.logfile' => __DIR__.'/development.log',
// ));

$app->get('/', function() use($app) { 
    $request = $app['request'];
    $access_token = $request->query->get('token');
    return $app['twig']->render("index.html", array(
        "token" => $access_token
    ));
}); 

$app->get("/account-info", function() use($app) {
    $request = $app['request'];
    $access_token = $request->query->get('token');
    $api = new API($access_token);
    $info = $api->accountInfo();
    return $app['twig']->render("index.html", array(
        "token" => $access_token,
        "result" => $info
    ));
});
$app->get("/operation-history", function() use($app) {
    $request = $app['request'];
    $access_token = $request->query->get('token');
    $api = new API($access_token);
    $records = $request->query->get("records");
    if($records != NULL) {
        $result = $api->operationHistory(array("records"=>$records));
    }
    else {
        $result = $api->operationHistory();
    }
    return $app['twig']->render("index.html", array(
        "token" => $access_token,
        "result" => $result
    ));
});
$app->get("/request-payment", function () use($app) {
    $request = $app['request'];
    $access_token = $request->query->get('token');
    $api = new API($access_token);

    $result = $api->requestPayment(array(
        "pattern_id" => "p2p",
        "to" => "410011161616877",
        "amount_due" => "0.02",
        "comment" => "test payment comment from yandex-money-php",
        "message" => "test payment message from yandex-money-php",
        "label" => "testPayment"
    ));
    return $app['twig']->render("index.html", array(
        "token" => $access_token,
        "show_process_payment" => true,
        "request_id" => $result->request_id,
        "result" => $result
    ));
});

$app->get("/process-payment", function () use($app) {
    $request = $app['request'];
    $access_token = $request->query->get('token');
    $request_id = $request->query->get('request_id');
    $api = new API($access_token);

    $result = $api->processPayment(array(
        "request_id" => $request_id
    ));
    return $app['twig']->render("index.html", array(
        "token" => $access_token,
        "result" => $result
    ));
});

$app->post("/obtain-token/", function () use ($app) {
    $request = $app['request'];
    $scope = $request->request->get('scope');
    $url = API::buildObtainTokenUrl(
        CLIENT_ID,
        "http://localhost:8000" . REDIRECT_URL,
        CLIENT_SECRET,
        split(" ", $scope)
    );
    return $app->redirect($url);
});

$app->get(REDIRECT_URL, function () use($app) {
    // authorization_code
    // public static function getAccessToken($client_id, $code, $redirect_uri) {
    $code = $app['request']->query->get('code');
    $result = API::getAccessToken(CLIENT_ID, $code,
        "http://localhost:8000" . REDIRECT_URL, CLIENT_SECRET);
    var_dump($result);
    return $app->redirect(sprintf("/?token=%s", $result->access_token));
});

$app['debug'] = true;
$app->run(); 

