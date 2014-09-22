<?php

require_once 'vendor/autoload.php';

require_once 'vendor/yandex-money/yandex-money-sdk-php-simplified/lib/api.php';
require_once 'vendor/yandex-money/yandex-money-sdk-php-simplified/lib/exceptions.php';
require_once "constants.php";

use \YandexMoney\API;

$app = new \Slim\Slim(array(
    "debug" => true,
    "templates.path" => "./views",
    "view" => new \Slim\Views\Twig(),
));


$app->get('/', function() use($app) { 
    $access_token = $app->request->get('token');
    return $app->render("index.html", array(
        "token" => $access_token,
    ));
}); 

$app->post("/obtain-token/", function () use ($app) {
    $scope = $app->request->post('scope');
    $url = API::buildObtainTokenUrl(
        CLIENT_ID,
        HOST . REDIRECT_URL,
        CLIENT_SECRET,
        explode(" ", $scope)
    );
    $app->redirect($url);
});

$app->get("/account-info", function() use($app) {
    $access_token = $app->request->get('token');
    $api = new API($access_token);
    $info = $api->accountInfo();

    return $app->render("index.html", array(
        "token" => $access_token,
        "result" => $info
    ));
});
$app->get("/operation-history", function() use($app) {
    $access_token = $app->request->get('token');
    $api = new API($access_token);
    $records = $app->request->get('records');
    if($records != NULL) {
        $result = $api->operationHistory(array("records"=>$records));
    }
    else {
        $result = $api->operationHistory();
    }
    return $app->render("index.html", array(
        "token" => $access_token,
        "result" => $result
    ));
});
$app->get("/request-payment", function () use($app) {
    $access_token = $app->request->get('token');
    $api = new API($access_token);

    $result = $api->requestPayment(array(
        "pattern_id" => "p2p",
        "to" => "410011161616877",
        "amount_due" => "0.02",
        "comment" => "test payment comment from yandex-money-php",
        "message" => "test payment message from yandex-money-php",
        "label" => "testPayment"
    ));
    return $app->render("index.html", array(
        "token" => $access_token,
        "show_process_payment" => true,
        "request_id" => $result->request_id,
        "result" => $result
    ));
});

$app->get("/process-payment", function () use($app) {
    $access_token = $app->request->get('token');
    $request_id = $app->request->get('request_id');
    $api = new API($access_token);

    $result = $api->processPayment(array(
        "request_id" => $request_id
    ));
    return $app->render("index.html", array(
        "token" => $access_token,
        "result" => $result
    ));
});


$app->get(REDIRECT_URL, function () use($app) {
    $code = $app->request->get('code');
    $result = API::getAccessToken(CLIENT_ID, $code,
        HOST . REDIRECT_URL, CLIENT_SECRET);
    $app->redirect(sprintf("/?token=%s", $result->access_token));
});

$app->run(); 

