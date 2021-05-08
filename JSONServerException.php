<?php

class JSONServerException extends Exception {
	public function __construct($code, $message = null) {
		parent::__construct($message, $code);
	}
}

?>