<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-type: application/json; charset=utf-8");
date_default_timezone_set("Europe/Moscow"); // заменить на Самару

include('../simplehtmldom/simple_html_dom.php');
include("../base/base.php");

$university_id = 1;

$url = 'https://conf.psuti.ru/';
$url_ = 'https://conf.psuti.ru';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$html_content = curl_exec($ch);

if (curl_errno($ch)) {
    die('Error: ' . curl_error($ch));
}

curl_close($ch);

$html = str_get_html($html_content);

if ($html === FALSE) {
    die("Error: Unable to parse HTML");
}

$years = array();

foreach ($html->find('.year a') as $element) {
    $year = array();
    $year['link'] = $element->href;
    $year['year'] = $element->innertext;
    $years[] = $year;
}

if (empty($years)) {
    die("Error: No years found on $url");
}

// $years = array_slice($years, 0, 1); // оставили только один элемент (один год)

// echo json_encode($years, JSON_UNESCAPED_UNICODE);






$conferences = array();

// массив для преобразования русских названий месяцев в английские
$months = array(
    'января' => 'January',
    'февраля' => 'February',
    'марта' => 'March',
    'апреля' => 'April',
    'мая' => 'May',
    'июня' => 'June',
    'июля' => 'July',
    'августа' => 'August',
    'сентября' => 'September',
    'октября' => 'October',
    'ноября' => 'November',
    'декабря' => 'December'
);

foreach ($years as $year) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_ . $year['link']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $html_content = curl_exec($ch);

    if (curl_errno($ch)) {
        die('Error: ' . curl_error($ch));
    }

    curl_close($ch);

    $html = str_get_html($html_content);

    if ($html === FALSE) {
        die("Error: Unable to parse HTML");
    }

    foreach ($html->find('.row') as $element) {
        $conference = array();
        $conference['name'] = trim($element->find('.conf-name a', 0)->plaintext);

        // извлечение и проверка типа конференции
        $type = $element->find('text', 2);
        $conference['type'] = $type ? trim($type->innertext) : "";

        $conference['link'] = $element->find('.conf-name a', 0)->href;
        $conference['url'] = $url_ . $conference['link'];


        $conferences[] = $conference;
    }
}

// print_r($conferences);

// echo json_encode($conferences, JSON_UNESCAPED_UNICODE);



// $conferences = array_reverse($conferences); // конференции в обратном порядке (для теста)



$rules_thesis = null;



