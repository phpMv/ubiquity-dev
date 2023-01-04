<?php
%namespace%

%uses%

%route%
class %controllerName% extends %baseClass% {

	public function __construct(){
		parent::__construct();
		\Ubiquity\orm\DAO::start();
		$this->model='%resource%';
		$this->style='%style%';
	}

	public function _getBaseRoute(): string {
		return '%routePath%';
	}
	
%content%
}
