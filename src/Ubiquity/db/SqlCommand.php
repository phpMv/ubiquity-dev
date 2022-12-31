<?php

namespace Ubiquity\db;

use Ubiquity\orm\DAO;

class SqlCommand {
	
	public static function executeSQLTransaction(string $activeDbOffset, string $sql): bool {
		$db = DAO::getDatabase($activeDbOffset ?? 'default');
		if (! $db->isConnected()) {
			$db->setDbName('');
			$db->connect();
		}
		if ($db->beginTransaction()) {
			try {
				$db->execute($sql);
				if ($db->inTransaction()) {
					$db->commit();
				}
				return true;
			} catch (\Error $e) {
				if ($db->inTransaction()) {
					$db->rollBack();
				}
				return false;
			}
		} else {
			$db->execute($sql);
			return true;
		}
	}
}