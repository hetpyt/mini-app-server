<?php

class AbstractController
{
    protected $_logger = null;
    // ид пользователя, полученный через строку запроса
    protected $_user_id = null;
    protected $_user_priv = null;

    // SPECIAL METHODS

    public function init() {
        global $APP_CONFIG;
        if (array_key_exists("enable_logger", $APP_CONFIG) && $APP_CONFIG['enable_logger']) {
            $this->_logger = new Logger('vkappsrvr', 'log');
        }
    }

    public function authorize() {
        global $APP_CONFIG;
        $status = false;
        $user_id = '';
        $token = '';

        // получим токен
        if (array_key_exists('token', $_GET)) {
            $token = $_GET['token'];
        }
        // получим пользователя
        if (array_key_exists('user_id', $_GET)) {
            $user_id = $_GET['user_id'];  
        }
        if ($user_id && $token) {
            $expected_token = $this->_get_auth_token($APP_CONFIG, $user_id);
            if ($status = $expected_token === $token) {
                $this->_user_id = $user_id;
                // привилегии пользователя
                try {
                    $db_data = DataBase::users_privileges_get($this->_user_id);
                } catch (InternalException $e) {
                    return false; //$status;
                }
                if ($db_data) $this->_user_priv = $db_data['privileges'];
                else $this->_user_priv = '';
            }
        }
        return $status;
    }

    // PROTECTED SECTION

    protected function _log($text) {
        if ($this->_logger) $this->_logger->log($text);
    }

    protected function _return_app_error() {
        $args = func_get_args();

        $code = array_shift($args);
        if ($code == null) $code = -999;
        $message = "";

        if (array_key_exists($code, APPERR_MESSAGES_RU)) {
            $message = APPERR_MESSAGES_RU[$code];
        }
        $index = 0;
        foreach ($args as $arg) {
            $message = str_replace('{'.$index.'}', $arg, $message);
            $index ++;
        }
        return [
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ];
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
        throw new RestException($rest_code, null);
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

    protected function _get_auth_token(&$config, $vk_user_id) {
        return $this->_hash(
            "" . $vk_user_id . '_' . $config['vk_app_id'] . '_' . $config['server_key'] . '_' . date('Ymd'),
            $config['client_secret']
        );
    }

    protected function _hash($data, $key) {
        $result = rtrim(
            strtr(
                base64_encode(
                    hash_hmac('sha256', $data, $key, true)
                ),
            '+/', '-_'), 
        '='); 
        return $result;
    }
}
?>