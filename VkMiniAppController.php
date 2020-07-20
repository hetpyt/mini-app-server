<?php
//require './config/config.php';
use Jacwright\RestServer\RestException;
use Krugozor\Database\Mysql\Mysql;

class VkMiniAppController {

    /**
    * @url GET /
    */
    public function test() {
        throw new RestException(404, 'Service unavaulable!');
    }

    /**
    * @noAuth
    * @url GET /registrationstatus/$id
    */
    public function registration_status($id) {
        try {
            $db = $this->db_open();

            $result = $db->query(
            "SELECT
                `id`,
                `request_date`,
                `is_approved`,
                `acc_id`
            FROM `registration_requests` 
            WHERE `vk_user_id` = ?i AND `hide_in_app` = 0 AND 'del_in_app' = 0;", 
            $id);

            $data = null;

            if ($result->getNumRows() != 0) {
                $data = $result->fetch_assoc_array();
                //$data = [$data];
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
            }
        } catch (Exception $e) {
            return $this->return_result($e->getMessage(), false);
        }

        sleep(1);
        return $this->return_result(null, true);
    }

    /**
    * @noAuth
    * @url POST /registrationrequest
    */
    public function registration_request($data) {
        $check_fields = ['vk_user_id', 'acc_id', 'surname', 'first_name', 'patronymic', 'street', 'n_dom', 'n_kv'];
        $check_int_fields = ['vk_user_id', 'n_kv'];
        try {
            if (!property_exists($data, 'result')) throw new Exception('bad request syntax');
            if (!$data->result) throw new Exception('result not true');
            // данные по показаниям
            if (!property_exists($data, 'registration_data')) throw new Exception('no registration data exists');
            $reg_data = $data->registration_data;

            foreach ($check_fields as $field) {
                if (!property_exists($reg_data, $field)) throw new Exception('bad registration data');
                if (in_array($field, $check_int_fields) && !is_numeric($reg_data->{$field})) $reg_data->{$field} = 0;
            }
            if (0 == $reg_data->vk_user_id) throw new Exception('bad vk user');


            $rows_inserted = 0;

            $db = $this->db_open();
            
            $db->query("INSERT INTO `registration_requests` (`vk_user_id`, `acc_id`, `surname`, `first_name`, `patronymic`, `street`, `n_dom`, `n_kv`)
                VALUES (?i, '?s', '?s', '?s', '?s', '?s', '?s', ?i);", 
                $reg_data->vk_user_id,
                $reg_data->acc_id,
                $reg_data->surname,
                $reg_data->first_name,
                $reg_data->patronymic,
                $reg_data->street,
                $reg_data->n_dom,
                $reg_data->n_kv
            );

        } catch(Exception $e) {
            return $this->return_result($e->getMessage(), false);
        }

        return $this->return_result(null, true);
    }
    /**
    * @noAuth
    * @url GET /getuser/$id
    */
    public function getuser($id) {
        $client_columns = ['acc_id', 'secret_code', 'acc_id_repr', 'tenant_repr', 'address_repr'];
        try {
            $db = $this->db_open();

            // secret_code должне быть уникальным
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

            $data = null;

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
            } else {
                // проверим нет ли заявки на регистрацию

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
            $db = $this->db_open();

            // secret_code должне быть уникальным
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

            $data = null;
            if ($result->getNumRows() != 0) {
                $meters = $result->fetch_assoc_array();
                $data = ['acc_id' => $acc_id];
                $data['meters'] = $meters;
                $data = [$data];
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
            }
        } catch(Exception $e) {
            return $this->return_result($e->getMessage(), false);
        }
        sleep(1);
        return $this->return_result(null, true);
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