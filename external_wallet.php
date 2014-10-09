<?php
use \YandexMoney\API;
use \YandexMoney\ExternalPayment;

require_once "utils.php";

function external_wallet($app) {

    $app->get("/hello", function () {
        echo "HERE";
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
            "ext_auth_success_uri" => $base_path . "/wallet/external-success/",
            "ext_auth_fail_uri" => $base_path . "/wallet/external-fail/"
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
                "ext_auth_success_uri" => $base_path . "/wallet/external-success/",
                "ext_auth_fail_uri" => $base_path . "/wallet/external-fail/"
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
}


