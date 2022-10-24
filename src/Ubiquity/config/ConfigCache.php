<?php


namespace Ubiquity\config;

use Ubiquity\exceptions\InvalidCodeException;
use Ubiquity\utils\base\CodeUtils;
use Ubiquity\utils\base\UArray;
use Ubiquity\utils\base\UFileSystem;

class ConfigCache {
	private const CONFIG_CACHE_LOCATION='cache/config/config.php';

	private static function addArrayToConfig(array $config, string $filename): ?array {
		if(\file_exists(\ROOT.DS.'config'.DS.$filename)){
			$newConfig=include \ROOT.DS.'config'.DS.$filename;
			$config=\array_replace_recursive($config,$newConfig);
		}
		return $config;
	}
	
	public static function generateCache(){
		EnvFile::load(\ROOT);
		EnvFile::load(\ROOT,'.env.local');
		$app_env=$_ENV['APP_ENV']??'dev';
		EnvFile::load(\ROOT,".env.$app_env");
		EnvFile::load(\ROOT,".env.$app_env.local");
		$config=include \ROOT.DS.'config'.DS.'config.php';
		$config=self::addArrayToConfig($config,"config-$app_env.php");
		return self::saveConfig($config);
	}

	public static function saveConfig(array $contentArray,string $configFilename='config') {
		$filename = \ROOT .static::CONFIG_CACHE_LOCATION. "$configFilename.php";
		$dir=\dirname($filename);
		UFileSystem::safeMkdir($dir);
		$content = "<?php\nreturn " . UArray::asPhpArray ( $contentArray, 'array', 1, true ) . ";";
		if (CodeUtils::isValidCode ( $content )) {
			if (! \file_exists ( $filename )) {
				return UFileSystem::save ( $filename, $content );
			}
		} else {
			throw new InvalidCodeException ( 'Config contains invalid code' );
		}
		return false;
	}

}