<?php
use Jacwright\RestServer\RestException;
require_once 'AbstractController.php';
require_once 'AppError.php';
require_once 'Common.php';
require_once 'InternalException.php';
require_once 'Logger.php';
require_once 'DataBase.php';

class AppController extends AbstractController
{
    /**
    * @noAuth
    * @url GET /
    */
    public function app() {
        global $APP_CONFIG;

        try {
            $this->_check_config($APP_CONFIG);
        } catch (Exception $e) {
            //echo 'invalid config: ' . $e->getMessage();
            $this->_handle_exception(500, $e);
            return;
        }

        $sign_params = [];
        if (!$APP_CONFIG['no_vk_auth']) {
            // аутентификация ВК не отключена
            foreach ($_GET as $name => $value) {
                if (strpos($name, 'vk_') !== 0) { // Получаем только vk параметры из query
                    continue;
                }
                $sign_params[$name] = $value;
            }

            try {
                $check_fields = ['vk_app_id', 'vk_user_id'];
                $this->_check_fields($sign_params, $check_fields, [], true);
            } catch (Exception $e) {
                //echo 'invalid request';
                $this->_handle_exception(403, $e);
                return;
            }

            // проверка ид приложения вк
            if ($sign_params['vk_app_id'] != $APP_CONFIG['vk_app_id']) {
                //echo 'wrong app id';
                $this->_handle_exception(403);
                return;
            }
            // Сортируем массив по ключам 
            ksort($sign_params); 
            // Формируем строку вида "param_name1=value&param_name2=value"
            $sign_params_query = http_build_query($sign_params); 
            // Получаем хеш-код от строки, используя защищеный ключ приложения. Генерация на основе метода HMAC. 
            $sign = $this->_hash($sign_params_query, $APP_CONFIG['client_secret']);
            // Сравниваем полученную подпись со значением параметра 'sign'
            $status = $sign === $_GET['sign']; 
        } else {
            // убрать!!!!
            $sign_params = [
                'vk_user_id' => $_GET['user_id'],
                'vk_app_id' => $APP_CONFIG['vk_app_id']
            ];
            $status = true;
            $this->_log("sign_params = " . print_r($sign_params, true));
        }

        if ($status) {
            // все хорошо, подпись верна
            $auth_token = $this->_get_auth_token($APP_CONFIG, $sign_params['vk_user_id']);

            $file_data = file_get_contents('../mini-app/build/index.html');
            echo str_replace("</body>", "<script> var " . $APP_CONFIG['server_token_name'] . $APP_CONFIG['vk_app_id'] . "='" . $auth_token . "'; </script></body>", $file_data);

        } else {
            // подпись не верна
            //echo 'invalid request sign';
            $this->_handle_exception(403);
        }
    }

    private function _check_config(&$config) {
        try {
            $check_fields = [
            // db options
                'db_host',
                'db_port',
                'db_name',
                'db_user',
                'db_pass',
                'db_charset',
            // server options
                'server_mode',
            // vk app id
                'vk_app_id',
            // client vk app secret key
                'client_secret',
                'server_token_name',
            // random phrase to generate auth token for clients
                'server_key',
                'no_vk_auth'
            ];
            $check_int_fields = [];
            $this->_check_fields($config, $check_fields, $check_int_fields, false);
        } catch (Exception $e) {
            throw new InternalException('Отсутствуют обязательные поля в конфигурационном файле! Пожалуйста сверьтесь с файлом config.php.template. Описание исключения: ' . $e->getMessage());
        }
    }
}
?>