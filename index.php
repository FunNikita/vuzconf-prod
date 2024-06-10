<?php
include("setting.php"); // что-то по типу настроечек (там база и функция для вывода ответа)

function getFormData($method)
{
    $request = file_get_contents('php://input');

    if ($method == 'GET')
        return $_GET;
    if ($method == 'POST') {
        return json_decode($request, true);
    }

    $data = json_decode($request, true);

    return $data;
}

$method = $_SERVER['REQUEST_METHOD']; // сам метод
$formData = getFormData($method); // получаем данные запроса

// разбираем ссылку (get-запрос), так как мы меняли файл .htaccess
$url = (isset($_GET['q'])) ? $_GET['q'] : '';
$url = rtrim($url, '/');
$urls = explode('/', $url);

$section = $urls[0];
$urlData = array_slice($urls, 1);

$ip = $_SERVER["REMOTE_ADDR"];
$userAgent = $_SERVER['HTTP_USER_AGENT'];
$requestTime = date('Y-m-d H:i:s');
$domain = $_SERVER['HTTP_HOST'];
$hash = getHash(0, 20);

// Проверяем лимиты запросов и логируем только если домен api.vuzconf.ru
if ($domain == 'api.vuzconf.ru') {
    limitRequest($ip);
}

// ищем файлик и подключаем роутер, если нет — выводим 404 =)
$file_section = 'sections/' . $section . '.php';
if (file_exists($file_section)) {
    include_once 'sections/' . $section . '.php';
    $response = route($method, $urlData, $formData);

    $responseTime = date('Y-m-d H:i:s');

    // kогируем только если домен api.vuzconf.ru
    if ($domain == 'api.vuzconf.ru') {
        logRequest($ip, $userAgent, $section, $urlData, $formData, $response['status'], $response['data'], $requestTime, $responseTime, $hash);
    }

    $response['hash'] = $hash; // lобавляем hash к ответу

    if ($response['status'] == 1) {
        getAnswer(1, $response['data'], [], $hash);
    } else {
        getAnswer(0, $response['data'], $response['data2'], $hash);
    }
} else {
    header('HTTP/1.0 404 Not found');
    getAnswer(0, "Not found.");
}
