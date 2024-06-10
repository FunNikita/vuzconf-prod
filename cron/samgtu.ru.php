<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-type: application/json; charset=utf-8");
date_default_timezone_set("Europe/Moscow"); // заменить на Самару

include('../simplehtmldom/simple_html_dom.php');
include("../base/base.php");

$university_id = 2;

$url = 'https://samgtu.ru/conferences';
$url_ = 'https://samgtu.ru';

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


foreach ($html->find('tr') as $row) {
    $eventCell = $row->find('td', 0);
    $dateCell = $row->find('td', 1);

    if ($eventCell && $dateCell) {
        $event = $eventCell->plaintext;
        $date = $dateCell->plaintext;

        if ($event && $date) {
            $event = trim($event);
            $date = trim($date);

            $link = $row->find('a', 0);
            if ($link) {
                $href = $link->href;
                $event = $link->plaintext;

                $href_ = $href;

                // проверяем, начинается ли ссылка с 'http'
                if (strpos($href, 'http') !== 0) {
                    // проверяем, начинается ли ссылка с '/'
                    if (substr($href, 0, 1) === '/') {
                        $href_ = $url_ . $href;
                    } else {
                        $href_ = $url . $href;
                    }
                }

                // проверяем расширение файла
                $file = false;
                $extension = pathinfo($href, PATHINFO_EXTENSION);
                if ($extension === 'pdf' || $extension === 'doc' || $extension === 'docx') {
                    $file = true;
                }

                $conferences[] = array(
                    "name" => $event,
                    "link" => $href_,
                    "link_base" => $href,
                    "date" => $date,
                    "file" => $file,
                );
            }
        }
    }
}

// print_r($conferences);




// ограничиваем количество конференций до 10
// $conferences = array_slice($conferences, 0, 15);

// $conferences = array_reverse($conferences); // конференции в обратном порядке (для теста)

$names = [
    "Условия участия и правила представления материалов" => "participation_conditions_and_rules_url",
    "Правила оформления" => "rules_formatting_url",
    "Требования к оформлению материалов" => "rules_material_url",
    "Правила оформления тезисов доклада" => "rules_thesis_url",
    "Программа конференции" => "program_url",
    "Место проведения" => "location_url",
    "Контакты" => "contacts_url",

    "Формат проведения" => "format_event_url",
    "Важные даты" => "major_dates_url",
    "Условия участия" => "terms_participation_url",
    "Программа" => "program_url",
    "Требования к работе" => "job_requirements_url",
];


foreach ($conferences as &$conference) {
    $conference['info'] = [];
    if ($conference['file'] == false) {
        $conference_url = $conference['link'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $conference_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $conference_html_content = curl_exec($ch);

        if (curl_errno($ch)) {
            die('Error: ' . curl_error($ch));
        }

        curl_close($ch);

        $conference_html = str_get_html($conference_html_content);

        if ($conference_html === FALSE) {
            die("Error: Unable to parse HTML");
        }

        $tabs = [];
        foreach ($conference_html->find('.sidebar-menu__item') as $item) {
            $tab_name = $item->find('.sidebar-menu__align', 0)->plaintext;
            $tab_link = $item->href;

            $tabs[] = array(
                "name" => $tab_name,
                "link" => $tab_link,
            );
        }

        $info = [];
        array_push($info, ["type" => "date", "value" => $conference["date"]]);
        // $conference['tabs'] = $tabs;

        foreach ($tabs as $tab) {
            if (array_key_exists($tab["name"], $names)) {
                // $conference[$names[$tab["name"]]] = $url_ . $tab["link"];
                array_push($info, ["type" => $names[$tab["name"]], "value" => $url_ . $tab["link"]]);
            }
        }

        $conference['info'] = $info;
    }

    print_r($conference);

    $findConfBase = R::findOne("conferences", "university_id = ? AND link = ?", [$university_id, $conference["link_base"]]);

    if ($findConfBase == null) {
        print_r("\nКонференции НЕТ в БД\n");
        // sleep(1); // для теста даты

        $newСonference = R::dispense("conferences");
        $newСonference->university_id = $university_id;
        $newСonference->name = $conference["name"];
        $newСonference->link = $conference["link_base"];
        $newСonference->url = $conference["link"];
        $newСonference->date = getDateTime();
        $newСonference->date_update = getDateTime();
        $newСonference->file = $conference["file"]; // конфа по ссылке — файл? 1 — да, 0 — нет
        R::store($newСonference);

        $newСonference->uid = "conf-{$newСonference->id}";
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
