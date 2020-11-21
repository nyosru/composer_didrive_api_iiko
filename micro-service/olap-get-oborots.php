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
            "from" => date('Y-m-d', strtotime('-' . ( $_REQUEST['scan_day'] ?? 3 ) . ' day')),
            "to" => date('Y-m-d', strtotime('-1 day')),
            "includeLow" => "true",
            "includeHigh" => "true"
        ]
    ]
];

// \f\pa(json_encode($post) ,'','','res0' );
//$post2 = http_build_query($post)  ;
//\f\pa( $post2 ,'','','post2' );

$uri = \Nyos\api\Iiko::$protokol . \Nyos\api\Iiko::$host . '/resto/api/v2/reports/olap?key=' . \Nyos\api\Iiko::$api_key;

if (isset($_REQUEST['show']))
    \f\pa($uri);

$ress = \Nyos\api\Iiko::curl_post(
                $uri,
                json_encode($post)
                // http_build_query($post)  
                //$post
                ,
                [CURLOPT_HTTPHEADER => ['Content-Type: application/json']]
);

// \f\pa( json_decode($ress) , 2, '', 'ress');

$return = json_decode($ress);


$rr = \Nyos\api\Iiko::getAnswer('выход');


$sp_all = \Nyos\mod\items::get($db, \Nyos\mod\JobDesc::$mod_sale_point, 'show', 'id_id');
$sps0 = \Nyos\mod\items::get($db, \Nyos\mod\JobDesc::$mod_sale_point);
$sps = [];
foreach ($sps0 as $k => $v) {
    if (
            !empty($v['id']) &&
            !empty($v['olap_name_group'])
    ) {
        $sps[$v['olap_name_group']] = $v['id'];
    }
}

if (isset($_REQUEST['show']))
    \f\pa($sps);

$return_sp_oborot = [];

$search = [];

foreach ($return->data as $k => $v) {
    $n2 = 0;
    $in0 = [];
    foreach ($v as $k1 => $v1) {

        if ($k1 == 'DishDiscountSumInt') {
            $n2 += 10;
            $in0['oborot'] = $v1;
        } elseif ($k1 == 'OpenDate.Typed') {
            $n2 += 100;
            $in0['date'] = $v1;
        } elseif ($k1 == 'RestorauntGroup' && isset($sps[$v1])) {
            $n2 += 1;
            $in0['sale_point'] = $sps[$v1];
        }
        // echo '<br/>' . $k1 . ' - ' . $v1;
    }

    if ($n2 == 111) {
        $in[$in0['sale_point']][$in0['date']] = $in0['oborot'];
        \Nyos\mod\items::$search['date'][] = $in0['date'];
        \Nyos\mod\items::$search['sale_point'][] = $in0['sale_point'];
    }
}

if (isset($_REQUEST['show']))
    \f\pa($in, 2, '', '$in');

$ee = \Nyos\mod\items::get($db, \Nyos\mod\JobDesc::$mod_oborots);
//\f\pa($ee, 2, '', '$ee');

$now_sp_date_oborot_id = [];
foreach ($ee as $k => $v) {
    if (!empty($v['sale_point']) && !empty($v['date']) && !empty($v['oborot_server']) && !empty($v['id']))
        $now_sp_date_oborot_id[$v['sale_point']][$v['date']] = ['oborot' => $v['oborot_server'], 'id' => $v['id']];
}

if (isset($_REQUEST['show']))
    \f\pa($now_sp_date_oborot_id, 2, '', '$now_sp_date_oborot_id что уже есть в базе');

$adds = [];
$edited = [];

    $sms = 'грузим обороты с ИИКО' . PHP_EOL ;


