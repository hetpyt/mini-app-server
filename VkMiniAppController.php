<?php
//require './config/config.php';
use Jacwright\RestServer\RestException;
use Krugozor\Database\Mysql\Mysql;

class VkMiniAppController {

    /**
    * @url GET /
    */
    public function test()
    {
        //global $_Config;
        //return $_Config;
    }

    /**
    * @noAuth
    * @url GET /getclient/$code/$passphrase
    */
    public function getclient($code, $passphrase)
    {
        $meter_columns = ['meter_id', 'title', 'current_count', 'updated', 'new_count', 'recieve_date', 'vk_user_id'];
        global $_Config;

        try {
            $db = VkMiniAppController::db_open($_Config);

            // secret_code должне быть уникальным
            $result = $db->query(
            "SELECT
                `clients`.`id` as 'client_id',
                `clients`.`nomer_ls`,
                `clients`.`familiya`,
                `clients`.`imya`,
                `clients`.`otchestvo`,
                `meters`.`id` as 'meter_id',
                `meters`.`title`,
                `meters`.`current_count`,
                `meters`.`updated`, 
                `lastIndications`.`count` as 'new_count',
                `lastIndications`.`recieve_date`,
                `lastIndications`.`vk_user_id`
            FROM `clients`
            LEFT JOIN `meters` 
            ON `meters`.`client_id` = `clients`.`id` 
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
            WHERE `clients`.`secret_code` = '?s';", $code);

            $ret_data = ['data' => null];
            if ($result->getNumRows() == 0) {
                // нет совпадений
                $ret_data['result'] = false;
            } else {
                $ret_data['result'] = true;
                $ret_data['data'] = ['meters' => []];
                while ($row = $result->fetch_assoc()) {
                    $meter = [];
                    foreach($row as $field => $value) {
                        if (in_array($field, $meter_columns, true)) {
                            $meter[$field] = $value;
                        } else {
                            $ret_data['data'][$field] = $value;
                        }
                    }
                    array_push($ret_data['data']['meters'], $meter);
                }    
            }
            //$result->free();
            //sleep(3);
            return $ret_data;

        } catch (Exception $e) {
            //print_r($e);
            throw new RestException(404, 'Service unavaulable!');
        }
    } 

    /**
    * @noAuth
    * @url POST /setmeters/$code/$passphrase
    */
    public function setmeters($code, $passphrase, $data)
    {
        global $_Config;
        $meters = null;
        $ret_result = ['result' => false];

        // проверки
        try {
            if (!$data->result) {
                throw Exception('result not true');
            }
            // данные по показаниям
            $meters = $data->meters;

        } catch (Exception $e) {
            $ret_result['message'] = $e->getMessage();
            return $ret_result;
        }

        try {

            $db = VkMiniAppController::db_open($_Config);

            $query = '';
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

                $result = $db->query("
                    INSERT 
                    INTO `indications` (`meter_id`, `count`, `vk_user_id`, `vk_user_familiya`, `vk_user_imya`) 
                    VALUES (?i, ?i, ?i, '?s', '?s');\n", 
                    $meter->meter_id,
                    $meter->new_count, 
                    $meter->vk_user_id, 
                    $meter->vk_user_familiya, 
                    $meter->vk_user_imya, 
                );

            }
        } catch(Exception $e) {
            $ret_result['message'] = $e->getMessage();
            return $ret_result;
        }

        $ret_result['result'] = true;
        return $ret_result;
}

    private function db_open($config) {
        $db = Mysql::create(
            $config['db_host'],
            $config['db_user'],
            $config['db_pass'],
            ($config['db_port'] ? $config['db_port'] : null)
        )
        ->setDatabaseName($config['db_name'])
        ->setCharset($config['db_charset']);

        return $db;
    }
}