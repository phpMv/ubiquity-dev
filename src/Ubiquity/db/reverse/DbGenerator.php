<?php
namespace Ubiquity\db\reverse;

use Ubiquity\db\Database;
use Ubiquity\db\providers\AbstractDbWrapper;
use Ubiquity\db\providers\DbOperations;
use Ubiquity\orm\reverse\TableReversor;
use Ubiquity\orm\OrmUtils;
use Ubiquity\cache\ClassUtils;
use Ubiquity\db\utils\DbTypes;

/**
 * Ubiquity\db\reverse$DbGenerator
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.2
 * @category ubiquity.dev
 *
 */
class DbGenerator {

	protected $nameProtection;

	protected $createDatabaseMask;

	protected $createTableMask;

	protected $fieldMask;

	protected $foreignKeyMask;

	protected $alterTableMask;

	protected $alterTableAddKey;

	protected $autoIncMask;

	protected $selectDbMask;

	protected $modifyFieldMask;

	protected $addFieldMask;

	protected $constraintNames = [];

	protected $sqlScript = [];

	protected $fieldTypes;

	protected $defaultType;

	protected $manyToManys = [];

	protected $tablesToCreate;
	
	protected $migrationMode;

	public function isInt($fieldType) {
		return DbTypes::isInt($fieldType);
	}

	public function __construct() {
		$this->nameProtection = '`';
		$this->createDatabaseMask = 'CREATE DATABASE {name}';
		$this->selectDbMask = "USE {name}";
		$this->createTableMask = 'CREATE TABLE {name} ({fields}) {attributes}';
		$this->fieldMask = '{name} {type} {extra}';
		$this->alterTableMask = 'ALTER TABLE {tableName} {alter}';
		$this->foreignKeyMask = 'ALTER TABLE {tableName} ADD CONSTRAINT {fkName} FOREIGN KEY ({fkFieldName}) REFERENCES {referencesTableName} ({referencesFieldName}) ON DELETE {onDelete% ON UPDATE {onUpdate}';
		$this->alterTableAddKey = 'ALTER TABLE {tableName} ADD {type} KEY ({pkFields})';
		$this->autoIncMask = 'ALTER TABLE {tableName} MODIFY {fieldInfos} AUTO_INCREMENT, AUTO_INCREMENT={value}';
		$this->addFieldMask='ALTER TABLE {tableName} ADD {fieldName} {attributes}';
		$this->modifyFieldMask='ALTER TABLE {tableName} MODIFY {fieldName} {attributes}';
		$this->fieldTypes = DbTypes::TYPES;
		$this->defaultType = DbTypes::DEFAULT_TYPE;
	}
	
	public function setDatabaseWrapper(AbstractDbWrapper $wrapper){
		$this->nameProtection=$wrapper->quote;
		$this->createDatabaseMask = $wrapper->migrateOperation(DbOperations::CREATE_DATABASE);
		$this->selectDbMask = $wrapper->migrateOperation(DbOperations::SELECT_DB);
		$this->createTableMask = $wrapper->migrateOperation(DbOperations::CREATE_TABLE);
		$this->fieldMask = $wrapper->migrateOperation(DbOperations::FIELD);
		$this->alterTableMask = $wrapper->migrateOperation(DbOperations::ALTER_TABLE);
		$this->foreignKeyMask = $wrapper->migrateOperation(DbOperations::FOREIGN_KEY);
		$this->alterTableAddKey = $wrapper->migrateOperation(DbOperations::ALTER_TABLE_KEY);
		$this->autoIncMask = $wrapper->migrateOperation(DbOperations::AUTO_INC);
		$this->addFieldMask=$wrapper->migrateOperation(DbOperations::ADD_FIELD);
		$this->modifyFieldMask=$wrapper->migrateOperation(DbOperations::MODIFY_FIELD);
		
	}

	public function setTablesToCreate(array $tables) {
		$this->tablesToCreate = $tables;
	}

	public function createDatabase($name) {
		$script = $this->replaceMask('name', $name, $this->createDatabaseMask);
		return $this->addScript('head', $script);
	}

	public function selectDatabase($name) {
		$script = $this->replaceMask('name', $name, $this->selectDbMask);
		return $this->addScript('head', $script);
	}

