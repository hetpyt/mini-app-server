<?php
use Krugozor\Database\Mysql\Mysql;
class DataBase {

    private static $clear_allowed_tables = ['clients', 'meters', 'indications'];

    public static function execute_query() {

        if (!func_num_args()) {
            return false;
        }
        $args = func_get_args();
        $method = array_shift($args);

        if (!method_exists(static::class, $method)) {
            return false;
        }

        try {
            $db = self::db_open();

        }  catch (Exception $e) {
            throw new Exception('DB ERROR: Can not create DB connection: ' . $e->getMessage(), 0, $e);

        }

        $result = self::$method();

    }

    public static function db_open($db_config) {
        $db = Mysql::create(
            $db_config['db_host'],
            $db_config['db_user'],
            $db_config['db_pass'],
            ($db_config['db_port'] ? $db_config['db_port'] : null)
        )
        ->setDatabaseName($db_config['db_name'])
        ->setCharset($db_config['db_charset']);

        return $db;
    }

    // /regrequest
    public static function regrequest_status($vk_user_id) {
        $db = self::db_open();
        $data = null;

        $result = $db->query(
        "SELECT
            `id`,
            `request_date`,
            `is_approved`,
            `rejection_reason`,
            `acc_id`
        FROM `registration_requests` 
        WHERE `vk_user_id` = ?i 
            AND `hide_in_app` = 0 
            AND 'del_in_app' = 0;", 
        $vk_user_id);

        if ($result->getNumRows() != 0) {
            $data = $result->fetch_assoc_array();
        }
        return $data;
    }

    public static function regrequest_add($vk_user_id, $reg_data) {
        $db = self::db_open();
        
        $db->query("INSERT INTO `registration_requests` (`vk_user_id`, `acc_id`, `surname`, `first_name`, `patronymic`, `street`, `n_dom`, `n_kv`)
            VALUES (?i, '?s', '?s', '?s', '?s', '?s', '?s', '?s');", 
            $vk_user_id,
            $reg_data->acc_id,
            $reg_data->surname,
            $reg_data->first_name,
            $reg_data->patronymic,
            $reg_data->street,
            $reg_data->n_dom,
            (string)$reg_data->n_kv
        );

        return null;
    }

    public static function regrequest_delete($vk_user_id, $regrequest_id) {
        $db = self::db_open();

        $result = $db->query(
            "UPDATE `registration_requests` SET `del_in_app` = 1, `hide_in_app` = 1 WHERE `registration_requests`.`id` = ?i AND `registration_requests`.`vk_user_id` = ?i", 
            $regrequest_id,
            $$vk_user_id);

        return null;
    }

    public static function regrequest_hide($vk_user_id, $regrequest_id) {
        $db = self::db_open();

        $result = $db->query(
            "UPDATE `registration_requests` SET `hide_in_app` = 1 WHERE `registration_requests`.`id` = ?i AND `registration_requests`.`vk_user_id` = ?i", 
            $regrequest_id,
            $$vk_user_id);

        return null;
    }

    // /meters
    public static function meters_get($vk_user_id, $acc_id) {
        $db = self::db_open();
        $data = null;
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
        return $data;  
    }

    // /meters/indications
    public static function meters_indications_add($vk_user_id, $meters) {
        $db = self::db_open();
        $rows_inserted = 0;

        $query = "INSERT 
            INTO `indications` (`meter_id`, `count`, `vk_user_id`) 
            VALUES ";
        foreach($meters as $meter) {
            $query .= ($rows_inserted ? ',' : '').(is_numeric($meter->new_count) 
                ? $db->prepare("(?i, ?i, ?i)", $meter->meter_id, $meter->new_count, $vk_user_id) 
                : $db->prepare("(?i, NULL, ?i)", $meter->meter_id, $vk_user_id));
            $rows_inserted++;
        }
        //throw new Exception($query);
        if ($rows_inserted) $result = $db->query($query);
        return null;
    }

    // /admin/regrequests
    public static function admin_regrequests_list($vk_user_id) {
        $db = self::db_open();

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
    }

    public static function admin_regrequests_detail($vk_user_id) {
        $db = self::db_open();

        $select_clause = "*, 
            DATE_FORMAT(`request_date`, '%d.%m.%Y') AS 'request_date', 
            DATE_FORMAT(`update_date`, '%d.%m.%Y') AS 'update_date'";
            
}
    
    private static function _clear_table($db, $table_name) {
        try {
            if (!in_array($table_name, self::$clear_allowed_tables)) throw new Exception("not allowed table '$table_name'");
            $result = $db->query("DELETE FROM `".$table_name."`;");
            if (!$result) throw new Exception('result of query is false');

        } catch (Exception $e) {
            throw new Exception("can not clear table '$table_name': ".$e->getMessage(), 0, $e);
        }
    }

    private static function _is_user_exists($db, $vk_user_id) {
        try {
            $result = $db->query(
                "SELECT `vk_user_id` 
                FROM `vk_users` 
                WHERE `vk_user_id` = ?i;",
            $vk_user_id);
            if ($result === false) throw new Exception('bad db query');
            return ($result->getNumRows() != 0);

        } catch (Exception $e) {
            throw new Exception('can not check user existence: '.$e->getMessage(), 0, $e);
        }
    }
    
    private static function _create_user($db, $vk_user_id, $priveleges, $registrator) {
        try {
            if (!self::_is_user_exists($vk_user_id)) {
                $result = $db->query(
                    "INSERT INTO `vk_users` 
                    (`vk_user_id`, 
                    `privileges`, 
                    `registered_by`) 
                    VALUES (?i, '?s', ?i);",
                $vk_user_id,
                $priveleges,
                $registrator);
                if (!$result) throw new Exception('result of query is false');
            }

        } catch (Exception $e) {
            throw new Exception('can not create user: '.$e->getMessage(), 0, $e);
        }
    }
}