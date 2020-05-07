<?php
namespace Ubiquity\orm\creator;

/**
 * Allows the creation of a model class.
 * Ubiquity\orm\creator$Model
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.2
 * @package ubiquity.dev
 *
 */
class Model {

	private $members;

	private $name;

	private $table;

	private $namespace;

	private $database;

	public function __construct($name, $namespace = "models") {
		$this->table = $name;
		$this->name = \ucfirst($name);
		$this->members = array();
		$this->namespace = $namespace;
	}

	public function addMember(Member $member) {
		$this->members[$member->getName()] = $member;
		return $this;
	}

	public function addManyToOne($member, $name, $className, $nullable = false) {
		if (\array_key_exists($member, $this->members) === false) {
			$this->addMember(new Member($member));
			$this->removeMember($name);
		}
		$this->members[$member]->addManyToOne($name, $className, $nullable);
	}

	public function removeMember($memberName) {
		if (isset($this->members[$memberName]) && $this->members[$memberName]->isPrimary() === false)
			unset($this->members[$memberName]);
	}

	public function addOneToMany($member, $mappedBy, $className) {
		if (\array_key_exists($member, $this->members) === false) {
			$this->addMember(new Member($member));
		}
		$this->members[$member]->addOneToMany($mappedBy, $className);
	}

	public function addManyToMany($member, $targetEntity, $inversedBy, $joinTable, $joinColumns = [], $inverseJoinColumns = []) {
		if (\array_key_exists($member, $this->members) === false) {
			$this->addMember(new Member($member));
		}
		$this->members[$member]->addManyToMany($targetEntity, $inversedBy, $joinTable, $joinColumns, $inverseJoinColumns);
	}

	public function __toString() {
		$result = "<?php\n";
		if ($this->namespace !== "" && $this->namespace !== null) {
			$result .= "namespace " . $this->namespace . ";\n";
		}
		if ($this->database != null && $this->database !== 'default') {
			$result .= $this->getAnnotation("database('{$this->database}')");
		}
		if ($this->table !== $this->name) {
			$result .= $this->getAnnotation("table('{$this->table}')");
		}
		$result .= "class " . ucfirst($this->name) . "{";
		$members = $this->members;
		\array_walk($members, function ($item) {
			return $item . "";
		});
		$result .= \implode("", $members);
		foreach ($members as $member) {
			$result .= $member->getGetter();
			$result .= $member->getSetter();
		}
		$result .= $this->getToString();
		$result .= "\n}";
		return $result;
	}

	public function getName() {
		$namespace = "";
		if ($this->namespace !== "" && $this->namespace !== null)
			$namespace = $this->namespace . '\\';
		return $namespace . $this->name;
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
		return $count == \sizeof($this->members);
	}

	public function getPrimaryKey() {
		foreach ($this->members as $member) {
			if ($member->isPrimary() === true) {
				return $member;
			}
		}
		return null;
	}

	public function getPkName() {
		$pk = $this->getPrimaryKey();
		if (isset($pk))
			return $pk->getName();
		return null;
	}

	public function getDefaultFk() {
		return "id" . $this->name;
	}

	public function getManyToOneMembers() {
		$result = array();
		foreach ($this->members as $member) {
			if ($member->isManyToOne() === true) {
				$result[] = $member;
			}
		}
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

	private function getAnnotation($content) {
		return "/**\n * @{$content}\n*/\n";
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
}
