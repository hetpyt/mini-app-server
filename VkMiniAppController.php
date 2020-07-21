<?php
//require './config/config.php';
use Jacwright\RestServer\RestException;
use Krugozor\Database\Mysql\Mysql;

class VkMiniAppController {

    /**
    * @url GET /
    */
    public function root() {
        throw new RestException(404, 'Service unavaulable!');
    }

    /**
    * @url GET /test
    */
    public function test() {
        print_r($this->_get_table_info('registration_requests'));
    }

    /**
    * @url POST /echo
    */
    public function post_echo($data) {
        print_r($data);
    }

    /**
    * @noAuth
    * @url GET /registrationstatus/$user_id
    */
    public function registration_status($user_id) {
        try {
            $data = null;
            $db = $this->db_open();

            $result = $db->query(
            "SELECT
                `id`,
                `request_date`,
                `is_approved`,
                `acc_id`
            FROM `registration_requests` 
            WHERE `vk_user_id` = ?i AND `hide_in_app` = 0 AND 'del_in_app' = 0;", 
            $user_id);

            if ($result->getNumRows() != 0) {
                $data = $result->fetch_assoc_array();
            }
            sleep(1);
            return $this->return_result($data);

        } catch (Exception $e) {
            //print_r($e);
            //throw new RestException(404, 'Service unavaulable!');
            return $this->return_result($data, false);

        }
    }

