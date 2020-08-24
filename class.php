<?php

/**
  класс модуля
 * */

namespace Nyos\api;

ini_set("max_execution_time", 120);


//if (!defined('IN_NYOS_PROJECT'))
//    throw new \Exception('Сработала защита от розовых хакеров, обратитесь к администрратору');

/**
 * Парсер ответа от АПИ ИИКО
 */
class Iiko {

    /**
     * ip или uri API + порт
     * @var type 
     */
    public static $host = null;
    public static $login = null;
    public static $pass = null;
    public static $api_key = null;
    public static $protokol = 'http://';
    public static $db_type = 'dblib';
    public static $db_host = '';
    public static $db_port = '';
    public static $db_base = '';
    public static $db_login = '';
    public static $db_pass = '';
    public static $file_cash = '';

    /**
     * для данных о загруженной информации о сотрудниках
     * @var массив 
     */
    public static $data_iiko_people = [];

    /**
     * кешировать или не кешировать
     * @var bool
     */
    public static $cash = true;

    /**
     * русское название запроса и его uri
     * @var array
     */
    public static $request_ru = array(
        'сотрудники' => 'employees',
        'выход' => 'logout'
    );

    /**
     * расщифровка типов отделов
     * @var array
     */
    public static $types_org = array(
        'CORPORATION' => 'Корпорация',
        'JURPERSON' => 'Юридическое лицо',
        'ORGDEVELOPMENT' => 'Структурное подразделение',
        'DEPARTMENT' => 'Торговое предприятие',
        'MANUFACTURE' => 'Производство',
        'CENTRALSTORE' => 'Центральный склад',
        'CENTRALOFFICE' => 'Центральный офис',
        'SALEPOINT' => 'Точка продаж',
        'STORE' => 'Склад'
    );

    /**
     * сохраняем ключ доступа к айко
     */
    public static function saveCashKey() {
        file_put_contents(dirname(__FILE__) . '/iiko.cash.key', self::$api_key);
    }

    /**
     * достаём ключ из памяти
     * @return boolean
     */
    public static function getCashKey() {

        // echo '<br/>' . dirname(__FILE__) . '/iiko.cash.key';

        if (file_exists(dirname(__FILE__) . '/iiko.cash.key') && filemtime(dirname(__FILE__) . '/iiko.cash.key') > $_SERVER['REQUEST_TIME'] - 3600 * 2) {
            return self::$api_key = file_get_contents(dirname(__FILE__) . '/iiko.cash.key');
        } else {
            return false;
        }
    }

    /**
     * затираем сохранённый ключ
     * @return boolean
     */
    public static function clearCashKey() {
        if (file_exists(dirname(__FILE__) . '/iiko.cash.key'))
            unlink(dirname(__FILE__) . '/iiko.cash.key');
    }

    /**
     * получаем ключ доступа к айко
     */
    public static function getAutKey() {

        if (self::$cash === true) {
            if (self::getCashKey() !== false) {
                echo '<br/><br/>достали ключ из кеша<br/><br/>';
                return self::$api_key;
            }
        }

        /**
         * если что то из конфига пустое .. то подгружаем
         */
        if (empty(self::$host) || empty(self::$login) || empty(self::$pass))
            self::getConfigDbIiko();

        self::$api_key = self::curl_get(self::$protokol . self::$host . '/resto/api/auth', array('login' => self::$login, 'pass' => sha1(self::$pass)));

        if (strpos(self::$api_key, 'no connections available') !== false)
            throw new \Exception('нет свободных доступов');

        if (strpos(self::$api_key, 'HTTP 404') !== false)
            throw new \Exception(self::$api_key);

        if (self::$cash === true) {
            self::saveCashKey();
        }

        //echo '<br><br>key<br/>' . self::$api_key . '<hr>';
        echo '<div style="background-color:yellow;padding:10px;margin-bottom:10px;">получили key для подключения: ' . self::$api_key . '</div>';

        return self::$api_key;
    }

