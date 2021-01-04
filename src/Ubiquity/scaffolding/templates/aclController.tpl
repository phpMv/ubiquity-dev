<?php
%namespace%

%uses%
use Ubiquity\controllers\Controller;
use Ubiquity\security\acl\controllers\AclControllerTrait;

%route%
class %controllerName% extends Controller {
	use AclControllerTrait;

	public function index() {
		%indexContent%
	}

	public function _getRole() {
		//TODO Return the active user role
	}

	/**
	 * {@inheritdoc}
	 * @see \Ubiquity\controllers\Controller::onInvalidControl()
	 */
	public function onInvalidControl() {
		echo $this->_getRole() . ' is not allowed!';
	}

}

