<?php

class VkMiniAppServer extends Jacwright\RestServer\RestServer {

    public function sendData($data) {
        global $_Config;
        
        if ($_Config['server_mode'] == 'debug') {
            // задержка отправки данных для целей отладки
            sleep(1);
        }
        // обертка данных
        //if (is_bool($data)) {
            $data = [
                'result' => $data
            ];
        //}
        parent::sendData($data);
    }

}

?>