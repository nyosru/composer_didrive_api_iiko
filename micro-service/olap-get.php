<?php


if (isset($skip_start) && $skip_start === true) {
    
} else {
    require_once '../../../../vendor/didrive/base/start-for-microservice.php';
}

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

    if( !empty($items) ){

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
    }else{
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