<?php

require_once 'vendor/autoload.php';

use \YandexMoney\API;
use \YandexMoney\ExternalPayment;

require_once "constants.php";
require_once "utils.php";
require_once "external_wallet.php";

date_default_timezone_set("Europe/Moscow");

$app = new \Slim\Slim(array(
    "debug" => true,
    "templates.path" => "./views",
    "view" => new \Slim\Views\Twig(),
));

$app->get('/', function() use($app) { 
    return $app->render("index.html");
}); 

$app->post("/obtain-token/", function () use ($app) {
    $scope = $app->request->post('scope');
    $url = API::buildObtainTokenUrl(
        CLIENT_ID,
        REDIRECT_URI,
        explode(" ", $scope)
    );
    $app->redirect($url);
});
function read_file($filename, $as_json=false) {
    $file = fopen($filename, "r");
    $content = fread($file,filesize($filename));
    fclose($file);
    if($as_json) {
        return json_decode($content);
    }
    else {
        return $content;
    }

}

$app->get("/some", function () use ($app) {
    echo $app->request->getHost();
});

$app->post("/process-external/", function () use ($app) {
    $cookie_expired = "20 minutes";
    $phone_number = $app->request->post("phone");
    $value = $app->request->post("value");

    $instance_id_json = ExternalPayment::getInstanceId(CLIENT_ID);
    $app->setCookie("instance_id", $instance_id_json->instance_id,
        $cookie_expired, "/");
    $app->setCookie("result/instance_id", json_encode($instance_id_json),
        $cookie_expired, "/");

    $instance_id = $instance_id_json->instance_id;
    $api = new ExternalPayment($instance_id);
    // check response

    $request_result = $api->request(array(
        "pattern_id" => "phone-topup",
        "phone-number" => $phone_number,
        "amount" => $value
    ));
    if($request_result->status != "success") {
        return show_error($request_result, $app);
    }
    $app->setCookie("request_id", $request_result->request_id,
        $cookie_expired, "/");

    $host = $app->request->getHostWithPort();
    $base_path =
        "http://"
        . $app->request->getHostWithPort()
        . $app->request->getPath()
        . "..";
    $process_result = $api->process(array(
        "request_id" => $request_result->request_id,
        "ext_auth_success_uri" => $base_path . "/external-success/",
        "ext_auth_fail_uri" => $base_path . "/external-fail/"
    ));

    $app->setCookie("result/request", json_encode($request_result),
        $cookie_expired, "/");
    $app->setCookie("result/process", json_encode($process_result),
        $cookie_expired, "/");

    $url = sprintf("%s?%s", $process_result->acs_uri,
        http_build_query($process_result->acs_params));
    $app->redirect($url);
});

$app->get("/external-success/", function () use ($app) {

    $request_id = $app->getCookie("request_id");
    $instance_id = $app->getCookie("instance_id");
    if(is_null($request_id) || is_null($instance_id)) {
        return show_error(
            array("sample_error" => "cookie is expired or incorrect"), $app);
    }

    $api = new ExternalPayment($instance_id);
    $base_path =
        "http://"
        . $app->request->getHostWithPort()
        . $app->request->getPath()
        . "..";

    do {
        $result = $api->process(array(
            "request_id" => $request_id,
            "ext_auth_success_uri" => $base_path . "/external-success/",
            "ext_auth_fail_uri" => $base_path . "/external-fail/"
        ));
        if($result->status == "in_progress") {
            sleep(1);
        }
    } while ($result->status == "in_progress");

    $get_cookie_json = function ($cookie_name) use ($app) {
        return json_decode($app->getCookie($cookie_name));
    };

    return $app->render("cards.html", array(
        "payment_result" => $result,
        "instance_id_code" =>
            read_file("code_samples/external_payment/obtain_instance_id.txt"),
        "request_code" =>
            read_file("code_samples/external_payment/request_payment.txt"),
        "process_code" =>
            read_file("code_samples/external_payment/process_payment.txt"),
        "process_code2" =>
            read_file("code_samples/external_payment/process_payment2.txt"),
        "responses" => array(
            "instance_id" => $get_cookie_json("result/instance_id"),
            "request" => $get_cookie_json("result/request"),
            "process1" => $get_cookie_json("result/process"),
            "process2" => $result
        ),
        "json_format_options" => JSON_PRETTY_PRINT
            | JSON_HEX_TAG
            | JSON_HEX_QUOT
            | JSON_HEX_AMP
            | JSON_UNESCAPED_UNICODE

    ));
});
$app->get("/external-fail/", function () use ($app) {
    $error = array( 
        "info" => "Check out GET params for additional information"
    );
    return show_error($error, $app);
});