    public static function loadData($request = 'checki_day', $id_user_iiko = '', $start_date = null, $date_fin = null) {

        try {

            if ($start_date === null)
                $start_date = date('Y-m-d', ($_SERVER['REQUEST_TIME'] - 3600 * 24 * 3));

            if ($request == 'checki_day') {

                $dops = array(
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
//                    ,
//                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8'
                );

//            if (isset($_POST['typedb']) && $_POST['typedb'] == 'mysql' ) {
                $dops[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES 'utf8'";
//            }

                $db7 = new \PDO(
                        self::$db_type
                        . ':dbname=' . ( isset(self::$db_base{1}) ? self::$db_base : '' )
                        . ';host=' . ( isset(self::$db_host{1}) ? self::$db_host : '' )
                        . ( isset(self::$db_port{1}) ? ';port=' . self::$db_port : '' )
                        , ( isset(self::$db_login{1}) ? self::$db_login : '')
                        , ( isset(self::$db_pass{1}) ? self::$db_pass : '')
                        , $dops
                );

                // $db->query("SET NAMES utf8");
//                $ff = $db7->prepare('SET NAMES "utf8"');
//                $ff->execute();
                //$db7->exec("SET NAMES 'utf8'");
//                        USE Chain
//GO
//
//SELECT
//  id
// ,lastModifyNode
// ,revision
// ,created
// ,dateFrom
// ,dateTo
// ,department
// ,employee
// ,modified
// ,lastEditor
// ,attendanceType
// ,closeEvent_id
// ,comment
// ,personalSessionEnd
// ,personalSessionStart
// ,salaryDepartment
// ,role
// ,confirmedManually
// ,autoClosed
//FROM dbo.EmployeeAttendanceEntry;
//GO

                $ar_in_sql = array(
                    // ':id_user' => 'f34d6d84-5ecb-4a40-9b03-71d03cb730cb',
                    ':dates' => date('Y-m-d 00:00:00', strtotime($start_date))
                );

                $sql_users = '';

                if (!empty($id_user_iiko) && is_array($id_user_iiko)) {

                    $nn = 1;
                    foreach ($id_user_iiko as $v7) {
                        $sql_users .= (!empty($sql_users) ? ' OR ' : '' ) . ' employee = :u' . $nn;
                        $ar_in_sql[':u' . $nn] = $v7;
                    }
                } else {
                    $ar_in_sql[':id_user'] = $id_user_iiko;
                }

                if (!empty($date_fin)) {
                    $ar_in_sql[':date_end'] = date('Y-m-d 23:59:00', strtotime($date_fin));
                }

                $ff = $db7->prepare('SELECT ' .
                        // ' dbo.EmployeeAttendanceEntry.employee \'user\', '.
                        ' dbo.EmployeeAttendanceEntry.personalSessionStart \'start\', ' .
                        ' dbo.EmployeeAttendanceEntry.personalSessionEnd \'end\'
                FROM 
                    dbo.EmployeeAttendanceEntry 
                WHERE '
                        . (!empty($sql_users) ? $sql_users : ' employee = :id_user ' )
                        . ' AND personalSessionStart >= :dates '
                        . (!empty($date_fin) ? ' AND personalSessionStart <= :date_end ' : '' )
                );

                $ff->execute($ar_in_sql);
                //$e3 = $ff->fetchAll();
                $e3 = [];
                while ($e = $ff->fetch()) {
                    // $e['user2'] = mb_convert_encoding($e['user'],'UTF-8','auto');
                    // $e['user2'] = utf8_decode($e['user']);
                    // $e['user'] = utf8_encode($e['user']);
                    // echo '<br/>'.mb_detect_order($e['user']);
                    // $e['user2'] = iconv('UCS-2LE','UTF-8',substr(base64_decode($e['user']),0,-1));
                    // $e['user2'] = html_entity_decode($e['user'], ENT_COMPAT | ENT_HTML401, 'UTF-8');
                    $e3[] = $e;
                }
                //\f\pa($e3);
                $db7 = $ff = null;

                return $e3;
            }
        } catch (\Exception $ex) {
            echo '<pre>--- ' . __FILE__ . ' ' . __LINE__ . '-------'
            . PHP_EOL . $ex->getMessage() . ' #' . $ex->getCode()
            . PHP_EOL . $ex->getFile() . ' #' . $ex->getLine()
            . PHP_EOL . $ex->getTraceAsString()
            . '</pre>';
        } catch (\PDOException $ex) {
            echo '<pre>--- ' . __FILE__ . ' ' . __LINE__ . '-------'
            . PHP_EOL . $ex->getMessage() . ' #' . $ex->getCode()
            . PHP_EOL . $ex->getFile() . ' #' . $ex->getLine()
            . PHP_EOL . $ex->getTraceAsString()
            . '</pre>';
        }
    }

    /**
     * грузим чек ины с сервера
     * @param type $db
     * @param string $start_date
     * @param type $date_fin
     * @return int
     */
    public static function loadChecksFromServer($db, string $start_date, $date_fin = null) {

        /**
         * сколько сек берём на запросы к серверу ИИКО
         */
        $sec_on_load = 25;

        $one_date_scan = (!empty($_REQUEST['start']) && !empty($_REQUEST['finish']) && $_REQUEST['start'] == $_REQUEST['finish']) ? date('Y-m-d', strtotime($_REQUEST['start'])) : null;


        try {

            $date_start = date('Y-m-d', strtotime($start_date));

            if ($date_fin === null)
                $date_fin = date('Y-m-d', $_SERVER['REQUEST_TIME']);

            // \f\timer_start(5);
            // \Nyos\mod\items::$show_sql = true;
            \Nyos\mod\items::$where2dop = ' AND ( midop.name = \'iiko_id\' OR midop.name = \'iiko_checks_last_loaded\' ) ';
            $jobmans = \Nyos\mod\items::get($db, \Nyos\mod\JobDesc::$mod_jobman);
            //\f\pa($jobmans,2,'','$jobmans');
            usort($jobmans, "\\f\\sort_ar__iiko_checks_last_loaded__desc");
            //\f\pa($jobmans,2,'','$jobmans');
            // echo \f\timer_stop(5);
            // exit;


            $dops = array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            );

            $db7 = new \PDO(
                    self::$db_type
                    . ':dbname=' . ( isset(self::$db_base{1}) ? self::$db_base : '' )
                    . ';host=' . ( isset(self::$db_host{1}) ? self::$db_host : '' )
                    . ( isset(self::$db_port{1}) ? ';port=' . self::$db_port : '' )
                    , ( isset(self::$db_login{1}) ? self::$db_login : '')
                    , ( isset(self::$db_pass{1}) ? self::$db_pass : '')
                    , $dops
            );

            $return['loaded_checks'] = 0;
            $loaded = [];

            // старт времени
            \f\timer_start(311);

            /**
             * массив для сохранения дата время когда последний раз грузили сотрудника чеки с ИИКО
             */
            $new_dops_jm = [];

            $return['loaded_people'] = 0;

            foreach ($jobmans as $jobman_id => $jobman_data) {

// грузим и проверяем только тех кого не проверяли последние 6 часов                

                if (!empty($one_date_scan)) {
                    
                } else {
                    if (!empty($jobman_data['iiko_checks_last_loaded']) && $jobman_data['iiko_checks_last_loaded'] >= date('Y-m-d H:I:s', $_SERVER['REQUEST_TIME'] - 3600 * 6)) {
                        //if (!empty($jobman_data['iiko_checks_last_loaded']) && $jobman_data['iiko_checks_last_loaded'] >= date('Y-m-d H:I:s', $_SERVER['REQUEST_TIME'] - 180 )){
                        continue;
                    }
                }
//                if ( !empty($jobman_data['iiko_checks_last_loaded']) ){
//                echo '<br/><br/>'.$jobman_data['iiko_checks_last_loaded'];
//                echo '<br/>'.date('Y-m-d H:I:s', $_SERVER['REQUEST_TIME'] - 3600 * 6);
//                }

                $tt = \f\timer_stop(311, 'ar');
                // echo '<Br/>' . $tt['sec'];

                if (isset($tt['sec']) && $tt['sec'] > $sec_on_load)
                    break;

                if (empty($jobman_data['iiko_id']))
                    continue;

                $return['loaded_people'] ++;

                // \f\pa( $jobman_data,'','','$jobman_data' );
//                if (!isset($jobmans_onload[$jobman_data['id']]))
//                    continue;
                // доп параметр в список сотрудников, чтобы всегда грузили давно не обновлённые чеки
                $new_dops_jm[$jobman_data['id']]['iiko_checks_last_loaded'] = date('Y-m-d H:I:s');



                if (!empty($one_date_scan)) {
                    $ar_in_sql = array(
                        // ':id_user' => 'f34d6d84-5ecb-4a40-9b03-71d03cb730cb',
                        ':id_user' => $jobman_data['iiko_id'],
                        ':ds' => $one_date_scan . ' 05:00:00',
                        ':df' => date('Y-m-d 03:00:00', strtotime($one_date_scan . ' +1 day'))
                    );
                } else {
                    $ar_in_sql = array(
                        // ':id_user' => 'f34d6d84-5ecb-4a40-9b03-71d03cb730cb',
                        ':id_user' => $jobman_data['iiko_id'],
                        ':ds' => $date_start . ' 00:00:00',
                        ':df' => $date_fin . ' 23:59:00'
                    );
                }

                $sql = 'SELECT '

                        // . '*, ' .
                        // ' dbo.EmployeeAttendanceEntry.employee \'user\', '.
                        . ' dbo.EmployeeAttendanceEntry.personalSessionStart \'start\' '
                        . ' , '
                        . ' dbo.EmployeeAttendanceEntry.personalSessionEnd \'end\' '
                        . ' FROM 
                    dbo.EmployeeAttendanceEntry 
                WHERE '
                        . ' employee = :id_user '
                        . ' AND '
                        . ' personalSessionStart >= :ds '
                        . ' AND '
                        . ' personalSessionStart <= :df '
                ;

                //echo '<pre>' . $sql . '</pre>';
                $ff = $db7->prepare($sql);

                $ff->execute($ar_in_sql);
                while ($e = $ff->fetch()) {
                    $return['loaded_checks'] ++;
                    $loaded[$jobman_data['id']][] = $e;
                    // \f\pa($e, '', '', 'результ');
                }
            }

            $db7 = $ff = null;

            //echo 'dddddddd '.( $return['loaded_people'] ?? '23' );

            if ($return['loaded_people'] == 0) {
                return \f\end3('нет людей для проверки, все проверены', false, $return);
            }

            /**
             * тащим чеки
             */
            \Nyos\mod\items::$join_where = ' INNER JOIN `mitems-dops` mid '
                    . ' ON mid.id_item = mi.id '
                    . ' AND mid.name = \'start\' '
                    . ' AND mid.value_datetime >= :ds1 '
                    . ' AND mid.value_datetime <= :ds2 '
            ;

            \Nyos\mod\items::$var_ar_for_1sql[':ds1'] = date('Y-m-d 05:00:00', strtotime($date_start));
            \Nyos\mod\items::$var_ar_for_1sql[':ds2'] = date('Y-m-d 05:00:00', strtotime($date_fin . ' +1 day '));
            //\f\pa(\Nyos\mod\items::$var_ar_for_1sql);

            $checks = \Nyos\mod\items::get($db, \Nyos\mod\JobDesc::$mod_checks);
            // \f\pa($checks, 2, '', '$checks');
            // echo '<Br/>подгружено чеков: '.sizeof($checks);
            $return['loaded_job_checks'] = sizeof($checks);

            $add_new = $dop_add = $add = [];

            foreach ($loaded as $jobman => $v2) {
                foreach ($v2 as $k1 => $v1) {

                    // \f\pa($v1);

                    $check_id = null;
                    //$check_start = null;
                    //$check_fin = null;

                    $new_start = $v1['start'];
                    $new_fin = $v1['end'];

                    $checked = null;

                    foreach ($checks as $k => $check) {
                        if (isset($check['jobman']) && $check['jobman'] == $jobman) {

                            if (isset($check['start'])) {
                                //$check_start = $check['dop']['start'];

                                if ($check['start'] == $v1['start']) {

                                    $check_id = $check['id'];
                                    $checked = 'start';

                                    if (isset($check['fin'])) {
                                        //$check_fin = $check['dop']['fin'];

                                        if ($check['fin'] == $v1['end']) {
                                            //$check_id = $check['id'];
                                            $checked = 'fin';
                                        }
                                    }
                                }

                                //break;
                            }
                        }
                    }


                    if (!empty($_REQUEST['show_dops'])) {
                        // echo '</br>чек id : ' . ( $check_id ?? '--' );
                        $return['job_checks'][$check_id] = '';
                    }

                    if ($checked == 'start') {

                        if (!empty($_REQUEST['show_dops'])) {
                            // echo '</br>старт есть, если есть финиш то его добавим';
                            $return['job_checks'][$check_id] .= '</br>старт есть, если есть финиш то его добавим';
                        }

                        if (!empty($new_fin)) {
                            $dop_add[] = array(
                                'id_item' => $check_id
                                ,
                                'name' => 'fin'
                                ,
                                'value_datetime' => $new_fin
                            );

                            $dop_add[] = array(
                                'id_item' => $check_id
                                ,
                                'name' => 'hour_on_job'
                                ,
                                'value' => \Nyos\mod\IikoChecks::calcHoursInSmena($new_start, $new_fin)
                            );
                        }
                    } elseif ($checked == 'fin') {

                        if (!empty($_REQUEST['show_dops'])) {
                            //echo 'всё есть';
                            $return['job_checks'][$check_id] .= 'всё есть';
                        }
                    } else {

                        if (!empty($_REQUEST['show_dops'])) {
                            // echo '</br>нет данных, нужно добавить';
                            $return['job_checks'][$check_id] .= '</br>нет данных, нужно добавить';
                        }

                        $aa = array(
                            'jobman' => $jobman
                            ,
                            'start' => $new_start
                            ,
                            'fin' => $new_fin
                            ,
                            'hour_on_job' => \Nyos\mod\IikoChecks::calcHoursInSmena($new_start, $new_fin)
                            ,
                            'who_add_item' => 'iiko'
                        );

                        /**
                         * ищем в спец назначениях
                         */
//                        if (isset($send_on_sp[$jobman][substr($new_start, 0, 9)]))
//                            $aa['sale_point'] = $send_on_sp[$jobman][substr($new_start, 0, 9)];

                        $add[] = $aa;
                    }
                }
            }

            if (!empty($add)) {
                // \f\pa($add, 2, '', '$add');
                $return['adds_kolvo'] = sizeof($add);
                $return['adds'] = $add;
                // \Nyos\mod\items::addNewSimples( $db, '050.chekin_checkout', $add );
            } else {
                $return['adds_kolvo'] = 0;
            }

            // \f\pa($add_new, 2, '', '$add_new');
            if (!empty($add_new)) {
                $return['adds_start'] = sizeof($add_new);
                $return['adds_start_ar'] = $add_new;
                // \Nyos\mod\items::addNewSimples($db, '050.chekin_checkout', $add_new);
            } else {
                $return['adds_start'] = 0;
            }

            // \f\pa($dop_add, 2, '', '$dop_add');
            if (!empty($dop_add)) {
                $return['adds_dop_kolvo'] = sizeof($dop_add) / 2;
                $return['adds_dop_kolvo_ar'] = $dop_add;
                // \f\db\sql_insert_mnogo($db, 'mitems-dops', $dop_add);
                // \f\pa($dop_add, 2, '', '$dop_add');
            } else {
                $return['adds_dop_kolvo'] = 0;
            }


            // подгружаем дата время в последнюю загрузку чеков из ИИКО
            \Nyos\mod\items::saveNewDop($db, $new_dops_jm);

            return $return;
        }
        //
        catch (\Exception $ex) {

            echo '<pre>--- ' . __FILE__ . ' ' . __LINE__ . '-------'
            . PHP_EOL . $ex->getMessage() . ' #' . $ex->getCode()
            . PHP_EOL . $ex->getFile() . ' #' . $ex->getLine()
            . PHP_EOL . $ex->getTraceAsString()
            . '</pre>';


            if (1 == 1 && class_exists('\\Nyos\\Msg')) {

                $msg = 'Загрузка чекинов - ошибка (при подключении к удалённой БД): ' . $ex->getMessage();

                if (!isset($vv['admin_ajax_job'])) {
                    require_once DR . '/sites/' . \Nyos\nyos::$folder_now . '/config.php';
                }

                // $txt = $e['txt'];

                \nyos\Msg::sendTelegramm($msg, null, 1);

                if (isset($vv['admin_ajax_job'])) {
                    foreach ($vv['admin_ajax_job'] as $k => $v) {
                        \Nyos\Msg::sendTelegramm($msg, $v);
                    }
                }
            }

            return \f\end3('ошибка ' . $ex->getMessage(), false);
        }
        //
        catch (\PDOException $ex) {

            echo '<pre>--- ' . __FILE__ . ' ' . __LINE__ . '-------'
            . PHP_EOL . $ex->getMessage() . ' #' . $ex->getCode()
            . PHP_EOL . $ex->getFile() . ' #' . $ex->getLine()
            . PHP_EOL . $ex->getTraceAsString()
            . '</pre>';

            //echo '<Br/>' . __FILE__ . ' [' . __LINE__ . ']';

            if (1 == 1 && class_exists('\\Nyos\\Msg')) {

                $msg = 'Загрузка чекинов - ошибка (при подключении к удалённой БД): ' . $ex->getMessage();

                if (!isset($vv['admin_ajax_job'])) {
                    require_once DR . '/sites/' . \Nyos\nyos::$folder_now . '/config.php';
                }

                // $txt = $e['txt'];

                \nyos\Msg::sendTelegramm($msg, null, 1);

                if (isset($vv['admin_ajax_job'])) {
                    foreach ($vv['admin_ajax_job'] as $k => $v) {
                        \Nyos\Msg::sendTelegramm($msg, $v);
                    }
                }
            }

            return \f\end3('ошибка ' . $ex->getMessage(), false);
        }
    }

