<?php
namespace Ubiquity\orm\creator;

use Ubiquity\contents\validation\ValidationModelGenerator;
use Ubiquity\annotations\AnnotationsEngineInterface;

/**
 * Represents a data member in a model class.
 * Ubiquity\orm\creator$Member
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.3
 * @category ubiquity.dev
 *
 */
class Member {

	private $name;

	private $primary;

	private $manyToOne;

	private $annotations;

	private $access;
	
	private $container;
	
	/**
	 * @var AnnotationsEngineInterface
	 */
	private $annotsEngine;

	public function __construct($container,$annotsEngine,$name, $access = 'private') {
		$this->container=$container;
		$this->annotsEngine=$annotsEngine;
		$this->name = $name;
		$this->annotations = [];
		$this->primary = false;
		$this->manyToOne = false;
		$this->access = $access;
	}

	public function __toString() {
		$annotationsStr = '';
		if (sizeof($this->annotations) > 0) {
			$annotationsStr = "\n".$this->annotsEngine->getAnnotationsStr($this->annotations);
		}
		return $annotationsStr . "\n\t{$this->access} $" . $this->name . ";\n";
	}

	public function setPrimary() {
		if ($this->primary === false) {
			$this->annotations[] = $this->annotsEngine->getAnnotation($this->container,'id');
			$this->primary = true;
		}
	}

	public function setDbType($infos) {
		$annot = $this->annotsEngine->getAnnotation($this->container,'column',['name'=>$this->name,'dbType'=>$infos['Type'],'nullable'=>(\strtolower($infos['Nullable']) === 'yes')]);
		$this->annotations['column'] = $annot;
	}

	public function addManyToOne($name, $className, $nullable = false) {
		$this->annotations[] = $this->annotsEngine->getAnnotation($this->container,'manyToOne');
		$joinColumn = $this->annotsEngine->getAnnotation($this->container,'joinColumn',['name'=>$name,'className'=>$className,'nullable'=>$nullable]);
		$this->annotations[] = $joinColumn;
		$this->manyToOne = true;
	}

	public function addOneToMany($mappedBy, $className) {
		$this->annotations[] = $this->annotsEngine->getAnnotation($this->container,'oneToMany',['mappedBy'=>$mappedBy,'className'=>$className]);;
	}

	private function addTransformer($name) {
		$this->annotations[] = $this->annotsEngine->getAnnotation($this->container,'transformer',['name'=>$name]);;
	}

	/**
	 * Try to set a transformer to the member.
	 */
	public function setTransformer() {
		if ($this->isPassword()) {
			$this->addTransformer('password');
		} else {
			$dbType = $this->getDbType();
			if ($dbType == 'datetime') {
				$this->addTransformer('datetime');
			}
		}
	}

	public function isPassword() {
		// Array of multiple translations of the word "password" which could be taken as name of the table field in database
		$pwArray = array(
			'password',
			'senha',
			'lozinka',
			'heslotajne',
			'helslo_tajne',
			'wachtwoord',
			'contrasena',
			'salasana',
			'motdepasse',
			'mot_de_passe',
			'passwort',
			'passord',
			'haslo',
			'senha',
			'parola',
			'naponb',
			'contrasena',
			'loesenord',
			'losenord',
			'sifre',
			'naponb',
			'matkhau',
			'mat_khau'
		);
		return \in_array($this->name, $pwArray);
	}

	public function addManyToMany($targetEntity, $inversedBy, $joinTable, $joinColumns = [], $inverseJoinColumns = []) {
		$manyToMany = $this->annotsEngine->getAnnotation($this->container,'manyToMany',\compact('targetEntity','inversedBy'));
		$jtArray['name'] = $joinTable;
		if (\count($joinColumns) == 2) {
			$jtArray['joinColumns'] = $joinColumns;
		}
		if (\count($inverseJoinColumns) == 2) {
			$jtArray['inverseJoinColumns'] = $inverseJoinColumns;
		}
		$this->annotations[] = $manyToMany;
		$this->annotations[] = $this->annotsEngine->getAnnotation($this->container,'joinTable',$jtArray);
	}

	public function getName() {
		return $this->name;
	}

	public function isManyToOne() {
		return $this->manyToOne;
	}

	public function getManyToOne() {
		foreach ($this->annotations as $annotation) {
			if ($this->annotsEngine->isManyToOne($annotation)) {
				return $annotation;
			}
		}
		return null;
	}

	public function isPrimary() {
		return $this->primary;
	}

	public function getGetter() {
		$result = "\n\tpublic function get" . \ucfirst($this->name) . "(){\n";
		$result .= "\t\t" . 'return $this->' . $this->name . ";\n";
		$result .= "\t}\n";
		return $result;
	}

	public function getSetter() {
		$result = "\n\tpublic function set" . \ucfirst($this->name) . '($' . $this->name . "){\n";
		$result .= "\t\t" . '$this->' . $this->name . '=$' . $this->name . ";\n";
		$result .= "\t}\n";
		return $result;
	}

	public function getAddInManyMember() {
		$name = $this->name;
		if (\substr($name, - 1) === 's') {
			$name = \substr($name, 0, - 1);
		}
		$result = "\n\t public function add" . \ucfirst($name) . '($' . $name . "){\n";
		$result .= "\t\t" . '$this->' . $this->name . '[]=$' . $name . ";\n";
		$result .= "\t}\n";
		return $result;
	}

	public function hasAnnotations() {
		return \count($this->annotations) > 1;
	}

	public function isMany() {
		foreach ($this->annotations as $annot) {
			if ($this->annotsEngine->isMany($annot)) {
				return true;
			}
		}
		return false;
	}

	public function isNullable() {
		if (isset($this->annotations['column']))
			return $this->annotations['column']->nullable;
		return false;
	}

	public function getDbType() {
		if (isset($this->annotations['column']))
			return $this->annotations['column']->dbType;
		return 'mixed';
	}

	public function addValidators() {
		$parser = new ValidationModelGenerator($this->container,$this->annotsEngine,$this->getDbType(), $this->name, ! $this->isNullable(), $this->primary);
		$validators = $parser->parse();
		if ($validators && \count($validators)) {
			$this->annotations = \array_merge($this->annotations, $validators);
		}
	}

	public function getAnnotations() {
		return $this->annotations;
	}
}
