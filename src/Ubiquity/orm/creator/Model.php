<?php
namespace Ubiquity\orm\creator;

use Ubiquity\annotations\AnnotationsEngineInterface;
use Ubiquity\creator\HasUsesTrait;
use Ubiquity\orm\comparator\ClassMerger;

/**
 * Allows the creation of a model class.
 * Ubiquity\orm\creator$Model
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.7
 * @category ubiquity.dev
 *
 */
class Model {
	use HasUsesTrait;

	private $simpleMembers;

	private $members;

	private $name;

	private $table;

	private $namespace;

	private $database;

	private $memberAccess;

	private $annotsEngine;

	private $annots;

	private $classMerger;

	public function __construct($annotsEngine, $name, $namespace = 'models', $memberAccess = 'private') {
		$this->annotsEngine = $annotsEngine;
		$this->table = $name;
		$this->name = \ucfirst($name);
		$this->members = array();
		$this->namespace = $namespace;
		$this->memberAccess = $memberAccess;
		$this->uses = [];
	}

	/**
	 *
	 * @return array
	 */
	public function getMembers(): array {
		return $this->members;
	}

	public function getMethods() {
		$methods = [
			'__toString'
		];
		if ($this->hasConstructor()) {
			$methods[] = '__construct';
		}
		foreach ($this->members as $member) {
			$methods = \array_merge($methods, $member->getMethods());
		}
		return $methods;
	}

	private function hasConstructor(): bool {
		foreach ($this->members as $member) {
			if ($member->isMany()) {
				return true;
			}
		}
		return false;
	}

	/**
	 *
	 * @return AnnotationsEngineInterface
	 */
	public function getAnnotsEngine() {
		return $this->annotsEngine;
	}

	public function addManyToOne($member, $name, $className, $alternateName) {
		$this->checkForUniqName($member, $alternateName);
		$nullable = false;
		if (\array_key_exists($member, $this->members) === false) {
			$this->addMember(new Member($this, $this->annotsEngine, $member, $this->memberAccess));
			if (isset($this->members[$name])) {
				$nullable = $this->members[$name]->isNullable();
				$this->removeMember($name);
			}
		}
		$this->members[$member]->addManyToOne($name, $className, $nullable);
	}

	private function checkForUniqName(&$member, $alternateName) {
		if (isset($this->members[$member]) && \array_search($member, $this->simpleMembers) === false) {
			if ($alternateName != null) {
				$this->checkForUniqName($alternateName, null);
				$member = $alternateName;
			} else {
				$member = $this->generateUniqName($member);
			}
		}
	}

	private function generateUniqName($member) {
		$i = 1;
		do {
			$name = $member . $i;
			$i ++;
		} while (isset($this->members[$name]));
		return $name;
	}

	public function addMember(Member $member) {
		$this->members[$member->getName()] = $member;
		return $this;
	}

	public function removeMember($memberName) {
		if (isset($this->members[$memberName]) && $this->members[$memberName]->isPrimary() === false)
			unset($this->members[$memberName]);
	}

	public function removeOneToManyMemberByClassAssociation($className) {
		$toDelete = [];
		foreach ($this->members as $name => $member) {
			$annotations = $member->getAnnotations();
			foreach ($annotations as $annotation) {
				if ($this->annotsEngine->is('oneToMany', $annotation)) {
					if ($annotation->className === $className) {
						$toDelete[] = $name;
						break;
					}
				}
			}
		}
		foreach ($toDelete as $name) {
			unset($this->members[$name]);
		}
	}

	public function addOneToMany($member, $mappedBy, $className, $alternateName) {
		$this->checkForUniqName($member, $alternateName);
		if (\array_key_exists($member, $this->members) === false) {
			$this->addMember(new Member($this, $this->annotsEngine, $member, $this->memberAccess));
		}
		$this->members[$member]->addOneToMany($mappedBy, $className);
	}

	public function addManyToMany($member, $targetEntity, $inversedBy, $joinTable, $joinColumns = [], $inverseJoinColumns = [], $alternateName = null) {
		$this->checkForUniqName($member, $alternateName);
		if (\array_key_exists($member, $this->members) === false) {
			$this->addMember(new Member($this, $this->annotsEngine, $member, $this->memberAccess));
		}
		$this->members[$member]->addManyToMany($targetEntity, $inversedBy, $joinTable, $joinColumns, $inverseJoinColumns);
		return $member;
	}

	public function addMainAnnots() {
		$annots = [];
		if (\class_exists('\AllowDynamicProperties')) {
			$annots[]='#[\AllowDynamicProperties()]';
		}
		if ($this->database != null && $this->database !== 'default') {
			$annots[] = $this->annotsEngine->getAnnotation($this, 'database', [
				'name' => $this->database
			]);
		}
		if ($this->table !== $this->name) {
			$annots[] = $this->annotsEngine->getAnnotation($this, 'table', [
				'name' => $this->table
			]);
		}
		$this->annots = $annots;
	}