    public static function getAnswer($request, $vars = []) {

        $request2 = $request;

        if (isset(self::$request_ru[$request]))
            $request = self::$request_ru[$request];

        if (self::$api_key === null)
            self::getAutKey();



//        echo '<br/>';
//        echo '<br/>';
        $vars['key'] = self::$api_key;
//        echo '<br/>';
//        echo '<br/>';

        $uri = self::$protokol . self::$host . '/resto/api/' . $request;

        // echo '<br><br>uri: ' . $uri ;

        $re = self::curl_get($uri, $vars);

        // если ключ уже не работает, то забываем его
        if (strpos($re, 'Token is expired or invalid') !== false) {

            self::clearCashKey();
            $re = self::curl_get($uri, $vars);

            if (strpos($re, 'Token is expired or invalid') !== false)
                throw new \Exception('Токен не верный или не рабочий');
        }

        if (strpos($re, 'HTTP 404') !== false) {
            self::getAnswer('выход');
            throw new \Exception($re);
        }

        echo '<div style="background-color:yellow;padding:10px;margin-bottom:10px;max-height:300px;overflow:auto;">res<br/>' . $re . '</div>';

        $re = self::compileArray($re, $request2);

//        if (function_exists('\f\pa'))
//            \f\pa($array);

        return $re;
    }

