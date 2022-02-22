<?php
namespace Ubiquity\scaffolding;

use Ubiquity\cache\CacheManager;
use Ubiquity\cache\ClassUtils;
use Ubiquity\controllers\Startup;
use Ubiquity\creator\HasUsesTrait;
use Ubiquity\domains\DDDManager;
use Ubiquity\scaffolding\creators\AuthControllerCreator;
use Ubiquity\scaffolding\creators\CrudControllerCreator;
use Ubiquity\scaffolding\creators\IndexCrudControllerCreator;
use Ubiquity\scaffolding\creators\RestControllerCreator;
use Ubiquity\utils\base\CodeUtils;
use Ubiquity\utils\base\UFileSystem;
use Ubiquity\utils\base\UIntrospection;
use Ubiquity\utils\base\UString;

/**
 * Base class for Scaffolding.
 * Ubiquity\scaffolding$ScaffoldController
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.8
 * @category ubiquity.dev
 *
 */
abstract class ScaffoldController {
	use HasUsesTrait;

	protected $config;

	protected $activeDb;

	public static $views = [
		"CRUD" => [
			"index" => "@framework/crud/index.html",
			"form" => "@framework/crud/form.html",
			"display" => "@framework/crud/display.html"
		],
		"indexCRUD" => [
			"index" => "@framework/crud/index.html",
			"form" => "@framework/crud/form.html",
			"display" => "@framework/crud/display.html",
			"home" => "@framework/crud/home.html",
			"itemHome" => "@framework/crud/itemHome.html",
			"nav" => "@framework/crud/nav.html"
		],
		"auth" => [
			"index" => "@framework/auth/index.html",
			"info" => "@framework/auth/info.html",
			"noAccess" => "@framework/auth/noAccess.html",
			"disconnected" => "@framework/auth/disconnected.html",
			"message" => "@framework/auth/message.html",
			"create" => "@framework/auth/create.html",
			"stepTwo" => "@framework/auth/stepTwo.html",
			"badTwoFACode" => "@framework/auth/badTwoFACode.html",
			"baseTemplate" => "@framework/auth/baseTemplate.html",
			"initRecovery" => "@framework/auth/initRecovery.html",
			"recovery" => "@framework/auth/recovery.html"
		]
	];

	public function getTemplateDir() {
		return \dirname(__DIR__) . "/scaffolding/templates/";
	}

	public function _refreshRest($refresh = false) {}

	public function initRestCache($refresh = true) {}

	protected abstract function storeControllerNameInSession($controller);

	public abstract function showSimpleMessage($content, $type, $title = null, $icon = "info", $timeout = NULL, $staticName = null);

	protected abstract function _addMessageForRouteCreation($path, $jsCallback = "");

	public function _createMethod($access, $name, $parameters = "", $return = "", $content = "", $comment = "") {
		$templateDir = $this->getTemplateDir();
		$keyAndValues = [
			"%access%" => $access,
			"%name%" => $name,
			"%parameters%" => $parameters,
			"%content%" => $content,
			"%comment%" => $comment,
			"%return%" => $return
		];
		return UFileSystem::openReplaceInTemplateFile($templateDir . "method.tpl", $keyAndValues);
	}

	public function getInitialize() {
		$domain = DDDManager::getActiveDomain();
		$initialize = '';
		if ($domain != '') {
			$initialize = "\n\tpublic function initialize(){\n\t\tparent::initialize();\n\t\t\Ubiquity\domains\DDDManager::setDomain('" . $domain . "');\n\t}";
		}
		return $initialize;
	}

