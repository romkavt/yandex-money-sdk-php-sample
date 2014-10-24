<?php

function show_error($params, $app) {
    return $app->render("error.html", array(
        "text" => $params['text'],
        "home" => $params['home'],
        "lang" => "PHP"
    ));
}

function read_sample($sample_path) {
    $full_path = sprintf(__DIR__ . "/code_samples/%s", $sample_path);
    $file = fopen($full_path, "r")
        or die("Unable to open file!");
    $content = fread($file, filesize($full_path));
    fclose($file);
    return $content;
}

function build_relative_url($redirect_url, $script_name) {
    $exploded_url = explode('/', $redirect_url);
    $relative_url_array = array_slice($exploded_url,
        3 + count(explode('/', $script_name)) - 1);
    return "/" . implode('/', $relative_url_array);
}

define("JSON_OPTIONS", JSON_PRETTY_PRINT
    | JSON_HEX_TAG
    | JSON_HEX_QUOT
    | JSON_HEX_AMP
    | JSON_UNESCAPED_UNICODE);