    public static function diffLoadData( $new , $old) {

//        echo '<br/>ff ' . __FUNCTION__;
//        echo '<br/>#' . __LINE__ . ' ' . __FILE__;
//        echo '<br/>';
//        echo '<br/>';

        $add_iiko_id = [];
        
        /**
         * массив для записи изменённых dop 
         */
        $re = [
            'new_head' => [],
            'new_items' => [],
            'new_dop_data' => []
        ];

        $n = 0;

        foreach ($new as $k => $new1) {

            $new00 = true;

            foreach ($old as $k1 => $old1) {

//                \f\pa($v1['id'],2);
//                \f\pa($v0['iiko_id'],2);
//                    echo '<table><tr>'
//                    . '<td>';
//                        \f\pa( [ $new1['id'], $old1['iiko_id'] ] );                            
//                    echo '</td>'
//                    . '<td>';
//                        \f\pa( $new1 );
//                    echo '</td>'
//                    . '<td>';
//                        \f\pa( $old1 );
//                    echo  '</td>'
//                    . '</td></tr><table>';
//                    
//                    break; 
                    
                if (isset($new1['id']) && isset($old1['iiko_id']) && $old1['iiko_id'] == $new1['id']) {

//                    echo '<table><tr>'
//                    . '<td>';
//                        \f\pa( [ $v1['id'], $v0['iiko_id'] ] );                            
//                    echo '</td>'
//                    . '<td>';
//                        \f\pa( $v1 );
//                    echo '</td>'
//                    . '<td>';
//                        \f\pa( $v0 );
//                    echo  '</td>'
//                    . '</td></tr><table>';

                    $new00 = false;
                    break;

                }
            }

            if ($new00 === true && !empty( $new1['id'] ) ) {
                if( isset($add_iiko_id[ $new1['id'] ]) )
                    continue;
                $add_iiko_id[ $new1['id'] ] = 1;
                $re['new_items'][] = $new1;
//                    echo '<table><tr>'
//                    . '<td>';
//                        \f\pa( [ $v1['id'], $v0['iiko_id'] ] );                            
//                    echo '</td>'
//                    . '<td>';
//                        \f\pa( $v1 );
//                    echo '</td>'
//                    . '<td>';
//                        \f\pa( $v0 );
//                    echo  '</td>'
//                    . '</td></tr><table>';
            }

            $n++;
//            if( $n > 1 )
//                break;
        }

        return \f\end3('ok', true, $re);

//        echo '<table><tr><td>new';
//        \f\pa($ar_new, 2);
//        echo '</td><td>old';
//        \f\pa($ar_old, 2);
//        echo '</td></tr></table>';
//        $diff_ar = [
//            'firstName', 
//            'middleName', 
//            'lastName', 
//            //'code'
//            ];

        $qq = 0;

        foreach ($ar_new as $k => $v0) {

            // считаем первые 10
            if (1 == 1) {
                if ($qq >= 500)
                    break;

                // $qq++;
            }

            // собираем массив для вставки -> $v
            if (1 == 1) {
                $v = [];
                foreach ($v0 as $k3 => $v3) {
                    if (is_array($v3)) {
                        foreach ($v3 as $k31 => $v31) {
                            $v[$k3 . '_' . $k31] = $v31;
                        }
                    } elseif ($k3 == 'id' || $k3 == 'name') {
                        $v['iiko_' . $k3] = $v3;
                    } else {
                        $v[$k3] = $v3;
                    }
                }
            }



            $e = false;

            $v_old_now = [];

            foreach ($ar_old as $k_old => $v_old) {


                // if (isset($v_old['iiko_id']) && $v_old['iiko_id'] == $v['id']) {
                if (isset($v_old['iiko_id']) && $v_old['iiko_id'] == $v['iiko_id']) {

                    $new_head = trim(( $v_old['lastName'] ?? '' ) . ' ' . ( $v_old['firstName'] ?? '' ) . ' ' . ($v_old['middleName'] ?? '' ));
                    if (trim($v_old['head']) != $new_head) {
                        // \f\pa($v_old, '', '', 'v_old');
                        // echo '<br/>'.$v_old['id'].'| '.$v_old['head'].' = '.( $v_old['lastName'] ?? '' ).' '.( $v_old['firstName'] ?? '' ).' '.($v_old['middleName'] ?? '' );
                        $re['new_head'][$v_old['id']] = $new_head;
                    }

                    $e = true;
                    $v_old_now = $v_old;
                    break;
                }
            }





            foreach ($v as $v2 => $k2) {

                if (!isset($v[$v2]))
                    $v[$v2] = '';

                if (!isset($v_old_now[$v2]))
                    $v_old_now[$v2] = '';

                if ($v2 == 'birthday') {

                    $v[$v2] = date('Y-m-d', strtotime($v[$v2]));

                    $v2 = 'bdate';
                    $v[$v2] = $k2;

                    // unset($v['birthday']);

                    $v['bdate'] = date('Y-m-d', strtotime($v[$v2]));
                } elseif ($v2 == 'hireDate') {
                    $v[$v2] = date('Y-m-d', strtotime($v[$v2]));
                }
            }




            // не найдено соответствия
            if ($e === false) {
                // echo '<br/>n #' . __LINE__;
                // \f\pa($v, '', '', 'новое значение');
                $re['new_items'][] = $v;
            }
            // найдено соответствие, ищем разницу
            else {
                // echo '<br/>n #' . __LINE__;
//                $v3 = array_diff($v_old_now,$v);
//                \f\pa($v3);

                foreach ($v as $v2 => $k2) {

                    if (( is_string($v[$v2]) && is_string($v_old_now[$v2]) && trim($v[$v2]) != trim($v_old_now[$v2]) ) || !isset($v_old_now[$v2])) {
                        echo '<Br/>#' . __LINE__ . ' ' . $v2 . ' ' . ( $v[$v2] ?? 'x' ) . ' != ' . ( $v_old[$v2] ?? 'x' );
                        $re['new_dop_data'][$v_old['id']][$v2] = trim($v[$v2]);
                        $qq++;
                    }
                }

//                echo '<table><tr><td>new';
//                \f\pa($v);
//                echo '</td><td>old';
//                \f\pa($v_old_now);
//                echo '</td></tr></table>';
            }
        }

//        \f\pa($new_dop_data);
//        \f\Cash::deleteKeyPoFilter([\Nyos\mod\JobDesc::$mod_jobman]);
//        $e = \Nyos\mod\items::saveNewDop($db, $new_dop_data);
//        \f\pa($e,'','','$e');

        return $re;
    }

