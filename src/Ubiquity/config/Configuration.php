<?php


namespace Ubiquity\config;

use Ubiquity\core\Framework;
use Ubiquity\exceptions\InvalidCodeException;
use Ubiquity\utils\base\CodeUtils;
use Ubiquity\utils\base\UArray;
use Ubiquity\utils\base\UFileSystem;

/**
 * Class Configuration
 * @package Ubiquity\config
 */
class Configuration {

	private const CONFIG_CACHE_LOCATION='cache/config/';

	private static function addArrayToConfig(array $config, string $env): ?array {
		return \array_replace_recursive($config, self::loadConfig($env));
	}

	private static function generateConfig(): array {
		$app_env=self::loadActiveEnv();
		$config=self::loadMainConfig();
		$config['app.env']=$app_env;
		return self::addArrayToConfig($config, $app_env);
	}

	public static function generateCache(bool $silent=false) {
		$config = self::saveConfig(self::generateConfig(),'config.cache');
		if (!$silent) {
			$folder = \realpath(\ROOT.self::CONFIG_CACHE_LOCATION.'config.cache.php');
			echo "Config cache generated in <b>$folder</b>";
		}
		return $config;
	}

	public static function loadMainConfig() {
		return include \ROOT.'config'.DS.'config.php';
	}

	public static function loadConfigCache() {
		return include \ROOT.self::CONFIG_CACHE_LOCATION.'config.cache.php';
	}

	public static function loadConfig($env): array {
		$filename=\ROOT.'config'.\DS.'config-'.$env.'.php';
		if(\file_exists($filename)){
			return include $filename;
		}
		return [];
	}

	public static function loadActiveEnv(): string {
		$envRoot=EnvFile::$ENV_ROOT;
		EnvFile::load($envRoot);
		EnvFile::load($envRoot,'.env.local');
		$app_env=$_ENV['APP_ENV']??'dev';
		self::loadEnv($app_env);
		return $app_env;
	}

	public static function hasEnvChanged(): bool {
		return Framework::getEnv()!==self::loadActiveEnv();
	}

	public static function isConfigUpdated(): bool {
		$cachedConfig=self::loadConfigCache();
		$newConfig=self::generateConfig();
		return UArray::asPhpArray($cachedConfig)!=UArray::asPhpArray($newConfig);
	}

	public static function getEnvFiles(): array{
		return UFileSystem::glob_recursive(EnvFile::$ENV_ROOT.'.env*');
	}

	public static function getConfigFiles(): array{
		return UFileSystem::glob_recursive(\ROOT.'config'.\DS.'config*.php');
	}

	public static function loadEnv($appEnv='dev'): void {
		$envRoot=EnvFile::$ENV_ROOT;
		EnvFile::load($envRoot,".env.$appEnv");
		EnvFile::load($envRoot,".env.$appEnv.local");
	}

	public static function getTheoreticalLoadedConfigFiles($appEnv):array{
		$envRoot=EnvFile::$ENV_ROOT;
		return \array_map('realpath',[
			$envRoot.'.env',
			$envRoot.'.env.local',
			$envRoot.'.env.'.$appEnv,
			$envRoot.'.env.'.$appEnv.'.local',
			\ROOT.'config'.DS.'config.php',
			\ROOT.'config'.\DS.'config-'.$appEnv.'.php'
		]);
	}

	public static function loadConfigWithoutEval(string $filename='config'): array{
		if (file_exists(\ROOT."config/$filename.php")) {
			$origContent = \file_get_contents(\ROOT . "config/$filename.php");
			$result = \preg_replace('/getenv\(\'(.*?)\'\)/', '"getenv(\'$1\')"', $origContent);
			$result = \preg_replace('/getenv\(\"(.*?)\"\)/', "'getenv(\"\$1\")'", $result);
			$tmpFilename = \ROOT . 'cache/config/tmp.cache.php';
			if (\file_put_contents($tmpFilename, $result)) {
				return include $tmpFilename;
			}
			return self::loadMainConfig();
		}
		return [];
	}

	/**
	 * @throws InvalidCodeException
	 */
	public static function saveConfig(array $contentArray, string $configFilename='config') {
		$filename = \ROOT .static::CONFIG_CACHE_LOCATION. "$configFilename.php";
		$dir=\dirname($filename);
		UFileSystem::safeMkdir($dir);
		$content = "<?php\nreturn " . UArray::asPhpArray($contentArray, 'array', 1, true) . ";";
		if (CodeUtils::isValidCode($content)) {
			return UFileSystem::save($filename, $content);
		}
		throw new InvalidCodeException('Config contains invalid code');
	}

}