<?php

function route($method, $urlData, $formData)
{
    /*
    Получение всех конференций университета
    GET /universities/{UID}/conferences/
    */

    if ($method == "GET" && count($urlData) == 2 && $urlData[1] == "conferences") {

        $uid = $urlData[0];

        $answer = getUniversities($uid);

        return ['status' => 1, 'data' => $answer];

        // getAnswer(1, $answer);
    }

    /*
    Получение конференции по uid у опредленному уника
    GET /universities/{UID}/conferences/{UID}/
    */

    if ($method == "GET" && count($urlData) == 3 && $urlData[1] == "conferences") {

        $university_uid = $urlData[0];
        $conf_uid = $urlData[2];

        $answer = getUniversityForConf($university_uid, $conf_uid);

        return ['status' => 1, 'data' => $answer];

        // getAnswer(1, $answer);
    }

    // header('HTTP/1.0 400 Bad Request');
    // getAnswer(0, "Bad Request");
    return ['status' => 0, 'data' => "Bad Request"];
}