	public function _createController($controllerName, $variables = [], $ctrlTemplate = 'controller.tpl', $hasView = false, $jsCallback = "") {
		$message = "";
		$templateDir = $this->getTemplateDir();
		$controllersNS = \rtrim(Startup::getNS('controllers'), "\\");
		$controllersDir = \ROOT . \DS . str_replace("\\", \DS, $controllersNS);
		$controllerName = \ucfirst($controllerName);
		$filename = $controllersDir . \DS . $controllerName . ".php";
		if (\file_exists($filename) === false) {
			$namespace = "";
			if ($controllersNS !== '') {
				$namespace = 'namespace ' . $controllersNS . ';';
			}
			$msgView = '';
			$indexContent = '';
			if ($hasView) {
				$viewDir = DDDManager::getActiveViewFolder() . $controllerName . \DS;
				UFileSystem::safeMkdir($viewDir);
				$viewName = $viewDir . \DS . 'index.html';
				UFileSystem::openReplaceWriteFromTemplateFile($templateDir . 'view.tpl', $viewName, [
					'%controllerName%' => $controllerName,
					'%actionName%' => "index"
				]);
				$msgView = "<br>The default view associated has been created in <b>" . UFileSystem::cleanPathname($viewDir) . "</b>";
				$indexContent = "\$this->loadView(\"" . DDDManager::getViewNamespace() . $controllerName . "/index.html\");";
			}
			$variables = \array_merge([
				'%controllerName%' => $controllerName,
				'%indexContent%' => $indexContent,
				'%namespace%' => $namespace,
				'%initialize%' => $this->getInitialize(),
				'%route%' => '',
				'%uses%' => ''
			], $variables);
			UFileSystem::openReplaceWriteFromTemplateFile($templateDir . $ctrlTemplate, $filename, $variables);
			$msgContent = "The <b>" . $controllerName . "</b> controller has been created in <b>" . UFileSystem::cleanFilePathname($filename) . "</b>." . $msgView;
			if (isset($variables['%routePath%']) && $variables['%routePath%'] !== '') {
				$msgContent .= $this->_addMessageForRouteCreation($variables['%routePath%'], $jsCallback);
			}
			$this->storeControllerNameInSession($controllersNS . "\\" . $controllerName);
			$message = $this->showSimpleMessage($msgContent, 'success', null, 'checkmark circle', NULL, 'msgGlobal');
		} else {
			$message = $this->showSimpleMessage("The file <b>" . $filename . "</b> already exists.<br>Can not create the <b>" . $controllerName . "</b> controller!", "warning", null, "warning circle", 100000, "msgGlobal");
		}
		return $message;
	}

	public function addCrudController($crudControllerName, $resource, $crudDatas = null, $crudViewer = null, $crudEvents = null, $crudViews = null, $routePath = '', $useViewInheritance = false, $style = '') {
		$crudController = new CrudControllerCreator($crudControllerName, $resource, $crudDatas, $crudViewer, $crudEvents, $crudViews, $routePath, $useViewInheritance, $style);
		$crudController->create($this);
	}

	public function addIndexCrudController($crudControllerName, $crudDatas = null, $crudViewer = null, $crudEvents = null, $crudViews = null, $routePath = '', $useViewInheritance = false, $style = '') {
		$crudController = new IndexCrudControllerCreator($crudControllerName, $crudDatas, $crudViewer, $crudEvents, $crudViews, $routePath, $useViewInheritance, $style);
		$crudController->create($this);
	}

	public function addAuthController($authControllerName, $baseClass, $authViews = null, $routePath = "", $useViewInheritance = false) {
		$authCreator = new AuthControllerCreator($authControllerName, $baseClass, $authViews, $routePath, $useViewInheritance);
		$authCreator->create($this);
	}

	public function addRestController($restControllerName, $baseClass, $resource, $routePath = "", $reInit = true) {
		$restCreator = new RestControllerCreator($restControllerName, $baseClass, $resource, $routePath);
		$restCreator->create($this, $reInit);
	}

