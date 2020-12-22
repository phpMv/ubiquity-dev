<?php

namespace Ubiquity\scaffolding\creators;

use Ubiquity\scaffolding\ScaffoldController;
use Ubiquity\utils\base\UString;

/**
 * Creates a CRUD controller.
 * Ubiquity\scaffolding\creators$CrudControllerCreator
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.1
 * @category ubiquity.dev
 *
 */
class CrudControllerCreator extends BaseControllerCreator {
	private $resource;
	private $crudDatas;
	private $crudViewer;
	private $crudEvents;

	public function __construct($crudControllerName, $resource, $crudDatas = null, $crudViewer = null, $crudEvents = null, $crudViews = null, $routePath = '', $useViewInheritance = false) {
		parent::__construct ( $crudControllerName, $routePath, $crudViews ,$useViewInheritance);
		$this->resource = $resource;
		$this->crudDatas = $crudDatas;
		$this->crudViewer = $crudViewer;
		$this->crudEvents = $crudEvents;
		$this->templateName = 'crudController.tpl';
	}

	public function create(ScaffoldController $scaffoldController) {
		$this->scaffoldController = $scaffoldController;
		$resource = $this->resource;
		$crudControllerName = $this->controllerName;
		$classContent = "";
		$controllerNS = $this->controllerNS;
		$messages = [ ];

		$scaffoldController->_createMethod ( "public", "__construct", "", "", "\n\t\tparent::__construct();\n\$this->model=\"{$resource}\";" );

		if (isset ( $this->crudDatas )) {
			$this->addUses("controllers\\crud\\datas\\{$crudControllerName}Datas","Ubiquity\\controllers\\crud\\CRUDDatas");

			$classContent .= $scaffoldController->_createMethod ( "protected", "getAdminData", "", ": CRUDDatas", "\t\treturn new {$crudControllerName}Datas(\$this);" );
			$messages [] = $this->createCRUDDatasClass ();
		}

		if (isset ( $this->crudViewer )) {
			$this->addUses("controllers\\crud\\viewers\\{$crudControllerName}Viewer","Ubiquity\\controllers\\crud\\viewers\\ModelViewer");

			$classContent .= $scaffoldController->_createMethod ( "protected", "getModelViewer", "", ": ModelViewer", "\t\treturn new {$crudControllerName}Viewer(\$this);" );
			$messages [] = $this->createModelViewerClass ();
		}
		if (isset ( $this->crudEvents )) {
			$this->addUses("controllers\\crud\\events\\{$crudControllerName}Events","Ubiquity\\controllers\\crud\\CRUDEvents");

			$classContent .= $scaffoldController->_createMethod ( "protected", "getEvents", "", ": CRUDEvents", "\t\treturn new {$crudControllerName}Events(\$this);" );
			$messages [] = $this->createEventsClass ();
		}

		if (isset ( $this->views )) {
			$this->addViews ( $messages, $classContent );
		}
		$routePath = $this->controllerName;
		$routeAnnot='';
		if ($this->routePath != null) {
			$routePath=$this->routePath;
			$routeAnnot=$this->getRouteAnnotation($this->routePath);
		}
		
		$uses = $this->getUsesStr();
		$messages [] = $scaffoldController->_createController ( $crudControllerName, [ "%routePath%" => $routePath,"%route%" => $routeAnnot,"%resource%" => $resource,"%uses%" => $uses,"%namespace%" => $controllerNS,"%baseClass%" => "\\Ubiquity\\controllers\\crud\\CRUDController","%content%" => $classContent ], $this->templateName );
		echo implode ( "\n", $messages );
	}

	protected function addViews(&$messages, &$classContent) {
		$crudControllerName = $this->controllerName;
		$crudViews = explode ( ",", $this->views );
		$this->addUses("controllers\\crud\\files\\{$crudControllerName}Files","Ubiquity\\controllers\\crud\\CRUDFiles");
		$classContent .= $this->scaffoldController->_createMethod ( "protected", "getFiles", "", ": CRUDFiles", "\t\treturn new {$crudControllerName}Files();" );
		$classFilesContent = [ ];
		foreach ( $crudViews as $file ) {
			if (isset ( ScaffoldController::$views ["CRUD"] [$file] )) {
				$frameworkViewname = ScaffoldController::$views ["CRUD"] [$file];
				$this->scaffoldController->createAuthCrudView ( $frameworkViewname, $crudControllerName, $file );
				$classFilesContent [] = $this->scaffoldController->_createMethod ( "public", "getView" . \ucfirst ( $file ), "", "", "\t\treturn \"" . $crudControllerName . "/" . $file . ".html\";" );
			}
		}
		$messages [] = $this->createCRUDFilesClass ( \implode ( "", $classFilesContent ) );
	}

	protected function createCRUDDatasClass() {
		$ns = $this->controllerNS . "crud\\datas";
		$uses = "\nuse Ubiquity\\controllers\\crud\\CRUDDatas;";
		return $this->scaffoldController->_createClass ( "class.tpl", $this->controllerName . "Datas", $ns, $uses, "extends CRUDDatas", "\t//use override/implement Methods" );
	}

	protected function createModelViewerClass() {
		$ns = $this->controllerNS . "crud\\viewers";
		$uses = "\nuse Ubiquity\\controllers\\crud\\viewers\\ModelViewer;";
		return $this->scaffoldController->_createClass ( "class.tpl", $this->controllerName . "Viewer", $ns, $uses, "extends ModelViewer", "\t//use override/implement Methods" );
	}

	protected function createEventsClass() {
		$ns = $this->controllerNS . "crud\\events";
		$uses = "\nuse Ubiquity\\controllers\\crud\\CRUDEvents;";
		return $this->scaffoldController->_createClass ( "class.tpl", $this->controllerName . "Events", $ns, $uses, "extends CRUDEvents", "\t//use override/implement Methods" );
	}

	public function createCRUDFilesClass($classContent = "") {
		$ns = $this->controllerNS . "crud\\files";
		$uses = "\nuse Ubiquity\\controllers\\crud\\CRUDFiles;";
		return $this->scaffoldController->_createClass ( "class.tpl", $this->controllerName . "Files", $ns, $uses, "extends CRUDFiles", $classContent );
	}
}

