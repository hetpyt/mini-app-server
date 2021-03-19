<?php

class VkMiniAppServer extends Jacwright\RestServer\RestServer {

    public function sendData($data) {
        global $APP_CONFIG;
        
        if ($APP_CONFIG['server_mode'] == 'debug') {
            // задержка отправки данных для целей отладки
            sleep(1);
        }
        // обертка данных
        $ret = [];
        if (is_array($data) && array_key_exists('error', $data)) {
            $ret = $data;
            if (!array_key_exists('result', $ret)) $ret['result'] = false;
        } else {
            $ret['result'] = $data;
        }
        parent::sendData($ret);
    }

}

?>