for ($u = 1; $u <= ( $_REQUEST['scan_day'] ?? 3 ); $u++) {

    $scan_day = date('Y-m-d', strtotime('-' . $u . ' day'));
    if (isset($_REQUEST['show'])) {
        echo '<hr/>' . $scan_day . '<hr/> ';
        echo '<blockqueote>';
    }

    foreach ($in as $sp => $vv) {

        if (isset($vv[$scan_day])) {

            if (isset($_REQUEST['show'])) {
                echo '<br/>';
                echo '<br/>' . $sp . ' ' . $scan_day . ' ' . $vv[$scan_day] . ' есть ';

                if (isset($now_sp_date_oborot_id[$sp][$scan_day]['id']))
                    echo '<Br/>id - ' . $now_sp_date_oborot_id[$sp][$scan_day]['id'];

                echo '<br/>' . $sp . ' ' . $scan_day . ' ';
            }


            // всё норм сходится и есть
            if (isset($now_sp_date_oborot_id[$sp][$scan_day]['oborot']) && $now_sp_date_oborot_id[$sp][$scan_day]['oborot'] == $vv[$scan_day]) {

                if (isset($_REQUEST['show']))
                    echo 'сходится';
                continue;
                break;
            }
            // ( есть не сходится) изменяем
            elseif (!empty($now_sp_date_oborot_id[$sp][$scan_day]['id'])) {

                if (isset($_REQUEST['show']))
                    echo 'НЕ сходится';

                $edited[$now_sp_date_oborot_id[$sp][$scan_day]['id']] = [':oborot' => $vv[$scan_day], ':id' => $now_sp_date_oborot_id[$sp][$scan_day]['id']];

                $sms .= PHP_EOL.$scan_day.' '.$sp_all[$sp]['head'].' edit '.$vv[$scan_day].'р';
                
                continue;
                break;
//
//
//                // echo '<br/>' . $sp . ' ' . $v['date'] . ' ' . ( $v['oborot_hand'] ?? '-' ) . '/' . ( $v['oborot_server_hand'] ?? '-' ) . '/' . ( $v['oborot_server'] ?? '-' ) . '/' . $oborot;
//                // $sql_delete[] = $v['id'];
//
//                $sql = 'UPDATE `mod_' . \f\translit(\Nyos\mod\jobdesc::$mod_oborots, 'uri2') . '` SET `oborot_server_hand` = NULL, `oborot_server`=:oborot WHERE id = :id LIMIT 1 ;';
//
//                echo '<br/>' . $sql;
//                $ff = $db->prepare($sql);
//
//                $ar = [':oborot' => $oborot, ':id' => $v['id']];
//                $ff->execute($ar);
//
//                break;
            }
            // добавляем новый
            else {
                if (isset($_REQUEST['show']))
                    echo ' Добавляем';
                $adds[] = [
                    'sale_point' => $sp,
                    'date' => $scan_day,
                    'oborot_server' => $vv[$scan_day]
                ];
                
                $sms .= PHP_EOL.$scan_day.' '.$sp_all[$sp]['head'].' add '.$vv[$scan_day].'р';
                
            }
        }
        //
        else {
            if (isset($_REQUEST['show']))
                echo '<br/>' . $scan_day . ' ' . $sp . ' ' . $vv[$scan_day] . ' нет';
        }
    }



    if (1 == 2) {

        foreach ($in as $sp => $vv) {

            if (isset($vv[$scan_day])) {

                \f\pa([$sp, $scan_day, $vv[$scan_day]]);

                $est = false;

                foreach ($ee as $k => $v) {
                    if ($v['sale_point'] == $sp && $v['date'] == $scan_day) {

                        $est = true;
                        echo '<br/>' . $sp . ' ' . $v['date'] . ' ' . ( $v['oborot_hand'] ?? '-' ) . '/' . ( $v['oborot_server_hand'] ?? '-' ) . '/' . ( $v['oborot_server'] ?? '-' ) . '/' . $oborot;

                        if ($v['oborot_server'] == $vv[$scan_day]) {

                            echo '<br/>' . $sp . ' ' . $date . ' ok';
                            break;
                        } else {

                            // echo '<br/>' . $sp . ' ' . $v['date'] . ' ' . ( $v['oborot_hand'] ?? '-' ) . '/' . ( $v['oborot_server_hand'] ?? '-' ) . '/' . ( $v['oborot_server'] ?? '-' ) . '/' . $oborot;
                            // $sql_delete[] = $v['id'];

                            $sql = 'UPDATE `mod_' . \f\translit(\Nyos\mod\jobdesc::$mod_oborots, 'uri2') . '` SET `oborot_server_hand` = NULL, `oborot_server`=:oborot WHERE id = :id LIMIT 1 ;';

                            echo '<br/>' . $sql;
                            $ff = $db->prepare($sql);

                            $ar = [':oborot' => $oborot, ':id' => $v['id']];
                            $ff->execute($ar);

                            break;
                        }
                    }
                }
            }
        }
    }

    if (isset($_REQUEST['show']))
        echo '</blockqueote>';
}

