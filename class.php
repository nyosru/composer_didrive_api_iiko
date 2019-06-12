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
     * получаем ключ доступа к айко
     */
    public static function getAutKey() {

        self::$api_key = self::curl_get(self::$protokol . self::$host . '/resto/api/auth', array('login' => self::$login, 'pass' => sha1(self::$pass)));
        return self::$api_key;
    }

    public static function getAnswer($request, $vars = []) {

        if (self::$api_key === null)
            self::getAutKey();

        $vars['key'] = self::$api_key;

        $e1 = self::curl_get(self::$protokol . self::$host . '/resto/api/' . $request, $vars);

        $xml = simplexml_load_string($e1, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($xml);
        $array = json_decode($json, TRUE);

        if (function_exists('\f\pa'))
            \f\pa($array);

        return $array;
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

}
