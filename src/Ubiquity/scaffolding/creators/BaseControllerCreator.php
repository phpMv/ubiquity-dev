<?php

namespace Ubiquity\scaffolding\creators;

use Ubiquity\scaffolding\ScaffoldController;
use Ubiquity\controllers\Startup;
use Ubiquity\cache\CacheManager;
use Ubiquity\creator\HasUsesTrait;

/**
 * Base class for class creation in scaffolding.
 * Ubiquity\scaffolding\creators$BaseControllerCreator
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.2
 * @category ubiquity.dev
 *
 */
abstract class BaseControllerCreator {
	use HasUsesTrait;
	
	protected $controllerName;
	protected $routePath;
	protected $views;
	protected $controllerNS;
	protected $templateName;

	/**
	 *
	 * @var ScaffoldController
	 */
	protected $scaffoldController;

	public function __construct($controllerName, $routePath, $views) {
		$this->controllerName = $controllerName;
		if($routePath!=null){
			$this->routePath = '/'.\ltrim($routePath,'/');
		}
		$this->views = $views;
		$this->controllerNS = Startup::getNS ( "controllers" );
	}

	protected function getRouteAnnotation($path){
		return CacheManager::getAnnotationsEngineInstance()->getAnnotation($this,'route',['path'=>$path,'automated'=>true,'inherited'=>true])->asAnnotation();
	}

	abstract public function create(ScaffoldController $scaffoldController);

	abstract protected function addViews(&$uses, &$messages, &$classContent);

	/**
	 *
	 * @return mixed
	 */
	public function getTemplateName() {
		return $this->templateName;
	}

	/**
	 *
	 * @param mixed $templateName
	 */
	public function setTemplateName($templateName) {
		$this->templateName = $templateName;
	}
}