	public function __toString() {
		$cm = $this->getClassMerger();
		$result = "<?php\n";
		if ($this->namespace !== '' && $this->namespace !== null) {
			$result .= 'namespace ' . \rtrim($this->namespace, '\\') . ";\n";
		}

		if (\count($this->uses) > 0) {
			$result .= "\n" . $this->getUsesStr() . "\n";
		}

		if (\count($this->annots) > 0) {
			$result .= $this->annotsEngine->getAnnotationsStr($this->annots, '') . "\n";
		}
		$result .= 'class ' . \ucfirst($this->name) . '{';
		$members = $this->members;
		\array_walk($members, function ($item) {
			return $item . '';
		});
		$result .= \implode('', $members) . "\n";
		$result .= $this->generateMethods($members, $cm);
		$result .= "\n}";

		return $result;
	}

	protected function getClassMerger(): ?ClassMerger {
		if (\class_exists('\\' . $this->getName(), true)) {
			if ($this->classMerger == null) {
				$this->classMerger = new ClassMerger($this->getName(), $this);
				$this->classMerger->merge();
			}
			return $this->classMerger;
		}
		return null;
	}

	public function getName() {
		$namespace = '';
		if ($this->namespace !== '' && $this->namespace !== null) {
			$namespace = \rtrim($this->namespace, '\\') . '\\';
		}
		return $namespace . $this->name;
	}

	/**
	 *
	 * @param Member[] $members
	 * @param ?ClassMerger $classMerger
	 * @return string
	 */
	protected function generateMethods(array $members, ?ClassMerger $classMerger) {
		$result = '';
		if ($classMerger == null) {
			if ($this->hasConstructor()) {
				$result = $this->getConstructor() . PHP_EOL;
			}
			foreach ($members as $member) {
				$result .= $member->getGetter() . PHP_EOL;
				$result .= $member->getSetter() . PHP_EOL;
				if ($member->isManyToMany()) {
					$result .= $member->getAddInManyToManyMember() . PHP_EOL;
				}
				if ($member->isOneToMany()) {
					$result .= $member->getAddInOneToManyMember() . PHP_EOL;
				}
			}
			$result .= $this->getToString();
		} else {
			if ($this->hasConstructor()) {
				$result = $classMerger->getMethodCode('__construct', $this->getConstructor()) . PHP_EOL;
			}
			foreach ($members as $member) {
				$result .= $classMerger->getMethodCode($member->getGetterName(), $member->getGetter()) . PHP_EOL;
				$result .= $classMerger->getMethodCode($member->getSetterName(), $member->getSetter()) . PHP_EOL;
				if ($member->isManyToMany()) {
					$result .= $classMerger->getMethodCode($member->getInManyToManyMemberName(), $member->getAddInManyToManyMember()) . PHP_EOL;
				}
				if ($member->isOneToMany()) {
					$result .= $classMerger->getMethodCode($member->getInOneToManyMemberName(), $member->getAddInOneToManyMember()) . PHP_EOL;
				}
			}
			$result .= $classMerger->getMethodCode('__toString', $this->getToString()) . PHP_EOL;
			$result .= $classMerger->getExtCode();
		}
		return $result;
	}

	public function getConstructor() {
		$initializes = [];
		foreach ($this->members as $member) {
			if ($member->isMany()) {
				$initializes[] = "\t\t\$this->" . $member->getName() . ' = [];';
			}
		}
		if (\count($initializes) > 0) {
			$corps = \implode(PHP_EOL, $initializes);
			$result = "\n\t public function __construct(){\n";
			$result .= "$corps\n";
			$result .= "\t}\n";
			return $result;
		}
		return '';
	}

	public function getToString() {
		$field = $this->getToStringField();
		if (isset($field)) {
			$corps = '($this->' . $field . "??'no value').''";
		} elseif (($pkName = $this->getPkName()) !== null) {
			$corps = '$this->' . $pkName . ".''";
		} else {
			$corps = '"' . $this->name . '@"' . '.\spl_object_hash($this)';
		}
		$result = "\n\t public function __toString(){\n";
		$result .= "\t\t" . 'return ' . $corps . ";\n";
		$result .= "\t}\n";
		return $result;
	}

	private function getToStringField() {
		$result = null;

		foreach ($this->members as $member) {
			if ($member->getDbType() !== 'mixed' && $member->isNullable() !== true && ! $member->isPrimary()) {
				$memberName = $member->getName();
				if (! $member->isPassword()) {
					$result = $memberName;
				}
			}
		}
		return $result;
	}

	public function getPkName() {
		$pk = $this->getPrimaryKey();
		if (isset($pk)) {
			return $pk->getName();
		}
		return null;
	}

	public function getPrimaryKey() {
		foreach ($this->members as $member) {
			if ($member->isPrimary() === true) {
				return $member;
			}
		}
		return null;
	}

	public function getSimpleName() {
		return $this->name;
	}

	public function setDatabase($database) {
		$this->database = $database;
	}

	public function isAssociation() {
		$count = 0;
		foreach ($this->members as $member) {
			if ($member->isManyToOne() === true || $member->isPrimary() === true) {
				$count ++;
			}
		}
		return $count == \count($this->members);
	}

	public function getDefaultFk() {
		return 'id' . $this->name;
	}

	public function getManyToOneMembers() {
		$result = [];
		foreach ($this->members as $member) {
			if ($member->isManyToOne() === true) {
				$result[] = $member;
			}
		}
		return $result;
	}

	public function setSimpleMembers($members) {
		$this->simpleMembers = $members;
	}

	/**
	 * @param mixed $table
	 */
	public function setTable($table): void {
		$this->table = $table;
	}
	
}
