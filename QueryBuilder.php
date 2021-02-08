<?php

class QueryBuilder {
    private static $_query = '';
    private static $condition = '';

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
            } elseif (self::_is_values_array_contents_null($value)) {
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

}

?>