// var_dump($app->environment()['SCRIPT_NAME']);

function build_relative_url($redirect_url, $script_name) {
    $exploded_url = explode('/', $redirect_url);
    $relative_url_array = array_slice($exploded_url,
        3 + count(explode('/', $script_name)) - 1);
    return "/" . implode('/', $relative_url_array);
}

function read_sample($sample_path) {
    $full_path = sprintf("code_samples/%s.txt", $sample_path);
    $file = fopen($full_path, "r")
        or die("Unable to open file!");
    $content = fread($file, filesize($full_path));
    fclose($file);
    return $content;
}

function build_response($app, $account_info, $operation_history, $request_payment,
    $process_payment) {

    if(count($operation_history->operations) < 3) {
        $operation_history_info =  sprintf(
            "You have less then 3 records in your payment history");
    }
    else {
        $operation_history_info =  sprintf(
            "The last 3 payment titles are: %s, %s, %s",
            $operation_history->operations[0]->title,
            $operation_history->operations[1]->title,
            $operation_history->operations[2]->title
        );
    }
    if($request_payment->status == "success") {
        $request_payment_info = "Response of request-payment is successive.";
        $is_process_error = false;
    }
    else {
        $request_payment_info = "Response of request-payment is errorneous."
            . sprintf(" The error label is %s", $request_payment->error);
        $is_process_error = true;
    }
    if($is_process_error) {
        $process_payment_info = "The request-payment returns error. No operation.";
    } 
    else {
        $process_payment_info = sprintf("You send %g to %s wallet",
            $process_payment->credit_amount,
            $process_payment->payee);
    }

    return $app->render("auth.html", array(
        "methods" => array(
            array(
                "info" => sprintf("You wallet balance is %s RUB",
                    $account_info->balance),
                "code" => read_sample("account_info"),
                "name" => "Account-info",
                "response" => $account_info
            ),
            array(
                "info" => $operation_history_info,
                "code" => read_sample("operation_history"),
                "name" => "Operation-history",
                "response" => $operation_history
            ),
            array(
                "info" => $request_payment_info,
                "code" => read_sample("request_payment"),
                "name" => "Request-payment",
                "response" => $request_payment
            ),
            array(
                "info" => $process_payment_info,
                "code" => read_sample("process_payment"),
                "name" => "Process-payment",
                "response" => $process_payment,
                "is_error" => $is_process_error,
                "message" => "Call process_payment method isn't possible."
                    . " See request_payment JSON for information"
            )
        ),
        "json_format_options" => JSON_PRETTY_PRINT
            | JSON_HEX_TAG
            | JSON_HEX_QUOT
            | JSON_HEX_AMP
            | JSON_UNESCAPED_UNICODE,
        "parent_url" => $app->environment['SCRIPT_NAME']
    ));
}

$app->get(build_relative_url(REDIRECT_URI, $app->environment['SCRIPT_NAME']),
        function () use($app) {
    $code = $app->request->get('code');
    $result = API::getAccessToken(CLIENT_ID, $code,
        REDIRECT_URI, CLIENT_SECRET);
    if(property_exists($result, "error")) {
        return show_error($result, $app);
    }
    $api = new API($result->access_token);
    $account_info = $api->accountInfo();
    $operation_history = $api->operationHistory(array("records"=>3));
    $request_payment = $api->requestPayment(array(
        "pattern_id" => "p2p",
        "to" => "410011161616877",
        "amount_due" => "0.02",
        "comment" => "test payment comment from yandex-money-php",
        "message" => "test payment message from yandex-money-php",
        "label" => "testPayment",
        "test_payment" => "true",
        "test_result" => "success" 
    ));
    if($request_payment->status !== "success") {
        $process_payment = array();
    }
    else {
        $process_payment = $api->processPayment(array(
            "request_id" => $request_payment->request_id,
            "test_payment" => "true",
            "test_result" => "success" 
        ));
    }
    return build_response($app, $account_info, $operation_history,
        $request_payment, $process_payment);
});

$app->get("/debug/", function () use($app) {
    $account_info = json_decode(json_encode(array(
        "balance" => "0.01"
    )), false);
    $operation_history = json_decode(json_encode(array(
        "operations" => array(
            array(
                "title" => "foo"
            ),
            array(
                "title" => "foo1"
            ),
            array(
                "title" => "foo2"
            )
        )
    )), false);

    $request_payment = json_decode(json_encode(array(
        "status" => "success"
    )), false);
    $process_payment = json_decode(json_encode(array(
        "credit_amount" => 1.1,
        "payee" => "some person"
    )), false);
    return build_response($app, $account_info, $operation_history, $request_payment,
        $process_payment);
});

external_wallet($app);
$app->run(); 

