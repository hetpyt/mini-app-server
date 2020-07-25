<?php

class VKMiniAppAuthServer implements \Jacwright\RestServer\AuthServer {
	protected $realm;

	public function __construct($realm = 'VK mini app rest Server') {
		$this->realm = $realm;
	}

	public function isAuthorized($classObj) {
		if (method_exists($classObj, 'authorize')) {
			return $classObj->authorize();
		}

		return true;
	}

	public function unauthorized($classObj) {
		//header("WWW-Authenticate: Basic realm=\"$this->realm\"");
		throw new \Jacwright\RestServer\RestException(401, "You are not authorized to access this resource.");
	}
}