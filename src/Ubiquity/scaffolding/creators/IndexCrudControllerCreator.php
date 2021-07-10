<?php
namespace src\Ubiquity\scaffolding\creators;

use Ubiquity\scaffolding\creators\CrudControllerCreator;
use Ubiquity\scaffolding\ScaffoldController;

class IndexCrudControllerCreator extends CrudControllerCreator{
    
    public function __construct($crudControllerName, $crudDatas = null, $crudViewer = null, $crudEvents = null, $crudViews = null, $routePath = '', $useViewInheritance = false,$style='') {
        parent::__construct($crudControllerName, null,$crudDatas,$crudViewer,$crudEvents,$crudViews,$routePath,$useViewInheritance,$style);
        $this->templateName='indexCrudController.tpl';
    }
    
    public function create(ScaffoldController $scaffoldController) {
        $this->scaffoldController = $scaffoldController;
        $crudControllerName = $this->controllerName;
        $classContent = '';
        $nsc=\trim($this->controllerNS,'\\');
        $messages = [ ];
        
        $this->createElements($nsc, $crudControllerName, $scaffoldController, $messages);
        
        $this->routePath ??= '{resource}';
        $routePath=$this->routePath;
        
        $uses = $this->getUsesStr();
        $messages [] = $scaffoldController->_createController ( $crudControllerName, [
            '%indexRoute'=>$this->getAnnotation('route', ['name'=>'crud.index']),
            '%homeRoute%'=>$this->getAnnotation('route', ['path'=>$this->getHome($routePath),'name'=>'crud.home']),
            '%routePath%' => '"'.\str_replace('{resource}', '".$this->resource."', $routePath).'"',
            '%route%' => $this->getRouteAnnotation($routePath),
            '%uses%' => $uses,
            '%namespace%' => $this->getNamespaceStr(),
            '%baseClass%' => "\\Ubiquity\\controllers\\crud\\IndexCRUDController",
            '%content%' => $classContent,
            '%style%'=>$this->style
        ]
            , $this->templateName );
        echo implode ( "\n", $messages );
    }
    
    protected function getHome($path) {
        $elements=\explode("\\",$path);
        $r=[];
        foreach ($elements as $index=>$elm){
            if($elm==='{resource}'){
                if($index==0){
                    $r[]='#/home';
                }else{
                    $r[]='home';
                }
            }elseif($elm!=''){
                $r[]=$elm;
            }
        }
        return implode("\\", $r);
    }
}
