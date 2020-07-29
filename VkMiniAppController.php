<?php
//require './config/config.php';
use Jacwright\RestServer\RestException;
use Krugozor\Database\Mysql\Mysql;

class VkMiniAppController {

    protected $_db;

    // ид пользователя, полученный через строку запроса
    private $_user_id;

    /**
    * @noAuth
    * @url GET /
    */
    public function app() {
        global $_Config;

        //throw new RestException(404, 'Service unavaulable!');
        //print_r($_GET);
        $sign_params = [];
        foreach ($_GET as $name => $value) {
            if (strpos($name, 'vk_') !== 0) { // Получаем только vk параметры из query
                continue;
            }
            $sign_params[$name] = $value;
        }
        // проверка ид приложения вк
        if ($sign_params['vk_app_id'] != $_Config['vk_app_id']) {
            echo 'wrong app id';
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
        if ($status) {
            // все хорошо, подпись верна
            setcookie(
                'rest_auth_token', 
                $this->_hash($sign_params['vk_user_id'].'_'.$sign_params['vk_app_id'].'_'.$_Config['server_key'], $_Config['client_secret']),
                0, // истекает
                '', // путь
                '', // домен
                true, // только шифрованое соединения
                true // только протокол http
            );
            echo file_get_contents('../mini-app/build/index.html');
        } else {
            // подпись не верна
            echo 'invalid request sign';
        }
    }

    /**
    * @noAuth
    * @url GET /test
    */
    public function test() {
        //print_r($this->_get_table_info('registration_requests'));
        print_r($_GET);
    }

    /**
    * @noAuth
    * @url POST /ptest
    */
    public function ptest($data) {
        try {
            $result = $this->_get_registration_requests($data, 'list');
            echo('result='); print_r($result); echo("\n");
        } catch (Exception $e) {
            echo($e->getMessage());
        }

}

    /**
    * @url POST /echo
    */
    public function post_echo($data) {
        print_r($data);
    }

    /**
    * @url GET /registrationstatus
    */
    public function registration_status() {
        try {
            if (!$this->_user_id) throw new Exception('unauthenticated user');
            $data = null;
            $db = $this->db_open();

            $result = $db->query(
            "SELECT
                `id`,
                `request_date`,
                `is_approved`,
                `rejection_reason`,
                `acc_id`
            FROM `registration_requests` 
            WHERE `vk_user_id` = ?i AND `hide_in_app` = 0 AND 'del_in_app' = 0;", 
            $this->_user_id);

            if ($result->getNumRows() != 0) {
                $data = $result->fetch_assoc_array();
            }
            return $this->return_result($data);

        } catch (Exception $e) {
            //print_r($e);
            //throw new RestException(404, 'Service unavaulable!');
            return $this->return_error($e->getMessage());
        }
    }

    /**
    * @url POST /adminprocessregistrationrequests
    */
    public function admin_process_registration_requests($data) {
        try {
            if (!$this->_user_id) throw new Exception('unauthenticated user');
            if (!$this->_check_user_privileges('OPERATOR'))  throw new Exception('not priveleged user');

            $check_fields = ['action', 'filters'];
            $check_int_fields = [];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);

            if (!is_array($data->filters)) throw new Exception("filters is not array");

            $action = strtoupper($data->action);
            $res_data = null;
            $options = null;
            if (property_exists($data, 'options')) $options = $data->options;

            switch ($action) {
                case "GETALL":
                    //print_r('getall');
                    $res_data = $this->_get_registration_requests($data->filters, $options);
                    break;

                case "DETAIL":
                    $res_data = $this->_get_registration_request_detail($data->filters, $options);
                    break;

                case "APPROVE":
                    $res_data = $this->_approve_registration_request($data->filters, $options);
                    break;

                case "REJECT":
                    $res_data = $this->_reject_registration_request($data->filters, $options);
                    break;

                case "DELETE":
                    $res_data = $this->_delete_registration_request($data->filters, $options);
                    break;

                default:
                    throw new Exception('unknown action');
            }

            return $this->return_result($res_data);

        } catch (Exception $e) {
            //print_r($e->getMessage());
            //throw new RestException(404, 'Service unavaulable!');
            return $this->return_error($e->getMessage());
        }
    }

