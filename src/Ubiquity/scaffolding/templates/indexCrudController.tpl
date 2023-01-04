<?php
%namespace%

%uses%

%route%
class %controllerName% extends %baseClass% {

	%indexRoute%
	public function index() {
		parent::index();
	}


	%homeRoute%
	public function home() {
		parent::home();
	}

	protected function getIndexType():array {
		return ['four link cards','card'];
	}
	
	public function _getBaseRoute():string {
		return %routePath%;
	}
	
%content%
}