	public function _createClass($template, $classname, $namespace, $uses, $extendsOrImplements, $classContent) {
		$namespaceVar = '';
		if (UString::isNotNull($namespace)) {
			$namespaceVar = "namespace {$namespace};";
		}
		$variables = [
			'%classname%' => $classname,
			'%namespace%' => $namespaceVar,
			'%uses%' => $uses,
			'%extendsOrImplements%' => $extendsOrImplements,
			'%classContent%' => $classContent
		];
		$templateDir = $this->getTemplateDir();
		$directory = UFileSystem::getDirFromNamespace($namespace);
		UFileSystem::safeMkdir($directory);
		$filename = UFileSystem::cleanFilePathname($directory . \DS . $classname . '.php');
		if (! file_exists($filename)) {
			UFileSystem::openReplaceWriteFromTemplateFile($templateDir . $template, $filename, $variables);
			$message = $this->showSimpleMessage("The <b>" . $classname . "</b> class has been created in <b>" . $filename . "</b>.", "success", "Creation", "checkmark circle");
		} else {
			$message = $this->showSimpleMessage("The file <b>" . $filename . "</b> already exists.<br>Can not create the <b>" . $classname . "</b> class!", "warning", "Creation", "warning circle");
		}
		return $message;
	}

	public function _newAction($controller, $action, $parameters = null, $content = '', $routeInfo = null, $createView = false, $theme = null) {
		$templateDir = $this->getTemplateDir();
		$msgContent = "";
		$r = new \ReflectionClass($controller);
		if (! \method_exists($controller, $action)) {
			$ctrlFilename = $r->getFileName();
			$content = CodeUtils::indent($content, 2);
			$classCode = UIntrospection::getClassCode($controller);
			if ($classCode !== false) {
				$fileContent = \implode('', $classCode);
				$fileContent = \trim($fileContent);
				$ctrlName = ClassUtils::getClassSimpleName($controller);
				if ($createView) {
					$viewname = $this->_createViewOp($ctrlName, $action, $theme);
					$content .= "\n\t\t\$this->loadView('" . $viewname . "');\n";
					$msgContent .= "<br>Created view : <b>" . $viewname . "</b>";
				}
				if ($routeInfo != null) {
					$routeInfo['path'] = $this->generateRoutePath($routeInfo['path'], $ctrlName, $action, $parameters);
				}
				$routeAnnotation = $this->generateRouteAnnotation($routeInfo, $ctrlName, $action);

				if ($routeAnnotation != '') {
					$msgContent .= $this->_addMessageForRouteCreation($routeInfo["path"]);
					if (\count($this->getUses()) > 0) {
						$namespace = 'namespace ' . $r->getNamespaceName() . ";";
						$posUses = \strpos($fileContent, $namespace);
						if ($posUses !== false) {
							$posUses += \strlen($namespace) + 1;
							$uses = $this->uses;
							foreach ($uses as $use => $_) {
								if (\strpos($fileContent, 'use ' . $use) !== false) {
									unset($this->uses[$use]);
								}
							}
							if (\count($this->getUses()) > 0) {
								$fileContent = \substr_replace($fileContent, "\n" . $this->getUsesStr(), $posUses - 1, 0);
							}
						}
					}
				}
				$parameters = CodeUtils::cleanParameters($parameters);
				$actionContent = UFileSystem::openReplaceInTemplateFile($templateDir . "action.tpl", [
					'%route%' => "\n\t" . $routeAnnotation ?? '',
					'%actionName%' => $action,
					'%parameters%' => $parameters,
					'%content%' => $content
				]);
				$posLast = \strrpos($fileContent, '}');
				$fileContent = \substr_replace($fileContent, "\n%content%", $posLast - 1, 0);
				if (! CodeUtils::isValidCode('<?php ' . $content)) {
					echo $this->showSimpleMessage("Errors parsing action content!", "warning", "Creation", "warning circle", null, "msgControllers");
					return;
				} else {
					if (UFileSystem::replaceWriteFromContent($fileContent . "\n", $ctrlFilename, [
						'%content%' => $actionContent
					])) {
						$msgContent = "The action <b>{$action}</b> is created in controller <b>{$controller}</b>" . $msgContent;
						echo $this->showSimpleMessage($msgContent, "success", "Creation", "info circle", null, "msgControllers");
					}
				}
			} else {
				echo $this->showSimpleMessage("Unable to get the code of the class {$controller}!", "error", "Creation", "warning circle", null, "msgControllers");
			}
		} else {
			echo $this->showSimpleMessage("The action {$action} already exists in {$controller}!", "error", "Creation", "warning circle", null, "msgControllers");
		}
	}

