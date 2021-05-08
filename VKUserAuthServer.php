<?php

class VKUserAuthServer {

    protected $user_id = null;
    protected $auth_status = null;

    public function __construct() {
    }

    public function authenticate($uri) {

        if ($uri == "") {
            // запрос к корневому элементу - первое обращение клиента
            // проверка подписи приложения ВК
            $this->auth_status = $this->checkSign();

        } else {
            $this->auth_status = $this->checkToken();

        }
        return $this->auth_status;
    }

    public function getUser() {
        return $this->user_id;
    }

    public function isAuthenticated() {
        return ($this->auth_status === true);
    }

    public function getAuthToken() {
        return $this->_hash(
            "" . $this->user_id . '_' . APP_CONFIG['vk_app_id'] . '_' . APP_CONFIG['server_key'] . '_' . date('Ymd'),
            APP_CONFIG['client_secret']
        );
    }

    private function checkToken() {
        // получим токен
        $token = '';
        if (array_key_exists('token', $_GET)) {
            $token = $_GET['token'];
        }
        // получим пользователя
        if (array_key_exists('user_id', $_GET)) {
            $this->user_id = $_GET['user_id'];  
        }
        if ($this->user_id && $token) {
            $expected_token = $this->getAuthToken();
             return ($expected_token === $token);
        }
        return false;
    }

    /*
    Проверка подписи клиентского приложения ВК
    */
    private function checkSign() {
        if (!APP_CONFIG['no_vk_auth']) {
            $sign_params = [];
            foreach ($_GET as $name => $value) {
                if (strpos($name, 'vk_') !== 0) { // Получаем только vk параметры из query
                    continue;
                }
                $sign_params[$name] = $value;
            }

            if (!(
                array_key_exists('vk_app_id', $sign_params) 
                && array_key_exists('vk_user_id', $sign_params)
                && array_key_exists('sign', $_GET)
            )) {
                return false;
            }

            $this->user_id = $sign_params['vk_user_id'];

            // проверка ид приложения вк
            if ($sign_params['vk_app_id'] != APP_CONFIG['vk_app_id']) {
                return false;
            }
            // Сортируем массив по ключам 
            ksort($sign_params); 
            // Формируем строку вида "param_name1=value&param_name2=value"
            $sign_params_query = http_build_query($sign_params); 
            // Получаем хеш-код от строки, используя защищеный ключ приложения. Генерация на основе метода HMAC. 
            $sign = $this->_hash($sign_params_query, APP_CONFIG['client_secret']);
            // Сравниваем полученную подпись со значением параметра 'sign'
            return ($sign === $_GET['sign']); 
        } else {
            // убрать!!!!
            return true;
        }
    }

    private function _hash($data, $key) {
        $result = rtrim(
            strtr(
                base64_encode(
                    hash_hmac('sha256', $data, $key, true)
                ),
            '+/', '-_'), 
        '='); 
        return $result;
    }

}

?>