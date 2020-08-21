<?php
namespace Ubiquity\orm\creator\database;

use Ubiquity\orm\creator\ModelsCreator;
use Ubiquity\db\Database;
use Ubiquity\orm\DAO;

/**
 * Generates models from a database.
 * Ubiquity\orm\creator\database$DbModelsCreator
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.2
 * @package ubiquity.dev
 *
 */
class DbModelsCreator extends ModelsCreator {

	/**
	 *
	 * @var Database
	 */
	private $database;

	protected function init($config, $offset = 'default') {
		parent::init($config, $offset);
		$this->database = DAO::getSqlOrNosqlDatabase($config, $offset);
	}

	protected function getTablesName() {
		return $this->database->getTablesName();
	}

	protected function getFieldsInfos($tableName) {
		return $this->database->getFieldsInfos($tableName);
	}

	protected function getPrimaryKeys($tableName) {
		return $this->database->getPrimaryKeys($tableName);
	}

	protected function getForeignKeys($tableName, $pkName, $dbName = null) {
		return $this->database->getForeignKeys($tableName, $pkName, $dbName);
	}
}