foreach ($conferences as &$conference) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_ . $conference['link']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $html_content = curl_exec($ch);

    if (curl_errno($ch)) {
        die('Error: ' . curl_error($ch));
    }

    curl_close($ch);

    $html = str_get_html($html_content);

    if ($html === FALSE) {
        die("Error: Unable to parse HTML");
    }

    // извлечение табов из меню
    $tabs = array();
    foreach ($html->find('#conf-menu ul.operations li a') as $element) {
        $tab = array();
        $tab['name'] = $element->innertext;
        $tab['link'] = $element->href;

        if ($tab['name'] == "Правила оформления тезисов докладов") {
            $rules_thesis = $url_ . $tab['link'];
        }
        $tabs[] = $tab;
    }
    $conference['tabs'] = $tabs;

    // извлечение общей информации
    $info = [];
    foreach ($html->find('.form.conf-info .row') as $element) {

        $labelElement = $element->find('.label label', 0);
        if ($labelElement !== null) {
            $label = trim($labelElement->innertext);

            $valueElement = $element->find('.value', 0);
            if ($valueElement !== null) {
                $value = trim($valueElement->innertext);

                $label = trim($element->find('.label label', 0)->innertext);
                $value = trim($element->find('.value', 0)->innertext);


                if ($label == 'E-mail') {
                    // разделение строки по символу ">"
                    $parts = explode('>', $value);
                    // Взятие нужной части
                    if (isset($parts[2])) {
                        $value = $parts[2];
                        // извлечение адреса электронной почты из JavaScript
                        preg_match_all('/[0-9]+/', $value, $matches);
                        $email = '';
                        foreach ($matches[0] as $match) {
                            $email .= chr($match);
                        }
                        // удаление префикса "mailto:"
                        $email = str_replace('mailto:', '', $email);
                        $value = $email;
                        // $info["emaillll"] = $parts;
                        // $info["email"] = $value;
                        array_push($info, ["type" => "email", "value" => $value]);
                    }
                } else if ($label == 'Телефон') {
                    // $info["phone"] = formattedNumbers($value);
                    $values = formattedNumbers($value);
                    foreach ($values as $num) {
                        array_push($info, ["type" => "phone", "value" => $num]);
                    }
                } else if ($label == 'Даты проведения') {
                    // $info["date"] = $value;
                    array_push($info, ["type" => "date", "value" => $value]);
                } else if ($label == 'Дата окончания регистрации') {
                    // $info["date_reg"] = $value;
                    array_push($info, ["type" => "date_reg", "value" => $value]);
                } else if ($label == 'Дата окончания принятия докладов') {
                    // $info["date_doc"] = $value;
                    array_push($info, ["type" => "date_doc", "value" => $value]);
                } else if ($label == 'Место проведения') {
                    // $info["location"] = $value;
                    array_push($info, ["type" => "location", "value" => $value]);
                } else if ($label == 'Телефон') {
                    $info["phone"] = $value;
                } else if ($label == 'Телефон') {
                    $info["phone"] = $value;
                }
                // $info[$label] = $value;
            }
        }
    }




    $conference['info'] = $info;

    if ($rules_thesis != null) {
        array_push($conference['info'], ["type" => "rules_thesis", "value" => $rules_thesis]);
    }











    foreach ($conference['tabs'] as $tab) {
        if ($tab['name'] == 'Программа конференции' || $tab['name'] == 'Труды конференции') {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url_ . $tab['link']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $tab_html_content = curl_exec($ch);

            if (curl_errno($ch)) {
                die('Error: ' . curl_error($ch));
            }

            curl_close($ch);

            $tab_html = str_get_html($tab_html_content);

            if ($tab_html === FALSE) {
                die("Error: Unable to parse HTML");
            }

            // извлечение ссылки на PDF
            $pdf_link = $tab_html->find('div.form a', 0)->href;

            // проверка, является ли URL полным
            if (!preg_match("/^http(s)?:\/\//i", $pdf_link)) {
                $pdf_link = $url_ . $pdf_link;
            }

            if ($tab['name'] == 'Программа конференции') {
                $tab_name_info = "program";
            } else if ($tab['name'] == 'Труды конференции') {
                $tab_name_info = "proceedings";
            }

            array_push($conference['info'], ["type" => $tab_name_info, "value" => $pdf_link]);

            // $conference[$tab['name']] = $pdf_link;
        }
    }



    print_r($conference);

    $findConfBase = R::findOne("conferences", "university_id = ? AND link = ?", [$university_id, $conference["link"]]);

    if ($findConfBase == null) {
        print_r("\nКонференции НЕТ в БД\n");
        // sleep(1); // для теста даты

        $newСonference = R::dispense("conferences");
        $newСonference->university_id = $university_id;
        $newСonference->name = $conference["name"];
        $newСonference->link = $conference["link"];
        $newСonference->url = $conference["url"];
        $newСonference->uid = substr($conference["link"], 1);
        $newСonference->type = $conference["type"];
        $newСonference->date = getDateTime();
        $newСonference->date_update = getDateTime();
        // $newСonference->start_date = $conference["start_date"];
        // $newСonference->end_date = $conference["end_date"];
        R::store($newСonference);

        foreach ($conference["info"] as $info) {
            $infoBase = R::findOne("info", "type = ?", [$info["type"]]);
            if ($infoBase != null) {
                $findInfoInСonference = R::findOne("infoconferences", "info_id = ? AND value = ? AND conference_id = ?", [$infoBase->id, $info["value"], $newСonference->id]);
                if ($findInfoInСonference == null) {
                    $newInfoInСonference = R::dispense("infoconferences");
                    $newInfoInСonference->info_id = $infoBase->id;
                    $newInfoInСonference->conference_id = $newСonference->id;
                    $newInfoInСonference->value = $info["value"];
                    $newInfoInСonference->date = getDateTime();
                    $newInfoInСonference->date_update = getDateTime();
                    R::store($newInfoInСonference);
                }
            }
        }
    } else {
        print_r("\nКонференция ЕСТЬ в БД");
        print_r("Нужно обновить данные\n\n");
    }
}







function formattedNumbers($phoneNumbers)
{
    $numbers = explode(';', $phoneNumbers);

    $formattedNumbers = [];
    foreach ($numbers as $number) {
        // удаление пробелов
        $number = trim($number);
        // если номер начинается с "8", заменить "8" на "+7" и сохранить префикс
        if (strpos($number, '8') === 0) {
            $prefix = '7' . substr($number, 2, 3);
            $number = substr($number, 5);
        } else {
            $prefix = '7' . substr($formattedNumbers[0], 1, 3);  // использование префикса предыдущего номера
        }
        // добавление префикса
        $number = $prefix . $number;
        // удаление пробелов и скобок
        $number = str_replace([' ', '(', ')', '-'], '', $number);
        $formattedNumbers[] = $number;
    }

    return $formattedNumbers;
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
