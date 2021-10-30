<?php
require_once './vendor/autoload.php';
use Jacwright\RestServer\RestServer;
use Jacwright\RestServer\RestException;

spl_autoload_register(); // don't load our classes unless we use them

require_once './config/config.php';
require_once './Logger.php';
require_once './AppError.php';
require_once './Common.php';
require_once './InternalException.php';
require_once './DataBase.php';

require_once './VkMiniAppServer.php';
//require_once './TestController.php'; //'./VkMiniAppController.php';
require_once './controllers/AbstractController.php';
require_once './controllers/AppController.php';
require_once './controllers/UserController.php';
require_once './controllers/AdminController.php';

require_once './VKMiniAppAuthServer.php';

$APP_CONFIG['__APP_ROOT__'] = __DIR__;

$mode = $APP_CONFIG['server_mode'];
//$server = new RestServer($mode);
$server = new VkMiniAppServer($mode);
if (array_key_exists('server_root', $APP_CONFIG) && $APP_CONFIG['server_root']) {
    $server->root = $APP_CONFIG['server_root'];
}
$server->setJsonAssoc(false);
//$server->addClass('TestController');
$server->addClass('AppController');
$server->addClass('UserController');
$server->addClass('AdminController');

$server->setAuthHandler(new VKMiniAppAuthServer);
$server->handle();
?>