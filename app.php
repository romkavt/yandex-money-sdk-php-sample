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
        "is_result" => false
    ));
}); 

$app->post("/obtain-token/", function () use ($app) {
    $scope = $app->request->post('scope');
    $url = API::buildObtainTokenUrl(
        CLIENT_ID,
        REDIRECT_URL,
        CLIENT_SECRET,
        explode(" ", $scope)
    );
    $app->redirect($url);
});

function build_relative_url($redirect_url) {
    $exploded_url = explode('/', $redirect_url);
    $relative_url_array = array_slice($exploded_url, 3);
    if($relative_url_array[count($relative_url_array) - 1] == "") {
        array_pop($relative_url_array);
    }
    return "/" . implode('/', $relative_url_array) . "/";
}

function read_sample($sample_path) {
    $full_path = sprintf("code_samples/%s.txt", $sample_path);
    $file = fopen($full_path, "r")
        or die("Unable to open file!");
    $content = fread($file, filesize($full_path));
    fclose($file);
    return $content;
}

$app->get(build_relative_url(REDIRECT_URL), function () use($app) {
    $code = $app->request->get('code');
    $access_token = API::getAccessToken(CLIENT_ID, $code,
        REDIRECT_URL, CLIENT_SECRET)->access_token;

    $api = new API($access_token);
    $account_info = $api->accountInfo();
    $operation_history = $api->operationHistory(array("records"=>3));
    $request_payment = $api->requestPayment(array(
        "pattern_id" => "p2p",
        "to" => "410011161616877",
        "amount_due" => "0.02",
        "comment" => "test payment comment from yandex-money-php",
        "message" => "test payment message from yandex-money-php",
        "label" => "testPayment",
        "test_payment" => true
    ));
    $process_payment = $api->processPayment(array(
        "request_id" => $request_payment->request_id,
        "test_payment" => true
    ));
    return $app->render("index.html", array(
        "token" => $access_token,
        "account_info" => $account_info,
        "operation_history" => $operation_history,
        "request_payment" => $request_payment,
        "process_payment" => $process_payment,
        "is_result" => true,
        "json_format_options" =>
            JSON_PRETTY_PRINT
            | JSON_HEX_TAG
            | JSON_HEX_QUOT
            | JSON_HEX_AMP
            | JSON_UNESCAPED_UNICODE
    ));
});

$app->get("/debug/", function () use($app) {
    $sample_json = array(
        "foo" => "bar",
        "foo" => "кирилица"
    );
    return $app->render("index.html", array(
        "methods" => array(
            array(
                "info" => "account info text here",
                "code" => read_sample("account_info"),
                "name" => "Account info",
                "response" => $sample_json
            ),
            array(
                "info" => "operation history text here",
                "code" => read_sample("operation_history"),
                "name" => "Operation history info",
                "response" => $sample_json
            ),
            array(
                "info" => "request_payment text here",
                "code" => read_sample("request_payment"),
                "name" => "Request_payment info",
                "response" => $sample_json
            ),
            array(
                "info" => "process payment text here",
                "code" => read_sample("process_payment"),
                "name" => "Process payment info",
                "response" => $sample_json
            )
        ),
        "token" => $sample_json,
        "is_result" => true,
        "json_format_options" => JSON_PRETTY_PRINT
            | JSON_HEX_TAG
            | JSON_HEX_QUOT
            | JSON_HEX_AMP
            | JSON_UNESCAPED_UNICODE
    ));
});
$app->run(); 

