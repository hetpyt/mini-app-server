<?php
use Jacwright\RestServer\RestException;
//use Krugozor\Database\Mysql\Mysql;
require_once 'Common.php';
require_once 'InternalException.php';
require_once 'Messages_RU.php';
require_once 'DataBase.php';

class TestController
{
    // ид пользователя, полученный через строку запроса
    private $_user_id = null;
    private $_user_priv = null;
    /**
    * @noAuth
    * @url GET /
    */
    public function app() {
        global $_Config;

        try {
            $this->_check_config($_Config);
        } catch (Exception $e) {
            //echo 'invalid config: ' . $e->getMessage();
            $this->_handle_error(500);
            return;
        }

        $sign_params = [];
        if (!$_Config['no_vk_auth']) {
            // аутентификация ВК не отключена
            foreach ($_GET as $name => $value) {
                if (strpos($name, 'vk_') !== 0) { // Получаем только vk параметры из query
                    continue;
                }
                $sign_params[$name] = $value;
            }

            try {
                $check_fields = ['vk_app_id', 'vk_user_id'];
                $this->_check_fields($sign_params, $check_fields, [], true);
            } catch (Exception $e) {
                //echo 'invalid request';
                $this->_handle_error(403);
                return;
            }

            // проверка ид приложения вк
            if ($sign_params['vk_app_id'] != $_Config['vk_app_id']) {
                //echo 'wrong app id';
                $this->_handle_error(403);
                return;
            }
            // Сортируем массив по ключам 
            ksort($sign_params); 
            // Формируем строку вида "param_name1=value&param_name2=value"
            $sign_params_query = http_build_query($sign_params); 
            // Получаем хеш-код от строки, используя защищеный ключ приложения. Генерация на основе метода HMAC. 
            $sign = $this->_hash($sign_params_query, $_Config['client_secret']);
            // Сравниваем полученную подпись со значением параметра 'sign'
            $status = $sign === $_GET['sign']; 
        } else {
            // убрать!!!!
            $sign_params = [
                'vk_user_id' => $_GET['user_id'],
                'vk_app_id' => $_Config['vk_app_id']
            ];
            $status = true;
        }

        if ($status) {
            // все хорошо, подпись верна
            $auth_token = $this->_get_auth_token($_Config, $sign_params['vk_user_id']);

            $file_data = file_get_contents('../mini-app/build/index.html');
            echo str_replace("</body>", "<script> var " . $_Config['server_token_name'] . $_Config['vk_app_id'] . "='" . $auth_token . "'; </script></body>", $file_data);

        } else {
            // подпись не верна
            //echo 'invalid request sign';
            $this->_handle_error(403);
        }
    }

    /**
    * @url POST /regrequests/get
    * @url GET /regrequests/get
    */
    public function regrequests_get($data) {
        try {
            $db_data = DataBase::regrequests_get($this->_user_id);
        } catch (InternalException $e) {$this->_handle_error(500);}
        return $db_data;
    }

    /**
    * @url POST /regrequests/add
    */
    public function regrequests_add($data) {
        // так же проверять не привязан ли уже данный лс к данному пользователю
        // проверка на блок и привилегии
        if (!$this->_has_user_privs()) {
            $this->_handle_error(403);
        }

        try {
            // проверка наличия данных заявки
            $check_fields = ['registration_data'];
            $this->_check_fields($data, $check_fields, [], false);
            // данные по показаниям
            $reg_data = $data->registration_data;
            // проверка наличия полей заявки
            $check_fields = ['acc_id', 'surname', 'first_name', 'patronymic', 'street', 'n_dom', 'n_kv', 'secret_code'];
            $check_int_fields = ['secret_code'];
            $this->_check_fields($reg_data, $check_fields, $check_int_fields, true);
        } catch (InternalException $e) {
            $this->_handle_error(400);
        }
        // проверка существования связи???

        try {
            // проверка на существование заявки
            if (DataBase::is_regrequest_exists($this->_user_id, $reg_data->acc_id)) {
                $this->_handle_error(409);
            }
            // проверка секретного кода
            $accounts = DataBase::get_account_by_secret_code($reg_data->secret_code, $reg_data->acc_id);
            if (count($accounts) != 1) {
                $this->_handle_error(403);
            }
            // 
            DataBase::regrequests_add($this->_user_id, $reg_data);
        } catch (InternalException $e) {$this->_handle_error(500);}

        return;
    }

