<?php

class Logger {

    private $_log_dir = '';
    private $_service_name = '';

    public function __construct($service_name = null, $log_dir = '') {
        global $APP_CONFIG;
        $this->_log_dir = $APP_CONFIG['__APP_ROOT__'] . '/' . trim($log_dir, '/');
        if ($service_name) $this->_service_name = $service_name;
    }

    public function __get($prop) {
        switch ($prop)
        {
            case 'log_dir':
                return $this->_log_dir;
            default:
                return null;
        }
    }

    public function __set($prop, $val) {
        switch ($prop)
        {
            case 'log_dir':
                $this->_log_dir = $val;
        }
    }

    public function log($text) {
        file_put_contents($this->_log_dir . '/' . $this->_log_file_name(), "[" . date('Y-m-d H:i:s') . "] " . $text . PHP_EOL, FILE_APPEND);
    }

    private function _log_file_name() {
        return "" . $this->_service_name . "_" . date('Y-m-d') . ".log";
    }
}

?>