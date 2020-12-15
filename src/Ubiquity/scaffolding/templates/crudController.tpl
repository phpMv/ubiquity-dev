<?php
%namespace%
%uses%

%route%
class %controllerName% extends %baseClass%{

	public function __construct(){
		parent::__construct();
		\Ubiquity\orm\DAO::start();
		$this->model="%resource%";
	}

	public function _getBaseRoute() {
		return '%routePath%';
	}
	
%content%
}
