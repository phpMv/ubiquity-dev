<?php


namespace Ubiquity\config;

use Ubiquity\exceptions\InvalidCodeException;
use Ubiquity\utils\base\CodeUtils;
use Ubiquity\utils\base\UArray;
use Ubiquity\utils\base\UFileSystem;

class Configuration {

	private const CONFIG_CACHE_LOCATION='cache/config/';

	private static function addArrayToConfig(array $config, string $env): ?array {
		return \array_replace_recursive($config,self::loadConfig($env));
	}

	public static function generateCache(){
		$app_env=self::loadActiveEnv();
		$config=self::loadMainConfig();
		$config=self::addArrayToConfig($config,$app_env);
		return self::saveConfig($config,'config.cache');
	}

	public static function loadMainConfig(){
		return include \ROOT.'config'.DS.'config.php';
	}

	public static function loadConfigCache(){
		return include \ROOT.self::CONFIG_CACHE_LOCATION.'config.php';
	}

	public static function loadConfig($env): array {
		$filename=\ROOT.'config'.\DS.'config-'.$env.'.php';
		if(\file_exists($filename)){
			return include $filename;
		}
		return [];
	}

	public static function loadActiveEnv(): string {
		EnvFile::load(\ROOT);
		EnvFile::load(\ROOT,'.env.local');
		$app_env=$_ENV['APP_ENV']??'dev';
		self::loadEnv($app_env);
		return $app_env;
	}

	public static function getEnvFiles(): array{
		return UFileSystem::glob_recursive(\ROOT.'../.env*');
	}

	public static function getConfigFiles(): array{
		return UFileSystem::glob_recursive(\ROOT.'config/config*.php*');
	}

	public static function loadEnv($appEnv='dev'){
		EnvFile::load(\ROOT,".env.$appEnv");
		EnvFile::load(\ROOT,".env.$appEnv.local");
	}

	public static function loadConfigWithoutEval(string $filename='config'): array{
		$origContent=\file_get_contents(\ROOT."config/$filename.php");
		$result=\preg_replace('/getenv\(\'(.*?)\'\)/','"getenv(\'$1\')"',$origContent);
		$result=\preg_replace('/getenv\(\"(.*?)\"\)/',"'getenv(\"\$1\")'",$result);
		$tmpFilename=\ROOT.'cache/config/tmp.cache.php';
		if(\file_put_contents($tmpFilename,$result)) {
			return include $tmpFilename;
		}
		return self::loadMainConfig();
	}

	public static function saveConfig(array $contentArray,string $configFilename='config') {
		$filename = \ROOT .static::CONFIG_CACHE_LOCATION. "$configFilename.php";
		$dir=\dirname($filename);
		UFileSystem::safeMkdir($dir);
		$content = "<?php\nreturn " . UArray::asPhpArray ( $contentArray, 'array', 1, true ) . ";";
		if (CodeUtils::isValidCode ( $content )) {
			return UFileSystem::save ( $filename, $content );
		}
		throw new InvalidCodeException ( 'Config contains invalid code' );
	}

}