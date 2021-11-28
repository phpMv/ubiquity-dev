<?php
namespace Ubiquity\orm\reverse;

use Ubiquity\db\utils\DbTypes;
use Ubiquity\orm\OrmUtils;
use Ubiquity\db\reverse\DbGenerator;

/**
 * Ubiquity\orm\reverse$TableReversor
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.0
 * @category ubiquity.dev
 *
 */
class TableReversor {

	private $model;

	private $fkFieldsToAdd = [];

	private $fkFieldTypesToAdd = [];

	private $metas;

	public function __construct($model = null) {
		$this->model = $model;
	}

	public function initFromClass() {
		if (isset($this->model))
			$this->metas = OrmUtils::getModelMetadata($this->model);
	}

	public function init($metas) {
		$this->metas = $metas;
	}

	public function getTable() {
		return $this->metas['#tableName'];
	}

	public function generateSQL(DbGenerator $generator) {
		$table = $this->metas['#tableName'];
		$primaryKeys = $this->metas['#primaryKeys'];
		$serializables = $this->getSerializableFields();
		$nullables = $this->metas['#nullable'];
		$fieldTypes = $this->metas['#fieldTypes'];
		$manyToOnes = $this->metas['#manyToOne'];
		$this->scanManyToManys($generator);
		$this->generatePks($generator, $primaryKeys, $table, $fieldTypes, $nullables);
		$this->generateForeignKeys($generator, $manyToOnes, $table);
		$serializables = \array_unique(\array_merge($serializables, $this->fkFieldsToAdd));
		$fieldTypes = \array_merge($fieldTypes, $this->fkFieldTypesToAdd);
		$fieldsAttributes = $this->generateFieldsAttributes($serializables, $fieldTypes, $nullables);
		$generator->createTable($table, $fieldsAttributes);
		foreach ($this->fkFieldsToAdd as $fkField) {
			$generator->addKey($table, [
				$fkField
			], '');
		}
	}

	protected function getSerializableFields() {
		$notSerializable = $this->metas['#notSerializable'];
		$fieldNames = $this->metas['#fieldNames'];
		return \array_diff($fieldNames, $notSerializable);
	}

	public function scanManyToManys(DbGenerator $generator) {
		if (isset($this->metas['#manyToMany'])) {
			$manyToManys = $this->metas['#manyToMany'];
			foreach ($manyToManys as $member => $manyToMany) {
				if (isset($this->metas['#joinTable'][$member])) {
					$annotJoinTable = $this->metas['#joinTable'][$member];
					$generator->addManyToMany($annotJoinTable, $manyToMany['targetEntity']);
				}
			}
		}
	}
	
	public function addPrimaryKeys(DbGenerator $generator,array $primayKeys){
		$nullables = $this->metas['#nullable'];
		$fieldTypes = $this->metas['#fieldTypes'];
		$this->generatePks($generator,$primayKeys,$this->getTable(),$fieldTypes,$nullables);
	}

	protected function generatePks(DbGenerator $generator, $primaryKeys, $table, $fieldTypes, $nullables) {
		if(\is_array($primaryKeys)) {
			$generator->addKey($table, $primaryKeys);
			if (\count($primaryKeys) === 1 && $generator->isInt($fieldTypes[$fpk=\current($primaryKeys)])) {
				$generator->addAutoInc($table, $fpk,$this->getFieldAttributes($generator, $fpk, $nullables, $fieldTypes, true));
			}
		}
	}

	protected function generateFieldsAttributes($serializables, $fieldTypes, $nullables) {
		$fieldsAttributes = [];
		foreach ($serializables as $field) {
			$fieldsAttributes[] = $this->_generateFieldAttributes($field, $nullables, $fieldTypes);
		}
		return $fieldsAttributes;
	}

	public function getFieldAttributes(DbGenerator $generator, $field, $nullables, $fieldTypes, $forPk = false) {
		return $generator->generateField($this->_generateFieldAttributes($field, $nullables, $fieldTypes), $forPk);
	}

	protected function _generateFieldAttributes($field, $nullables, $fieldTypes) {
		$nullable = 'NOT NULL';
		if (\array_search($field, $nullables) !== false) {
			$nullable = '';
		}
		return [
			'name' => $field,
			'type' => $fieldTypes[$field]??DbTypes::DEFAULT_TYPE,
			'extra' => $nullable
		];
	}

	protected function generateForeignKey(DbGenerator $generator, $tableName, $member) {
		$fieldAnnot = OrmUtils::getMemberJoinColumns('', $member, $this->metas);
		if ($fieldAnnot !== null) {
			$annotationArray = $fieldAnnot[1];
			$referencesTableName = OrmUtils::getTableName($annotationArray['className']);
			$referencesFieldName = OrmUtils::getFirstKey($annotationArray['className']);
			$fkFieldName = $fieldAnnot[0];
			$this->fkFieldsToAdd[] = $fkFieldName;
			$this->fkFieldTypesToAdd[$fkFieldName] = OrmUtils::getFieldType($annotationArray['className'], $referencesFieldName);
			$generator->addForeignKey($tableName, $fkFieldName, $referencesTableName, $referencesFieldName);
		}
	}

	protected function generateForeignKeys(DbGenerator $generator, $manyToOnes, $tableName) {
		foreach ($manyToOnes as $member) {
			$this->generateForeignKey($generator, $tableName, $member);
		}
	}
}
