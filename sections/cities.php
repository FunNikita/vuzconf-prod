<?php

function route($method, $urlData, $formData)
{
    /*
    Получение всех городов
    GET /cities/
    */

    if ($method == "GET" && count($urlData) == 0) {

        $answer = getCities();

        return ['status' => 1, 'data' => $answer];
        // getAnswer(1, $answer);
    }

    /*
    Получение универов по опредленному городу
    GET /cities/{UID}/universities/
    */

    if ($method == "GET" && count($urlData) == 2 && $urlData[1] == "universities") {

        $uid = $urlData[0];

        $answer = getCitiesInfoForUID($uid);

        return ['status' => 1, 'data' => $answer];
        // getAnswer(1, $answer);
    }

    // header('HTTP/1.0 400 Bad Request');
    // getAnswer(0, "Bad Request");
    return ['status' => 0, 'data' => "Bad Request"];
}
