<?php
namespace Ubiquity\creator;

trait HasUsesTrait {
	protected $uses=[];
	public function getUses(){
		return \array_keys($this->uses);
	}
	
	public function addUse($classname){
		$this->uses[$classname]=true;
	}
	
	public function addUses(...$classnames){
		foreach ($classnames as $classname){
			$this->uses[$classname]=true;
		}
	}
	
	public function getUsesStr(){
		$uses=$this->getUses();
		$r=[];
		foreach ($uses as $use){
			$r[]='use '.\ltrim($use,'\\').';';
		}
		return \implode("\n",$r);
	}
}

