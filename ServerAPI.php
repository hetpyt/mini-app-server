<?php

    //use Exception;
    require_once './AppError.php';
    require_once './APIException.php';
    require_once './DataBase.php';
    require_once './DBQueryBuilder.php';

    class ServerAPI {
        public const _API_VERSION = '0.2';

        protected $server;
        protected $query;
        protected $postData;
        protected $uri = '';
        protected $subsys = '';
        protected $object = '';
        protected $action = '';

        protected $_logger = null;
        protected $controller = null;

        public function __construct($server) {
            $this->server = $server;
            $this->uri = $server->getPath();
            $this->query = $server->getQuery();
            $this->postData = $server->getData(true);
            //echo print_r($this);
        }

        public function __destruct() {

        }

        public function route() {
            //echo $uri;
            try {
                $auri = explode('/', $this->uri);
                if (count($auri) != 3) {
                    throw new AppError(APPERR_FORBIDDEN);
                }
                list($this->subsys, $this->object, $this->action) = $auri;

                $controllerName = ucfirst(strtolower($this->subsys)) . 'Controller';
                $controllerFile = './controllers/' . $controllerName . '.php';
                if (is_file($controllerFile)) {
                    include $controllerFile;
                    $this->controller = new $controllerName($this->query, $this->postData);
                } else {
                    throw new AppError(APPERR_FORBIDDEN);
                }

                // method name resolution
                $methodName = $this->getMethod();
                if (!$methodName) {
                    throw new AppError(APPERR_FORBIDDEN);
                }

                // authentication
                if (!$this->authenticate()) {
                    throw new AppError(APPERR_USER_NOT_AUTHENTICATED);
                }

                //throw new APIException("foo", 100);

                // method call
                //return $this->_return_data( $this->controller->$methodName(null) );

                $qb = new DBQueryBuilder('accounts');
                echo $qb->execute();
            }
            catch (AppError $e) {
                // application errors processing and sending to client
                return $this->_return_app_error($e->getCode(), $e->getMessage());
            }  
            catch (APIException $e) {
                // all is bad - throw to next catcher - server
                throw new Exception($e->getMessage(), $e->getCode());
            }
            
        }

        public function authenticate() {
            global $APP_CONFIG;
            $status = false;
            $user_id = '';
            $token = '';
    
            // получим токен
            if (array_key_exists('token', $this->query)) {
                $token = $this->query['token'];
            }
            // получим пользователя
            if (array_key_exists('user_id', $this->query)) {
                $user_id = $this->query['user_id'];  
            }
            if ($user_id && $token) {
                $expected_token = $this->_get_auth_token($APP_CONFIG, $user_id);
                if ($status = $expected_token === $token) {
                }
            }
            return $status;
        }

        /*
            return non list data to clent in API subscribed format
            all uri handlers of controller classes must use it to return non list data
        */
        protected function _return_data($data) {
            $result = $this->_return_result(true);
            $result['data'] = $data; // returned data, specified by operation
            return $result;
        }

        /*
            return application error to clent in API subscribed format
            all uri handlers of controller classes must use it to return errors to client
        */
        protected function _return_app_error($code, $message) {
            $result = $this->_return_result(false);
            $result['error']['code'] = $code;
            $result['error']['message'] = $message;
            return $result;
        }
        
        /*
            common result of server
            not for call directly by controllers
        */
        private function _return_result($ok = true) {
            $result = [
                'api' => self::_API_VERSION, // version of API
                'ok' => $ok, // indicator of successfull opertion
            ];
            if (!$ok) {
                $result['error'] = [
                    'code' => -1,
                    'message' => 'unhandled error'
                ];
            }
            return $result;
        }

        protected function getMethod() {
            $methodNames = [];
            $methodNames[] = strtolower($this->subsys . '_' . $this->object . '_' . $this->action);
            $methodNames[] = strtolower($this->object . '_' . $this->action);

            foreach ($methodNames as $methodName) {
                if (method_exists($this->controller, $methodName)) {
                    return $methodName;
                }
            }
            return false;
        }

        protected function _get_auth_token(&$config, $vk_user_id) {
            return $this->_hash(
                "" . $vk_user_id . '_' . $config['vk_app_id'] . '_' . $config['server_key'] . '_' . date('Ymd'),
                $config['client_secret']
            );
        }
    
        protected function _hash($data, $key) {
            $result = rtrim(
                strtr(
                    base64_encode(
                        hash_hmac('sha256', $data, $key, true)
                    ),
                '+/', '-_'), 
            '='); 
            return $result;
        }
    }

?>