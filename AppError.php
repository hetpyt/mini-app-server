<?php
    define('APPERR_REGREQUEST_DUBL', -1);
    define('APPERR_BAD_SECRETCODE', -2);
    define('APPERR_ACCOUNT_NOT_FOUND', -3);
    define('APPERR_ACCOUNT_NOT_OWNED', -4);
    define('APPERR_REGREQUEST_NOT_EXISTS', -5);
    define('APPERR_REGREQUEST_ALREADY_PROCESSED', -6);
    define('APPERR_ACCOUNT_NOT_EXISTS', -7);
    define('APPERR_USER_NOT_AUTHENTICATED', -8);
    define('APPERR_USER_NOT_AUTHORIZED', -9);
    define('APPERR_FORBIDDEN', -403);

   


    define('APPERR_MESSAGES_RU', array(
        APPERR_REGREQUEST_DUBL => "Заявка на присоединение указанного лицевого счета уже существует и еще не обработана.",
        APPERR_BAD_SECRETCODE => "Не укзан либо неверно указан проверочный код.",
        APPERR_ACCOUNT_NOT_FOUND => "Неверно указан номер лицевого счета или проверочный код.",
        APPERR_ACCOUNT_NOT_OWNED => "Указанный лицевой счет не привязан к текущему пользователю ВК.",
        APPERR_REGREQUEST_NOT_EXISTS => "Заявка с номером {0} не существует",
        APPERR_REGREQUEST_ALREADY_PROCESSED => "Заявка с номером {0} уже обработана",
        APPERR_ACCOUNT_NOT_EXISTS => "Лицевой счет с номером {0} отсутствует в базе данных",
        APPERR_USER_NOT_AUTHENTICATED => "Пользователь не аутентифицирован",
        APPERR_USER_NOT_AUTHORIZED => "Пользователь не авторизован",
        APPERR_FORBIDDEN => "Недопустимая команда"
    ));

    class AppError extends Exception {
        public function __construct() {
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
            parent::__construct($message, $code);
        }
    }
?>