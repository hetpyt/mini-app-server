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
    * @url POST /registrationrequest
    */
    public function registration_request($data) {
        $check_fields = ['vk_user_id', 'acc_id', 'surname', 'first_name', 'patronymic', 'street', 'n_dom', 'n_kv'];

        try {
            if (!property_exists($data, 'result')) throw new Exception('bad request syntax');
            if (!$data->result) throw new Exception('result not true');
            // данные по показаниям
            if (!property_exists($data, 'registration_data')) throw new Exception('no registration data exists');
            $reg_data = $data->registration_data;

            foreach ($check_fields as $field) {
                if (!property_exists($reg_data, $field)) throw new Exception('bad registration data');
            }

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
            }
            
            return $this->return_result($data);

        } catch (Exception $e) {
            //print_r($e);
            throw new RestException(404, 'Service unavaulable!');
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

            return $this->return_result($data);

        } catch (Exception $e) {
            print_r($e);
            throw new RestException(404, 'Service unavaulable!');
        }
    } 

    /**
    * @noAuth
    * @url POST /setmeters
    */
    public function setmeters($data) {
        $meters = null;
        // проверки
        try {
            if (!property_exists($data, 'result')) throw new Exception('bad request syntax');
            if (!$data->result) throw new Exception('result not true');
            // данные по показаниям
            if (!property_exists($data, 'meters')) throw new Exception('no meters data exists');
            $meters = $data->meters;
            if (!is_array($meters)) throw new Exception('bad meters data');

            $rows_inserted = 0;

            $db = $this->db_open();

            $query = "INSERT 
            INTO `indications` (`meter_id`, `count`, `vk_user_id`) 
            VALUES ";
            foreach($meters as $meter) {
                // жесткие проверки
                if (!is_numeric($meter->vk_user_id)) throw Exception('bad vk user');
                if (!is_numeric($meter->meter_id)) throw Exception('bad meter id');
                // нежесткие проверки
                // пользователь передал пустую строку (считаем что не передал)
                if ("" === $meter->new_count) {
                    continue;
                }
                // проверка показаний
                if (!is_numeric($meter->new_count)) throw Exception('bad new count');
                $query .= ($rows_inserted ? ',' : '').$db->prepare("(?i, ?i, ?i)", $meter->meter_id, $meter->new_count, $meter->vk_user_id);
                $rows_inserted++;
            }
            //throw new Exception($query);
            if ($rows_inserted) $result = $db->query($query);

        } catch(Exception $e) {
            return $this->return_result($e->getMessage(), false);
        }

        return $this->return_result(null, true);
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