	public function createTable($name, $fieldsAttributes, $attributes = []) {
		$fields = $this->generateFields($fieldsAttributes);
		$attributes = \implode(" ", $attributes);
		$script = $this->replaceArrayMask([
			'name' => $name,
			'fields' => $fields,
			'attributes' => $attributes
		], $this->createTableMask);
		return $this->addScript('body', $script);
	}

	public function addKey($tableName, $fieldNames, $type = 'PRIMARY') {
		$pks = [];
		foreach ($fieldNames as $fieldName) {
			$pks[] = $this->nameProtection . $fieldName . $this->nameProtection;
		}
		$script = $this->replaceArrayMask([
			'tableName' => $tableName,
			'pkFields' => \implode(",", $pks),
			'type' => $type
		], $this->alterTableAddKey);
		return $this->addScript('before-constraints', $script);
	}

	public function addForeignKey($tableName, $fkFieldName, $referencesTableName, $referencesFieldName, $fkName = null, $onDelete = 'CASCADE', $onUpdate = 'NO ACTION') {
		if (! isset($fkName)) {
			$fkName = $this->checkConstraintName('fk_' . $tableName . '_' . $referencesTableName);
		}
		$script = $this->replaceArrayMask([
			'tableName' => $tableName,
			'fkName' => $fkName,
			'fkFieldName' => $fkFieldName,
			'referencesTableName' => $referencesTableName,
			'referencesFieldName' => $referencesFieldName,
			'onDelete' => $onDelete,
			'onUpdate' => $onUpdate
		], $this->foreignKeyMask);
		return $this->addScript('constraints', $script);
	}

	public function addAutoInc($tableName, $fieldName,$fieldInfos, $value = 1) {
		$script = $this->replaceArrayMask([
			'tableName' => $tableName,
			'fieldInfos' => $fieldInfos,
			'fieldName'=>$fieldName,
			'seqName'=>'seq_'.$tableName,
			'value' => $value
		], $this->autoIncMask);
		return $this->addScript('before-constraints', $script);
	}
	
	public function addField($tableName,$fieldName,$fieldAttributes){
		$this->addOrUpdateField($tableName,$fieldName,$fieldAttributes,'addFieldMask');
	}

	public function modifyField($tableName,$fieldName,$fieldAttributes){
		$this->addOrUpdateField($tableName,$fieldName,$fieldAttributes,'modifyFieldMask');
	}
	
	protected function addScript($key, $script) {
		if (! isset($this->sqlScript[$key])) {
			$this->sqlScript[$key] = [];
		}
		$this->sqlScript[$key][] = $script;
		return $script;
	}

	protected function addOrUpdateField($tableName,$fieldName,$fieldAttributes,$part='addFieldMask'){
		$fieldAttributes=$this->checkFieldAttributes($fieldAttributes,false);
		$script = $this->replaceArrayMask([
			'tableName' => $tableName,
			'fieldName' => $fieldName,
			'attributes' => \implode(" ", $fieldAttributes)
		], $this->{$part});
		return $this->addScript('body', $script);
	}

	protected function checkConstraintName($name) {
		if (\array_search($name, $this->constraintNames)) {
			$matches = [];
			if (\preg_match('@([\s\S]*?)((?:\d)+)$@', $name, $matches)) {
				if (isset($matches[2])) {
					$nb = \intval($matches[2]) + 1;
					$name = $matches[1] . $nb;
				}
			} else {
				$name = $name . "1";
			}
		}
		$this->constraintNames[] = $name;
		return $name;
	}

	public function generateField($fieldAttributes, $forPk = false) {
		$fieldAttributes = $this->checkFieldAttributes($fieldAttributes, $forPk);
		return $this->replaceArrayMask($fieldAttributes, $this->fieldMask);
	}

	protected function checkFieldAttributes($fieldAttributes, $forPk = false) {
		$result = $fieldAttributes;
		$type = $fieldAttributes['type'];
		$existingType = false;
		$strType = DbTypes::getType($type);
		if (isset($strType)) {
			if (isset($this->fieldTypes[$strType])) {
				if (! $forPk && (! isset($fieldAttributes['extra']) || $fieldAttributes['extra'] == '')) {
					$result['extra'] = 'DEFAULT ' . $this->fieldTypes[$strType];
				}
				$existingType = true;
			}
		}
		if (! $existingType) {
			$result['type'] = $this->defaultType;
		}
		return $result;
	}

