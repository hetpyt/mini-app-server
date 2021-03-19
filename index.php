<?php
require_once './config/config.php';

require_once './vendor/autoload.php';
require_once './VkMiniAppServer.php';
//require_once './TestController.php'; //'./VkMiniAppController.php';
require_once './controllers/AppController.php';
require_once './controllers/UserController.php';
require_once './controllers/AdminController.php';

require_once './VKMiniAppAuthServer.php';

use Jacwright\RestServer\RestServer;
spl_autoload_register(); // don't load our classes unless we use them

$mode = $APP_CONFIG['server_mode'];
//$server = new RestServer($mode);
$server = new VkMiniAppServer($mode);
if ($mode === 'debug') {
    $server->root = '/api';
}
$server->setJsonAssoc(false);
//$server->addClass('TestController');
$server->addClass('AppController');
$server->addClass('UserController');
$server->addClass('AdminController');

$server->setAuthHandler(new VKMiniAppAuthServer);
$server->handle();
?>