<?php

    function _is_assoc($array) {
        if (!is_array($array)) return false;
        foreach (array_keys($array) as $k => $v) {
            // подразумевается что ассоциативный массив не должен содержать в первом элементе ноль в качестве ключа
            return ($k !== $v);
        }
    }
    
    function _str_to_date($date_str, $set_time = null) {
        try {
            $dt = date_create($date_str);
            if (is_array($set_time)) date_time_set($dt, $set_time['hour'], $set_time['minute'], $set_time['second']);
            $result = date_format($dt, "Y-m-d H:i:s");
            return $result;

        } catch (Exception $e) {
            return null;
        }
    }

?>