<?php
namespace Ubiquity\orm\reverse;

use Ubiquity\exceptions\DAOException;
use Ubiquity\orm\DAO;
use Ubiquity\db\Database;
use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\Startup;
use Ubiquity\orm\parser\Reflexion;

/**
 * Check the differences between the database and models & cache info.
 * Ubiquity\orm\reverse$DatabaseChecker
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.1
 * @package Ubiquity.dev
 *
 */
class DatabaseChecker {

	private string $dbOffset;

	private $databaseExist;

	private ?Database $db;

	private ?array $models = null;

	private ?array $metadatas = null;

	private ?array $nonExistingTables = null;

	private ?array $checkResults = null;

	public function __construct(string $dbOffset = 'default') {
		$this->dbOffset = $dbOffset;
		$this->models = CacheManager::getModels(Startup::$config, true, $this->dbOffset);
		foreach ($this->models as $model) {
			$this->metadatas[$model] = CacheManager::getOrmModelCache($model);
		}
	}

	public function checkDatabase(): bool {
		try {
			$this->db = DAO::getDatabase($this->dbOffset);
			return $this->databaseExist = isset($this->db) && $this->db->isConnected();
		} catch (\Exception | \Error $e) {
			return $this->databaseExist = false;
		}
	}

	public function getNonExistingTables(): array {
		$existingTables = [];
		if ($this->databaseExist) {
			$existingTables = $this->db->getTablesName();
		}
		$tables = Reflexion::getAllJoinTables($this->models);
		if (isset($this->metadatas)) {
			foreach ($this->metadatas as $model => $metas) {
				$tablename = $metas['#tableName'];
				if (\array_search($tablename, $existingTables) === false && \array_search($tablename, $tables) === false) {
					$tables[$model] = $tablename;
				}
			}
		}
		return \array_diff($tables, $existingTables);
	}

	private function _getNonExistingTables() {
		return $this->nonExistingTables ??= $this->getNonExistingTables();
	}

	protected function tableExists(string $table): bool {
		return \array_search($table, $this->_getNonExistingTables()) === false;
	}

	public function checkAll(): array {
		$result = [];
		$this->databaseExist = true;
		if (! $this->checkDatabase()) {
			$result['database'] = $this->dbOffset;
		}
		$result['nonExistingTables'] = $this->_getNonExistingTables();
		foreach ($this->models as $model) {
			$metadatas = $this->metadatas[$model];
			$tableName = $metadatas['#tableName'];
			$updatedPks = $this->checkPrimaryKeys($model);
			if (\count($updatedPks) > 0) {
				$result['pks'][$tableName] = $updatedPks;
			}
			$updatedFields = $this->getUpdatedFields($model);
			if (\count($updatedFields) > 0) {
				$result['updatedFields'][$tableName] = $updatedFields;
			}
			$manyToOneUpdateds = $this->checkManyToOne($model);
			if (\count($manyToOneUpdateds) > 0) {
				$result['manyToOne'][$tableName] = $manyToOneUpdateds;
			}
			$manyToManyUpdateds = $this->checkManyToMany($model);
			if (\count($manyToManyUpdateds) > 0) {
				$result['manyToMany'][$tableName] = $manyToManyUpdateds;
			}
		}
		return $this->checkResults = $result;
	}

	public function hasErrors(): bool {
		if (\is_array($this->checkResults)) {
			$ckR = $this->checkResults;
			return ($ckR['database'] ?? false) || ($ckR['nonExistingTables'] ?? false) || ($ckR['updatedFields'] ?? false) || ($ckR['pks'] ?? false) || ($ckR['manyToOne'] ?? false) || ($ckR['manyToMany'] ?? false);
		}
		return false;
	}

	public function getResultDatabaseNotExist(): bool {
		return $this->checkResults['database'] ?? false;
	}

	public function getResultNonExistingTables(): array {
		return $this->checkResults['nonExistingTables'] ?? [];
	}