	private function generateRoutePath($path, $controllerName, $action, $parameters) {
		if ($path == 1) {
			$path = \str_replace('.', '/', $this->generateRouteName($controllerName, $action));
		}
		$params = CodeUtils::getParametersForRoute($parameters);
		foreach ($params as $param) {
			if ($param !== '{}' && \strpos($path, $param) === false) {
				$path = \rtrim($path, '/') . '/' . $param;
			}
		}
		return $path;
	}

	private function generateRouteName(string $controllerName, string $action) {
		$ctrl = \str_ireplace('controller', '', $controllerName);
		return \lcfirst($ctrl) . '.' . $action;
	}

	protected function generateRouteAnnotation($routeInfo, $controllerName, $action) {
		if (\is_array($routeInfo)) {
			$name = 'route';
			$path = $routeInfo['path'];
			$routeProperties['path'] = $path;
			$strMethods = $routeInfo['methods'];
			if (UString::isNotNull($strMethods)) {
				$methods = \explode(',', $strMethods);
				$methodsCount = \count($methods);
				if ($methodsCount > 1) {
					$routeProperties['methods'] = $methods;
				} elseif ($methodsCount == 1) {
					$name = \current($methods);
				}
			}
			if (isset($routeInfo['ck-Cache'])) {
				$routeProperties['cache'] = true;
				if (isset($routeInfo['duration'])) {
					$duration = $routeInfo['duration'];
					if (\ctype_digit($duration)) {
						$routeProperties['duration'] = $duration;
					}
				}
			}
			$routeProperties['name'] = $this->generateRouteName($controllerName, $action);

			return CacheManager::getAnnotationsEngineInstance()->getAnnotation($this, $name, $routeProperties)->asAnnotation();
		}
		return '';
	}

	public function _createViewOp($controller, $action, $theme = null) {
		$prefix = '';
		if (! isset($theme) || $theme == '') {
			$theme = $this->config['templateEngineOptions']['activeTheme'] ?? null;
		}
		if ($theme != null && DDDManager::getActiveDomain() != '') {
			$prefix = 'themes/' . $theme . '/';
		}
		$viewFolder = DDDManager::getActiveViewFolder();
		$viewName = $prefix . $controller . '/' . $action . ".html";
		UFileSystem::safeMkdir($viewFolder . $prefix . $controller);
		$templateDir = $this->getTemplateDir();
		UFileSystem::openReplaceWriteFromTemplateFile($templateDir . 'view.tpl', $viewFolder . $viewName, [
			'%controllerName%' => $controller,
			'%actionName%' => $action
		]);
		return DDDManager::getViewNamespace() . $viewName;
	}

	public function createAuthCrudView($frameworkName, $controllerName, $newName, $useViewInheritance) {
		$folder = DDDManager::getActiveViewFolder() . $controllerName;
		UFileSystem::safeMkdir($folder);
		try {
			$teInstance = Startup::getTemplateEngineInstance();
			if (isset($teInstance)) {
				if ($useViewInheritance) {
					$blocks = $teInstance->getBlockNames($frameworkName);
					if (sizeof($blocks) > 0) {
						$content = [
							"{% extends \"" . $frameworkName . "\" %}\n"
						];
						foreach ($blocks as $blockname) {
							$content[] = "{% block " . $blockname . " %}\n\t{{ parent() }}\n{% endblock %}\n";
						}
					} else {
						$content = [
							$teInstance->getCode($frameworkName)
						];
					}
				} else {
					$content = [
						$teInstance->getCode($frameworkName)
					];
				}
			}
		} catch (\Exception $e) {
			$content = [
				$teInstance->getCode($frameworkName)
			];
		}
		if (isset($content)) {
			return UFileSystem::save($folder . \DS . $newName . '.html', implode('', $content));
		}
	}

	public function setConfig($config) {
		$this->config = $config;
	}

	/**
	 *
	 * @param string $activeDb
	 */
	public function setActiveDb($activeDb): void {
		$this->activeDb = $activeDb;
	}

	/**
	 *
	 * @return string
	 */
	public function getActiveDb(): string {
		return $this->activeDb;
	}
}
