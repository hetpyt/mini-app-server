<?php

require_once __DIR__ . '/JSONServerException.php'; 

class JSONServer {
    const JSON_AS_ASSOC = false;
    const HTTP_FORMAT = "application/json";
	const HTTP_CODES = array(
		'100' => 'Continue',
		'200' => 'OK',
		'201' => 'Created',
		'202' => 'Accepted',
		'203' => 'Non-Authoritative Information',
		'204' => 'No Content',
		'205' => 'Reset Content',
		'206' => 'Partial Content',
		'300' => 'Multiple Choices',
		'301' => 'Moved Permanently',
		'302' => 'Found',
		'303' => 'See Other',
		'304' => 'Not Modified',
		'305' => 'Use Proxy',
		'307' => 'Temporary Redirect',
		'400' => 'Bad Request',
		'401' => 'Unauthorized',
		'402' => 'Payment Required',
		'403' => 'Forbidden',
		'404' => 'Not Found',
		'405' => 'Method Not Allowed',
		'406' => 'Not Acceptable',
		'409' => 'Conflict',
		'410' => 'Gone',
		'411' => 'Length Required',
		'412' => 'Precondition Failed',
		'413' => 'Request Entity Too Large',
		'414' => 'Request-URI Too Long',
		'415' => 'Unsupported Media Type',
		'416' => 'Requested Range Not Satisfiable',
		'417' => 'Expectation Failed',
		'500' => 'Internal Server Error',
		'501' => 'Not Implemented',
		'503' => 'Service Unavailable'
	);

    private $debug;
    private $serverRoot;
    private $requestMethod;
    private $requestData;
    private $uri;

    private $authHandler;
    private $requestHandlerClass;
    private $requestHandler;

    private $responseData = null;

    public function __construct($serverRoot, $debug = false) {
        $this->serverRoot = $serverRoot;
        $this->debug = $debug;
    }

    public function setAuthHandler($obj) {
        $this->authHandler = $obj;
    }

    public function setRequestHandlerClass($class) {
        if (!class_exists($class)) {
            throw new Exception("Class $class not exists");
        }
        $this->requestHandlerClass = $class;
    }

    public function handle() {
        try {
            // request method
            $this->requestMethod = $_SERVER['REQUEST_METHOD'];
            // request uri
            $this->uri = $this->getURI();

            // аутентификация
            if ($this->authHandler) {
                if (!$this->authHandler->authenticate($this->uri)) {
                    throw new JSONServerException(401);
                }
            }

            if ($this->requestMethod == "POST") {
                $this->requestData = $this->getPostData();
            }
            
            if ($this->requestHandlerClass) {
                $this->requestHandler = new $this->requestHandlerClass;
                if (!method_exists($this->requestHandler, 'handle')) {
                    throw new Exception("Method handle not exists in class $this->requestHandlerClass");
                }
                $this->responseData = $this->requestHandler->handle($this->uri, $this->requestData);
            }

            if ($this->responseData != null) {
                $this->sendData($this->requestData);
            }

        } catch (JSONServerException $e) {
            $this->handleError($e->getCode(), $e->getMessage());
        }   

    }

	public function sendData($data) {
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: 0");
		header('Content-Type: ' . self::HTTP_FORMAT);

        $options = 0;
        if ($this->debug && defined('JSON_PRETTY_PRINT')) {
            $options = JSON_PRETTY_PRINT;
        }

        if (defined('JSON_UNESCAPED_UNICODE')) {
            $options = $options | JSON_UNESCAPED_UNICODE;
        }

        echo json_encode($data, $options);
	}

	public function setStatus($code) {
		if (function_exists('http_response_code')) {
			http_response_code($code);
		} else {
			$protocol = $_SERVER['SERVER_PROTOCOL'] ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
			$code .= ' ' . self::HTTP_CODES[strval($code)];
			header("$protocol $code");
		}
	}

	public function handleError($statusCode, $errorMessage = null) {
		if (!$errorMessage) {
			$errorMessage = self::HTTP_CODES[strval($statusCode)];
		}

		$this->setStatus($statusCode);
		$this->sendData(array('error' => array('code' => $statusCode, 'message' => $errorMessage)));
	}

    protected function getURI() {
        $path = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
        // remove root from path
        if ($this->serverRoot) $path = preg_replace('/^' . preg_quote($this->serverRoot, '/') . '/', '', $path);
        return ltrim($path, '/');
    }

    protected function getPostData() {
		$data = file_get_contents('php://input');
		$data = json_decode($data, self::JSON_AS_ASSOC);

		return $data;
    }

    private function sendApp() {

        $sign_params = [];
        if (!APP_CONFIG['no_vk_auth']) {
            // аутентификация ВК не отключена
            foreach ($_GET as $name => $value) {
                if (strpos($name, 'vk_') !== 0) { // Получаем только vk параметры из query
                    continue;
                }
                $sign_params[$name] = $value;
            }

            if (!(
                array_key_exists('vk_app_id', $sign_params) 
                && array_key_exists('vk_user_id', $sign_params)
                && array_key_exists('sign', $_GET)
            )) {
                throw new JSONServerException(403);
            }

            // проверка ид приложения вк
            if ($sign_params['vk_app_id'] != APP_CONFIG['vk_app_id']) {
                throw new JSONServerException(403);
            }
            // Сортируем массив по ключам 
            ksort($sign_params); 
            // Формируем строку вида "param_name1=value&param_name2=value"
            $sign_params_query = http_build_query($sign_params); 
            // Получаем хеш-код от строки, используя защищеный ключ приложения. Генерация на основе метода HMAC. 
            $sign = $this->_hash($sign_params_query, APP_CONFIG['client_secret']);
            // Сравниваем полученную подпись со значением параметра 'sign'
            $status = $sign === $_GET['sign']; 
        } else {
            // убрать!!!!
            $sign_params = [
                'vk_user_id' => $_GET['user_id'],
                'vk_app_id' => APP_CONFIG['vk_app_id']
            ];
            $status = true;
            //$this->_log("sign_params = " . print_r($sign_params, true));
        }

        if ($status) {
            // все хорошо, подпись верна
            $auth_token = $this->getAuthToken($sign_params['vk_user_id']);

            $file_data = file_get_contents('../mini-app/build/index.html');
            echo str_replace("</body>", "<script> var " . APP_CONFIG['server_token_name'] . APP_CONFIG['vk_app_id'] . "='" . $auth_token . "'; </script></body>", $file_data);

        } else {
            // подпись не верна
            //echo 'invalid request sign';
            throw new JSONServerException(403);
        }
    }

}

?>