<?php
use Jacwright\RestServer\RestException;
//use Krugozor\Database\Mysql\Mysql;
require_once 'AppError.php';
require_once 'Common.php';
require_once 'InternalException.php';
require_once 'Logger.php';
require_once 'DataBase.php';

class TestController
{
    private $_logger = null;
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
            $this->_handle_error(500, $e);
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
                $this->_handle_error(403, $e);
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
            $this->_log("sign_params = " . print_r($sign_params, true));
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
    * @url POST /regrequests/list
    * @url GET /regrequests/list
    */
    public function regrequests_list($data) {
        try {
            $db_data = DataBase::regrequests_list($this->_user_id);
        } catch (InternalException $e) {$this->_handle_error(500, $e);}
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
            $this->_handle_error(400, $e);
        }
        // проверка существования связи???

        try {
            // проверка на существование заявки
            if (DataBase::is_waiting_regrequest_exists($this->_user_id, $reg_data->acc_id)) {
                //$this->_handle_error(409);
                return $this->_return_app_error(APPERR_REGREQUEST_DUBL);
            }
            // проверка секретного кода
            if (!$reg_data->secret_code) {
                //$this->_handle_error(400);
                return $this->_return_app_error(APPERR_BAD_SECRETCODE);
            }
            $accounts = DataBase::get_accounts_by_repr($reg_data->acc_id, $reg_data->secret_code);
            if (count($accounts) != 1) {
                //$this->_handle_error(APPERR_ACCOUNT_NOT_FOUND);
                return $this->_return_app_error(APPERR_ACCOUNT_NOT_FOUND);
            }
            // 
            $rows_insert = DataBase::regrequests_add($this->_user_id, $reg_data);

        } catch (InternalException $e) {$this->_handle_error(500, $e);}

