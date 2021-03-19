<?php
use Jacwright\RestServer\RestException;
require_once 'AbstractController.php';
require_once 'AppError.php';
require_once 'Common.php';
require_once 'InternalException.php';
require_once 'Logger.php';
require_once 'DataBase.php';

class UserController extends AbstractController
{
    /**
    * @url POST /regrequests/list
    * @url GET /regrequests/list
    */
    public function regrequests_list($data) {
        try {
            $db_data = DataBase::regrequests_list($this->_user_id);
        } catch (InternalException $e) {$this->_handle_exception(500, $e);}
        return $db_data;
    }

    /**
    * @url POST /regrequests/add
    */
    public function regrequests_add($data) {
        // так же проверять не привязан ли уже данный лс к данному пользователю
        // проверка на блок и привилегии
        if (!$this->_has_user_privs()) {
            $this->_handle_exception(403);
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
            $this->_handle_exception(400, $e);
        }
        // проверка существования связи???

        try {
            // проверка на существование заявки
            if (DataBase::is_waiting_regrequest_exists($this->_user_id, $reg_data->acc_id)) {
                //$this->_handle_exception(409);
                return $this->_return_app_error(APPERR_REGREQUEST_DUBL);
            }
            // проверка секретного кода
            if (!$reg_data->secret_code) {
                //$this->_handle_exception(400);
                return $this->_return_app_error(APPERR_BAD_SECRETCODE);
            }
            $accounts = DataBase::get_accounts_by_repr($reg_data->acc_id, $reg_data->secret_code);
            if (count($accounts) != 1) {
                //$this->_handle_exception(APPERR_ACCOUNT_NOT_FOUND);
                return $this->_return_app_error(APPERR_ACCOUNT_NOT_FOUND);
            }
            // 
            $rows_insert = DataBase::regrequests_add($this->_user_id, $reg_data);

        } catch (InternalException $e) {$this->_handle_exception(500, $e);}

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
            $this->_handle_exception(400, $e);
        }

        try {
            DataBase::regrequests_hide($this->_user_id, $data['request_id']);

        } catch (InternalException $e) {$this->_handle_exception(500, $e);}
        
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
            $this->_handle_exception(400, $e);
        }
        
        try {
            DataBase::regrequests_del($this->_user_id, $data['request_id']);
            
        } catch (InternalException $e) {$this->_handle_exception(500, $e);}

        return;
    }

    /**
    * @url POST /privileges/get
    * @url GET /privileges/get
    */
    public function privileges_get($data) {
        try {
            $priv_data = DataBase::users_privileges_get($this->_user_id);
            if (!$priv_data) {
                // пользователь не зарегистрирован - по умолчанию USER
                $priv_data = [
                    'vk_user_id' => $this->_user_id,
                    'is_blocked' => 0,
                    'privileges' => 'USER'
                ];
            }
            $perm_data = DataBase::app_permissions_get();
        } catch (InternalException $e) {$this->_handle_exception(500, $e);}

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
        } catch (InternalException $e) {$this->_handle_exception(500, $e);}

        return $perm_data;
    }
    
    /**
    * @url POST /accounts/list
    * @url GET /accounts/list
    */
    public function accounts_list($data) {
        // проверка на блок и привилегии
        if (!$this->_has_user_privs()) {
            $this->_handle_exception(403);
        }
        try {
            $db_data = DataBase::accounts_list($this->_user_id);
        } catch (InternalException $e) {$this->_handle_exception(500, $e);}
            
        return $db_data;
    }
    
    /**
    * @url POST /meters/list
    */
    public function meters_list($data) {
        // проверка на блок и привилегии
        if (!$this->_has_user_privs()) {
            $this->_handle_exception(403);
        }
        try {
            $check_fields = ['account_id'];
            $check_int_fields = ['account_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_exception(400, $e);
        }
        // проверка аккаунта на принадлежность пользователю
        try {
            if (DataBase::accounts_get($this->_user_id, $data->account_id) === null) {
                return $this->_return_app_error(APPERR_ACCOUNT_NOT_OWNED);
            }
            // получение данных по счетчикам
            $db_data = DataBase::meters_list($this->_user_id, $data->account_id);
        } catch (InternalException $e) {
            $this->_handle_exception(500, $e);
        }
        
        return $db_data;
    }

    /**
    * @url POST /indications/list
    */
    public function indications_list($data) {
        // проверка на блок и привилегии
        if (!$this->_has_user_privs()) {
            $this->_handle_exception(403);
        }
        try {
            $check_fields = ['account_id'];
            $check_int_fields = ['account_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
        } catch (InternalException $e) {
            $this->_handle_exception(400, $e);
        }
        try {
            if (DataBase::accounts_get($this->_user_id, $data->account_id) === null) {
                return $this->_return_app_error(APPERR_ACCOUNT_NOT_OWNED);
            }
            // получение данных по счетчикам
            $db_data = DataBase::indications_list($this->_user_id, $data->account_id);
        } catch (InternalException $e) {
            $this->_handle_exception(500, $e);
        }
        
        return $db_data;
    }
    
    /**
    * @url POST /indications/add
    */
    public function indications_add($data) {
        // проверка на блок и привилегии
        if (!$this->_has_user_privs()) {
            $this->_handle_exception(403);
        }
        try {
            $check_fields = ['account_id', 'meters'];
            $check_int_fields = ['account_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, true);

            $meters = $data->meters;
            if (!is_array($meters)) throw new InternalException('bad meters data');

        } catch (InternalException $e) {
            $this->_handle_exception(400, $e);
        }

        if (count($meters) > 0) {
            try {
                $check_fields = ['meter_id', 'new_count'];
                $check_int_fields = ['meter_id'];
                $this->_check_fields($meters, $check_fields, $check_int_fields, false);
            } catch (InternalException $e) {
                $this->_handle_exception(400, $e);
            }

            // проверка принадлежности счетчиков
            $owned_meters = array_column(DataBase::meters_list($this->_user_id, $data->account_id), 'meter_id');
            foreach ($meters as $meter) {
                if (!in_array($meter->meter_id, $owned_meters)) {
                    $this->_handle_exception(400);
                }
            }
            try {
                DataBase::indications_add($this->_user_id, $meters);
            } catch (InternalException $e) {
                $this->_handle_exception(500, $e);
            }
        }

        return true;
    }
    
}
?>