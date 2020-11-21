<?php

if (isset($skip_start) && $skip_start === true) {
    
} else {
    require_once '../../../../vendor/didrive/base/start-for-microservice.php';
}

\Nyos\api\Iiko::getConfigDbIiko();

if (!empty($_REQUEST['exit_key'])) {

    \Nyos\api\iiko::$api_key = $_REQUEST['exit_key'];
    $rr = \Nyos\api\Iiko::getAnswer('выход');
    // \f\pa($rr);
    die('вышли из ключа');
}

//\f\pa(\Nyos\api\Iiko::$api_key);



if (!empty($_REQUEST['polya'])) {

    $polya = \Nyos\api\Iiko::getOlapPolya();
// \f\pa($polya,2);

    echo '<div style="border: 1px solid green; padding: 20px; display:block; overflow: auto; max-height:200px;" >';
    echo '<table class="table table-striped" ><tbody>';
    foreach ($polya as $k => $v) {
        echo '<tr>'
        . '<td>' . ( $v['name'] ?? '-' ) . '</td>'
        . '<td>' . $k . '</td>';

        echo '<td>';
        foreach ($v as $k1 => $v1) {
            if (!is_array($v1)) {
                echo $k1 . ' - ' . $v1 . '<br/>';
            } else {
                echo '===' . $k1 . ' === <br/>';
                foreach ($v1 as $k2 => $v2) {
                    echo ' -- ' . $k2 . ' - ' . $v2 . '<br/>';
                }
            }
        }
        echo '</td>';


        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}

//"OpenDate.Typed": {
//"filterType": "DateRange",
//"periodType": "CUSTOM",
//"from": "2014-01-01T00:00:00.000",
//"to": "2014-01-03T00:00:00.000" 
//}
// http://api-delivery.iiko.ru:8080/resto/api/v2/reports/olap?&key=4de01862-d72d-cf30-c36c-a2f2632c389c
// Тип запроса: POST

\Nyos\api\Iiko::$show_dop_info = true;

if (\Nyos\api\Iiko::$api_key === null)
    \Nyos\api\Iiko::getAutKey();

$post = [
    "reportType" => "SALES",
    "groupByRowFields" => [
        "RestorauntGroup"
    ],
    "groupByColFields" => [
        "OpenDate.Typed"
    ],
    "aggregateFields" => [
        // "DishSumInt",
        "DishDiscountSumInt"
    ],
    "filters" => [
        "OpenDate.Typed" => [
            "filterType" => "DateRange",
            "periodType" => "CUSTOM",
            "from" => "2020-11-09",
            "to" => "2020-11-15",
            "includeLow" => "true",
            "includeHigh" => "true"
        ]
    ]
];

// \f\pa(json_encode($post) ,'','','res0' );

//$post2 = http_build_query($post)  ;
//\f\pa( $post2 ,'','','post2' );

$uri = \Nyos\api\Iiko::$protokol . \Nyos\api\Iiko::$host . '/resto/api/v2/reports/olap?key=' . \Nyos\api\Iiko::$api_key;

\f\pa($uri);

$ress = \Nyos\api\Iiko::curl_post(
        $uri , 
         json_encode($post)  
         // http_build_query($post)  
        //$post
        ,
        [ CURLOPT_HTTPHEADER => ['Content-Type: application/json'] ]
        );

\f\pa( json_decode($ress) , 2, '', 'ress');


$rr = \Nyos\api\Iiko::getAnswer('выход');

\f\end2('exit now, false', false);
























// если нужно не обращать внимания на кеш
// if (!empty($_GET['no_load_cash']))
\Nyos\api\Iiko::$cash = false;

// getConfigDbIiko


die();

\Nyos\api\Iiko::getConfigDbIiko();

\Nyos\api\Iiko::getOlapPolya();

$re['data'] = \Nyos\api\Iiko::getAnswer('выход');

\f\pa($re);

die('<br/>конец');

// $re['data'] = \Nyos\api\Iiko::getAnswer('подразделения');

$v = [
    // 'department' => '8e5f876b-7b41-45ac-b01b-9311c552bb33' ,
    // 'department' => '4c360162-6e12-da32-0145-88f5ce8c000d' ,
    // 'department' => 'f7ccc3ad-0e49-41f6-8f1a-54271aefbab9' ,
    // 'department' => '19fdf41e-74f9-4926-aa7d-54cf0016c51b' ,
    // 'department' => '7135f47f-7ce6-4189-be06-93cd4c5d6431' ,
    // 'department' => '8e5f876b-7b41-45ac-b01b-9311c552bb33' ,
    // 'department' => 'befb12f4-0615-4c79-8163-1db7089f4c01' ,
    //'department' => '4c360162-6e12-da32-0145-88f5ce8c000d' ,
    //tt1
    'department' => 'f939f35f-c169-4be9-9933-5af230748ede',
    'dateFrom' => date('d.m.Y', $_SERVER['REQUEST_TIME'] - 3600 * 24 * 2),
//    'hourFrom' => 6,
//    'hourTo' => 5,
//    'dishDetails' => 'false',
//    'allRevenue' => 'false'
];
$v['dateTo'] = date('d.m.Y', strtotime($v['dateFrom'] . ' +1 day '));

\Nyos\api\Iiko::$show_uri = true;
$re['data'] = \Nyos\api\Iiko::getAnswer('reports/sales', $v);

//\f\pa($re['data']);

$re['data'] = \Nyos\api\Iiko::getAnswer('выход');
// \f\pa($re['data'],'','','');
echo '<br/>стёрли ключ доступа';






//
//URL
//http://94.137.22.202:9080/resto/api/auth?login=modules&pass=fe5f82b1307c85d42a85f7281c2ada54ef061f35
//
//Тип запроса: GET
//
//Параметры:
//{
//  "login": "modules",
//  "pass": "fe5f82b1307c85d42a85f7281c2ada54ef061f35"
//}
//
//Ответ:
//0ddc74e8-e898-ee56-d4ad-ad6bc6de2cee
//










\f\end2('что то не так', false);

die('123');

try {

//    $date = $in['date'] ?? $_REQUEST['date'] ?? null;
//    if (empty($date))
//        throw new \Exception('нет даты');

    require_once '0start.php';

    \f\pa($_REQUEST);

    echo '<h3>удаляем все автобонусы</h3>';

    $sql = 'UPDATE `mod_072_plus` '
            . ' SET `status` = \'delete\' '
            . ' WHERE `auto_bonus_zp` = \'da\' '
            . ' ;';
    // \f\pa($sql);
    $ff = $db->prepare($sql);
    $ff->execute();

//    $date_start = date('Y-m-01', strtotime($_REQUEST['date']));
//    $date_finish = date('Y-m-d', strtotime($date_start . ' +1 month -1 day'));

    \f\Cash::deleteKeyPoFilter([\Nyos\mod\JobDesc::$mod_bonus]);

    \Nyos\mod\items::$search['auto_bonus_zp'] = 'da';
//    \Nyos\mod\items::$between_date['date_now'] = [$date_start, $date_finish];
    // \Nyos\mod\items::$return_items_header = true;
    // \Nyos\mod\items::$show_sql = true;
    $items = \Nyos\mod\items::get($db, \Nyos\mod\JobDesc::$mod_bonus);
    \f\pa($items, 2);

    if (!empty($items)) {

        $ids = implode(', ', array_keys($items));
        \f\pa($ids);

        $sql = 'UPDATE `mitems` mi '
                . ' SET `mi`.`status` = \'delete\' '
                . ' WHERE mi.`module` = :module AND mi.`id` IN (' . $ids . ') '
                . ' ;';
        \f\pa($sql);
        $ff = $db->prepare($sql);

        // \f\pa($var_in_sql);
        $ff->execute([':module' => \Nyos\mod\JobDesc::$mod_bonus]);
    } else {
        echo '<br/>нечего удалять';
    }
    echo '<br/>' . __FILE__ . ' #' . __LINE__;

    die('удалено ' . sizeof($items));
} catch (Exception $exc) {

    echo '<pre>';
    print_r($exc);
    echo '</pre>';
    // echo $exc->getTraceAsString();
}