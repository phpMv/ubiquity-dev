<?php
namespace Ubiquity\orm\reverse;

use Ubiquity\db\reverse\DbGenerator;
use Ubiquity\controllers\Startup;
use Ubiquity\cache\CacheManager;
use Ubiquity\orm\DAO;
use Ubiquity\db\Database;
use Ubiquity\exceptions\UbiquityException;

/**
 * Generates database from models.
 * Ubiquity\orm\reverse$DatabaseReversor
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.3
 * @package Ubiquity.dev
 *
 */
class DatabaseReversor {

	private $generator;

	private $database;

	private $models;

	private $databaseMetas;

	public function __construct(DbGenerator $generator, $databaseOffset = 'default') {
		$this->generator = $generator;
		$this->database = $databaseOffset;
	}

	public function createDatabase(string $name, bool $createDb = true): void {
		if ($createDb) {
			$this->generator->createDatabase($name);
		}
		$config = Startup::getConfig();
		$models = $this->models ?? CacheManager::getModels($config, true, $this->database);
		foreach ($models as $model) {
			$tableReversor = new TableReversor($model);
			$tableReversor->initFromClass();
			if ($this->generator->hasToCreateTable($tableReversor->getTable())) {
				$tableReversor->generateSQL($this->generator);
			} else {
				$tableReversor->scanManyToManys($this->generator);
			}
		}
		$this->generator->generateManyToManys();
	}

	private function getDbName(): ?string {
		$config = Startup::$config;
		$dbOffset = DAO::getDbOffset($config, $this->database);
		if (isset($dbOffset['dbName'])) {
			return $dbOffset['dbName'];
		}
		throw new UbiquityException('dbName field is required in database config!');
	}

	public function migrate(): void {
		$checker = new DatabaseChecker($this->database);
		$hasError=false;
		$dbName=$this->getDbName();
		if (! $checker->checkDatabase()) {
			$this->createDatabase($dbName);
			$hasError=true;
			return;
		}
		$tablesToCreate = $checker->getNonExistingTables();
		if (\count($tablesToCreate) > 0) {
			$this->generator->setTablesToCreate($tablesToCreate);
			$this->createDatabase($dbName, false);
			$hasError=true;
		}
		//TODO check each part
		$config = Startup::getConfig();
		$models = $this->models ?? CacheManager::getModels($config, true, $this->database);
		foreach ($models as $model){
			$tablereversor=new TableReversor($model);
			$tablereversor->initFromClass();

			$uFields=$checker->getUpdatedFields($model);
			$missingFields=$uFields['missing'][$model]??[];
			foreach ($missingFields as $missingField){
				$this->generator->addField($missingField['table'],$missingField['name'],$missingField['attributes']);
			}
			$updatedFields=$uFields['updated'][$model]??[];
			$hasError=$hasError || \count($updatedFields)>0;
			foreach ($updatedFields as $updatedField){
				$this->generator->modifyField($updatedField['table'],$updatedField['name'],$updatedField['attributes']);
			}
			$missingPks=$checker->checkPrimaryKeys($model);
			if(\count($missingPks)>0){
				$hasError=true;
				$pks=$missingPks['primaryKeys'];
				$tablereversor->addPrimaryKeys($this->generator,$pks);
			}
			$missingFks=$checker->checkManyToOne($model);
			if(\count($missingFks)>0){
				$hasError=true;
				foreach ($missingFks as $fk){
					$this->generator->addForeignKey($fk['table'], $fk['column'], $fk['fkTable'], $fk['fkId']);
				}
			}

			$missingFks=$checker->checkManyToMany($model);
			if(\count($missingFks)>0){
				$hasError=true;
				foreach ($missingFks as $fk){
					$this->generator->addForeignKey($fk['table'], $fk['column'], $fk['fkTable'], $fk['fkId']);
				}
			}
		}

		if($hasError){
			$this->generator->selectDatabase($dbName);
		}
	}

	public function __toString() {
		return $this->generator->__toString();
	}

	public function setModels($models) {
		$this->models = $models;
	}
}