    /**
     * запись того что добыли тут loadIikoPeople()
     */
    public static function saveIikoPeople($db, $data, $mod_jobman = '070.jobman') {

        echo '<br/>ff ' . __FUNCTION__;
        echo '<br/>#' . __LINE__ . ' ' . __FILE__;

        $re = [
            'kolvo_in' => sizeof($data)
            , 'kolvo_edit_dop' => 0
        ];

        // $jm = \Nyos\mod\items::getItemsSimple3($db, $mod_jobman);
        $jm = \Nyos\mod\items::get($db, \Nyos\mod\JobDesc::$mod_jobman);


        // сравниваем старое и новое
        $res_diff = self::diffLoadData($jm, $data);
        \f\pa($res_diff, 2, '', '$res_diff');

        \f\Cash::deleteKeyPoFilter([\Nyos\mod\JobDesc::$mod_jobman]);

        // новые head у записей

        \f\pa($res_diff['new_head'], 2, '', 'записываем новые заголовки ' . sizeof($res_diff['new_head']) . ' ( id > head )');
        // $e = \Nyos\mod\items::saveEdit($db, $id_item, $folder, $cfg_mod, $data); // saveNewDop($db, $res_diff['new_head']);
        $nnh = 0;
        foreach ($res_diff['new_head'] as $u_id => $u_head) {

            if (isset($u_head{5})) {
                \f\db\db_edit2($db, 'mitems', array('id' => $u_id), array('head' => $u_head), false, 1, 'da');

                if ($nnh >= 50)
                    break;

                $nnh++;
            }
        }

        // записываем изменённые допы

        \f\pa($res_diff['new_dop_data'], 2, '', 'записываем обновлённые допы');
        $e = \Nyos\mod\items::saveNewDop($db, $res_diff['new_dop_data']);

        // \f\pa($e, '', '', 'items_save_new_dops');
        // записываем новые итемы

        \f\pa($res_diff['new_items'], 2, '', 'записываем новые записи');
        \Nyos\mod\items::addNewSimples($db, \Nyos\mod\JobDesc::$mod_jobman, $res_diff['new_items']);

        return [
            'new_head' => $nnh,
            'new_items' => sizeof($res_diff['new_items']),
            'new_dops_kolvo' => sizeof($res_diff['new_dop_data'])
        ];

        // \f\pa($jm, 2, '', 'jm');
//        foreach ($jm as $k => $v3) {
//            if ( $v3['iiko_id'] == 'c0db03ac-ba5c-4f4d-a8a4-7f60a322a02a') {
//                \f\pa($v3, '', '', 'one jm');
//                break;
//            }
//        }
        // die('<br/>#' . __LINE__ . ' ' . __FILE__);
        // exit;

        $re['kolvo_now'] = 0;
        $keys = [];

        foreach ($jm as $k => $v) {
            if (!empty($v['iiko_id'])) {
                $keys[$v['iiko_id']] = $k;
                $re['kolvo_now'] ++;
            }
        }

        $re['kolvo_old'] = $re['kolvo_new'] = 0;
        $nn = 0;

        $data_new_option = [];

        // die('<br/>#' . __LINE__ . ' ' . __FILE__);

        echo '<br/>#' . __LINE__ . ' ' . __FILE__;

        $nn1 = 0;
        foreach ($data as $k => $v) {

//            if ($v['id'] != 'c0db03ac-ba5c-4f4d-a8a4-7f60a322a02a')
//                continue;

            if ($nn1 <= 2) {
                \f\pa($v, 2, '', 'ser in 2');
                $nn1++;
            }

//            die();
            // если новый сотрудник
            if (!isset($keys[$v['id']])) {

                \f\pa($v);

                $re['kolvo_new'] ++;

                echo '<br/>#' . __LINE__ . ' ' . $re['kolvo_new'];


//                $d1 = [];
//                foreach ($v as $k1 => $v1) {
//                    if ($k1 == 'id' || $k1 == 'name') {
//                        $d1['iiko_' . $k1] = $v1;
//                    } elseif (is_array($v1)) {
//                        foreach ($v1 as $k2 => $v2) {
//                            $d1[$k1 . '_' . $k2] = $v2;
//                        }
//                    } else {
//                        $d1[$k1] = $v1;
//                    }
//                }
//
//                $data_new[] = $d1;

                $data_new[] = self::convertIikoPeopleAr($v);
            }
            // если есть id в имеющихся записях 
            // формируем массив $data_new_option с новыми доп параметрами
            else {

                foreach ($v as $k3 => $v3) {

                    if ($k3 == 'birthday')
                        continue;

//                    if ($k3 == 'id' && $v3 == 'c0db03ac-ba5c-4f4d-a8a4-7f60a322a02a')
//                        \f\pa($v);
//                    if ($k3 == 'name' || $k3 == 'id')
//                        echo '<br/>'.$k3.' - '.$v3;

                    if ($k3 == 'name' || $k3 == 'id') {
                        $k3_loc = 'iiko_' . $k3;
                    } else {
                        $k3_loc = $k3;
                    }

                    if (
                            !isset($jm[$keys[$v['id']]][$k3_loc]) || (
                            isset($jm[$keys[$v['id']]][$k3_loc]) && $jm[$keys[$v['id']]][$k3_loc] != $v3
                            )
                    ) {

//                        if ($k3 == 'id' && $v3 == 'c0db03ac-ba5c-4f4d-a8a4-7f60a322a02a')
//                            \f\pa($jm[$keys[$v['id']]]);
                        // \f\pa($jm[$keys[$v['id']]],2,'','уже есть в базе' )
                        // echo '<br/>' . __LINE__ . ' ' . $k3 . ' [' . $jm[$keys[$v['id']]][$k3_loc] . '] != [' . $v[$k3] . ']';

                        if ($nn <= 50 && 1 == 1) {
//                        if ( $nn <= 50 && 1 == 1 && (
//                                $k3 == 'name' ||
//                                $k3 == 'firstName' ||
//                                $k3 == 'middleName' ||
//                                $k3 == 'lastName' ||
//                                $k3 == 'cellPhone' || $k3 == 'phone' || $k3 == 'snils' || $k3 == 'address' )) 
//                            echo '<table><tr><td>';
//                            \f\pa($jm[$keys[$v['id']]], '', '', 'data3');
//                            echo '</td><td>';
//                            \f\pa($v, '', '', 'v3');
//                            echo '</td></tr></table>';
                            // если тут нет а там есть значение, добавляем новое
                            if (is_string($v3) && empty($jm[$keys[$v['id']]][$k3_loc]) && !empty($v3)) {

                                //echo '<br/>' . __LINE__ . ' ' . $k3 . ' добавляем [' . ( is_string($v[$k3]) ? $v[$k3] : 'ar' ) . ']';

                                $data_new_option[$jm[$keys[$v['id']]]['id']][$k3_loc] = $v3;
                                $re['kolvo_edit_dop'] ++;

//                                echo '<table><tr><td>';
//                                \f\pa($jm[$keys[$v['id']]]);
//                                echo '</td><td>';
//                                \f\pa($v);
//                                echo '</td></tr></table>';
                            }
                            // если и там и там значения есть но не сходятся
                            elseif (!empty($jm[$keys[$v['id']]][$k3_loc]) && !empty($v3) && is_string($v3) && $jm[$keys[$v['id']]][$k3_loc] != $v3) {

//                                echo '<br/>#' . __LINE__ . ( $jm[$keys[$v['id']]][$k3_loc] ?? 'x' ) && ( $v3 ?? 'x' );
//                                echo '<br/>#' . __LINE__ . $v['id'] . ' ' . ( $k3_loc ?? 'x' ) && ( $v3 ?? 'x' );
//                                echo '<br/>' . __LINE__ . ' ' . $k3 . ' [' . (!empty($jm[$keys[$v['id']]][$k3_loc]) ?
//                                        ( is_string($jm[$keys[$v['id']]][$k3_loc]) ? $jm[$keys[$v['id']]][$k3_loc] : 'ar' ) : 'empty' ) . '] != [' . ( is_string($v[$k3]) ? $v[$k3] : 'ar' ) . ']';
//                                echo '<table><tr><td>';
//                                \f\pa($jm[$keys[$v['id']]]);
//                                echo '</td><td>';
//                                \f\pa($v);
//                                echo '</td></tr></table>';

                                $data_new_option[$jm[$keys[$v['id']]]['id']][$k3_loc] = $v3;
                                $re['kolvo_edit_dop'] ++;
                            }
                            $nn++;
                        }

                        //
                        elseif (1 == 2) {
                            echo '<br/>' . __LINE__ . ' ' . $k3 . ' [' . (!empty($jm[$keys[$v['id']]][$k3_loc]) ?
                                    ( is_string($jm[$keys[$v['id']]][$k3_loc]) ? $jm[$keys[$v['id']]][$k3_loc] : 'ar' ) : 'empty' ) . '] != [' .
                            (
                            empty($v3) ?
                                    'empty' :
                                    ( is_string($v3) ? $v3 : 'ar' )
                            )
                            . ']';
                        }
                    }
                }

                $re['kolvo_old'] ++;
                unset($data[$k]);
            }
        }

        echo '<br/>#' . __LINE__ . ' ' . __FILE__;

        if (!empty($data_new_option)) {
            // \f\pa($data_new_option, 2, '', '$data_new_option');
            $ee = \Nyos\mod\items::saveNewDop($db, $data_new_option);
            // \f\pa($ee, 2, '', '');
        }

        if (!empty($data_new)) {
            // \f\pa($data_new, 2, '', 'new data');
            \Nyos\mod\items::addNewSimples($db, \Nyos\mod\JobDesc::$mod_jobman, $data_new);
        }

        \f\Cash::deleteKeyPoFilter([\Nyos\mod\JobDesc::$mod_jobman]);

        return $re;
    }

