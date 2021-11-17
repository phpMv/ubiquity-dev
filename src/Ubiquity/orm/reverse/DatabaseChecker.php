<?php
namespace Ubiquity\orm\reverse;

use Ubiquity\orm\DAO;
use Ubiquity\db\Database;
use Ubiquity\cache\CacheManager;
use Ubiquity\controllers\Startup;
use Ubiquity\orm\parser\Reflexion;

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
		$fields = $this->metadatas['#fieldNames'];
		$fieldTypes = $this->metadatas['#fieldTypes'];
		$nullables = $this->metadatas['#nullable'];
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
			} elseif ($fieldTypes[$member] !== $originalFieldInfos[$field]['Type'] || ($originalFieldInfos[$field]['Null'] && ! $nullable)) {
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
			$originalFks = $this->db->getForeignKeys($fkTable, $fkId);
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
		}
		return $result;
	}

	public function checkManyToMany(string $model): array {}
}

