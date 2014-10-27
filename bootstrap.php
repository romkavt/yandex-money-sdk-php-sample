<?php

require_once 'vendor/autoload.php';
require_once "constants.php";

date_default_timezone_set("Europe/Moscow");

use \YandexMoney\API;
use \YandexMoney\ExternalPayment;

$app = new \Slim\Slim(array(
    "debug" => true,
    "templates.path" => "./views",
    "view" => new \Slim\Views\Twig(),
));

$app->get('/', function() use($app) { 
    return $app->render("index.html", array("lang" => "PHP"));
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

$app->get("/external-fail/", function () use ($app) {
    $error = array( 
        "info" => "Check out GET params for additional information"
    );
    return show_error($error, $app);
});
