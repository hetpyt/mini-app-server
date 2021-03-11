<?php

    function _is_assoc($array) {
        if (!is_array($array)) return false;
        foreach (array_keys($array) as $k => $v) {
            // подразумевается что ассоциативный массив не должен содержать в первом элементе ноль в качестве ключа
            return ($k !== $v);
        }
    }
    
?>