<?php
require_once './vendor/autoload.php';
require_once './config/config.php';
require_once './VkMiniAppController.php';

use Jacwright\RestServer\RestServer;
spl_autoload_register(); // don't load our classes unless we use them
global $_Config;

class MyRestServer extends RestServer {
    public function getPostData() {
        return $this->data;
    }

    public function getData() {
        $data = file_get_contents('php://input');
        //print_r($data);
        $data = json_decode($data, false);
        //print_r($data);

		return $data;
	}

}

//$mode = 'debug'; // 'debug' or 'production'
$mode = $_Config['server_mode'];
$server = new RestServer($mode);
if ($mode === 'debug') {
    $server->root = '/api';
}
$server->addClass('VkMiniAppController');

$server->handle();
?>