	protected function generateFields($fieldsAttributes) {
		$result = [];
		foreach ($fieldsAttributes as $fieldAttribute) {
			$result[] = $this->generateField($fieldAttribute);
		}
		return \implode(",", $result);
	}

	protected function replaceMask($key, $value, $mask) {
		if (\strstr(\strtolower($key), 'name'))
			$value = $this->nameProtection . $value . $this->nameProtection;
		return \str_replace('{'.$key.'}', $value, $mask);
	}

	protected function replaceArrayMask($keyValues, $mask) {
		foreach ($keyValues as $key => $value) {
			$mask = $this->replaceMask($key, $value, $mask);
		}
		return $mask;
	}

	public function getSqlScript() {
		return $this->sqlScript;
	}

	public function addManyToMany($jointableInfos, $targetEntity) {
		$jointable=$jointableInfos['name'];
		if (! isset($this->manyToManys[$jointable])) {
			$this->manyToManys[$jointable] = [];
		}
		$this->manyToManys[$jointable][] = ['targetEntity'=>$targetEntity,'jointableInfos'=>$jointableInfos];
	}

	public function generateManyToManys() {
		foreach ($this->manyToManys as $joinTable => $infos) {
			if ($this->hasToCreateTable($joinTable)) {
				$this->generateManyToMany($joinTable,$infos);
			}
		}
	}

	public function hasToCreateTable(string $table) {
		if (\is_array($this->tablesToCreate)) {
			return \array_search($table, $this->tablesToCreate) !== false;
		}
		return $this->migrationMode;
	}

	protected function generateManyToMany($joinTable, $infos) {
		$fields = [];
		$fieldTypes = [];
		$manyToOnes = [];
		$invertedJoinColumns = [];
		foreach ($infos as $info) {
			$targetEntity=$info['targetEntity'];
			$joinTableInfos=$info['jointableInfos'];
			$pk = OrmUtils::getFirstKey($targetEntity);
			$shortClassName = ClassUtils::getClassSimpleName($targetEntity);
			$fieldName = $joinTableInfos['inverseJoinColumns']['name']??($pk . \ucfirst($shortClassName));
			$fields[] = $fieldName;
			$type = OrmUtils::getFieldType($targetEntity, $pk);
			$fieldTypes[$fieldName] = $type;
			$memberName = \lcfirst($shortClassName);
			$manyToOnes[] = $memberName;
			$invertedJoinColumns[$fieldName] = [
				"member" => $memberName,
				"className" => $targetEntity
			];
		}
		$metas = [
			'#tableName' => $joinTable,
			'#primaryKeys' => \array_combine($fields, $fields),
			'#nullable' => [],
			'#notSerializable' => [],
			'#fieldTypes' => $fieldTypes,
			'#manyToOne' => $manyToOnes,
			'#invertedJoinColumn' => $invertedJoinColumns,
			'#oneToMany' => [],
			'#joinTable' => [],
			'#manyToMany' => [],
			'#fieldNames' => $fields,
			'#memberNames' => $fields
		];

		$tableGenerator = new TableReversor();
		$tableGenerator->init($metas);
		$tableGenerator->generateSQL($this);
	}

	public function getScript(): array {
		$scripts = \array_merge($this->sqlScript['head']??[], $this->sqlScript['body']??[]);
		if (isset($this->sqlScript['before-constraints'])) {
			$scripts = \array_merge($scripts, $this->sqlScript['before-constraints']);
		}
		if (isset($this->sqlScript['constraints'])) {
			$scripts = \array_merge($scripts, $this->sqlScript['constraints']);
		}
		return $scripts;
	}

	public function __toString() {
		$scripts = $this->getScript();
		if(\count($scripts)>0) {
			return \implode(";\n", $scripts) . ';';
		}
		return '';
	}

	/**
	 * @param mixed $migrationMode
	 */
	public function setMigrationMode($migrationMode): void {
		$this->migrationMode = $migrationMode;
	}
	
}
