<?php

require_once './AppError.php';
require_once './APIException.php';
require_once './DataBase.php';

class AbstractController
{
    // STATIC MEMBERS
    protected const _API_VERSION = '0.2';

    protected $query = [];
    protected $data = null;
    // SPECIAL METHODS

    public function __construct($query, $postData) {
        $this->query = $query;
        $this->data = $postData;
        //echo print_r($this);
    }

    public function init() {
    }

    // PROTECTED SECTION

    protected function _log($text) {
    }


    protected function _handle_exception($rest_code = null, $exception = null) {
        if ($rest_code === null) $rest_code = 500;
        $message = "UNKNOWN EXCEPTION";
        if ($exception !== null) {
            if (is_object($exception) && method_exists($exception, 'getMessage')) {
                $message = $exception->getMessage();
            } else {
                $message = (string)$exception;
            }
        }
        $this->_log("REST EXCEPTION $rest_code: $message");
        throw new APIException(null, $rest_code);
    }

    protected function _has_user_privs() {
        if (!$this->_user_priv) {
            return false;
        }
        return "USER" == $this->_user_priv || $this->_has_operator_privs();
    }

    protected function _has_operator_privs() {
        if (!$this->_user_priv) {
            return false;
        }
        return "OPERATOR" == $this->_user_priv || $this->_has_admin_privs();
    }

    protected function _has_admin_privs() {
        if (!$this->_user_priv) {
            return false;
        }
        return "ADMIN" == $this->_user_priv;
    }

    protected function _check_filters(&$filters) {
        if (!is_array($filters)) {
            throw new InternalException("attribute 'filters' should been of array type");
        }
        if (count($filters)) {
            $check_fields = ['field', 'value'];
            $check_int_fields = [];

            try {
                $this->_check_fields($filters, $check_fields, $check_int_fields, false);
            } catch (InternalException $e) {
                throw new InternalException("bad filters item: " . $e->getMessage());
            }
        }
    }

    protected function _check_order(&$order) {
    }

    protected function _check_limits(&$limits) {
        if (is_object($limits)) {
            // передан ассоциативный массив с номером страницы и количеством на странице
            $check_fields = ['page_num', 'page_len'];
            $check_int_fields = ['page_num', 'page_len'];

            try {
                $this->_check_fields($limits, $check_fields, $check_int_fields, false);
            } catch (InternalException $e) {
                throw new InternalException("bad limits : " . $e->getMessage());
            }
            // преобразуем к массиву индекс-количество
            $offset = ($limits->page_num -1) * $limits->page_len;
            $page_len = $limits->page_len;
            $limits = [];
            array_push($limits, $offset, $page_len);

        } elseif (is_array($limits)) {
            // передан индексный массив - должен состоять из двух элементов
            // 1 - начальный индекс
            // 2 - количество
            if (count($limits) != 2) {
                throw new InternalException("attribute 'limits' should been array of 2 integers");
            }
        } else {
            throw new InternalException("attribute 'limits' should been of array or object type");
        }
    }
    
    protected function _check_fields(&$data, $fields, $int_fields, $set_zero = true, $context = null) {
        if (is_array($data) && !_is_assoc($data)) {
            foreach ($data as $dataItem) {
                $this->_check_fields($dataItem, $fields, $int_fields, $set_zero);
            }

        } else if (is_object($data) || _is_assoc($data)) {
            $is_obj = is_object($data);
            foreach ($fields as $field) {
                if ($is_obj) {
                    if (!property_exists($data, $field)) 
                        throw new InternalException(($context ? "$context. " : "") . "field '$field' not exists");
                    if (in_array($field, $int_fields) && !is_numeric($data->{$field})) {
                        if ($set_zero) $data->{$field} = 0;
                        else throw new InternalException(($context ? "$context. " : "") . "not int value in field '$field'");
                    }

                } else {

                    if (!array_key_exists($field, $data)) 
                        throw new InternalException(($context ? "$context. " : "") . "field '$field' not exists");
                    if (in_array($field, $int_fields) && !is_numeric($data[$field])) {
                        if ($set_zero) $data[$field] = 0;
                        else throw new InternalException(($context ? "$context. " : "") . "not int value in field '$field'");
                    }
                }
            }

        } else {
            throw new InternalException(($context ? "$context. " : "") . "data must been array or object");
        }
    }

}
?>