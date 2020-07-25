<?php
require_once './vendor/autoload.php';
require_once './config/config.php';
require_once './VkMiniAppController.php';
require_once './VKMiniAppAuthServer.php';

use Jacwright\RestServer\RestServer;
spl_autoload_register(); // don't load our classes unless we use them
global $_Config;

$mode = $_Config['server_mode'];
$server = new RestServer($mode);
if ($mode === 'debug') {
    $server->root = '/api';
}
$server->addClass('VkMiniAppController');
$server->setAuthHandler(new VKMiniAppAuthServer);
$server->handle();
?>