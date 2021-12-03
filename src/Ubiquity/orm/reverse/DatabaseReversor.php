<?php
namespace Ubiquity\orm\reverse;

use Ubiquity\db\reverse\DbGenerator;
use Ubiquity\controllers\Startup;
use Ubiquity\cache\CacheManager;
use Ubiquity\db\utils\DbTypes;
use Ubiquity\exceptions\DBException;
use Ubiquity\orm\DAO;
use Ubiquity\db\Database;
use Ubiquity\exceptions\UbiquityException;

/**
 * Generates database from models.
 * Ubiquity\orm\reverse$DatabaseReversor
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.4
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
		$config=Startup::$config;
		$this->generator->setDatabaseWrapper($this->getWrapperInstance($config,$databaseOffset));
	}

	protected function getWrapperInstance(&$config,$databaseOffset='default'){
		$dbOffsetConfig=DAO::getDbOffset($config,$databaseOffset);
		if(isset($dbOffsetConfig['wrapper'])){
			$wrapperClass=$dbOffsetConfig['wrapper'];
			if(\class_exists($wrapperClass)){
				return new $wrapperClass($dbOffsetConfig['type']??null);
			}
			throw new DBException("Wrapper class $wrapperClass does not exist!");
		}
		throw new DBException("Wrapper class is not set for database at offset $databaseOffset!");
	}

	public function createDatabase(string $name, bool $createDb = true): void {
		$this->generator->setMigrationMode(true);
		if ($createDb) {
			$this->generator->createDatabase($name);
			$this->generator->selectDatabase($name);
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
		$this->generator->setMigrationMode(true);
		$checker = new DatabaseChecker($this->database);
		$dbName=$this->getDbName();
		if (! $checker->checkDatabase()) {
			$this->createDatabase($dbName);
			return;
		}
		$tablesToCreate = $checker->getNonExistingTables();
		if (\count($tablesToCreate) > 0) {
			$this->generator->setTablesToCreate($tablesToCreate);
			$this->createDatabase($dbName, false);
		}
		//TODO check each part
		$config = Startup::getConfig();
		$models = $this->models ?? CacheManager::getModels($config, true, $this->database);
		$newMissingPks=[];

		foreach ($models as $model){
			$tablereversor=new TableReversor($model);
			$tablereversor->initFromClass();

			$uFields=$checker->getUpdatedFields($model);
			$missingFields=$uFields['missing'][$model]??[];
			foreach ($missingFields as $missingField){
				$this->generator->addField($missingField['table'],$missingField['name'],$missingField['attributes']);
			}
			$updatedFields=$uFields['updated'][$model]??[];
			foreach ($updatedFields as $updatedField){
				$this->generator->modifyField($updatedField['table'],$updatedField['name'],$updatedField['attributes']);
			}
			$missingPks=$checker->checkPrimaryKeys($model);
			if(\count($missingPks)>0){
				$pks=$missingPks['primaryKeys'];
				$tablereversor->addPrimaryKeys($this->generator,$pks);
			}
			$missingFks=$checker->checkManyToOne($model);
			if(\count($missingFks)>0){
				foreach ($missingFks as $fk){
					$this->generator->addForeignKey($fk['table'], $fk['column'], $fk['fkTable'], $fk['fkId']);
				}
			}

			$missingFks=$checker->checkManyToMany($model);
			if(\count($missingFks)>0){
				foreach ($missingFks as $fk){
					if(!$this->generator->hasToCreateTable($fk['table'])) {
						$this->checkManyToManyFields($checker, $fk['table'], $fk['column'],$newMissingPks);
						$this->generator->addForeignKey($fk['table'], $fk['column'], $fk['fkTable'], $fk['fkId']);
					}
				}
			}
		}
		foreach ($newMissingPks as $table=>$pks){
			$this->generator->addKey($table,$pks);
		}
	}

	private function checkManyToManyFields(DatabaseChecker $checker,string $table,string $field,&$newMissingPks): void {
		$originalFieldInfos = $checker->getDb()->getFieldsInfos($table);
		$pks=$checker->getDb()->getPrimaryKeys($table);
		if (!isset($originalFieldInfos[$field])) {
			$this->generator->addField($table, $field, ['type' => 'int']);
		}
		if(\array_search($field,$pks)===false){
			$newMissingPks[$table][]=$field;
		}
	}

	public function __toString() {
		return $this->generator->__toString();
	}
	
	public function getScript(){
		return $this->generator->getScript();
	}

	public function setModels($models) {
		$this->models = $models;
	}
}
