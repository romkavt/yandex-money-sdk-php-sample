<?php
require_once __DIR__ . "/../utils.php";
use \YandexMoney\API;
use \YandexMoney\ExternalPayment;

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

    $template_meta = function ($method, $index) {
        $method['includes'] = array(
            array(
                "is_collapsed" => false,
                "title" => "Source code",
                "id" => $index,
                "body" => $method['code']
            ),
            array(
                "is_collapsed" => true,
                "title" => "Response",
                "id" => $index + 100,
                "body" => json_encode($method['response'], JSON_OPTIONS)
            )
        );
        return $method;
    };

    $methods = array(
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
    );

    return $app->render("auth.html", array(
        "methods" => array_map($template_meta, $methods, array_keys($methods)),
        "home" => $app->environment['SCRIPT_NAME'],
        "lang" => "PHP"
    ));
}

$app->get(build_relative_url(REDIRECT_URI, $app->environment['SCRIPT_NAME']),
        function () use($app) {
    $code = $app->request->get('code');
    $result = API::getAccessToken(CLIENT_ID, $code,
        REDIRECT_URI, CLIENT_SECRET);
    if(property_exists($result, "error")) {
        $script_name = $app->environment['SCRIPT_NAME'];
        $params= array(
            "text" => json_encode($result, JSON_OPTIONS),
            "home" => str_repeat("../", count(explode('/', $script_name)))
        );
        return show_error($params, $app);
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