    /**
     * приводим массив данных из иико к норм виду для добавления в БД
     * один пользователь
     * @param type $ar
     * @return type
     */
    public static function convertIikoPeopleAr($v) {

        $d1 = [];
        foreach ($v as $k1 => $v1) {
            if ($k1 == 'id' || $k1 == 'name') {
                $d1['iiko_' . $k1] = $v1;
            } elseif (is_array($v1)) {
                foreach ($v1 as $k2 => $v2) {
                    $d1[$k1 . '_' . $k2] = $v2;
                }
            } else {
                $d1[$k1] = $v1;
            }
        }

        return $d1;
    }

    /**
     * загрузка с удалённой БД информации о работниках
     * @return type
     */
    public static function loadIikoPeople() {

        try {


            // echo '<br/>#' . __LINE__ . ' ' . __FUNCTION__;


            if (empty(self::$file_cash))
                self::$file_cash = DR . DS . 'sites' . DS . \Nyos\Nyos::$folder_now . DS . 'people.iiko';

            $re = ['file_cash' => self::$file_cash];

            if (!empty(self::$file_cash) && file_exists(self::$file_cash) && filemtime(self::$file_cash) > ($_SERVER['REQUEST_TIME'] - 3600 * 4)) {
                
                // echo '<br/>#' . __LINE__ . ' + грузим кеш файл ( size ' . self::$file_cash . ' s ' . round(filesize(self::$file_cash) / 1024 / 1024, 2) . ' Mb )';

                $re['file_cash_est'] = 'da';
                $re['file_cash_time'] = date('Y-m-d H:i:s', filemtime(self::$file_cash));
                $re['data'] = json_decode(file_get_contents(self::$file_cash), true);
                return $re;
            } else {

                $re['file_cash_est'] = 'net';
            }



            echo '<hr>подключаемся к айке, получаем данные<hr>';
            // exit;
            // self::$data_iiko_people = 
            $re['data'] = self::getAnswer('сотрудники');

            echo '<div style="background-color:#efefef;border:1px;padding:10px;margin-bottom:10px;max-height:300px;overflow:auto;">';
            // \f\pa($re['data'], 2, '', 'данные рез');
            echo 'загружено ' . sizeof($re['data']);
            echo '</div>';

            echo '<hr>выходим из айки<hr>';
            $re2 = self::getAnswer('выход');

            echo '<div style="background-color:#efefef;border:1px;padding:10px;margin-bottom:10px;max-height:300px;overflow:auto;">';
            \f\pa($re2, 2, '', 'выход рез');
            echo '</div>';


            if (class_exists('\\Nyos\\Msg')) {
                echo 'Послали отчёт об операции в телеграм';
                $msg = 'Загрузили данные с айки по пользователям ( ' . sizeof($re['data']) . ' записей )';
                \Nyos\Msg::sendTelegramm($msg, null, 1);

                if (!empty($vv['admin_auerific'])) {
                    foreach ($vv['admin_auerific'] as $k => $v) {
                        \Nyos\Msg::sendTelegramm($msg, $v);
                    }
                }
            } else {
                echo 'НЕ Послали отчёт об операции в телеграм';
            }

            // \f\pa($re2);

            echo '<br/>#' . __LINE__ . ' записали кеш файл: ' . self::$file_cash;
            file_put_contents(self::$file_cash, json_encode($re['data']));

            // $re = \Nyos\api\Iiko::compileArray($e,'сотрудники');
            // \Nyos\api\Iiko::$cash = true;

            return $re;
        } catch (\PDOException $ex) {

            echo '<pre>--- ' . __FILE__ . ' ' . __LINE__ . '-------'
            . PHP_EOL . $ex->getMessage() . ' #' . $ex->getCode()
            . PHP_EOL . $ex->getFile() . ' #' . $ex->getLine()
            . PHP_EOL . $ex->getTraceAsString()
            . '</pre>';


            return ['status' => 'ошибка', 'error_txt' => $ex->getMessage()];
        } catch (\Exception $ex) {

            echo '<pre>--- ' . __FILE__ . ' ' . __LINE__ . '-------'
            . PHP_EOL . $ex->getMessage() . ' #' . $ex->getCode()
            . PHP_EOL . $ex->getFile() . ' #' . $ex->getLine()
            . PHP_EOL . $ex->getTraceAsString()
            . '</pre>';

            return ['status' => 'ошибка', 'error_txt' => $ex->getMessage()];
        }
    }