if (!empty($adds)) {
    \f\pa($adds, 2, '', 'добавляем записи');
    \Nyos\mod\items::adds($db, \Nyos\mod\JobDesc::$mod_oborots, $adds);
}


if (!empty($edited)) {

    $sql = 'UPDATE `mod_' . \f\translit(\Nyos\mod\jobdesc::$mod_oborots, 'uri2') . '` SET `oborot_server_hand` = NULL, `oborot_server`=:oborot WHERE id = :id LIMIT 1 ;';

    try {
        // Начало транзакции
        $db->beginTransaction();
        // ... code

        foreach ($edited as $kk => $ar) {

            // echo '<br/>' . $sql;
            $ff = $db->prepare($sql);
            if (isset($_REQUEST['show']))
                \f\pa($ar);
            $ff->execute($ar);
        }

        // Если в результате выполнения нашего кода всё прошло успешно,
        // то зафиксируем этот результат
        $db->commit();
    } catch (Exception $e) {
        // Иначе, откатим транзакцию. 
        $pdo->rollBack();
        echo "Ошибка: " . $e->getMessage();
    }
}

$sms .= PHP_EOL . 'итого: изменили ' . sizeof($edited) . ' добавили ' . sizeof($adds);
\Nyos\Msg::sendTelegramm($sms, null, 2);

die('изменили ' . sizeof($edited) . ' добавили ' . sizeof($adds) . ' end');

\f\pa($new, 2, '', '$new');




$new = [];

foreach ($in as $sp => $v1) {
    foreach ($v1 as $date => $oborot) {

        $est = false;

        foreach ($ee as $k => $v) {
            if ($v['sale_point'] == $sp && $v['date'] == $date) {

                $est = true;

                echo '<br/>' . $sp . ' ' . $v['date'] . ' ' . ( $v['oborot_hand'] ?? '-' ) . '/' . ( $v['oborot_server_hand'] ?? '-' ) . '/' . ( $v['oborot_server'] ?? '-' ) . '/' . $oborot;

                if ($v['oborot_server'] == $oborot) {

                    echo '<br/>' . $sp . ' ' . $date . ' ok';
                    break;
                } elseif ($v['oborot_server'] != $oborot) {

                    // echo '<br/>' . $sp . ' ' . $v['date'] . ' ' . ( $v['oborot_hand'] ?? '-' ) . '/' . ( $v['oborot_server_hand'] ?? '-' ) . '/' . ( $v['oborot_server'] ?? '-' ) . '/' . $oborot;
                    // $sql_delete[] = $v['id'];

                    $sql = 'UPDATE `mod_' . \f\translit(\Nyos\mod\jobdesc::$mod_oborots, 'uri2') . '` SET `oborot_server_hand` = NULL, `oborot_server`=:oborot WHERE id = :id LIMIT 1 ;';

                    echo '<br/>' . $sql;
                    $ff = $db->prepare($sql);

                    $ar = [':oborot' => $oborot, ':id' => $v['id']];
                    $ff->execute($ar);

                    break;
                }
            }
        }

        if ($est === false) {
            echo '<br/>' . __LINE__ . ' ' . $sp . ' ' . $date . ' ' . $oborot;

            $new[] = [
                'sale_point' => $sp,
                'date' => $date,
                'server_oborot' => $oborot
            ];

            break;
        }
    }
}

\f\pa($new, 2, '', '$new');



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