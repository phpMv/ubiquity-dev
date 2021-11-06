<?php
namespace Ubiquity\scaffolding\creators;

use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\Startup;
use Ubiquity\domains\DDDManager;
use Ubiquity\scaffolding\creators\CrudControllerCreator;
use Ubiquity\scaffolding\ScaffoldController;

class IndexCrudControllerCreator extends CrudControllerCreator {

	public function __construct($crudControllerName, $crudDatas = null, $crudViewer = null, $crudEvents = null, $crudViews = null, $routePath = '', $useViewInheritance = false, $style = '') {
		parent::__construct($crudControllerName, null, $crudDatas, $crudViewer, $crudEvents, $crudViews, $routePath, $useViewInheritance, $style);
		$this->templateName = 'indexCrudController.tpl';
		$this->viewKey = 'indexCRUD';
	}

	public function create(ScaffoldController $scaffoldController) {
		$this->scaffoldController = $scaffoldController;
		$crudControllerName = $this->controllerName;
		$classContent = '';
		$nsc = \trim($this->controllerNS, '\\');
		$messages = [];
		$domain = DDDManager::getActiveDomain();
		$initializeContent = '';
		$routeNamePrefix = '';
		if ($domain != '') {
			$initializeContent = "\t\t\Ubiquity\domains\DDDManager::setDomain('" . $domain . "');";
			$routeNamePrefix = $domain . '.';
		}
		if (($aDb = $this->scaffoldController->getActiveDb()) != null) {
			if ($aDb !== 'default') {
				$ns = CacheManager::getModelsNamespace($aDb);
				$routeNamePrefix .= $aDb . '.';
				if (isset($ns)) {
					$classContent .= $scaffoldController->_createMethod('protected', 'getModelName', '', '', "\t\treturn '" . $ns . "\\\\'.\ucfirst(\$this->resource);");
					$classContent .= $scaffoldController->_createMethod('protected', 'getIndexModels', '', ': array ', "\t\treturn \Ubiquity\orm\DAO::getModels('" . $aDb . "');");
					$initializeContent .= "\n\t\t\Ubiquity\orm\DAO::start();";
				}
			}
		}
		if ($routeNamePrefix != '') {
			$classContent .= $scaffoldController->_createMethod('protected', 'getRouteNamePrefix', '', ': string ', "\t\treturn '" . $routeNamePrefix . "';");
		}
		if ($initializeContent !== '') {
			$classContent .= $scaffoldController->_createMethod('public', 'initialize', '', '', $initializeContent . "\n\t\tparent::initialize();");
		}
		$this->createElements($nsc, $crudControllerName, $scaffoldController, $messages, $classContent);

		$this->routePath ??= '{resource}';
		$routePath = \rtrim($this->routePath, '/');
		$routeAnnotation = $this->getRouteAnnotation($routePath);

		$uses = $this->getUsesStr();
		$messages[] = $scaffoldController->_createController($crudControllerName, [
			'%indexRoute%' => $this->getAnnotation('route', [
				'name' => $routeNamePrefix . 'crud.index',
				'priority' => - 1
			]),
			'%homeRoute%' => $this->getAnnotation('route', [
				'path' => $this->getHome($routePath),
				'name' => $routeNamePrefix . 'crud.home',
				'priority' => 100
			]),
			'%routePath%' => '"' . \str_replace('{resource}', '".$this->resource."', $routePath) . '"',
			'%route%' => $routeAnnotation,
			'%uses%' => $uses,
			'%namespace%' => $this->getNamespaceStr(),
			'%baseClass%' => "\\Ubiquity\\controllers\\crud\\MultiResourceCRUDController",
			'%content%' => $classContent
		], $this->templateName);
		echo implode("\n", $messages);
	}

	protected function getHome($path) {
		return '#/' . \str_replace('{resource}', 'home', $path);
	}
}

