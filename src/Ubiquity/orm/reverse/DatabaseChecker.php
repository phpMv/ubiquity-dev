<?php
namespace Ubiquity\orm\reverse;

use Ubiquity\orm\DAO;
use Ubiquity\db\Database;
use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\Startup;
use Ubiquity\orm\parser\Reflexion;

/**
 * Check a database with models & cache info.
 * Ubiquity\orm\reverse$DatabaseChecker
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.0
 * @package Ubiquity.dev
 *
 */
class DatabaseChecker {

	private string $dbOffset;

	private Database $db;

	private array $models;

	private array $metadatas;

	public function __construct(string $dbOffset = 'default') {
		$this->dbOffset = $dbOffset;
		$this->models = CacheManager::getModels(Startup::$config, true, $this->dbOffset);
		foreach ($this->models as $model) {
			$this->metadatas[$model] = CacheManager::getOrmModelCache($model);
		}
	}

	public function checkDatabase(): bool {
		$this->db = DAO::getDatabase($this->dbOffset);
		return isset($this->db) && $this->db->isConnected();
	}

	public function getNonExistingTables(): array {
		$existingTables = $this->db->getTablesName();
		$tables = Reflexion::getAllJoinTables($this->models);
		foreach ($this->metadatas as $metas) {
			$tables[] = $metas['#tableName'];
		}
		return \array_diff($tables, $existingTables);
	}

	public function getUpdatedFields(string $model): array {
		$metadatas = $this->metadatas[$model];
		$fields = $metadatas['#fieldNames'];
		$fieldTypes = $metadatas['#fieldTypes'];
		$nullables = $metadatas['#nullable'];
		$originalFieldInfos = $this->db->getFieldsInfos($metadatas['#tableName']);
		$result = [];
		foreach ($fields as $member => $field) {
			$nullable = \array_search($member, $nullables) !== false;
			$fieldInfos = [
				'Type' => $fieldTypes[$member],
				'Null' => $nullable
			];
			if (! isset($originalFieldInfos[$field])) {
				$result['missing'][$field] = $fieldInfos;
			} elseif ($fieldTypes[$member] !== 'mixed' && ($fieldTypes[$member] !== $originalFieldInfos[$field]['Type']) || ($originalFieldInfos[$field]['Nullable'] !== 'NO' && ! $nullable)) {
				$result['updated'][$field] = $fieldInfos;
			}
		}
		return $result;
	}

	public function checkPrimaryKeys(string $model): array {
		$metadatas = $this->metadatas[$model];
		$pks = $metadatas['#primaryKeys'];
		$originalPks = $this->db->getPrimaryKeys($metadatas['#tableName']);
		foreach ($pks as $pk) {
			if (\array_search($pk, $originalPks) === false) {
				return $pks;
			}
		}
		return [];
	}

	public function checkManyToOne(string $model): array {
		$metadatas = $this->metadatas[$model];
		$manyToOnes = $metadatas['#manyToOne'];
		$joinColumns = $metadatas['#joinColumn'];
		$table = $metadatas['#tableName'];
		$result = [];
		foreach ($manyToOnes as $manyToOneMember) {
			$joinColumn = $joinColumns[$manyToOneMember];
			$fkClass = $joinColumn['className'];
			$fkField = $joinColumn['name'];
			$fkTable = $this->metadatas[$fkClass]['#tableName'];
			$fkId = $this->metadatas[$fkClass]['#primaryKeys'][0] ?? 'id';
			$result = \array_merge($result, $this->checkFk($table, $fkField, $fkTable, $fkId));
		}
		return $result;
	}

	private function checkFk($table, $fkField, $fkTable, $fkId) {
		$result = [];
		$originalFks = $this->db->getForeignKeys($fkTable, $fkId, $this->db->getDbName());
		$findedFk = false;
		foreach ($originalFks as $ofk) {
			if ($ofk['TABLE_NAME'] === $table && $ofk['COLUMN_NAME'] === $fkField) {
				$findedFk = true;
				break;
			}
		}
		if (! $findedFk) {
			$result[] = [
				'table' => $table,
				'column' => $fkField,
				'fkTable' => $fkTable,
				'$fkId' => $fkId
			];
		}
		return $result;
	}

	public function checkManyToMany(string $model): array {
		$metadatas = $this->metadatas[$model];
		$manyToManys = $metadatas['#manyToMany'];
		$joinTables = $metadatas['#joinTable'];
		$result = [];
		foreach ($manyToManys as $member => $manyToManyInfos) {
			$joinTableInfos = $joinTables[$member];
			$joinTableName = $joinTableInfos['name'];
			$targetEntity = $manyToManyInfos['targetEntity'];
			$fkTable = $this->metadatas[$targetEntity]['#tableName'];
			$fkId = $this->metadatas[$targetEntity]['#primaryKeys'][0] ?? 'id';
			$fkId = $joinTableInfos['inverseJoinColumns']['referencedColumnName'] ?? $fkId;
			$fkField = $joinTableInfos['inverseJoinColumns']['name'] ?? ($fkId . \ucfirst($fkTable));
			$result = \array_merge($result, $this->checkFk($joinTableName, $fkField, $fkTable, $fkId));
		}
		return $result;
	}
}

