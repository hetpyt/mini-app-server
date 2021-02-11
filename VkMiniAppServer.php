<?php

class VkMiniAppServer extends Jacwright\RestServer\RestServer {


    public function sendData($data) {
        // обертка данных
        parent::sendData($data);
    }

    // public function handleError($statusCode, $errorMessage = null) {
    //     if (!$errorMessage) {
    //         $errorMessage = 'Internal Server Error';
    //     }
	// 	$this->setStatus($statusCode);
	// 	$this->sendData(array('error' => array('code' => $statusCode, 'message' => $errorMessage)));
	// }

}

?>