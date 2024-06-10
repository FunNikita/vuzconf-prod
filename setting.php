<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('memory_limit', '-1');
ini_set("error_log", "log.txt");
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: HEAD, GET, POST, PUT, PATCH, DELETE, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    header("HTTP/1.0 200 OK");
    exit(0);
}

header("Content-type: application/json; charset=utf-8");
date_default_timezone_set("Europe/Moscow"); // задаём часовой пояс

require_once "vendor/autoload.php";
include("base/base.php"); // подключаем базу

if (!R::testConnection()) {
    getAnswer(0, "Нет соединения с базой данных!");
}


function getAnswer($status, $data, $data2 = [], $hash = '')
{
    $response = [];
    if ($status == 1) {
        header("HTTP/1.0 200 OK");
        $response = ["status" => "success", "data" => $data, "hash" => $hash];
    } else {
        header("HTTP/1.0 400 Bad Request");
        $response = ["status" => "error", "message" => $data, "data" => $data2, "hash" => $hash];
    }
    die(json_encode($response, JSON_UNESCAPED_UNICODE));
}


function getUniversityForConf($university_uid, $conf_uid)
{
    $university = R::findOne("universities", "status = 1 AND uid = ?", [$university_uid]);

    if ($university == null) {
        // getAnswer(0, "Университет не найден.");
        return ['status' => 0, 'data' => "Университет не найден."];
    }

    $conference = R::findOne("conferences", "uid = ? AND university_id = ?", [$conf_uid, $university->id]);

    if ($conference == null) {
        return ['status' => 0, 'data' => "Конференция не найдена."];
    }

    $sql = "SELECT i.name, i.type, i.class, ic.value
        FROM infoconferences ic
        LEFT JOIN info i ON ic.info_id = i.id
        WHERE ic.conference_id = ?
        ORDER BY i.name";

    $info_values = R::getAll($sql, [$conference->id]);

    $array_info = [];
    $conference_date = null;

    foreach ($info_values as $info_value) {
        if ($info_value['type'] == 'date') {
            $conference_date = $info_value['value'];
            break;
        }
    }

    $array = [
        "uid" => $conference['uid'],
        "name" => $conference['name'],
        "url" => $conference['url'],
        "type" => $conference['type'],
        "file" => (bool) $conference['file'],
        "date" => $conference_date,
        "info" => $info_values
    ];

    return $array;
}


function getUniversities($uid)
{
    $university = R::findOne("universities", "status = 1 AND uid = ?", [$uid]);

    if ($university == null) {
        return ['status' => 0, 'data' => "Университет не найден."];
    }

    $sql = "SELECT c.*, ic.value as infoconferences_value
        FROM conferences c
        LEFT JOIN infoconferences ic ON c.id = ic.conference_id
        LEFT JOIN info i ON ic.info_id = i.id
        WHERE c.university_id = ? AND i.type = 'date'
        ORDER BY c.date DESC";

    $conferences = R::getAll($sql, [$university->id]);

    $array = [];

    foreach ($conferences as $conference) {
        array_push($array, [
            "uid" => $conference['uid'],
            "name" => $conference['name'],
            "url" => $conference['url'],
            "type" => $conference['type'],
            "file" => (bool) $conference['file'],
            "date" => $conference['infoconferences_value']
        ]);
    }


    $answer = [
        "conferences" => [
            "count" => count($array),
            "items" => $array
        ],
        "university" => getUniversityForId($university->id)
    ];

    return $answer;
}

function getUniversityForId($id)
{
    $university = R::findOne("universities", "id = ?", [$id]);

    $array = [
        "uid" => $university->uid,
        "name" => $university->name,
        "short_name" => $university->short_name,
    ];

    return $array;
}

function getCities()
{
    $cities = R::findAll("cities", "status = 1");

    $array = [];

    foreach ($cities as $city) {
        array_push($array, [
            "uid" => $city->uid,
            "name" => $city->name
        ]);
    }

    $answer = [
        "count" => count($array),
        "items" => $array
    ];

    return $answer;
}

function getCitiesInfoForUID($uid)
{
    $city = R::findOne("cities", "uid = ?", [$uid]);
    if ($city == null) {
        return ['status' => 0, 'data' => "Город не найден."];
    }

    $array = [];

    $universities = R::findAll("universities", "city_id = ? AND status = 1", [$city->id]);

    foreach ($universities as $university) {
        array_push($array, [
            "uid" => $university->uid,
            "name" => $university->name,
            "short_name" => $university->short_name,
        ]);
    }

    $answer = [
        "count" => count($array),
        "items" => $array
    ];

    return $answer;
}

function getDateTime($time = 0, $date = 0, $atom = 0, $str_date = "")
{
    if ($time == 1) {
        return time();
    }
    if ($date != 0) {
        return date("Y-m-d H:i:s", $date);
    }
    if ($atom == 1) {
        return date(DATE_ATOM, strtotime($str_date));
    }
    return date("Y-m-d H:i:s");
}

function getHash($type = 0, $len = 18)
{
    if ($type == 1) { // 4aae568ed80a11bf6f6d
        return bin2hex(random_bytes(10));
    } else if ($type == 2) { // 86696D42-B0E9-465E-8374-6475D53FF0DB
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }
        return sprintf(
            '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(16384, 20479),
            mt_rand(32768, 49151),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535)
        );
    } else if ($type == 3) { // a30c8d89-f2aa-4af9-b1df-6b6443349706
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    } else { // 19cedfdfbecd52d1f60e06997155ff3ad33f
        return bin2hex(random_bytes($len));
    }
}

function logRequest($ip, $userAgent, $section, $urlParams, $requestBody, $responseStatus, $responseBody, $requestTime, $responseTime, $hash)
{
    $log = R::dispense('requestlogs');
    $log->ipAddress = $ip;
    $log->userAgent = $userAgent;
    $log->section = $section;
    $log->urlParams = json_encode($urlParams, JSON_UNESCAPED_UNICODE);
    $log->requestBody = json_encode($requestBody, JSON_UNESCAPED_UNICODE);
    $log->requestTime = $requestTime;
    $log->responseStatus = $responseStatus;
    $log->responseBody = json_encode($responseBody, JSON_UNESCAPED_UNICODE);
    $log->responseTime = $responseTime;
    $log->hash = $hash;
    R::store($log);
}

function limitRequest($ip)
{
    $timeFrame = 1; // 1 second
    $maxRequests = 5; // max 5 requests per second

    $currentTime = date('Y-m-d H:i:s');
    $startTime = date('Y-m-d H:i:s', strtotime($currentTime) - $timeFrame);

    $requests = R::count('requestlogs', 'ip_address = ? AND request_time BETWEEN ? AND ?', [$ip, $startTime, $currentTime]);

    if ($requests >= $maxRequests) {
        getAnswer(0, "Too many requests. Please try again later.");
    }
}