    /**
    * @url POST /regrequests/hide
    */
    public function regrequests_hide($data) {
        try {
            $check_fields = ['request_id'];
            $check_int_fields = ['request_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_error(400);
        }

        try {
            DataBase::regrequests_hide($this->_user_id, $data['request_id']);

        } catch (InternalException $e) {$this->_handle_error(500);}
        
        return;
    }

    /**
    * @url POST /regrequests/del
    */
    public function regrequests_del($data) {
        try {
            $check_fields = ['request_id'];
            $check_int_fields = ['request_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_error(400);
        }
        
        try {
            DataBase::regrequests_del($this->_user_id, $data['request_id']);
            
        } catch (InternalException $e) {$this->_handle_error(500);}

        return;
    }

    /**
    * @url POST /users/privileges/get
    * @url GET /users/privileges/get
    */
    public function users_privileges_get($data) {
        try {
            $db_data = DataBase::users_privileges_get($this->_user_id);
        } catch (InternalException $e) {$this->_handle_error(500);}

        return $db_data;
    }

    /**
    * @url POST /accounts/list
    * @url GET /accounts/list
    */
    public function accounts_list($data) {
        // проверка на блок и привилегии
        if (!$this->_has_user_privs()) {
            $this->_handle_error(403);
        }
        try {
            $db_data = DataBase::accounts_list($this->_user_id);
        } catch (InternalException $e) {$this->_handle_error(500);}
            
        return $db_data;
    }
    
    /**
    * @url POST /meters/list
    */
    public function meters_list($data) {
        // проверка на блок и привилегии
        if (!$this->_has_user_privs()) {
            $this->_handle_error(403);
        }
        try {
            $check_fields = ['account_id'];
            $check_int_fields = ['account_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_error(400);
        }
        // проверка аккаунта на принадлежность пользователю
        try {
            if (DataBase::accounts_detail($this->_user_id, $data->account_id) === null) {
                $this->_handle_error(403);
            }
            // получение данных по счетчикам
            $db_data = DataBase::meters_list($this->_user_id, $data->account_id);
        } catch (InternalException $e) {
            $this->_handle_error(500);
        }
        
        return $db_data;
    }

    /**
    * @url POST /indications/add
    */
    public function indications_add($data) {
        // проверка на блок и привилегии
        if (!$this->_has_user_privs()) {
            $this->_handle_error(403);
        }
        try {
            $check_fields = ['account_id', 'meters'];
            $check_int_fields = ['account_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, true);

            $meters = $data->meters;
            if (!is_array($meters)) throw new InternalException('bad meters data');

        } catch (InternalException $e) {
            $this->_handle_error(400);
        }

        if (count($meters) > 0) {
            try {
                $check_fields = ['meter_id', 'new_count'];
                $check_int_fields = ['meter_id'];
                $this->_check_fields($meters, $check_fields, $check_int_fields, false);
            } catch (InternalException $e) {
                $this->_handle_error(400);
            }

            // проверка принадлежности счетчиков
            $owned_meters = array_column(DataBase::meters_list($this->_user_id, $data->account_id), 'meter_id');
            foreach ($meters as $meter) {
                if (!in_array($meter->meter_id, $owned_meters)) {
                    $this->_handle_error(400);
                }
            }
            try {
                DataBase::indications_add($this->_user_id, $meters);
            } catch (InternalException $e) {
                $this->_handle_error(500);
            }
        }

        return;
    }
    
    /**
    * @url POST /admin/regrequests/list
    */
    public function admin_regrequests_list($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_error(403);
        }
        $filters = null;
        try {
            $db_data = DataBase::admin_regrequests_list();
        } catch (InternalException $e) {
            $this->_handle_error(500);
        }
        return $db_data;
    }
    
    /**
    * @url POST /admin/regrequests/get
    */
    public function admin_regrequests_get($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_error(403);
        }
    }
    
    /**
    * @url POST /admin/regrequests/aprove
    */
    public function admin_regrequests_aprove($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_error(403);
        }
    }
    
    /**
    * @url POST /admin/regrequests/reject
    */
    public function admin_regrequests_reject($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_error(403);
        }
    }

