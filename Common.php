<?php

    function _is_assoc($array) {
        if (!is_array($array)) return false;
        foreach (array_keys($array) as $k => $v) {
            if ($k !== $v) return true;
        }
        return false;
    }
    
?>