        return $rows_insert > 0;
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
            $this->_handle_error(400, $e);
        }

        try {
            DataBase::regrequests_hide($this->_user_id, $data['request_id']);

        } catch (InternalException $e) {$this->_handle_error(500, $e);}
        
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
            $this->_handle_error(400, $e);
        }
        
        try {
            DataBase::regrequests_del($this->_user_id, $data['request_id']);
            
        } catch (InternalException $e) {$this->_handle_error(500, $e);}

        return;
    }

    /**
    * @url POST /privileges/get
    * @url GET /privileges/get
    */
    public function privileges_get($data) {
        try {
            $priv_data = DataBase::users_privileges_get($this->_user_id);
            $perm_data = DataBase::app_permissions_get();
        } catch (InternalException $e) {$this->_handle_error(500, $e);}

        return [
            'user_privileges' => $priv_data,
            'app_permissions' => $perm_data
        ];
    }

    /**
    * @url POST /apppermissions/get
    * @url GET /apppermissions/get
    */
    public function apppermissions_get($data) {
        try {
            $perm_data = DataBase::app_permissions_get();
        } catch (InternalException $e) {$this->_handle_error(500, $e);}

        return $perm_data;
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
        } catch (InternalException $e) {$this->_handle_error(500, $e);}
            
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
            $this->_handle_error(400, $e);
        }
        // проверка аккаунта на принадлежность пользователю
        try {
            if (DataBase::accounts_get($this->_user_id, $data->account_id) === null) {
                return $this->_return_app_error(APPERR_ACCOUNT_NOT_OWNED);
            }
            // получение данных по счетчикам
            $db_data = DataBase::meters_list($this->_user_id, $data->account_id);
        } catch (InternalException $e) {
            $this->_handle_error(500, $e);
        }
        
        return $db_data;
    }

    /**
    * @url POST /indications/list
    */
    public function indications_list($data) {
        // проверка на блок и привилегии
        if (!$this->_has_user_privs()) {
            $this->_handle_error(403);
        }
        try {
            $check_fields = ['account_id'];
            $check_int_fields = ['account_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_error(400, $e);
        }
        try {
            if (DataBase::accounts_get($this->_user_id, $data->account_id) === null) {
                return $this->_return_app_error(APPERR_ACCOUNT_NOT_OWNED);
            }
            // получение данных по счетчикам
            $db_data = DataBase::indications_list($this->_user_id, $data->account_id);
        } catch (InternalException $e) {
            $this->_handle_error(500, $e);
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
            $this->_handle_error(400, $e);
        }

        if (count($meters) > 0) {
            try {
                $check_fields = ['meter_id', 'new_count'];
                $check_int_fields = ['meter_id'];
                $this->_check_fields($meters, $check_fields, $check_int_fields, false);
            } catch (InternalException $e) {
                $this->_handle_error(400, $e);
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
                $this->_handle_error(500, $e);
            }
        }

        return true;
    }
    
    /**
    * @url POST /admin/regrequests/list
    */
    public function admin_regrequests_list($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_error(403);
        }
        $filters = null;
        $limits = null;
        $order = null;

        if (is_object($data)) {
            if (property_exists($data, 'filters') && $data->filters) {
                $filters = $data->filters;
                try {
                    $this->_check_filters($filters);
                } catch (InternalException $e) {
                    $this->_handle_error(400, $e);
                }
            }

            if (property_exists($data, 'limits') && $data->limits) {
                $limits = $data->limits;
                try {
                    $this->_check_limits($limits);
                } catch (InternalException $e) {
                    $this->_handle_error(400, $e);
                }
            }

            if (property_exists($data, 'order') && $data->order) {
                $order = $data->order;
            }
        }

        try {
            $db_data = DataBase::admin_regrequests_list($filters, $order, $limits);
        } catch (InternalException $e) {
            $this->_handle_error(500, $e);
        }
        return $db_data;
    }
    
    /**
    * @url POST /admin/regrequests/count
    */
    public function admin_regrequests_count($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_error(403);
        }
        $filters = null;

        if (is_object($data)) {
            if (property_exists($data, 'filters') && $data->filters) {
                $filters = $data->filters;
                try {
                    $this->_check_filters($filters);
                } catch (InternalException $e) {
                    $this->_handle_error(400, $e);
                }
            }
        }
        try {
            $db_data = DataBase::admin_regrequests_count($filters);
        } catch (InternalException $e) {
            $this->_handle_error(500, $e);
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
        try {
            $check_fields = ['regrequest_id'];
            $check_int_fields = ['regrequest_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_error(400, $e);
        }

        try {
            $db_data = DataBase::admin_regrequests_get($data->regrequest_id);
            if ($db_data) {
                if ($db_data['is_approved'] === null) {
                    // для ожидающих заявок подбор лицевых счетов в соответствии с заявкой
                    $acc_data = DataBase::get_accounts_by_repr($db_data['acc_id'], null, 3);
                    $db_data['selected_accounts'] = $acc_data;

                } elseif ($db_data['is_approved']) {
                    // для утвержденных выдаем информацию о привязанном лс
                    $acc_data = DataBase::accounts_get($db_data['vk_user_id'], $db_data['linked_acc_id']);
                    $db_data['selected_accounts'] = [$acc_data];
                }
            }

        } catch (InternalException $e) {
            $this->_handle_error(500, $e);
        }

        return $db_data;
    }
    
    /**
    * @url POST /admin/regrequests/aprove
    */
    public function admin_regrequests_aprove($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_error(403);
        }

        try {
            $check_fields = ['regrequest_id', 'account_id'];
            $check_int_fields = ['regrequest_id', 'account_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_error(400, $e);
        }

        try {
            $req_data = DataBase::admin_regrequests_get($data->regrequest_id);
            if (!$req_data) {
                //$this->_handle_error(400, "registration request with id '$data->regrequest_id' not exists");
                return $this->_return_app_error(APPERR_REGREQUEST_NOT_EXISTS, $data->regrequest_id);
            }

            if ($req_data['is_approved'] !== null) {
                //$this->_handle_error(400, "registration request with id '$data->regrequest_id' already processed");
                return $this->_return_app_error(APPERR_REGREQUEST_ALREADY_PROCESSED, $data->regrequest_id);
            }

            if (!DataBase::is_client_exists($data->account_id)) {
                //$this->_handle_error(400, "account with id '$data->account_id' not exists");
                return $this->_return_app_error(APPERR_ACCOUNT_NOT_EXISTS, $data->account_id);
            }

        } catch (InternalException $e) {
            $this->_handle_error(500, $e);
        }

        try {
            DataBase::transaction_begin();

            DataBase::admin_users_add($req_data['vk_user_id'], 'USER', $this->_user_id);
            DataBase::admin_accounts_add($req_data['vk_user_id'], $data->account_id);
            DataBase::admin_regrequests_approve($req_data, $data->account_id, $this->_user_id);

            DataBase::transaction_commit();

        } catch (InternalException $e) {
            DataBase::transaction_rollback();
            $this->_handle_error(500, $e);
        }
        return true;
    }
    
    /**
    * @url POST /admin/regrequests/reject
    */
    public function admin_regrequests_reject($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_error(403);
        }

        try {
            $check_fields = ['regrequest_id'];
            $check_int_fields = ['regrequest_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_error(400, $e);
        }
        $rejection_reason = null;
        if (property_exists($data, 'rejection_reason')) {
            $rejection_reason = $data->rejection_reason;
        }

        try {
            $req_data = DataBase::admin_regrequests_get($data->regrequest_id);
            if (!$req_data) {
                //$this->_handle_error(400, "registration request with id '$data->regrequest_id' not exists");
                return $this->_return_app_error(APPERR_REGREQUEST_NOT_EXISTS, $data->regrequest_id);
            }

            if ($req_data['is_approved'] !== null) {
                //$this->_handle_error(400, "registration request with id '$data->regrequest_id' already processed");
                return $this->_return_app_error(APPERR_REGREQUEST_ALREADY_PROCESSED, $data->regrequest_id);
            }

            DataBase::admin_regrequests_reject($req_data, $this->_user_id, $rejection_reason);

        } catch (InternalException $e) {
            $this->_handle_error(500, $e);
        }
        return true;
    }
    
    /**
    * @url POST /admin/data/set
    */
    public function admin_data_set($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_error(403);
        }

        // check data
        try {
            $check_fields = ['clients', 'meters', 'clients_count', 'meters_count'];
            $check_int_fields = ['clients_count', 'meters_count'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);

            $clients = $data->clients;
            if (!is_array($clients)) throw new InternalException('bad clients data');
            
            $meters = $data->meters;
            if (!is_array($meters)) throw new InternalException('bad meters data');

            $clients_fields = ['acc_id', 'secret_code', 'acc_id_repr', 'tenant_repr', 'address_repr'];
            $check_int_fields = ['acc_id', 'secret_code'];
            $this->_check_fields($clients, $clients_fields, $check_int_fields, false);

            $meters_fields = ['index_num', 'acc_id', 'title', 'current_count'];
            $check_int_fields = ['index_num', 'acc_id', 'current_count'];
            $this->_check_fields($meters, $meters_fields, $check_int_fields, false);

        } catch (InternalException $e) {
            $this->_handle_error(400, $e);
        }

        $result = [
            'clients_count' => 0,
            'meters_count' => 0
        ];

        try {
            DataBase::transaction_begin();

            // clear tables
            DataBase::clear_table('indications');
            DataBase::clear_table('meters');
            DataBase::clear_table('clients');

            $result['clients_count'] = DataBase::insert_data('clients', $clients_fields, $clients, 1000);

            $result['meters_count'] = DataBase::insert_data('meters', $meters_fields, $meters, 1000);

            DataBase::transaction_commit();

        } catch (InternalException $e) {
            DataBase::transaction_rollback();
            $this->_handle_error(500, $e);
        }

        return $result;
    }
    
    /**
    * @url POST /admin/data/get
    */
    public function admin_data_get($data) {
        if (!$this->_has_operator_privs()) {
            $this->_handle_error(403);
        }
        $filters = null;

        if (is_object($data)) {
            if (property_exists($data, 'filters') && $data->filters) {
                $filters = $data->filters;
                try {
                    $this->_check_filters($filters);
                } catch (InternalException $e) {
                    $this->_handle_error(400, $e);
                }
            }
        }

        try {
            $db_data = DataBase::admin_indications_get($filters);

        } catch (InternalException $e) {
            $this->_handle_error(500, $e);
        }
        return $db_data;
    }

    // ADMIN ONLY
    
    /**
    * @url POST /admin/apppermissions/set
    */
    public function admin_apppermissions_set($data) {
        if (!$this->_has_admin_privs()) {
            $this->_handle_error(403);
        }

        // check data
        try {
            $check_fields = ['indications', 'registration'];
            $check_int_fields = ['indications', 'registration'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);

            $permissions = [];
            foreach ($check_int_fields as $perm) {
                $permissions[$perm] = (int)$data->$perm;
            }

            $date_begin = null;
            if (property_exists($data, 'date_begin')) {
                $date_begin = _str_to_date($data->date_begin);
            }
        } catch (InternalException $e) {
            $this->_handle_error(400, $e);
        }

        try {
            $db_data = DataBase::app_permissions_set($permissions, $this->_user_id, $date_begin);

        } catch (InternalException $e) {
            $this->_handle_error(500, $e);
        }
        return $db_data;
    }

    /**
    * @url POST /admin/users/list
    */
    public function admin_users_list($data) {
        if (!$this->_has_admin_privs()) {
            $this->_handle_error(403);
        }
        $filters = null;
        $limits = null;
        $order = null;

        if (is_object($data)) {
            if (property_exists($data, 'filters') && $data->filters) {
                $filters = $data->filters;
                try {
                    $this->_check_filters($filters);
                } catch (InternalException $e) {
                    $this->_handle_error(400, $e);
                }
            }

            if (property_exists($data, 'limits') && $data->limits) {
                $limits = $data->limits;
                try {
                    $this->_check_limits($limits);
                } catch (InternalException $e) {
                    $this->_handle_error(400, $e);
                }
            }

            if (property_exists($data, 'order') && $data->order) {
                $order = $data->order;
            }
        }

        try {
            $db_data = DataBase::admin_users_list($filters, $order, $limits);

        } catch (InternalException $e) {
            $this->_handle_error(500, $e);
        }
        return $db_data;
    }

    // SPECIAL METHODS

    public function init() {
        $this->_logger = new Logger('vkappsrvr');
    }

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

    private function _log($text) {
        if ($this->_logger) $this->_logger->log($text);
    }

    private function _return_app_error() {
        $args = func_get_args();

        $code = array_shift($args);
        if ($code == null) $code = -999;
        $message = "";

        if (array_key_exists($code, APPERR_MESSAGES_RU)) {
            $message = APPERR_MESSAGES_RU[$code];
        }
        $index = 0;
        foreach ($args as $arg) {
            $message = str_replace('{'.$index.'}', $arg, $message);
            $index ++;
        }
        return [
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
    }

    private function _handle_error($rest_code = null, $exception = null) {
        if ($rest_code === null) $rest_code = 500;
        $message = "UNKNOWN EXCEPTION";
        if ($exception !== null) {
            if (is_object($exception) && method_exists($exception, 'getMessage')) {
                $message = $exception->getMessage();
            } else {
                $message = (string)$exception;
            }
        }
        $this->_log("REST EXCEPTION $rest_code: $message");
        throw new RestException($rest_code, null);
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

    private function _check_filters(&$filters) {
        if (!is_array($filters)) {
            throw new InternalException("attribute 'filters' should been of array type");
        }
        if (count($filters)) {
            $check_fields = ['field', 'value'];
            $check_int_fields = [];

            try {
                $this->_check_fields($filters, $check_fields, $check_int_fields, false);
            } catch (InternalException $e) {
                throw new InternalException("bad filters item: " . $e->getMessage());
            }
        }
    }

    private function _check_order(&$order) {
    }

    private function _check_limits(&$limits) {
        if (is_object($limits)) {
            // передан ассоциативный массив с номером страницы и количеством на странице
            $check_fields = ['page_num', 'page_len'];
            $check_int_fields = ['page_num', 'page_len'];

            try {
                $this->_check_fields($limits, $check_fields, $check_int_fields, false);
            } catch (InternalException $e) {
                throw new InternalException("bad limits : " . $e->getMessage());
            }
            // преобразуем к массиву индекс-количество
            $offset = ($limits->page_num -1) * $limits->page_len;
            $page_len = $limits->page_len;
            $limits = [];
            array_push($limits, $offset, $page_len);

        } elseif (is_array($limits)) {
            // передан индексный массив - должен состоять из двух элементов
            // 1 - начальный индекс
            // 2 - количество
            if (count($limits) != 2) {
                throw new InternalException("attribute 'limits' should been array of 2 integers");
            }
        } else {
            throw new InternalException("attribute 'limits' should been of array or object type");
        }
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