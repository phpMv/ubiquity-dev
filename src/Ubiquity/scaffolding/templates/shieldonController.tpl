<?php
namespace controllers;

%uses%

/**
 * Controller Shieldon
 */
class %controllerName% extends \Ubiquity\controllers\Controller {

	
	%route%
	public function index() {
		$controlPanel = \Ubiquity\security\shieldon\ShieldonManager::createPanel('%routePath%');
		%csrf%
		$controlPanel->entry();
	}
}
