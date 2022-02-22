<?php
namespace Ubiquity\scaffolding\creators;

use Ubiquity\controllers\Startup;
use Ubiquity\scaffolding\ScaffoldController;

/**
 * Creates an authentification controller.
 * Ubiquity\scaffolding\creators$AuthControllerCreator
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.1
 * @category ubiquity.dev
 *
 */
class AuthControllerCreator extends BaseControllerCreator {

	private $baseClass;

	public function __construct($controllerName, $baseClass, $views = null, $routePath = '', $useViewInheritance = false) {
		parent::__construct($controllerName, $routePath, $views, $useViewInheritance);
		$this->baseClass = $baseClass;
	}

	public function create(ScaffoldController $scaffoldController) {
		$this->scaffoldController = $scaffoldController;
		$classContent = '';
		$rClass=new \ReflectionClass($this->baseClass);
		if ($rClass->isAbstract()) {
			$controllerTemplate = "authController.tpl";
			$this->uses = [
				"Ubiquity\\utils\\http\\USession" => true,
				"Ubiquity\\utils\\http\\URequest" => true
			];
			if($this->baseClass=='\\Ubiquity\\controllers\\auth\\AuthControllerConfig'){
				$filename=\lcfirst($this->controllerName);
				$classContent.=$scaffoldController->_createMethod('protected','getConfigFilename','',': string ',"\t\treturn '$filename';");
				$completeClassname = $this->controllerNS . "auth\\".$this->controllerName;
				if(\method_exists($this->baseClass,'init')){
					\call_user_func($this->baseClass."::init",$filename);
				}
			}
		} else {
			$controllerTemplate = 'authController_.tpl';
		}

		$messages = [];
		if (isset($this->views)) {
			$this->addViews($messages, $classContent);
		}

		$routePath = $this->controllerName;
		$routeAnnot = '';

		if ($this->routePath != null) {
			$routeAnnot = $this->getRouteAnnotation($this->routePath);
			$routePath = $this->routePath;
		}
		$messages[] = $scaffoldController->_createController($this->controllerName, [
			'%routePath%' => $routePath,
			'%route%' => $routeAnnot,
			'%uses%' => $this->getUsesStr(),
			'%namespace%' => $this->getNamespaceStr(),
			'%baseClass%' => $this->baseClass,
			'%content%' => $classContent
		], $controllerTemplate);
		echo implode("\n", $messages);
	}

	protected function addViews(&$messages, &$classContent) {
		$scaffoldController = $this->scaffoldController;
		$authControllerName = $this->controllerName;
		$authViews = \explode(',', $this->views);
		$this->addUse("controllers\\auth\\files\\{$authControllerName}Files");
		$this->addUse("Ubiquity\\controllers\\auth\\AuthFiles");
		$classContent .= $scaffoldController->_createMethod('protected', 'getFiles', '', ': AuthFiles', "\t\treturn new {$authControllerName}Files();");
		$classFilesContent = [];
		foreach ($authViews as $file) {
			if (isset(ScaffoldController::$views['auth'][$file])) {
				$frameworkViewname = ScaffoldController::$views["auth"][$file];
				$scaffoldController->createAuthCrudView($frameworkViewname, $authControllerName, $file, $this->useViewInheritance);
				$classFilesContent[] = $scaffoldController->_createMethod('public', 'getView' . \ucfirst($file), '', ': string', "\t\treturn \"" . $authControllerName . "/" . $file . ".html\";");
			}
		}
		$messages[] = $this->createAuthFilesClass($scaffoldController, \implode('', $classFilesContent));
	}

	protected function createAuthFilesClass(ScaffoldController $scaffoldController, $classContent = '') {
		$ns = Startup::getNS('controllers') . "auth\\files";
		$uses = "\nuse Ubiquity\\controllers\\auth\\AuthFiles;";
		return $scaffoldController->_createClass('class.tpl', $this->controllerName . 'Files', $ns, $uses, 'extends AuthFiles', $classContent);
	}
}

