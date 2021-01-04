<?php
%namespace%

%uses%
use Hybridauth\Adapter\AdapterInterface;
 /**
  * Controller %controllerName%
  */
class %controllerName% extends %baseClass%{

	public function index(){
	}
	
	%route%
	public function _oauth(string $name):void {
		parent::_oauth($name);
	}
	
	protected function onConnect(string $name,AdapterInterface $provider){
		//TODO
	}
}
