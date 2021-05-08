<?php

class QueryBuilder {
    private $tables;
    private $fields;

    public function __construct($tables = null, $fields = null) {
        $this->tables = [];
        $this->fields = [];
    }

    public function setFields($fields) {
        $this->fields = $fields;
    }

    public function setFilters($filters) {

    }


    public static function simple_list($fields, $table_name, $filters = null, $order = null, $limits = null) {
        try {
            $db = self::db_open();
            $tables_info = self::_get_tables_info($db, $table_name);

            $select_clause = "";
            foreach($fields as $field) {
                self::_parse_field($field, $tables_info);
                $select_clause .= ($select_clause ? ",`" : "`") . $field . "`";
            }

            $from_clause = "FROM `$table_name`";
            $where_clause = "";
            $order_clause = "";
            $limit_clause = "";


            $params = [];
            if ($filters) {
                $where_clause = self::_build_filters($filters, $tables_info, $params);
            }
            if ($order) {
                $order_clause = self::_build_order($order, $tables_info);
            }
            if ($limits) {
                $limit_clause = self::_build_limits($limits, $params);
            }
            $params2 = $params;
            $result = $db->queryArguments(
                "SELECT " . 
                $select_clause . " " . 
                $from_clause . " " . 
                ($where_clause ? "WHERE $where_clause" : "") . " " .
                $order_clause . " " .
                $limit_clause,
                $params);

            if ($result === false) throw new Exception('result of query is false');

            $data = $result->fetch_assoc_array();
            $total_count = self::_get_rows_count($db, $table_name, $where_clause, $params2);
            return [
                "data" => $data,
                "total_count" => $total_count
            ];
            
        } catch (Exception $e) {
            throw new InternalException(__METHOD__.': '.$e->getMessage(), 0, $e);
        }
    }

}

?>