    /**
    * @url POST /adminprocessdata
    */
    public function admin_process_data($data) {
        try {
            if (!$this->_user_id) throw new Exception('unauthenticated user');
            if (!$this->_check_user_privileges('OPERATOR'))  throw new Exception('not priveleged user');

            // проверка данных
            $check_fields = ['action', 'data'];
            $check_int_fields = [];
            $this->_check_fields($data, $check_fields, $check_int_fields, false, 'checking data');

            $ret_data = null;
            $action = strtoupper($data->action);
            switch ($action) {
                case "UPLOAD":
                    $ret_data = $this->_process_data_upload($data->data);
                    break;

                case "DOWNLOAD":
                    $ret_data = $this->_process_data_download($data->data);
                    break;
            }

            return $this->return_result($ret_data);

        } catch (Exception $e) {
            return $this->return_error($e->getMessage());
        }
    }

    /**
    * @url POST /registrationrequestaction
    */
    public function registration_request_action($data) {
        try {
            if (!$this->_user_id) throw new Exception('unauthenticated user');

            $check_fields = ['request_id', 'action'];
            $check_int_fields = ['request_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
            //print_r($data->action);

            $db = $this->db_open();
            $set_clause = 'SET ';
            $num_fields = 0;
            switch ($data->action) {
                case 'delete':
                    $set_clause .= ($num_fields ? ', ' : '')."`del_in_app` = 1";
                    $num_fields ++;

                case 'hide':
                    $set_clause .= ($num_fields ? ', ' : '')."`hide_in_app` = 1";
                    $num_fields ++;
                    break;
            };

            if ($num_fields) {
                $result = $db->query(
                    "UPDATE `registration_requests` ".$set_clause." WHERE `registration_requests`.`id` = ?i AND `registration_requests`.`vk_user_id` = ?i", 
                    $data->request_id,
                    $this->_user_id);

                return $this->return_result(null);
            }

        } catch (Exception $e) {
            return $this->return_error($e->getMessage());
        }
    }

    /**
    * @url POST /registrationrequest
    */
    public function registration_request($data) {
        try {
            if (!$this->_user_id) throw new Exception('unauthenticated user');

            // !TODO проверить количество ожидающих заявок

            $check_fields = ['registration_data'];
            $this->_check_fields($data, $check_fields, [], false);

            // данные по показаниям
            $reg_data = $data->registration_data;

            $check_fields = ['acc_id', 'surname', 'first_name', 'patronymic', 'street', 'n_dom', 'n_kv', 'secret_code'];
            $check_int_fields = ['secret_code'];
            $this->_check_fields($reg_data, $check_fields, $check_int_fields, true);

            // проверка секретного кода
            if (!$this->_check_secret_code($reg_data->acc_id, $reg_data->secret_code)) {
                return $this->return_error('Неверно введн номер лицевого счета или проверочный код', 'CODE_CHECK_FAIL');
            }

            $db = $this->db_open();
            
            $db->query("INSERT INTO `registration_requests` (`vk_user_id`, `acc_id`, `surname`, `first_name`, `patronymic`, `street`, `n_dom`, `n_kv`)
                VALUES (?i, '?s', '?s', '?s', '?s', '?s', '?s', '?s');", 
                $this->_user_id,
                $reg_data->acc_id,
                $reg_data->surname,
                $reg_data->first_name,
                $reg_data->patronymic,
                $reg_data->street,
                $reg_data->n_dom,
                (string)$reg_data->n_kv
            );

            return $this->return_result(null);

        } catch(Exception $e) {
            return $this->return_error($e->getMessage());
        }
    }

    /**
    * @url GET /getuser
    */
    public function getuser() {
        try {
            if (!$this->_user_id) throw new Exception('unauthenticated user');

            $client_columns = ['acc_id', 'secret_code', 'acc_id_repr', 'tenant_repr', 'address_repr'];
            $data = null;
            $db = $this->db_open();

            $result = $db->query(
            "SELECT 
                `vk_users`.`is_blocked`,
                `vk_users`.`privileges`,
                `vk_users`.`registered_by`,
                `vk_users`.`registration_date`,
                `clients`.`acc_id`, 
                `clients`.`secret_code`, 
                `clients`.`acc_id_repr`, 
                `clients`.`tenant_repr`, 
                `clients`.`address_repr`
            FROM `vk_users` 
            LEFT JOIN `accounts` ON `accounts`.`vk_user_id` = `vk_users`.`vk_user_id`
            LEFT JOIN `clients` ON `clients`.`acc_id` = `accounts`.`acc_id`
            WHERE `vk_users`.`vk_user_id` = ?i;", 
            $this->_user_id);

            if ($result->getNumRows() != 0) {
                $data = $this->expand_db_result($result, $client_columns, 'accounts');
                // проверка на блок пользователя
                if ((int)$data['is_blocked'] !== 0) {
                    // не разрешаем передавать показания и выполнять какие либо действия
                    $data['accounts'] = [];
                    // сброс полномочий, если они есть
                    $data['privileges'] = 'USER';
                }
                $data = [$data];
            }
            
            return $this->return_result($data);

        } catch (Exception $e) {
            //print_r($e);
            //throw new RestException(404, 'Service unavaulable!');
            return $this->return_error($e->getMessage());
        }
    }
    
    /**
    * @url GET /getmeters/$acc_id
    */
    public function getmeters($acc_id) {
        try {
            if (!$this->_user_id) throw new Exception('unauthenticated user');

            $data = null;
            $db = $this->db_open();

            $result = $db->query(
            "SELECT
                `meters`.`id` as 'meter_id',
                `meters`.`title`,
                `meters`.`current_count`,
                `meters`.`updated`, 
                `lastIndications`.`count` as 'new_count',
                DATE_FORMAT(`lastIndications`.`recieve_date`, '%d.%m.%Y') AS 'recieve_date',
                `lastIndications`.`vk_user_id`
            FROM `meters` 
            LEFT JOIN (
                SELECT `indications`.*
                FROM `indications` 
                    JOIN (
                        SELECT MAX(id) maxId
                        FROM `indications`
                        GROUP BY `meter_id`
                        ) maxIndacations
                    ON `indications`.`id` = `maxIndacations`.`maxId`
                ) lastIndications
            ON `meters`.`id` = `lastIndications`.`meter_id`
            WHERE `meters`.`acc_id` = '?i';", $acc_id);

            if ($result->getNumRows() != 0) {
                $data = $result->fetch_assoc_array();
            }    

            return $this->return_result($data);

        } catch (Exception $e) {
            //print_r($e);
            //throw new RestException(404, 'Service unavaulable!');
            return $this->return_error($e->getMessage());
        }
    } 

    /**
    * @url POST /setmeters
    */
    public function setmeters($data) {
        try {
            if (!$this->_user_id) throw new Exception('unauthenticated user');

            $check_fields = ['meters'];
            $check_int_fields = [];
            $this->_check_fields($data, $check_fields, $check_int_fields, true);

            //check user
            if (!$this->_check_user_privileges('user')) throw new Exception('not priveleged user');
            // данные по показаниям
            $meters = $data->meters;
            if (!is_array($meters)) throw new Exception('bad meters data');

            if (count($meters) > 0) {
                $check_fields = ['meter_id', 'new_count'];
                $check_int_fields = ['meter_id'];
        
                $this->_check_fields($meters, $check_fields, $check_int_fields, false);

                $rows_inserted = 0;

                $db = $this->db_open();

                $query = "INSERT 
                INTO `indications` (`meter_id`, `count`, `vk_user_id`) 
                VALUES ";
                foreach($meters as $meter) {
                    $query .= ($rows_inserted ? ',' : '').(is_numeric($meter->new_count) 
                        ? $db->prepare("(?i, ?i, ?i)", $meter->meter_id, $meter->new_count, $this->_user_id) 
                        : $db->prepare("(?i, NULL, ?i)", $meter->meter_id, $this->_user_id));
                    $rows_inserted++;
                }
                //throw new Exception($query);
                if ($rows_inserted) $result = $db->query($query);

                return $this->return_result(null);
            }
        } catch(Exception $e) {
            return $this->return_error($e->getMessage());
        }
    }

    public function authorize() {
        global $_Config;
        $user_id = '';
        $token = '';
        // получим токен
        if (array_key_exists('token', $_GET)) {
            $token = $_GET['token'];
        }
        elseif (array_key_exists('rest_auth_token', $_COOKIE)) {
            $token = $_COOKIE['rest_auth_token'];
        }
        // получим пользователя
        if (array_key_exists('user_id', $_GET)) {
            $user_id = $_GET['user_id'];  
        }
        if (!$user_id || !$token) return false;
        $expected_token = $this->_hash($user_id.'_'.$_Config['vk_app_id'].'_'.$_Config['server_key'], $_Config['client_secret']);
        $status = $expected_token === $token;
        if ($status) $this->_user_id = $user_id;
        return $status;
    }

    private function _get_table_info($table_name) {
        try {
            $db = $this->db_open();

            $result = $db->query("SELECT 
            `COLUMN_NAME`,
            `DATA_TYPE`,
            `IS_NULLABLE`,
            `COLUMN_TYPE`
            FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_NAME` = '?s' AND `TABLE_SCHEMA` = DATABASE();",
            $table_name);

            return $result->fetch_assoc_array();

        } catch (Exception $e) {
            return false;
        }
    }

    private function _is_values_array_contents_null($values) {
        foreach ($values as $value) {
            if ( is_null($value) || (is_string($value) && strtoupper($value) === "NULL") ) return true;
        }
        return false;
    }

    private function _build_date_condition($field, $value, $data_type, &$params) {
        $condition = '';

        if (is_array($value)) {
            $len = count($value);
            if (!$len) {
                // пустой массив - ложное условие
                $condition = " FALSE ";
            } elseif ($len == 1) {
                // один элемент
                $condition = " DATE(`$field`) = '?s' ";
                $params[] = $this->_str_to_date($value[0]);
            } else {
                // в массиве две даты: меньшая - начало периода, большая - конец периода
                sort($value);
                $condition = " `$field` BETWEEN '?s' AND '?s' ";
                $params[] = $this->_str_to_date($value[0], [
                    "hour" => 0,
                    "minute" => 0,
                    "second" => 0
                ]);
                $params[] = $this->_str_to_date($value[$len - 1], [
                    "hour" => 23,
                    "minute" => 59,
                    "second" => 59
                ]);
            }
        } else {
            // одна дата: делаем выборку за день
            $condition = " DATE(`$field`) = '?s' ";
            $params[] = $this->_str_to_date($value);
        }
        return $condition;
    }

    private function _build_condition($field, $value, $data_type, &$params) {
        $condition = '';
        if ( is_null($value) || (is_string($value) && strtoupper($value) === "NULL") ) {
            $condition = " `$field` IS NULL ";
        }
        elseif (is_array($value)) {
            // если в массиве есть null, то нельзя использовать IN ()
            if (!count($value)) {
                // пустой массив - условие ложно
                $condition = " FALSE ";
            } elseif ($this->_is_values_array_contents_null($value)) {
                foreach ($value as $value_item) {
                    $condition .= (strlen($condition) ? " OR " : "") . $this->_build_condition($field, $value_item, $data_type, $params);
                }
                $condition = " (" . $condition . ") ";

            } else {
                $condition = " `$field` IN " . ($data_type == 'INT' ? "(?ai) " : "(?as) ");
                $params[] = $value;
            }
        }
        else {
            $condition = " `$field` = " . ($data_type == 'INT' ? "?i " : "'?s' ");
            $params[] = $value;
        }
        
        return $condition;
    }

    private function _bild_filters($filters, $table_fields, &$params) {
        
        $where_clause = '';
        if (is_array($filters)) {
            foreach ($filters as $filter) {
                $key = array_search($filter->field, array_column($table_fields, 'COLUMN_NAME'));
                if ($key === false) throw new Exception("bad field name '$filter->field'");
                $data_type = strtoupper($table_fields[$key]['DATA_TYPE']);

                $where_clause .= (strlen($where_clause) ? " AND " : "") . ($data_type == "TIMESTAMP" 
                    ? $this->_build_date_condition($filter->field, $filter->value, $data_type, $params)
                    : $this->_build_condition($filter->field, $filter->value, $data_type, $params));
            }
        }
        return $where_clause;
    }

    private function _get_registration_request_detail($filters, $option = null) {
        try {
            // выбрать заявки по фильтрам
            $requests = $this->_get_registration_requests($filters, 'detail');
            for ($index = 0; $index < count($requests); $index++) {
                // подбор лицевых счетов в соответствии с заявкой
                $account = trim($requests[$index]['acc_id']);
                $db = $this->db_open();
                $result = $db->query("SELECT * 
                    FROM `clients` 
                    WHERE `acc_id_repr` LIKE '%?S%'",
                    $account);
                if (!$result) throw new Exception('result of query is false');
                $requests[$index]['selected_accounts'] = $result->fetch_assoc_array();
                //print_r($db->getQueryString());
            }
            return $requests;
        } catch (Exception $e) {
            throw new Exception('can not fetch request detail', 0, $e);
        }
    }

    private function _get_registration_requests($filters, $option = null) {
        try {
            $select_clause = '*';
            if (is_string($option)) {
                $option = strtoupper($option);
                switch ($option) {
                    case 'DETAIL':
                        $select_clause = "*, 
                        DATE_FORMAT(`request_date`, '%d.%m.%Y') AS 'request_date', 
                        DATE_FORMAT(`update_date`, '%d.%m.%Y') AS 'update_date'";
                        break;

                    case 'LIST':
                        $select_clause = "
                            `id`, 
                            `vk_user_id`, 
                            `acc_id`, 
                            DATE_FORMAT(`request_date`, '%d.%m.%Y') AS 'request_date', 
                            DATE_FORMAT(`update_date`, '%d.%m.%Y') AS 'update_date', 
                            `is_approved`, 
                            `processed_by`, 
                            `hide_in_app`, 
                            `del_in_app`";
                        break;

                }
            }
            $db = $this->db_open();

            $table_fields = $this->_get_table_info('registration_requests');
            if ($table_fields === false) throw new Exception('can not fetch data schema');

            $params = [];
            $query = "SELECT " . $select_clause . " FROM `registration_requests` ";
            $where_clause = $this->_bild_filters($filters, $table_fields, $params);
            //print_r($where_clause);
            $result = $db->queryArguments($query . (strlen($where_clause) ? ' WHERE ' . $where_clause : ''), $params);
            //print_r($db->getQueryString()); echo("\r");
            if (!$result) throw new Exception('result of query is false');
            return $result->fetch_assoc_array();

        } catch (Exception $e) {
            throw new Exception('can not fetch registration requests: ' . $e->getMessage(), 0, $e);
        }
    }

    private function _approve_registration_request($filters, $option) {
        try {
            $registrator = $this->_user_id;
            //echo('options='); print_r($option); echo("\n");
            $check_fields = ['account_id'];
            $check_int_fields = ['account_id'];

            $this->_check_fields($option, $check_fields, $check_int_fields, false);
            // выбрать заявки по фильтрам
            $requests = $this->_get_registration_requests($filters);
            if (count($requests) != 1) throw new Exception('multiple approval is not allowed');

            $this->_db = $this->db_open();
            $this->_db->getMysqli()->begin_transaction(); //(MYSQLI_TRANS_START_READ_WRITE);

            $request = $requests[0];
            // создать пользователей
            $this->_create_user($request['vk_user_id'], 'USER', $registrator);    
            // привязать лс
            $this->_link_account($request['vk_user_id'], $option->account_id);
            // заапрувить заявки
            $result = $this->_db->query("UPDATE `registration_requests`
                 SET `is_approved` = 1, 
                 `processed_by` = ?i
                 WHERE `id` = ?i;",
                 $registrator,
                 $request['id']);

            $this->_db->getMysqli()->commit();

        } catch (Exception $e) {
            if ($this->_db) $this->_db->getMysqli()->rollback();
            //print_r($e);
            throw new Exception('can not aprove request', 0, $e);

        } finally {
            if ($this->_db) {
                $this->_db->__destruct();
                $this->_db = null;
            }
        }

    }

    private function _reject_registration_request($filters, $option) {
        try {
            $registrator = $this->_user_id;
            $rejection_reason= "";
            if (is_object($option) && property_exists($option, 'rejection_reason')) $rejection_reason= $option->rejection_reason;

            $db = $this->db_open();

            $table_fields = $this->_get_table_info('registration_requests');
            if ($table_fields === false) throw new Exception('can not fetch data schema');

            $params = [];
            $params[] = $registrator;
            $params[] = $rejection_reason;
            $query = "UPDATE `registration_requests` SET `is_approved` = 0, `processed_by` = ?i, `rejection_reason` = '?s' ";
            $where_clause = $this->_bild_filters($filters, $table_fields, $params);

            if (!strlen($where_clause)) throw new Exception('no filters given');

            $result = $db->queryArguments($query . ' WHERE ' . $where_clause, $params);
            if (!$result) throw new Exception('result of query is false');

        } catch (Exception $e) {
            throw new Exception('can not reject request', 0, $e);
        }
    }

    private function _delete_registration_request($filters, $option) {
        try {
            $db = $this->db_open();

            $table_fields = $this->_get_table_info('registration_requests');
            if ($table_fields === false) throw new Exception('can not fetch data schema');

            $params = [];
            $query = "DELETE FROM `registration_requests` ";
            $where_clause = $this->_bild_filters($filters, $table_fields, $params);

            if (!strlen($where_clause)) throw new Exception('no filters given');

            //удаляем только удаленные пользователем!
            $where_clause .= " AND `del_in_app` = 1";

            $result = $db->queryArguments($query . ' WHERE ' . $where_clause, $params);
            if (!$result) throw new Exception('result of query is false');

        } catch (Exception $e) {
            throw new Exception('can not delete request', 0, $e);
        }
    }

    private function _process_data_download($data_params) {
        try {
            if (!is_object($data_params)) throw new Exception("data_params is not object");
            $where_clause = "";
            $params = [];
            if (property_exists($data_params, 'period_begin') && strlen($data_params->period_begin)) {
                $where_clause .= (strlen($where_clause) ? ' AND ' : '') . "`recieve_date` >= '?s'";
                $params[] = $this->_str_to_date($data_params->period_begin, [
                    "hour" => 0,
                    "minute" => 0,
                    "second" => 0
                ]);
            }
            if (property_exists($data_params, 'period_end') && strlen($data_params->period_end)) {
                $where_clause .= (strlen($where_clause) ? ' AND ' : '') . "`recieve_date` <= '?s'";
                $params[] = $this->_str_to_date($data_params->period_end, [
                    "hour" => 23,
                    "minute" => 59,
                    "second" => 59
                ]);
            }

            $db = $this->db_open();
            $query = "
                SELECT  
                    `indications`.`id` AS 'indication_id',
                    `indications`.`count` AS 'count',
                    `indications`.`recieve_date` AS 'recieve_date',
                    `indications`.`vk_user_id` AS 'vk_user_id',
                    `meters`.`index_num` AS 'meter_code',
                    `meters`.`title` AS 'meter_title',
                    `meters`.`current_count` AS 'current_count'
                FROM `indications`
                    LEFT JOIN `meters` ON `indications`.`meter_id` = `meters`.`id`";
            if (strlen($where_clause)) {
                $query .= " WHERE " . $where_clause;
            }
            $result = $db->queryArguments($query, $params);
            if (!$result) throw new Exception('result of query is false');
            return $result->fetch_assoc_array();

        } catch (Exception $e) {
            throw new Exception("can not process download data: " . $e->getMessage(), 0, $e);
        }
    }

    private function _process_data_upload($data_to_fill) {
        try {
            if (!is_array($data_to_fill)) throw new Exception("data is not array");
            // поля для проверки аккаунтов 
            $check_fields_acc = ['acc_id', 'secret_code', 'acc_id_repr', 'tenant_repr', 'address_repr', 'meters'];
            $check_int_fields_acc = ['acc_id', 'secret_code'];
            // поля для проверки счетчиков
            $check_fields_mtr = ['code', 'title', 'current_count'];
            $check_int_fields_mtr = ['code', 'current_count'];
            foreach ($data_to_fill as $account) {
                $this->_check_fields($account, $check_fields_acc, $check_int_fields_acc, false, 'checking account');
                foreach ($account->meters as $meter) {
                    $this->_check_fields($meter, $check_fields_mtr, $check_int_fields_mtr, false, 'checking meter');
                }
            }
            // загрузка данных
            $this->_fill_data($data_to_fill);

            return null;

        } catch (Exception $e) {
            throw new Exception("can not process upload data: " . $e->getMessage(), 0, $e);
        }
    }

    private function _fill_data($data) {
        try {
            $this->_db = $this->db_open();
            $this->_db->getMysqli()->begin_transaction(); //(MYSQLI_TRANS_START_READ_WRITE);
            // очистка таблиц
            $this->_clear_table('indications');
            $this->_clear_table('meters');
            $this->_clear_table('clients');
            // заполнение таблицы clients
            $query = "INSERT INTO `clients` (`acc_id`, `secret_code`, `acc_id_repr`, `tenant_repr`, `address_repr`) VALUES ";
            $clients_values_clause = "";
            $meters_values_clause = "";
            $clients_lines = 0;
            $meters_lines = 0;
            foreach ($data as $account) {
                $clients_values_clause .= ($clients_lines ? ", " : "") . $this->_db->prepare("(?i, ?i, '?s', '?s', '?s')",
                (int)$account->acc_id,
                (int)$account->secret_code,
                $account->acc_id_repr,
                $account->tenant_repr,
                $account->address_repr);

                $clients_lines ++;

                foreach ($account->meters as $meter) {
                    $meters_values_clause .= ($meters_lines ? ", " : "") . $this->_db->prepare("(?i, ?i, '?s', ?i)",
                    (int)$meter->code,
                    (int)$account->acc_id,
                    $meter->title,
                    $meter->current_count);

                    $meters_lines ++;
                }
            }
            // вставка аккаунтов
            $result = true;
            if ($clients_lines) {
                $result = $this->_db->query($query . $clients_values_clause);
            }
            if (!$result) throw new Exception("result of query '$query' is false");
            // вставка счетчиков
            $result = true;
            $query = "INSERT INTO `meters` (`index_num`, `acc_id`, `title`, `current_count`) VALUES ";
            if ($meters_lines) {
                $result = $this->_db->query($query . $meters_values_clause);
            }
            if (!$result) throw new Exception("result of query '$query' is false");
            // коммит транзакции 
            $this->_db->getMysqli()->commit();

        } catch (Exception $e) {
            // откат транзакции
            if ($this->_db) $this->_db->getMysqli()->rollback();
            throw new Exception("can not fill data: " . $e->getMessage(), 0, $e);

        } finally {
            if ($this->_db) {
                $this->_db->__destruct();
                $this->_db = null;
            }
        }
    }

    private function _clear_table($table_name) {
        try {
            $allowed_tables = ['clients', 'meters', 'indications'];
            if (!in_array($table_name, $allowed_tables)) throw new Exception("not allowed table '$table_name'");
            if ($this->_db) $db = $this->_db; else $db = $this->db_open();
            $result = $db->query("DELETE FROM `".$table_name."`;");
            if (!$result) throw new Exception('result of query is false');

        } catch (Exception $e) {
            throw new Exception("can not clear table '$table_name'", 0, $e);
        }
    }

    private function _create_user($user_id, $priveleges, $registrator) {
        try {
            if (!$this->_is_user_exists($user_id)) {
                if ($this->_db) $db = $this->_db; else $db = $this->db_open();
                $result = $db->query("
                    INSERT INTO `vk_users` 
                    (`vk_user_id`, 
                    `privileges`, 
                    `registered_by`) 
                    VALUES (?i, '?s', ?i);",
                $user_id,
                $priveleges,
                $registrator);
                if (!$result) throw new Exception('result of query is false');
            }

        } catch (Exception $e) {
            throw new Exception('can not create user', 0, $e);
        }
    }

    private function _link_account($user_id, $account) {
        try {
            if ($this->_is_user_exists($user_id) && !$this->_is_link_exists($user_id, $account)) {
                if ($this->_db) $db = $this->_db; else $db = $this->db_open();
                $result = $db->query("
                    INSERT INTO `accounts` 
                    (`vk_user_id`, 
                    `acc_id`) 
                    VALUES (?i, ?i);",
                $user_id,
                $account);
                if (!$result) throw new Exception('result of query is false');
            }

        } catch (Exception $e) {
            throw new Exception('can not link account to user', 0, $e);
        }
    }

    private function _is_link_exists($user_id, $account) {
        try {
            if ($this->_db) $db = $this->_db; else $db = $this->db_open();
            $result = $db->query("
                SELECT `id` 
                FROM `accounts` 
                WHERE `vk_user_id` = ?i AND `acc_id` = ?i;",
            $user_id,
            $account);
            if ($result === false) throw new Exception('bad db query');
            return ($result->getNumRows() != 0);

        } catch (Exception $e) {
            throw new Exception('can not check user existence', 0, $e);
        }
    }

    private function _is_user_exists($user_id) {
        try {
            if ($this->_db) $db = $this->_db; else $db = $this->db_open();
            $result = $db->query("
                SELECT `vk_user_id` 
                FROM `vk_users` 
                WHERE `vk_user_id` = ?i;",
            $user_id);
            if ($result === false) throw new Exception('bad db query');
            return ($result->getNumRows() != 0);

        } catch (Exception $e) {
            throw new Exception('can not check user existence', 0, $e);
        }
    }

    private function _check_secret_code($acc_id_repr, $Secret_code) {
        try {
            $db = $this->db_open();
            $result = $db->query("
                SELECT 
                    `acc_id`, 
                    `secret_code` 
                FROM `clients` 
                WHERE `secret_code` = ?i AND `acc_id_repr` LIKE '%?S%'",
            $Secret_code,
            $acc_id_repr);

            $data = $result->fetch_assoc_array();
            return (count($data) === 1);

        } catch (Exception $e) {
            throw new Exception("can not check secret code: " . $e->getMessage(), 0, $e);
        }
    }

    private function _check_user_privileges($requested_privilege) {
        try {
            $priv_clause = '';
            $requested_privilege = strtoupper($requested_privilege);
            switch ($requested_privilege) {
                case 'USER':
                    $priv_clause .= (strlen($priv_clause) ? ',' : '')."'USER'";
                case 'OPERATOR':
                    $priv_clause .= (strlen($priv_clause) ? ',' : '')."'OPERATOR'";
                case 'ADMIN':
                    $priv_clause .= (strlen($priv_clause) ? ',' : '')."'ADMIN'";
                    break;
                default:
                    return false;
            }
            $db = $this->db_open();
            $result = $db->query("
                SELECT `vk_user_id` 
                FROM `vk_users` 
                WHERE `vk_user_id` = ?i AND `is_blocked` = 0 AND `privileges` IN (".$priv_clause.");",
            $this->_user_id);
            //print_r($db->getQueryString()); echo("\n");

            return ($result->getNumRows() != 0 );

        } catch (Exception $e) {
            //print_r($e->getMessage());
            return false;
            
        }
    }

    private function _str_to_date($date_str, $set_time = null) {
        try {
            $dt = date_create($date_str);
            if (is_array($set_time)) date_time_set($dt, $set_time['hour'], $set_time['minute'], $set_time['second']);
            $result = date_format($dt, "Y-m-d H:i:s");
            if ($result === false) throw new Exception("not valid date string '$date_str'");
            return $result;

        } catch (Exception $e) {
            throw new Exception("can not convert string '$date_str' to Date: " . $e->getMessage(), 0, $e);
        }
    }

    private function _check_fields(&$data, $fields, $int_fields, $set_zero = true, $context = null) {
        if (is_array($data)) {
            foreach ($data as $dataItem) {
                $this->_check_fields($dataItem, $fields, $int_fields, $set_zero);
            }

        } else if (is_object($data)) {
            foreach ($fields as $field) {
                if (!property_exists($data, $field)) throw new Exception(($context ? "$context. " : "") . "field '$field' not exists");
                if (in_array($field, $int_fields) && !is_numeric($data->{$field})) {
                    if ($set_zero) $data->{$field} = 0;
                    else throw new Exception(($context ? "$context. " : "") . "not int value in field '$field'");
                }
            }

        } else {
            throw new Exception(($context ? "$context. " : "") . "data must been array or object");
        }
    }

    private function expand_db_result($db_result, $unique_fields, $unique_list_name = 'items', $no_null_rows = true) {
        $data = [];
        $data[$unique_list_name] = [];
        while ($row = $db_result->fetch_assoc()) {
            $unique_row = [];
            $is_null_row = true;
            foreach($row as $field => $value) {
                if (in_array($field, $unique_fields, true)) {
                    $unique_row[$field] = $value;
                    $is_null_row = $is_null_row && ($value === null);
                } else {
                    $data[$field] = $value;
                }
            }
            if (!$is_null_row)
                array_push($data[$unique_list_name], $unique_row);
        }
        return $data;
    }

    private function return_result($data) {
        $ret_data = [
            'result' => true,
            'data' => [],
            'data_len' => 0
        ];
        if ($data !== null) {
            if (is_array($data)) 
                $ret_data['data'] = $data;
            else
                array_push($ret_data['data'], $data);
        }
        $ret_data['data_len'] = count($ret_data['data']);
        return  $ret_data;
    }

    private function return_error($message, $name = '') {
        $ret_data = [
            'result' => false,
            'data' => [],
            'error' => [
                'name' => $name,
                'message' => $message
            ],
            'data_len' => 0
        ];
        return  $ret_data;
    }

    private function db_open() {
        global $_Config;
        $db = Mysql::create(
            $_Config['db_host'],
            $_Config['db_user'],
            $_Config['db_pass'],
            ($_Config['db_port'] ? $_Config['db_port'] : null)
        )
        ->setDatabaseName($_Config['db_name'])
        ->setCharset($_Config['db_charset']);

        return $db;
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