<?php
namespace Ubiquity\orm\creator;

use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\Startup;
use Ubiquity\utils\base\UFileSystem;
use Ubiquity\orm\DAO;

/**
 * Generates models classes.
 * Ubiquity\orm\creator$ModelsCreator
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.9
 * @category ubiquity.dev
 *
 */
abstract class ModelsCreator {

	private $silent = false;

	protected $config;

	protected $tables = array();

	protected $classes = array();

	protected $memberAccess;

	abstract protected function getTablesName();

	abstract protected function getFieldsInfos($tableName);

	abstract protected function getPrimaryKeys($tableName);

	abstract protected function getForeignKeys($tableName, $pkName, $dbName = null);

	protected function init(array $config, string $offset = 'default') {
		$this->config = DAO::getDbOffset($config, $offset);
	}

	public function create(array $config, bool $initCache = true, ?string $singleTable = null, string $offset = 'default', string $memberAccess = 'private') {
		\ob_start();
		$engine = CacheManager::getAnnotationsEngineInstance();
		$this->init($config, $offset);
		$this->memberAccess = $memberAccess;
		$dirPostfix = '';
		$nsPostfix = '';
		if ($offset !== 'default') {
			$dirPostfix = \DS . $offset;
			$nsPostfix = $offset;
		}
		$modelsDir = Startup::getModelsCompletePath() . $dirPostfix;
		if (UFileSystem::safeMkdir($modelsDir)) {
			$this->tables = $this->getTablesName();
			CacheManager::checkCache($config);

			foreach ($this->tables as $table) {
				$class = new Model($engine, $table, Startup::getNS('models') . $nsPostfix, $memberAccess);
				$class->setDatabase($offset);

				$fieldsInfos = $this->getFieldsInfos($table);
				$class->setSimpleMembers(\array_keys($fieldsInfos));
				$keys = $this->getPrimaryKeys($table);
				foreach ($fieldsInfos as $field => $info) {
					$member = new Member($class, $engine, $field, $memberAccess);
					if (\in_array($field, $keys)) {
						$member->setPrimary();
					}
					$member->setDbType($info);
					$member->addValidators();
					$member->setTransformer();
					$class->addMember($member);
				}
				$class->addMainAnnots();
				$this->classes[$table] = $class;
			}
			$this->createRelations();

			if (isset($singleTable)) {
				$this->createOneClass($singleTable, $modelsDir);
			} else {
				foreach ($this->classes as $table => $class) {
					$name = $class->getSimpleName();
					echo "Creating the {$name} class\n";
					$classContent = $class->__toString();
					$this->writeFile($modelsDir . \DS . $name . '.php', $classContent);
				}
			}
			if ($initCache === true) {
				CacheManager::initCache($config, 'models', $this->silent);
			}
		}
		$r = \ob_get_clean();
		if ($this->silent) {
			return $r;
		}
		echo $r;
	}

	protected function createOneClass(string $singleTable, string $modelsDir) {
		if (isset($this->classes[$singleTable])) {
			$class = $this->classes[$singleTable];
			echo "Creating the {$class->getName()} class\n";
			$classContent = $class->__toString();
			$this->writeFile($modelsDir . \DS . $class->getSimpleName() . '.php', $classContent);
		} else {
			echo "The {$singleTable} table does not exist in the database\n";
		}
	}

	protected function createRelations() {
		foreach ($this->classes as $table => $class) {
			$keys = $this->getPrimaryKeys($table);
			foreach ($keys as $key) {
				$fks = $this->getForeignKeys($table, $key, $this->config['dbName'] ?? '');
				foreach ($fks as $fk) {
					$field = \lcfirst($table);
					$fkTable = $fk['TABLE_NAME'];
					$this->classes[$table]->addOneToMany(\lcfirst($fkTable) . 's', \lcfirst($table), $this->classes[$fkTable]->getName(), $this->getAlternateName($fk['COLUMN_NAME'], $fk['REFERENCED_COLUMN_NAME'] ?? $field) . 's');
					$this->classes[$fkTable]->addManyToOne($field, \lcfirst($fk['COLUMN_NAME']), $class->getName(), $this->getAlternateName($fk['COLUMN_NAME'], $fk['REFERENCED_COLUMN_NAME'] ?? $field));
				}
			}
		}
		$this->createManyToMany();
	}

	protected function getAlternateName(string $fkName, string $pkName): string {
		$alter = $fkName;
		$pkName = \ucfirst($pkName);
		if (\substr($fkName, 0, \strlen($pkName)) == $pkName) {
			$alter = \substr($fkName, \strlen($pkName));
		}
		$needle_position = \strlen($pkName) * - 1;

		if (\substr($alter, $needle_position) == $pkName) {
			$alter = \substr($alter, 0, $needle_position);
		}
		$alter = \trim($alter, '_');
		return \lcfirst($alter);
	}

	protected function getTableName(string $classname): string {
		foreach ($this->classes as $table => $class) {
			if ($class->getName() === $classname) {
				return $table;
			}
		}
		$posSlash = strrpos($classname, '\\');
		$tablename = substr($classname, $posSlash + 1);
		return \lcfirst($tablename);
	}

	protected function createManyToMany() {
		foreach ($this->classes as $table => $class) {
			if ($class->isAssociation() === true) {
				$members = $class->getManyToOneMembers();
				if (\count($members) == 2) {
					$manyToOne1 = $members[0]->getManyToOne();
					$manyToOne2 = $members[1]->getManyToOne();
					$table1 = $this->getTableName($manyToOne1->className);
					$table2 = $this->getTableName($manyToOne2->className);
					$class1 = $this->classes[$table1];
					$class2 = $this->classes[$table2];
					$reflexive = ($class1 === $class2);
					if ($reflexive) {
						$table1Member = $table2Member = $table . 's';
						$altName1 = $this->getAlternateName($manyToOne2->name, \current($this->getPrimaryKeys($table1))) . 's';
					} else {
						$table1Member = \lcfirst($table1) . 's';
						$table2Member = \lcfirst($table2) . 's';
						$altName1 = $altName2 = $table . 's';
					}
					$joinTable1 = $this->getJoinTableArray($class1, $manyToOne1);
					$joinTable2 = $this->getJoinTableArray($class2, $manyToOne2);
					$class1->removeOneToManyMemberByClassAssociation($class->getName());
					$class1->addManyToMany($table2Member, $manyToOne2->className, $table1Member, $table, $joinTable1, $joinTable2, $altName1);
					if (! $reflexive) {
						$class2->removeOneToManyMemberByClassAssociation($class->getName());
						$class2->addManyToMany($table1Member, $manyToOne1->className, $table2Member, $table, $joinTable2, $joinTable1, $altName2);
					}
					unset($this->classes[$table]);
				}
			}
		}
	}

	protected function getJoinTableArray(Model $class, object $joinColumn) {
		$pk = $class->getPrimaryKey();
		$fk = $joinColumn->name;
		$dFk = $class->getDefaultFk();
		if ($fk !== $dFk) {
			if ($pk !== null && $fk !== null)
				return [
					'name' => $fk,
					'referencedColumnName' => $pk->getName()
				];
		}
		return [];
	}

	protected function writeFile(string $filename, string $data): int {
		return \file_put_contents($filename, $data);
	}

	/**
	 *
	 * @param boolean $silent
	 */
	public function setSilent(bool $silent): void {
		$this->silent = $silent;
	}
}