	public function getResultUpdatedFields(): array {
		return $this->checkResults['updatedFields'] ?? [];
	}

	public function getResultPrimaryKeys(): array {
		return $this->checkResults['pks'] ?? [];
	}

	public function getResultManyToOne(): array {
		return $this->checkResults['manyToOne'] ?? [];
	}

	public function getResultManyToMany(): array {
		return $this->checkResults['manyToMany'] ?? [];
	}

	public function getUpdatedFields(string $model): array {
		$result = [];
		$metadatas = $this->metadatas[$model];
		$tableName = $metadatas['#tableName'];
		if ($this->tableExists($tableName)) {
			$fields = $metadatas['#fieldNames'];
			$fieldTypes = $metadatas['#fieldTypes'];
			$nullables = $metadatas['#nullable'];
			$notSerializable = $metadatas['#notSerializable'];
			$originalFieldInfos = [];
			if ($this->databaseExist) {
				$originalFieldInfos = $this->db->getFieldsInfos($tableName);
			}
			foreach ($fields as $member => $field) {
				if (\array_search($member, $notSerializable) === false) {
					$nullable = \array_search($member, $nullables) !== false;
					$fieldInfos = [
						'table' => $tableName,
						'name' => $field,
						'attributes' => [
							'type' => $fieldTypes[$member],
							'extra' => $nullable ? '' : 'NOT NULL'
						]
					];
					if (! isset($originalFieldInfos[$field])) {
						$result['missing'][$model][] = $fieldInfos;
					} elseif ($fieldTypes[$member] !== 'mixed' && ($fieldTypes[$member] !== $originalFieldInfos[$field]['Type']) || ($originalFieldInfos[$field]['Nullable'] !== 'NO' && ! $nullable)) {
						$result['updated'][$model][] = $fieldInfos;
					}
				}
			}
		}
		return $result;
	}

	public function concatArrayKeyValue(array $array, callable $callable, string $sep = ',') {
		$results = [];
		foreach ($array as $value) {
			$results[] = $callable($value);
		}
		return \implode($sep, $results);
	}

	public function checkPrimaryKeys(string $model): array {
		$metadatas = $this->metadatas[$model];
		$tableName = $metadatas['#tableName'];
		if ($this->tableExists($tableName)) {
			$pks = $metadatas['#primaryKeys'];
			$originalPks = [];
			if ($this->databaseExist) {
				$originalPks = $this->db->getPrimaryKeys($tableName);
			}
			if (\is_array($pks)) {
				foreach ($pks as $pk) {
					if (\array_search($pk, $originalPks) === false) {
						return [
							'table' => $tableName,
							'primaryKeys' => $pks,
							'model' => $model
						];
					}
				}
			}
		}
		return [];
	}

	public function checkManyToOne(string $model): array {
		$metadatas = $this->metadatas[$model];
		$manyToOnes = $metadatas['#manyToOne'] ?? [];
		$joinColumns = $metadatas['#joinColumn'] ?? [];
		$table = $metadatas['#tableName'];
		$result = [];
		if ($this->tableExists($table)) {
			foreach ($manyToOnes as $manyToOneMember) {
				$joinColumn = $joinColumns[$manyToOneMember];
				$fkClass = $joinColumn['className'];
				$fkField = $joinColumn['name'];
				$fkTable = $this->metadatas[$fkClass]['#tableName'];
				$fkId = $this->metadatas[$fkClass]['#primaryKeys'][0] ?? 'id';
				$result = \array_merge($result, $this->checkFk($table, $fkField, $fkTable, $fkId));
			}
		}
		return $result;
	}