    /**
     * ищем данныев меню для коннекта с базой ИИКО
     */
    public static function getConfigDbIiko() {

        foreach (\Nyos\Nyos::$menu as $k => $v) {
            if (!empty($v['server_iiko'])) {

                // \f\pa($v);
                // \f\pa($v['server_iiko'], '', '', 'serv iiko');

                if (!empty($v['server_iiko']['host']))
                    \Nyos\api\Iiko::$host = $v['server_iiko']['host'];

                if (!empty($v['server_iiko']['login']))
                    \Nyos\api\Iiko::$login = $v['server_iiko']['login'];

                if (!empty($v['server_iiko']['pass']))
                    \Nyos\api\Iiko::$pass = $v['server_iiko']['pass'];

                return;
            }
        }
    }

    /**
     * Send a GET request using cURL
     * @param string $url to request
     * @param array $get values to send
     * @param array $options for cURL
     * @return string
     */
    public static function curl_get($url, array $get = NULL, array $options = array()) {

        // echo $url;

        $defaults = array(
            CURLOPT_URL => $url . (strpos($url, "?") === FALSE ? "?" : "") . http_build_query($get),
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_DNS_USE_GLOBAL_CACHE => false,
            CURLOPT_SSL_VERIFYHOST => 0, //unsafe, but the fastest solution for the error " SSL certificate problem, verify that the CA cert is OK"
            CURLOPT_SSL_VERIFYPEER => 0, //unsafe, but the fastest solution for the error " SSL certificate problem, verify that the CA cert is OK"
        );
        $ch = curl_init();
        curl_setopt_array($ch, ($options + $defaults));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        if (!$result = curl_exec($ch)) {
            trigger_error(curl_error($ch));
        }

        curl_close($ch);
        return $result;
    }