    /**
    * @noAuth
    * @url POST /adminprocessregistrationrequests
    */
    public function admin_process_registration_requests($data) {
        try {
            $check_fields = ['result', 'vk_user_id', 'action', 'filters'];
            $check_int_fields = ['vk_user_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, false);

            if (!$this->_check_user_privileges($data->vk_user_id, 'OPERATOR'))  throw new Exception('not priveleged user');

            $action = strtoupper($data->action);
            $res_data = null;

            switch ($action) {
                case "GETALL":
                    //print_r('getall');
                    $res_data = $this->_get_registration_requests($data->filters);
                    break;

                case "APPROVE":
                    $query = "UPDATE `registration_requests`
                    SET `is_approved` = 1, `processed_by` = ?i ";
                    break;

                case "REJECT":
                    $res_data = $this->_reject_registration_request($data->filters, $data->vk_user_id);
                    break;

                default:
                    throw new Exception('unknown action');
            }

            return $this->return_result($res_data);

        } catch (Exception $e) {
            //print_r($e->getMessage());
            //throw new RestException(404, 'Service unavaulable!');
            return $this->return_result($e->getMessage(), false);
        }
    }

    /**
    * @noAuth
    * @url POST /registrationrequestaction
    */
    public function registration_request_action($data) {
        $check_fields = ['result', 'vk_user_id', 'request_id', 'action'];
        $check_int_fields = ['vk_user_id', 'request_id'];
        try {
            $this->_check_fields($data, $check_fields, $check_int_fields, false);
            //print_r($data->action);
            if (!$data->result) throw new Exception('result not true');

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
                    $data->vk_user_id);

                //print_r($db->getQueryString());
                sleep(1);
                return $this->return_result(null, true);
            }

        } catch (Exception $e) {
            return $this->return_result($e->getMessage(), false);

        }

    }

    /**
    * @noAuth
    * @url POST /registrationrequest
    */
    public function registration_request($data) {
        try {
            $check_fields = ['result', 'registration_data'];
            $this->_check_fields($data, $check_fields, [], false);

            if (!$data->result) throw new Exception('result not true');
            // данные по показаниям
            $reg_data = $data->registration_data;

            $check_fields = ['vk_user_id', 'acc_id', 'surname', 'first_name', 'patronymic', 'street', 'n_dom', 'n_kv'];
            $check_int_fields = ['vk_user_id'];
            $this->_check_fields($reg_data, $check_fields, $check_int_fields, true);

            if (0 === $reg_data->vk_user_id) throw new Exception('bad vk user');

            $rows_inserted = 0;

            $db = $this->db_open();
            
            $db->query("INSERT INTO `registration_requests` (`vk_user_id`, `acc_id`, `surname`, `first_name`, `patronymic`, `street`, `n_dom`, `n_kv`)
                VALUES (?i, '?s', '?s', '?s', '?s', '?s', '?s', ?s);", 
                $reg_data->vk_user_id,
                $reg_data->acc_id,
                $reg_data->surname,
                $reg_data->first_name,
                $reg_data->patronymic,
                $reg_data->street,
                $reg_data->n_dom,
                (string)$reg_data->n_kv
            );

            return $this->return_result(null, true);

        } catch(Exception $e) {
            return $this->return_result($e->getMessage(), false);
            
        }
    }

    /**
    * @noAuth
    * @url GET /getuser/$id
    */
    public function getuser($id) {
        $client_columns = ['acc_id', 'secret_code', 'acc_id_repr', 'tenant_repr', 'address_repr'];
        try {
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
            $id);

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
            return $this->return_result($e->getMessage(), false);
            
        }
    }
    
    /**
    * @noAuth
    * @url GET /getmeters/$acc_id
    */
    public function getmeters($acc_id) {
        try {
            $data = null;
            $db = $this->db_open();

            $result = $db->query(
            "SELECT
                `meters`.`id` as 'meter_id',
                `meters`.`title`,
                `meters`.`current_count`,
                `meters`.`updated`, 
                `lastIndications`.`count` as 'new_count',
                `lastIndications`.`recieve_date`,
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

            sleep(1);
            return $this->return_result($data);

        } catch (Exception $e) {
            //print_r($e);
            //throw new RestException(404, 'Service unavaulable!');
            return $this->return_result(null, false);
            
        }
    } 

    /**
    * @noAuth
    * @url POST /setmeters
    */
    public function setmeters($data) {
        try {
            //echo('data='); print_r($data); echo("\n");
            $check_fields = ['result', 'vk_user_id', 'meters'];
            $check_int_fields = ['vk_user_id'];
            $this->_check_fields($data, $check_fields, $check_int_fields, true);

            if (!$data->result) throw new Exception('result not true');

            //check user
            if (!$this->_check_user_privileges($data->vk_user_id, 'user')) {
                throw new Exception('not priveleged user');
            }
            // данные по показаниям
            $meters = $data->meters;
            if (!is_array($meters)) throw new Exception('bad meters data');

            if (count($meters) > 0) {
                $check_fields = ['meter_id', 'new_count'];
                $check_int_fields = ['meter_id', 'new_count'];
        
                $this->_check_fields($meters, $check_fields, $check_int_fields, true);

                $rows_inserted = 0;

                $db = $this->db_open();

                $query = "INSERT 
                INTO `indications` (`meter_id`, `count`, `vk_user_id`) 
                VALUES ";
                foreach($meters as $meter) {
                    $query .= ($rows_inserted ? ',' : '').$db->prepare("(?i, ?i, ?i)", $meter->meter_id, $meter->new_count, $data->vk_user_id);
                    $rows_inserted++;
                }
                //throw new Exception($query);
                if ($rows_inserted) $result = $db->query($query);

                sleep(1);
                return $this->return_result(null, true);
            }
        } catch(Exception $e) {
            return $this->return_result($e->getMessage(), false);
            
        }
    }

    private function _get_table_info($table_name) {
        try {
            $db = $this->db_open();

            $result = $db->query("SELECT 
            `COLUMN_NAME`,
            `DATA_TYPE`
            FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_NAME` = '?s' AND `TABLE_SCHEMA` = DATABASE();",
            $table_name);

            return $result->fetch_assoc_array();

        } catch (Exception $e) {
            return false;
        }
    }

    private function _bild_filters($filters, $table_fields, &$params) {
        
        $where_clause = '';
        if (is_array($filters)) {
            foreach ($filters as $filter) {
                $key = array_search($filter->field, array_column($table_fields, 'COLUMN_NAME'));
                if ($key === false) throw new Exception('bad field name');
                $operator = "=";
                $filler = "?s";
                if ($filter->value === null) {
                    $operator = "IS";
                    $filler = "NULL";
                }
                else if (is_array($filter->value)) {
                    $operator = "IN";
                    $filler = (strtoupper($table_fields[$key]['DATA_TYPE']) == 'INT' ? "(?ai)" : "(?as)");
                }
                else {
                    $filler = (strtoupper($table_fields[$key]['DATA_TYPE']) == 'INT' ? "?i" : "'?s'");
                }
                
                $where_clause .= (strlen($where_clause) ? ' AND ' : '') . " `" . $filter->field . "` " . $operator . " " . $filler;
                $params[] = $filter->value;
            }
        }
        return $where_clause;
    }

    private function _get_registration_requests($filters) {
        try {
            $db = $this->db_open();

            $table_fields = $this->_get_table_info('registration_requests');
            if ($table_fields === false) throw new Exception('can not fetch data schema');

            $params = [];
            $query = "SELECT * FROM `registration_requests` ";
            $where_clause = $this->_bild_filters($filters, $table_fields, $params);
            //print_r($where_clause);
            $result = $db->queryArguments($query . (strlen($where_clause) ? ' WHERE ' . $where_clause : ''), $params);
            //print_r($db->getQueryString()); echo("\r");
            return $result->fetch_assoc_array();

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function _approve_registration_request($filters, $admin_id) {
        try {

            // выбрать заявки по фильтрам
            $requests = $this->_get_registration_requests($filters);
            // создать пользователей

            // заапрувить заявки
            if (!strlen($where_clause)) throw new Exception('no filters given');

            $result = $db->queryArguments($query . ' WHERE ' . $where_clause, $params);
            if (!$result) throw new Exception('result of query is false');

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function _reject_registration_request($filters, $admin_id) {
        try {
            $db = $this->db_open();

            $table_fields = $this->_get_table_info('registration_requests');
            if ($table_fields === false) throw new Exception('can not fetch data schema');

            $params = [];
            $params[] = $admin_id;
            $query = "UPDATE `registration_requests` SET `is_approved` = 0, `processed_by` = ?i ";
            $where_clause = $this->_bild_filters($filters, $table_fields, $params);

            if (!strlen($where_clause)) throw new Exception('no filters given');

            $result = $db->queryArguments($query . ' WHERE ' . $where_clause, $params);
            if (!$result) throw new Exception('result of query is false');

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function _check_user_privileges($user_id, $requested_privilege) {
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
            $user_id);
            //print_r($db->getQueryString()); echo("\n");

            return ($result->getNumRows() != 0 );

        } catch (Exception $e) {
            //print_r($e->getMessage());
            return false;
            
        }
    }

    private function _check_fields(&$data, $fields, $int_fields, $set_zero = true) {
        if (is_array($data)) {
            foreach ($data as $dataItem) {
                $this->_check_fields($dataItem, $fields, $int_fields, $set_zero);
            }

        } else if (is_object($data)) {
            foreach ($fields as $field) {
                if (!property_exists($data, $field)) throw new Exception("field '$field' not exists");
                if (in_array($field, $int_fields) && !is_numeric($data->{$field})) {
                    if ($set_zero) $data->{$field} = 0;
                    else throw new Exception("not int value in field '$field'");
                }
            }

        } else {
            throw new Exception("data must been array or object");
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

    private function return_result($data, $result = true) {
        $ret_data = [
            'result' => $result,
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
}