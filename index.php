<?php
require_once './vendor/autoload.php';
//use Jacwright\RestServer\RestServer;
//use Jacwright\RestServer\RestException;

spl_autoload_register(); // don't load our classes unless we use them
require_once './api.php';
require_once './config/config.php';
require_once './Logger.php';
require_once './AppError.php';
require_once './Common.php';
require_once './InternalException.php';
require_once './DataBase.php';

require_once './VKUserAuthServer.php';
require_once './RequestHandler.php';
require_once './JSONServer.php';

//require_once './VkMiniAppServer.php';
//require_once './TestController.php'; //'./VkMiniAppController.php';
//require_once './controllers/AbstractController.php';
//require_once './controllers/AppController.php';
//require_once './controllers/UserController.php';
//require_once './controllers/AdminController.php';

//require_once './VKMiniAppAuthServer.php';

//$mode = $APP_CONFIG['server_mode'];
//$server = new RestServer($mode);
//$server = new VkMiniAppServer($mode);
//if ($mode === 'debug') {
//    $server->root = '/api';
//}
//$server->setJsonAssoc(false);
//$server->addClass('TestController');
//$server->addClass('AppController');
//$server->addClass('UserController');
//$server->addClass('AdminController');

//$server->setAuthHandler(new VKMiniAppAuthServer);
$server = new JSONServer('/api', true);
$server->setAuthHandler(new VKUserAuthServer);
$server->setRequestHandlerClass('RequestHandler');
$server->handle();
?>