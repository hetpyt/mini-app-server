<?php

require_once('./ServerAPI.php');
require_once('./Logger.php');

class JSONServer {
    public $url;
	public $method;
	public $mode;
	public $root;
	public $rootPath;

	protected $postData = null;   // special parameter for post data
	protected $query = null;  // special parameter for query string
	protected $map = array();
	protected $errorClasses = array();

	public function  __construct($mode = 'debug') {
        $this->logger = new Logger('json_sever');
		$this->mode = $mode;

		// Set the root
		$dir = str_replace('\\', '/', dirname(str_replace($_SERVER['DOCUMENT_ROOT'], '', $_SERVER['SCRIPT_FILENAME'])));

		if ($dir == '.') {
			$dir = '/';
		} else {
			// add a slash at the beginning, and remove the one at the end
			if (substr($dir, -1) == '/') $dir = substr($dir, 0, -1);
			if (substr($dir, 0, 1) != '/') $dir = '/' . $dir;
		}

		$this->root = $dir;
	}

	public function  __destruct() {

	}

	public function handle() {

        try {
            $api = new ServerAPI($this);
            $result = $api->route();
            $this->sendData($result);

        } catch (Exception $e) {
            $this->handleError($e->getCode(), $e->getMessage());
        }
	}

	public function setRootPath($path) {
		$this->rootPath = '/' . trim($path, '/');
	}

	public function handleError($code, $message = null) {
        $this->logger->log("[$code] $message");
		$this->setStatus(500);
	}

    public function getQuery() {
        return $_GET;
    }

	public function getPath() {
		$path = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);

		// remove root from path
		if ($this->root) $path = preg_replace('/^' . preg_quote($this->root, '/') . '/', '', $path);

		// remove root path from path, like /root/path/api -> /api
		if ($this->rootPath) $path = str_replace($this->rootPath, '', $path);

		return trim($path, '/');
	}

	public function getMethod() {
		$method = $_SERVER['REQUEST_METHOD'];

		return $method;
	}

	public function getData($jsonAssoc = false) {
		$data = file_get_contents('php://input');
		$data = json_decode($data, $jsonAssoc);

		return $data;
	}

	public function sendData($data) {
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: 0");
		header('Content-Type: application/json');

        $options = 0;
        if ($this->mode == 'debug' && defined('JSON_PRETTY_PRINT')) {
            $options = JSON_PRETTY_PRINT;
        }

        if (defined('JSON_UNESCAPED_UNICODE')) {
            $options = $options | JSON_UNESCAPED_UNICODE;
        }

        echo json_encode($data, $options);
	}

	public function setStatus($code) {
        $httpCode = 500;
        if (array_key_exists(strval($code), $this->codes)) {
            $httpCode = $code;
        }
		if (function_exists('http_response_code')) {
			http_response_code($httpCode);
		} else {
			$protocol = $_SERVER['SERVER_PROTOCOL'] ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
			$httpCode .= ' ' . $this->codes[strval($httpCode)];
			header("$protocol $httpCode");
		}
	}

	private $codes = array(
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

}

?>