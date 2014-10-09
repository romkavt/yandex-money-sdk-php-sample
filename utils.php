<?php

function show_error($result, $app) {
        return $app->render("error.html", array(
            "json_format_options" => JSON_PRETTY_PRINT
                | JSON_HEX_TAG
                | JSON_HEX_QUOT
                | JSON_HEX_AMP
                | JSON_UNESCAPED_UNICODE,
            "response" => $result
        ));
}
