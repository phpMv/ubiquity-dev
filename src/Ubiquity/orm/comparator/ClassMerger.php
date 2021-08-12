<?php


namespace Ubiquity\orm\comparator;


use Ubiquity\orm\creator\Member;
use Ubiquity\orm\creator\Model;
use Ubiquity\utils\base\UIntrospection;

/**
 * Merge a model class with an existing model class.
 * Ubiquity\orm\comparator$ClassMerger
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.0
 * @category ubiquity.dev
 *
 */
class ClassMerger {
    protected $class;
    protected $classCode;
    protected Model $model;
    protected $merged;
    protected $extCode;
    
    public function __construct(string $class,Model $model){
        $this->class=$class;
        $this->classCode=UIntrospection::getClassCode($class);
        $this->model=$model;
        $this->merged=false;
        $this->extCode='';
    }
    
    public function merge(){
        if(!$this->merged) {
            $r = new \ReflectionClass($this->class);
            $properties = $r->getProperties();
            $newMembers = $this->model->getMembers();
            $annotsEngine = $this->model->getAnnotsEngine();
            foreach ($properties as $property) {
                $propName = $property->getName();
                $propComparator = new MemberComparator($this->class, $propName);
                if (isset($newMembers[$propName])) {
                    $newMember = $newMembers[$propName];
                    $propComparator->compareTo($newMember);
                    $preservedAttributes = $propComparator->compareAttributes();
                    if (\count($preservedAttributes) > 0) {
                        $newMember->addAnnotations($preservedAttributes);
                    }
                } else {
                    if ($propComparator->maintain()) {
                        $m = new Member($this->model, $annotsEngine, $propName);
                        $m->setTransient();
                        $this->model->addMember($m);
                    }
                }
            }
            if(\method_exists(\ReflectionMethod::class, 'getAttributes')){
                $actualMethods=$r->getMethods();
                $newMethods=$this->model->getMethods();
                foreach ($actualMethods as $reflectionMethod){
                    $code='';
                    $methodName=$reflectionMethod->getName();
                    if(\array_search($methodName,$newMethods)===false){
                        $code=UIntrospection::getMethodCode($reflectionMethod,$this->classCode);
                        $annotations=$reflectionMethod->getAttributes();
                        if(\count($annotations)>0) {
                            $code = $this->model->getAnnotsEngine()->getAnnotationsStr($annotations).$code;
                        }
                    }
                    $this->extCode.=$code;
                }
            }
            $this->merged = true;
        }
    }
    
    private function removeBlank($str){
        return \str_replace ([' ',PHP_EOL,"\t"], '', $str);
    }
    
    public function getMethodCode($methodName,$newCode){
        if(\method_exists($this->class,$methodName)){
            $r=new \ReflectionMethod($this->class.'::'.$methodName);
            $oldCode=UIntrospection::getMethodCode($r,$this->classCode);
            if(\method_exists(\ReflectionMethod::class,'getAttributes')){
                $annotations=$r->getAttributes();
                if(\count($annotations)>0) {
                    $oldCode = $this->model->getAnnotsEngine()->getAnnotationsStr($annotations).$oldCode;
                }
                if($this->removeBlank($oldCode)!==$this->removeBlank($newCode)){
                    return $oldCode;
                }
            }
        }
        return $newCode;
    }
    
    /**
     * @return string
     */
    public function getExtCode(): string {
        return $this->extCode;
    }

}
