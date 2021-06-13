<?php

namespace Ubiquity\orm\comparator;


use Ubiquity\cache\CacheManager;
use Ubiquity\orm\creator\Member;

/**
 * Compare model members with an existing model.
 * Ubiquity\orm\comparatorMemberComparator
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.0
 * @category ubiquity.dev
 *
 */
class MemberComparator {
    protected $member;
    protected $class;
    protected Member $newMember;
    
    public function __construct(string $class,string $member){
        $this->class=$class;
        $this->member=$member;
    }
    
    public function compareTo(Member $member){
        $this->newMember=$member;
    }
    
    
    protected function getAnnotations(string $class,string $member): array {
        return CacheManager::getAnnotationsEngineInstance()->getAnnotsOfProperty($class,$member);
    }
    
    public function compareAttributes(){
        $myAnnots=$this->getAnnotations($this->class,$this->member);
        $otherAnnots=$this->newMember->getAnnotations();
        $result=[];
        foreach ($myAnnots as $myAnnot){
            $index=$this->indexOfAnnotation($myAnnot,$otherAnnots);
            if($index===-1){
                $result[]=$myAnnot;
            }
        }
        return $result;
    }
    
    protected function indexOfAnnotation($annotation,array $annots){
        $contains=-1;
        foreach ($annots as $index=>$annot){
            if($annot->isSameAs($annotation)){
                return $index;
            }
        }
        return $contains;
    }
    
    public function maintain(){
        return \count(CacheManager::getAnnotationsEngineInstance()->getAnnotsOfProperty($this->class,$this->member,'transient'))>0;
    }
}