    /**
     * Send a POST request using cURL
     * @param string $url to request
     * @param array|string $post values to send
     * @param array $options for cURL
     * @internal param array $get
     * @return string
     */
    public static function curl_post(string $url, $post = null, array $options = array()) {
        $defaults = array(
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_SSL_VERIFYHOST => 0, //unsafe, but the fastest solution for the error " SSL certificate problem, verify that the CA cert is OK"
            CURLOPT_SSL_VERIFYPEER => 0, //unsafe, but the fastest solution for the error " SSL certificate problem, verify that the CA cert is OK"
            CURLOPT_POSTFIELDS => $post
        );
        $ch = curl_init();
        curl_setopt_array($ch, ($options + $defaults));
        if (!$result = curl_exec($ch)) {
            trigger_error(curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }

    /**
     * приводим в порядок (массив) что получили
     * @param array $ar
     * @param type $type
     * @return array
     */
    public static function compileArray(string $xml, $type = '') {

        // \f\pa($type);
        //
        if ($type == 'выход' || $type == 'exit') {
            return $xml;
        }
        // если xml
        else {

            $xml2 = simplexml_load_string($xml, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml2);
            $array = json_decode($json, TRUE);

            $re = [];


            if ($type == 'сотрудники') {
                foreach ($array['employee'] as $k => $v) {
                    $re[] = $v;
                }
            }
            //
            elseif ($type == 'corporation/departments/') {
                foreach ($array['corporateItemDto'] as $k => $v) {
                    $re[] = $v;
                }
            } else {
                return $array;
            }
            return $re;
        }
    }

}
