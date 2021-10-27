<?php
namespace Ubiquity\orm\reverse;

use Ubiquity\db\reverse\DbGenerator;
use Ubiquity\controllers\Startup;
use Ubiquity\cache\CacheManager;
use Ubiquity\orm\DAO;

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

	public function getDatabaseMeta() {
		$db = DAO::getDatabase();
		if ($db->isConnected()) {
			$tables = $db->getTablesName();
			foreach ($tables as $table) {
				// $this->databaseMetas[$table]=;//TODO to continue
			}
		}
	}

	public function createDatabase(string $name, bool $createDb = true): void {
		if ($createDb) {
			$this->generator->createDatabase($name);
		}
		$this->generator->selectDatabase($name);
		$config = Startup::getConfig();
		$models = $this->models ?? CacheManager::getModels($config, true, $this->database);
		foreach ($models as $model) {
			$tableReversor = new TableReversor($model);
			$tableReversor->initFromClass();
			$tableReversor->generateSQL($this->generator);
		}
		$this->generator->generateManyToManys();
	}

	public function __toString() {
		return $this->generator->__toString();
	}

	public function setModels($models) {
		$this->models = $models;
	}
}