    // 

    public function authorize() {
        global $_Config;
        $status = false;
        $user_id = '';
        $token = '';

        // получим токен
        if (array_key_exists('token', $_GET)) {
            $token = $_GET['token'];
        }
        // получим пользователя
        if (array_key_exists('user_id', $_GET)) {
            $user_id = $_GET['user_id'];  
        }
        if ($user_id && $token) {
            $expected_token = $this->_get_auth_token($_Config, $user_id);
            if ($status = $expected_token === $token) {
                $this->_user_id = $user_id;
                // привилегии пользователя
                try {
                    $db_data = DataBase::users_privileges_get($this->_user_id);
                } catch (InternalException $e) {
                    return false; //$status;
                }
                $this->_user_priv = $db_data['privileges'];
            }
        }
        return $status;
    }

    // PRIVATE SECTION

    private function _handle_error($code = null, $message = null) {
        if ($code === null) $code = 500;
        throw new RestException($code, $message);
    }

    private function _has_user_privs() {
        if (!$this->_user_priv) {
            return false;
        }
        return "USER" == $this->_user_priv || $this->_has_operator_privs();
    }

    private function _has_operator_privs() {
        if (!$this->_user_priv) {
            return false;
        }
        return "OPERATOR" == $this->_user_priv || $this->_has_admin_privs();
    }

    private function _has_admin_privs() {
        if (!$this->_user_priv) {
            return false;
        }
        return "ADMIN" == $this->_user_priv;
    }

    private function _check_fields(&$data, $fields, $int_fields, $set_zero = true, $context = null) {
        if (is_array($data) && !_is_assoc($data)) {
            foreach ($data as $dataItem) {
                $this->_check_fields($dataItem, $fields, $int_fields, $set_zero);
            }

        } else if (is_object($data) || _is_assoc($data)) {
            $is_obj = is_object($data);
            foreach ($fields as $field) {
                if ($is_obj) {
                    if (!property_exists($data, $field)) 
                        throw new InternalException(($context ? "$context. " : "") . "field '$field' not exists");
                    if (in_array($field, $int_fields) && !is_numeric($data->{$field})) {
                        if ($set_zero) $data->{$field} = 0;
                        else throw new InternalException(($context ? "$context. " : "") . "not int value in field '$field'");
                    }

                } else {

                    if (!array_key_exists($field, $data)) 
                        throw new InternalException(($context ? "$context. " : "") . "field '$field' not exists");
                    if (in_array($field, $int_fields) && !is_numeric($data[$field])) {
                        if ($set_zero) $data[$field] = 0;
                        else throw new InternalException(($context ? "$context. " : "") . "not int value in field '$field'");
                    }
                }
            }

        } else {
            throw new InternalException(($context ? "$context. " : "") . "data must been array or object");
        }
    }

    private function _get_auth_token(&$config, $vk_user_id) {
        return $this->_hash(
            "" . $vk_user_id . '_' . $config['vk_app_id'] . '_' . $config['server_key'] . '_' . date('Ymd'),
            $config['client_secret']
        );
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

    private function _check_config(&$config) {
        try {
            $check_fields = [
            // db options
                'db_host',
                'db_port',
                'db_name',
                'db_user',
                'db_pass',
                'db_charset',
            // server options
                'server_mode',
            // vk app id
                'vk_app_id',
            // client vk app secret key
                'client_secret',
                'server_token_name',
            // random phrase to generate auth token for clients
                'server_key',
                'no_vk_auth'
            ];
            $check_int_fields = [];
            $this->_check_fields($config, $check_fields, $check_int_fields, false);
        } catch (Exception $e) {
            throw new InternalException('Отсутствуют обязательные поля в конфигурационном файле! Пожалуйста сверьтесь с файлом config.php.template. Описание исключения: ' . $e->getMessage());
        }
    }
}
?>