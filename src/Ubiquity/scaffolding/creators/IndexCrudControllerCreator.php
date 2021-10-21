<?php
namespace Ubiquity\scaffolding\creators;

use Ubiquity\domains\DDDManager;
use Ubiquity\scaffolding\creators\CrudControllerCreator;
use Ubiquity\scaffolding\ScaffoldController;

class IndexCrudControllerCreator extends CrudControllerCreator{
    
    public function __construct($crudControllerName, $crudDatas = null, $crudViewer = null, $crudEvents = null, $crudViews = null, $routePath = '', $useViewInheritance = false,$style='') {
        parent::__construct($crudControllerName, null,$crudDatas,$crudViewer,$crudEvents,$crudViews,$routePath,$useViewInheritance,$style);
        $this->templateName='indexCrudController.tpl';
        $this->viewKey='indexCRUD';
    }
    
    public function create(ScaffoldController $scaffoldController) {
        $this->scaffoldController = $scaffoldController;
        $crudControllerName = $this->controllerName;
        $classContent = '';
        $nsc=\trim($this->controllerNS,'\\');
        $messages = [ ];
        $domain=DDDManager::getActiveDomain();
		if($domain!=''){
			$scaffoldController->_createMethod ( 'public','initialize','','',"\n\t\tparent::initialize();\n\t\t\Ubiquity\domains\DDDManager::setDomain('".$domain."');");
		}
        $this->createElements($nsc, $crudControllerName, $scaffoldController, $messages,$classContent);
        
        $this->routePath ??= '{resource}';
        $routePath=$this->routePath;
        $routeAnnotation=$this->getRouteAnnotation($routePath);
        
        $uses = $this->getUsesStr();
        $messages [] = $scaffoldController->_createController ( $crudControllerName, [
            '%indexRoute%'=>$this->getAnnotation('route', ['name'=>'crud.index','priority'=>-1]),
            '%homeRoute%'=>$this->getAnnotation('route', ['path'=>$this->getHome($routePath),'name'=>'crud.home','priority'=>100]),
            '%routePath%' => '"'.\str_replace('{resource}', '".$this->resource."', $routePath).'"',
            '%route%' => $routeAnnotation,
            '%uses%' => $uses,
            '%namespace%' => $this->getNamespaceStr(),
            '%baseClass%' => "\\Ubiquity\\controllers\\crud\\MultiResourceCRUDController",
            '%content%' => $classContent
        ]
            , $this->templateName );
        echo implode ( "\n", $messages );
    }
    
    protected function getHome($path) {
        return '#/'.\str_replace('{resource}', 'home',$path);
    }
}

