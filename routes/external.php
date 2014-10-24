<?php
require_once __DIR__ . "/../utils.php";
use \YandexMoney\API;
use \YandexMoney\ExternalPayment;

function template_meta($method, $index) {
    return array(
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
};

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
        $params = array(
            "text" => json_encode($request_result, JSON_OPTIONS),
            "home" => "../"
        );
        return show_error($params, $app);
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
        $params = array(
            "text" => "cookie is expired or incorrect",
            "home" => "../"
        );
        return show_error(params, $app);
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

    $codesamples_base = "external_payment/";
    return $app->render("cards.html", array(
        "payment_result" => $result,
        "panels" => array(
            "instance_id" => template_meta(array(
                "code" => read_sample($codesamples_base . "obtain_instance_id.txt"),
                "response" => $get_cookie_json("result/instance_id")
            ), 1),
            "request_payment" => template_meta(array(
                "code" => read_sample($codesamples_base . "request_payment.txt"),
                "response" => $get_cookie_json("result/request")
            ), 2),
            "process_payment1" => template_meta(array(
                "code" => read_sample($codesamples_base . "process_payment1.txt"),
                "response" => $get_cookie_json("result/process")
            ), 3),
            "process_payment2" => template_meta(array(
                "code" => read_sample($codesamples_base . "process_payment2.txt"),
                "response" => $result
            ), 4)
        ),
        "home" => "../",
        "lang" => "PHP"
    ));
});

$app->post("/wallet/process-external/", function () use($app) {
    $cookie_expired = "20 minutes";
    $wallet = $app->request->post("wallet");
    $value = $app->request->post("value");

    $instance_id_json = ExternalPayment::getInstanceId(CLIENT_ID);
    $app->setCookie("instance_id", $instance_id_json->instance_id,
        $cookie_expired, "/");
    $app->setCookie("result/instance_id", json_encode($instance_id_json),
        $cookie_expired, "/");

    $instance_id = $instance_id_json->instance_id;
    $api = new ExternalPayment($instance_id);

    $request_result = $api->request(array(
        "pattern_id" => "p2p",
        "to" => $wallet,
        "amount_due" => $value,
        "comment" => "sample test payment",
        "message" => "sample test payment",
    ));
    if($request_result->status != "success") {
        $params = array(
            "text" => json_encode($request_result, JSON_OPTIONS),
            "home" => "../"
        );
        return show_error($params, $app);
    }
    $app->setCookie("request_id", $request_result->request_id,
        $cookie_expired, "/");

    $base_path =
        "http://"
        . $app->request->getHostWithPort()
        . $app->request->getPath()
        . "..";
    $process_result = $api->process(array(
        "request_id" => $request_result->request_id,
        "ext_auth_success_uri" => $base_path . "/external-success/",
        "ext_auth_fail_uri" => $base_path . "/../external-fail/"
    ));

    $app->setCookie("result/request", json_encode($request_result),
        $cookie_expired, "/");
    $app->setCookie("result/process", json_encode($process_result),
        $cookie_expired, "/");

    $url = sprintf("%s?%s", $process_result->acs_uri,
        http_build_query($process_result->acs_params));
    $app->redirect($url);
});

$app->get("/wallet/external-success/", function () use ($app) {
    $request_id = $app->getCookie("request_id");
    $instance_id = $app->getCookie("instance_id");
    if(is_null($request_id) || is_null($instance_id)) {
        $params = array(
            "text" => "cookie is expired or incorrect",
            "home" => "../"
        );
        return show_error(params, $app);
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
            "ext_auth_fail_uri" => $base_path . "/../external-fail/"
        ));
        if($result->status == "in_progress") {
            sleep(1);
        }
    } while ($result->status == "in_progress");

    $get_cookie_json = function ($cookie_name) use ($app) {
        return json_decode($app->getCookie($cookie_name));
    };

    $codesamples_base = "external_payment/wallet/";
    return $app->render("cards.html", array(
        "payment_result" => $result,
        "panels" => array(
            "instance_id" => template_meta(array(
                "code" => read_sample($codesamples_base . "obtain_instance_id.txt"),
                "response" => $get_cookie_json("result/instance_id")
            ), 1),
            "request_payment" => template_meta(array(
                "code" => read_sample($codesamples_base . "request_payment.txt"),
                "response" => $get_cookie_json("result/request")
            ), 2),
            "process_payment1" => template_meta(array(
                "code" => read_sample($codesamples_base . "process_payment1.txt"),
                "response" => $get_cookie_json("result/process")
            ), 3),
            "process_payment2" => template_meta(array(
                "code" => read_sample($codesamples_base . "process_payment2.txt"),
                "response" => $result
            ), 4)
        ),
        "home" => "../../",
        "lang" => "PHP"
    ));
});
$app->get("/wallet/external-fail/", function () use ($app) {
    $params = array( 
        "text" => "Check out GET params for additional information",
        "home" => "../../"
    );
    return show_error($params, $app);
});
$app->get("/wallet/", function () use ($app) {
    $app->redirect("../");
});
