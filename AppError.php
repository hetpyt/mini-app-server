<?php
    define('APPERR_REGREQUEST_DUBL', -1);
    define('APPERR_BAD_SECRETCODE', -2);
    define('APPERR_ACCOUNT_NOT_FOUND', -3);
    define('APPERR_ACCOUNT_NOT_OWNED', -4);
    define('APPERR_REGREQUEST_NOT_EXISTS', -5);
    define('APPERR_REGREQUEST_ALREADY_PROCESSED', -6);
    define('APPERR_ACCOUNT_NOT_EXISTS', -7);

   


    define('APPERR_MESSAGES_RU', array(
        APPERR_REGREQUEST_DUBL => "Заявка на присоединение указанного лицевого счета уже существует и еще не обработана.",
        APPERR_BAD_SECRETCODE => "Не укзан либо неверно указан проверочный код.",
        APPERR_ACCOUNT_NOT_FOUND => "Неверно указан номер лицевого счета или проверочный код.",
        APPERR_ACCOUNT_NOT_OWNED => "Указанный лицевой счет не привязан к текущему пользователю ВК.",
        APPERR_REGREQUEST_NOT_EXISTS => "Заявка с номером {0} не существует",
        APPERR_REGREQUEST_ALREADY_PROCESSED => "Заявка с номером {0} уже обработана",
        APPERR_ACCOUNT_NOT_EXISTS => "Лицевой счет с номером {0} отсутствует в базе данных"
    ));
?>