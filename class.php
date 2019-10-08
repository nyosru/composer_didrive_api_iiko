<?php

/**
  класс модуля
 * */

namespace Nyos\api;

if (!defined('IN_NYOS_PROJECT'))
    throw new \Exception('Сработала защита от розовых хакеров, обратитесь к администрратору');

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

        echo '<br/>' . dirname(__FILE__) . '/iiko.cash.key';

        if (file_exists(dirname(__FILE__) . '/iiko.cash.key')) {
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

        self::$api_key = self::curl_get(self::$protokol . self::$host . '/resto/api/auth', array('login' => self::$login, 'pass' => sha1(self::$pass)));

        if (strpos(self::$api_key, 'no connections available') !== false)
            throw new \Exception('нет свободных доступов');

        if (strpos(self::$api_key, 'HTTP 404') !== false)
            throw new \Exception(self::$api_key);

        if (self::$cash === true) {
            self::saveCashKey();
        }

        //echo '<br><br>key<br/>' . self::$api_key . '<hr>';
        echo '<div style="background-color:yellow;padding:10px;margin-bottom:10px;">key<br/>' . self::$api_key . '</div>';

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
//                $dops[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES 'utf8'";
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
                    ':id_user' => $id_user_iiko,
                    ':dates' => date('Y-m-d 00:00:00', strtotime($start_date))
                );

                if (!empty($date_fin)) {
                    $ar_in_sql[':date_end'] = date('Y-m-d 23:59:00', strtotime($date_fin));
                }

                $ff = $db7->prepare('SELECT ' .
                        // ' dbo.EmployeeAttendanceEntry.employee \'user\', '.
                        ' dbo.EmployeeAttendanceEntry.personalSessionStart \'start\',
                    dbo.EmployeeAttendanceEntry.personalSessionEnd \'end\'
                FROM 
                    dbo.EmployeeAttendanceEntry 
                WHERE 
                    employee = :id_user 
                    AND personalSessionStart >= :dates '
                        . (!empty($date_fin) ? ' AND personalSessionStart <= :date_end ' : '' )
                );

                $ff->execute($ar_in_sql);
                //$e3 = $ff->fetchAll();
                $e3 = [];
                while ($e = $ff->fetch()) {
                    //$e['user'] = mb_convert_encoding($e['user'],'UTF-8','auto');
                    //$e['user'] = utf8_decode($e['user']);
//                $e['user'] = utf8_encode($e['user']);
//                echo '<br/>'.mb_detect_order($e['user']);
                    //$e['user'] = iconv('UCS-2LE','UTF-8',substr(base64_decode($e['user']),0,-1));
                    //$e['user'] = html_entity_decode($e['user'], ENT_COMPAT | ENT_HTML401, 'UTF-8');
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

        try {

            $date_start = date('Y-m-d', strtotime($start_date));

            if ($date_fin === null)
                $date_fin = date('Y-m-d', $_SERVER['REQUEST_TIME']);

            $dops = array(
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
//                    ,
//                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8'
            );

//            if (isset($_POST['typedb']) && $_POST['typedb'] == 'mysql' ) {
//                $dops[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES 'utf8'";
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
            //$jobmans_send_to_sp = \Nyos\mod\items::getItemsSimple($db, 'jobman_send_on_sp');
            //\f\pa($jobmans_send_to_sp, 2, '', '$jobmans_send_to_sp');
//            foreach ($jobmans_send_to_sp['data'] as $k => $v) {
//                $jobmans_onload[$v['dop']['jobman']] = 1;
//            }

            $jobmans = \Nyos\mod\items::getItemsSimple($db, '070.jobman');
            //\f\pa($jobmans, 2, '', '$jobmans');

            $return['loaded_checks'] = 0;
            $loaded = [];

            foreach ($jobmans['data'] as $jobman_id => $jobman_data) {

//                if (!isset($jobmans_onload[$jobman_data['id']]))
//                    continue;

                if (empty($jobman_data['dop']['iiko_id']))
                    continue;

                $ar_in_sql = array(
                    // ':id_user' => 'f34d6d84-5ecb-4a40-9b03-71d03cb730cb',
                    ':id_user' => $jobman_data['dop']['iiko_id']
                    ,
                    ':ds' => $date_start . ' 00:00:00'
                    ,
                    ':df' => $date_fin . ' 23:59:00'
                );

                $sql = 'SELECT ' .
                        // ' dbo.EmployeeAttendanceEntry.employee \'user\', '.
                        ' dbo.EmployeeAttendanceEntry.personalSessionStart \'start\',
                    dbo.EmployeeAttendanceEntry.personalSessionEnd \'end\'
                FROM 
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
                }
            }

            $db7 = $ff = null;

            // \f\pa($loaded, 2, '', '$loaded');
            //\f\pa($return);
            // удаляем все чеки
            if (isset($_REQUEST['delete']) && $_REQUEST['delete'] == 'da')
                \Nyos\mod\items::deleteItems($db, $sql, '050.chekin_checkout');

            /**
             * тащим чеки
             */
            $checks = \Nyos\mod\items::getItemsSimple($db, '050.chekin_checkout');
            //\f\pa($checks, 2, '', '$checks');

            /**
             * назначения на работу (спец)
             */
            $send_on_sp0 = \Nyos\mod\items::getItemsSimple($db, 'jobman_send_on_sp');
            \f\pa($send_on_sp0, 2, '', '$send_on_sp');
            $send_on_sp = [];
            foreach ($send_on_sp0['data'] as $k => $v) {
                $send_on_sp[$v['dop']['jobman']][$v['dop']['date']] = $v['dop']['sale_point'];
            }

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

                    foreach ($checks['data'] as $k => $check) {
                        if (isset($check['dop']['jobman']) && $check['dop']['jobman'] == $jobman) {

                            if (isset($check['dop']['start'])) {
                                //$check_start = $check['dop']['start'];

                                if ($check['dop']['start'] == $v1['start']) {
                                    $check_id = $check['id'];
                                    $checked = 'start';

                                    if (isset($check['dop']['fin'])) {
                                        //$check_fin = $check['dop']['fin'];

                                        if ($check['dop']['fin'] == $v1['end']) {
                                            //$check_id = $check['id'];
                                            $checked = 'fin';
                                        }
                                    }
                                }

                                //break;
                            }
                        }
                    }


                    echo '</br>';
                    echo $check_id ?? '--';

//                    echo '</br>';
//                    echo $new_start ?? '--';
//                    echo '</br>';
//                    echo $new_fin ?? '--';

                    if ($checked == 'start') {

                        echo '</br>';
                        echo 'старт есть, если есть финиш то его добавим';

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

                        echo 'всё есть';
                    } else {

                        echo '</br>';
                        echo 'нет данных, нужно добавить';

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
                        if (isset($send_on_sp[$jobman][substr($new_start, 0, 9)]))
                            $aa['sale_point'] = $send_on_sp[$jobman][substr($new_start, 0, 9)];

                        $add[] = $aa;
                    }
                }
            }


            // \f\pa($add, 2, '', '$add');

            if (!empty($add)) {
                $return['adds_kolvo'] = sizeof($add);
                \Nyos\mod\items::addNewSimples($db, '050.chekin_checkout', $add);
            } else {
                $return['adds_kolvo'] = 0;
            }

            if (!empty($add_new)) {
                $return['adds_start'] = sizeof($add_new);
                \Nyos\mod\items::addNewSimples($db, '050.chekin_checkout', $add_new);
            } else {
                $return['adds_start'] = 0;
            }

            if (!empty($dop_add)) {
                $return['adds_dop_kolvo'] = sizeof($dop_add) / 2;
                \f\db\sql_insert_mnogo($db, 'mitems-dops', $dop_add);
                \f\pa($dop_add, 2, '', '$dop_add');
            } else {
                $return['adds_dop_kolvo'] = 0;
            }

            return $return;
        }
        //
        catch (\Exception $ex) {
            
            echo '<pre>--- ' . __FILE__ . ' ' . __LINE__ . '-------'
            . PHP_EOL . $ex->getMessage() . ' #' . $ex->getCode()
            . PHP_EOL . $ex->getFile() . ' #' . $ex->getLine()
            . PHP_EOL . $ex->getTraceAsString()
            . '</pre>';


            if ( 1 == 1 && class_exists('\\Nyos\\Msg')) {

                $msg = 'Загрузка чекинов - ошибка: '.$ex->getMessage();
                
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

            if ( 1 == 1 && class_exists('\\Nyos\\Msg')) {

                $msg = 'Загрузка чекинов - ошибка: '.$ex->getMessage();
                
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

    /**
     * Send a GET request using cURL
     * @param string $url to request
     * @param array $get values to send
     * @param array $options for cURL
     * @return string
     */
    public static function curl_get($url, array $get = NULL, array $options = array()) {
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

        $xml2 = simplexml_load_string($xml, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml2);
        $array = json_decode($json, TRUE);

        if ($type == 'сотрудники') {

            $re = [];

            foreach ($array['employee'] as $k => $v) {
                $re[] = $v;
            }
        } elseif ($type == 'corporation/departments/') {

            $re = [];

            foreach ($array['corporateItemDto'] as $k => $v) {
                $re[] = $v;
            }
        } else {
            return $array;
        }

        return $re;
    }

}