	private function checkFk($table, $fkField, $fkTable, $fkId) {
		$result = [];
		$originalFks = [];
		if ($this->databaseExist && $this->tableExists($table)) {
			$originalFks = $this->db->getForeignKeys($fkTable, $fkId, $this->db->getDbName());
		}
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
				'fkId' => $fkId
			];
		}
		return $result;
	}

	public function checkManyToMany(string $model): array {
		$metadatas = $this->metadatas[$model];
		$manyToManys = $metadatas['#manyToMany'] ?? [];
		$joinTables = $metadatas['#joinTable'] ?? [];
		$table = $metadatas['#tableName'];
		$result = [];
		if ($this->tableExists($table)) {
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
		}
		return $result;
	}

	/**
	 *
	 * @return Database
	 */
	public function getDb(): Database {
		return $this->db;
	}

	public function displayAll(callable $displayCallable) {
		$dbResults = $this->checkResults;

		if (isset($dbResults['database'])) {
			$displayCallable('error', 'database', "The database at offset <b>" . $dbResults['database'] . "</b> does not exist!");
		}
		if (\count($notExistingTables = $dbResults['nonExistingTables']) > 0) {
			$notExistingTables = \array_unique($notExistingTables);
			foreach ($notExistingTables as $model => $table) {
				if (\is_string($model)) {
					$displayCallable('warning', 'Missing table', "The table <b>" . $table . "</b> does not exist for the model <b>" . $model . "</b>.");
				} else {
					$displayCallable('warning', 'Missing table', "The table <b>" . $table . "</b> does not exist.");
				}
			}
		}
		if (\count($uFields = $this->getResultUpdatedFields()) > 0) {
			foreach ($uFields as $table => $updatedFieldInfos) {
				if (isset($updatedFieldInfos['missing'])) {
					$model = \array_key_first($updatedFieldInfos['missing']);
					if (\count($fInfos = $updatedFieldInfos['missing'][$model] ?? []) > 0) {
						$names = $this->concatArrayKeyValue($fInfos, function ($value) {
							return $value['name'];
						});
						$displayCallable('warning', 'Missing columns', "Missing fields in table <b>`$table`</b> for the model <b>`$model`</b>: <b>($names)</b>");
					}
				}
				if (isset($updatedFieldInfos['updated'])) {
					$model = \array_key_first($updatedFieldInfos['updated']);
					if (\count($fInfos = $updatedFieldInfos['updated'][$model] ?? []) > 0) {
						$names = $this->concatArrayKeyValue($fInfos, function ($value) {
							return $value['name'];
						});
						$displayCallable('warning', 'Updated columns', "Updated fields in table <b>`$table`</b> for the model <b>`$model`</b>: <b>($names)</b>");
					}
				}
			}
		}
		if (\count($pks = $this->getResultPrimaryKeys()) > 0) {
			foreach ($pks as $table => $pksFieldInfos) {
				$model = $pksFieldInfos['model'];
				$names = implode(',', $pksFieldInfos['primaryKeys']);
				$displayCallable('warning', 'Missing key', "Missing primary keys in table <b>`$table`</b> for the model <b>`$model`</b>: <b>($names)</b>");
			}
		}
		if (\count($manyToOnes = $this->getResultManyToOne()) > 0) {
			foreach ($manyToOnes as $table => $manyToOneFieldInfos) {
				$names = $this->concatArrayKeyValue($manyToOneFieldInfos, function ($value) {
					return $value['table'] . '.' . $value['column'] . ' => ' . $value['fkTable'] . '.' . $value['fkId'];
				});
				$displayCallable('warning', 'Missing hashtag (manyToOne)', "Missing foreign keys in table <b>`$table`</b> : <b>($names)</b>");
			}
		}

		if (\count($manyToManys = $this->getResultManyToMany()) > 0) {
			foreach ($manyToManys as $table => $manyToManyFieldInfos) {
				$names = $this->concatArrayKeyValue($manyToManyFieldInfos, function ($value) {
					return $value['table'] . '.' . $value['column'] . ' => ' . $value['fkTable'] . '.' . $value['fkId'];
				});
				$displayCallable('warning', 'Missing hashtag (manyToMany)', "Missing foreign keys for manyToMany with table <b>`$table`</b> : <b>($names)</b>");
			}
		}
	}
}
