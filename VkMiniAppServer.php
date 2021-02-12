<?php

class VkMiniAppServer extends Jacwright\RestServer\RestServer {

    public function sendData($data) {
        // обертка данных
        parent::sendData($data);
    }

}

?>