<?php
use Krugozor\Database\Mysql\Mysql;
class DataBase {

    private static $clear_allowed_tables = ['clients', 'meters', 'indications'];

    private static $_db = null;

    // /regrequests

    public static function regrequests_get($vk_user_id) {
        try {
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
                AND `del_in_app` = 0;", 
            $vk_user_id);

            if ($result->getNumRows() != 0) {
                $data = $result->fetch_assoc_array();
            }
            return $data;

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    public static function regrequests_add($vk_user_id, $reg_data) {
        try {
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

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    public static function regrequests_delete($vk_user_id, $regrequest_id) {
        try {
            $db = self::db_open();

            $result = $db->query(
                "UPDATE `registration_requests` SET `del_in_app` = 1, `hide_in_app` = 1 WHERE `registration_requests`.`id` = ?i AND `registration_requests`.`vk_user_id` = ?i", 
                $regrequest_id,
                $$vk_user_id);

            return null;

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    public static function regrequests_hide($vk_user_id, $regrequest_id) {
        try {
            $db = self::db_open();

            $result = $db->query(
                "UPDATE `registration_requests` SET `hide_in_app` = 1 WHERE `registration_requests`.`id` = ?i AND `registration_requests`.`vk_user_id` = ?i", 
                $regrequest_id,
                $$vk_user_id);

            return null;

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    // /users/privileges
    
    public static function users_privileges_get($vk_user_id) {
        try {
            $db = self::db_open();
            $data = null;
            $result = $db->query(
                "SELECT 
                    `vk_users`.`vk_user_id`,
                    `vk_users`.`is_blocked`,
                    `vk_users`.`privileges`
                FROM `vk_users` 
                WHERE `vk_users`.`vk_user_id` = ?i;", 
                $vk_user_id);

            if ($result->getNumRows() != 0) {
                $data = $result->fetch_assoc_array();
            }
            return $data;

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    // /accounts

    public static function accounts_detail($vk_user_id, $acc_id) {
        try {
            $db = self::db_open();
            $data = null;

            $result = $db->query("SELECT 
                `vk_users`.`vk_user_id`,
                `clients`.`acc_id`, 
                `clients`.`acc_id_repr`, 
                `clients`.`tenant_repr`, 
                `clients`.`address_repr`
                FROM `vk_users` 
                LEFT JOIN `accounts` ON `accounts`.`vk_user_id` = `vk_users`.`vk_user_id`
                LEFT JOIN `clients` ON `clients`.`acc_id` = `accounts`.`acc_id`
                WHERE `vk_users`.`vk_user_id` = ?i AND `clients`.`acc_id` = ?i",
            $vk_user_id, 
            $acc_id);

            if ($result->getNumRows() != 0) {
                $data = $result->fetch_assoc_array()[0];
            }
            return $data;

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    public static function accounts_list($vk_user_id, $filters = null) {
        try {
            $db = self::db_open();
            $data = null;
            $result = $db->query(
                "SELECT 
                    `vk_users`.`vk_user_id`,
                    `clients`.`acc_id`, 
                    `clients`.`acc_id_repr`, 
                    `clients`.`tenant_repr`, 
                    `clients`.`address_repr`
                FROM `vk_users` 
                LEFT JOIN `accounts` ON `accounts`.`vk_user_id` = `vk_users`.`vk_user_id`
                LEFT JOIN `clients` ON `clients`.`acc_id` = `accounts`.`acc_id`
                WHERE `vk_users`.`vk_user_id` = ?i AND `clients`.`acc_id` IS NOT NULL;", 
            $vk_user_id);

            if ($result->getNumRows() != 0) {
                $data = $result->fetch_assoc_array();
            }
            return $data;

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    // /meters

    public static function meters_get($vk_user_id, $acc_id) {
        try {
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

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    // /meters/indications

    public static function meters_indications_add($vk_user_id, $meters) {
        try {
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

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    // /admin/regrequests

    public static function admin_regrequests_list($vk_user_id) {
        try {
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

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    public static function admin_regrequests_detail($vk_user_id) {
        try {
            $db = self::db_open();

            $select_clause = "*, 
                DATE_FORMAT(`request_date`, '%d.%m.%Y') AS 'request_date', 
                DATE_FORMAT(`update_date`, '%d.%m.%Y') AS 'update_date'";

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }
    
    public static function admin_regrequests_approve($request, $account_id, $registrator) {
        try {
            $db = self::db_open();
            $db->getMysqli()->begin_transaction(); //(MYSQLI_TRANS_START_READ_WRITE);

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }

        try {
            // создать пользователей
            self::_create_user($db, $request['vk_user_id'], 'USER', $registrator);    
            // привязать лс
            self::_link_account($db, $request['vk_user_id'], $account_id);
            // заапрувить заявки
            $result = $db->query("UPDATE `registration_requests`
                SET `is_approved` = 1, 
                `linked_acc_id` = ?i,
                `processed_by` = ?i
                WHERE `id` = ?i;",
                $account_id,
                $registrator,
                $request['id']);

            $db->getMysqli()->commit();

        } catch (Exception $e) {
            $db->getMysqli()->rollback();
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    public static function admin_regrequests_reject($request, $registrator, $rejection_reason) {
        try {
            $db = self::db_open();

            $result = $db->query("UPDATE `registration_requests`
                SET `is_approved` = 0, 
                `rejection_reason` = '?s',
                `processed_by` = ?i
                WHERE `id` = ?i;",
                $rejection_reason,
                $registrator,
                $request['id']);

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    // /admin/acc_data

    public static function admin_accdata_set($data) {

        try {
            $db = self::db_open();
            $db->getMysqli()->begin_transaction(); //(MYSQLI_TRANS_START_READ_WRITE);
            
        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }

        try {
            // clear tables
            self::_clear_table('indications');
            self::_clear_table('meters');
            self::_clear_table('clients');

            // prepare clients query
            $clients_values_clause = "";
            $meters_values_clause = "";
            $clients_lines = 0;
            $meters_lines = 0;
            foreach ($data as $account) {
                $clients_values_clause .= ($clients_lines ? ", " : "") . $db->prepare("(?i, ?i, '?s', '?s', '?s')",
                (int)$account->acc_id,
                (int)$account->secret_code,
                $account->acc_id_repr,
                $account->tenant_repr,
                $account->address_repr);

                $clients_lines ++;

                // prepare meters query
                foreach ($account->meters as $meter) {
                    $meters_values_clause .= ($meters_lines ? ", " : "") . $db->prepare("(?i, ?i, '?s', ?i)",
                    (int)$meter->code,
                    (int)$account->acc_id,
                    $meter->title,
                    $meter->current_count);

                    $meters_lines ++;
                }
            }
            // execute clients insert query
            $result = true;
            if ($clients_lines) {
                $query = "INSERT INTO `clients` (`acc_id`, `secret_code`, `acc_id_repr`, `tenant_repr`, `address_repr`) VALUES ";
                $result = $db->query($query . $clients_values_clause);
                if ($result === false) throw new Exception("result of query '".$query."' is false");

                // execute meters insert query
                $result = true;
                if ($meters_lines) {
                    $query = "INSERT INTO `meters` (`index_num`, `acc_id`, `title`, `current_count`) VALUES ";
                    $result = $db->query($query . $meters_values_clause);
                    if ($result === false) throw new Exception("result of query '".$query."' is false");
                }
            }

            // коммит транзакции 
            $db->getMysqli()->commit();

        } catch (Exception $e) {
            $db->getMysqli()->rollback();
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    // /admin/indications

    public static function admin_indications_get($period_begin = null, $period_end = null) {
        try {
            if (!is_object($data_params)) throw new Exception("data_params is not object");
            $where_clause = "";
            $params = [];
            if (is_string($period_begin) && strlen($period_begin)) {
                $where_clause .= (strlen($where_clause) ? ' AND ' : '') . "`recieve_date` >= '?s'";
                $params[] = $period_begin;
            }
            if (is_string($period_end) && strlen($period_end)) {
                $where_clause .= (strlen($where_clause) ? ' AND ' : '') . "`recieve_date` <= '?s'";
                $params[] = $period_end;
            }

            $db = $self::db_open();
            $query = "
                SELECT  
                    `indications`.`id` AS 'indication_id',
                    `indications`.`count` AS 'count',
                    `indications`.`recieve_date` AS 'recieve_date',
                    `indications`.`vk_user_id` AS 'vk_user_id',
                    `meters`.`index_num` AS 'meter_code',
                    `meters`.`title` AS 'meter_title',
                    `meters`.`current_count` AS 'current_count',
                    `clients`.`acc_id`
                FROM `indications`
                    LEFT JOIN `meters` ON `indications`.`meter_id` = `meters`.`id`
                    LEFT JOIN `clients` ON `meters`.`acc_id` = `clients`.`acc_id`";
            if (strlen($where_clause)) {
                $query .= " WHERE " . $where_clause;
            }
            $result = $db->queryArguments($query, $params);
            if ($result === false) throw new Exception('result of query is false');
            return $result->fetch_assoc_array();

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    // support functions 

    public static function get_account_by_secret_code($secret_code, $acc_id_repr) {
        try {
            $db = self::db_open();

            $result = $db->query("
            SELECT 
                `acc_id`, 
                `secret_code` 
            FROM `clients` 
            WHERE `secret_code` = ?i AND `acc_id_repr` LIKE '%?S%'",
            $secret_code,
            $acc_id_repr);

            $data = $result->fetch_assoc_array();
            return $data;

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    public static function is_regrequest_exists($vk_user_id, $acc_id) {
        try {
            $db = self::db_open();

            $result = $db->query(
            "SELECT
                `id`
            FROM `registration_requests` 
            WHERE `vk_user_id` = ?i 
                AND `acc_id` LIKE '%?S%'
                AND `is_approved` IS NULL
                AND `del_in_app` = 0;", 
            $vk_user_id,
            $acc_id);

            if ($result->getNumRows() != 0) {
                return true;
            }
            return false;

        } catch (Exception $e) {
            throw new Exception(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

    // filters build functions

    private static function _is_values_array_contents_null($values) {
        foreach ($values as $value) {
            if ( is_null($value) || (is_string($value) && strtoupper($value) === "NULL") ) return true;
        }
        return false;
    }

    private static function _build_date_condition($field, $value, $data_type, &$params) {
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

    private static function _build_condition($field, $value, $data_type, &$params) {
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
                    $condition .= (strlen($condition) ? " OR " : "") . self::_build_condition($field, $value_item, $data_type, $params);
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

    private static function _bild_filters($filters, $table_fields, &$params) {
        
        $where_clause = '';
        if (is_array($filters)) {
            foreach ($filters as $filter) {
                $key = array_search($filter->field, array_column($table_fields, 'COLUMN_NAME'));
                if ($key === false) throw new Exception("bad table field name '$filter->field'");
                $data_type = strtoupper($table_fields[$key]['DATA_TYPE']);

                $where_clause .= (strlen($where_clause) ? " AND " : "")
                    . ($data_type == "TIMESTAMP" 
                    ? self::_build_date_condition($filter->field, $filter->value, $data_type, $params)
                    : self::_build_condition($filter->field, $filter->value, $data_type, $params));
            }
        }
        return $where_clause;
    }


    // open db connection

    private static function db_open() {
        global $_Config;
        if (self::$_db !== null) return self::$_db;
        $db = Mysql::create(
            $_Config['db_host'],
            $_Config['db_user'],
            $_Config['db_pass'],
            ($_Config['db_port'] ? $_Config['db_port'] : null)
        )
        ->setDatabaseName($_Config['db_name'])
        ->setCharset($_Config['db_charset']);
        self::$_db = $db;

        return $db;
    }

    // support functions (need opened db object)

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

    private static function _is_link_exists($db, $vk_user_id, $account_id) {
        try {
            $result = $db->query("
                SELECT `id` 
                FROM `accounts` 
                WHERE `vk_user_id` = ?i AND `acc_id` = ?i;",
            $vk_user_id,
            $account_id);
            if ($result === false) throw new Exception('bad db query');
            return ($result->getNumRows() != 0);

        } catch (Exception $e) {
            throw new Exception('can not check acc-user link existence: '.$e->getMessage(), 0, $e);
        }
    }
    
    private static function _link_account($db, $vk_user_id, $account_id) {
        try {
            if (self::_is_user_exists($db, $vk_user_id) && !self::_is_link_exists($db, $vk_user_id, $account_id)) {
                $result = $db->query("
                    INSERT INTO `accounts` 
                    (`vk_user_id`, 
                    `acc_id`) 
                    VALUES (?i, ?i);",
                $vk_user_id,
                $account_id);
                if (!$result) throw new Exception('result of query is false');
            }

        } catch (Exception $e) {
            throw new Exception('can not link account to user: '.$e->getMessage(), 0, $e);
        }
    }

    private static function _get_table_info($db, $table_name) {
        try {
            $result = $db->query("SELECT 
                `COLUMN_NAME`,
                `DATA_TYPE`,
                `IS_NULLABLE`,
                `COLUMN_TYPE`
                FROM `information_schema`.`COLUMNS`
                WHERE `TABLE_NAME` = '?s' AND `TABLE_SCHEMA` = DATABASE();",
            $table_name);

            $data = $result->fetch_assoc_array();
            if (!count($data)) throw new Exception('can not retrieve data schema: bad table name', 0, $e);

            return $data;

        } catch (Exception $e) {
            throw new Exception('can not retrieve data schema: '.$e->getMessage(), 0, $e);
        }
    }


}