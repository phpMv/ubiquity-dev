<?php


namespace Ubiquity\config;


use Dotenv\Dotenv;
use Ubiquity\utils\base\UFileSystem;
use Ubiquity\utils\base\UString;

/**
 * Class EnvFile
 * @package Ubiquity\config
 */
class EnvFile {

	public static  string $ENV_ROOT=\ROOT.'../';

	/**
	 * @param $v
	 * @return int|string
	 */
	private static function parseValue($v) {
		if (\is_numeric($v)) {
			$result = $v;
		} elseif ($v !== '' && UString::isBooleanStr($v)) {
			$result = UString::getBooleanStr($v);
		}else{
			$result='"'.$v.'"';
		}
		return $result;
	}

	/**
	 * Saves a content array on disk.
	 *
	 * @param array $content
	 * @param string|null $path
	 * @param string $filename
	 * @return false|int
	 */
	public static function save(array $content,?string $path=null, string $filename='.env') {
		$result=[];
		$path??=self::$ENV_ROOT;
		foreach ($content as $k=>$v){
			$result[]=$k.'='.self::parseValue($v);
		}
		$result= \implode("\n",$result);
		return UFileSystem::save($path.$filename,$result);
	}

	/**
	 * Savec a content text on disk.
	 *
	 * @param string $textContent
	 * @param string|null $path
	 * @param string $filename
	 * @return false|int
	 */
	public static function saveText(string $textContent,?string $path=null, string $filename='.env') {
		$path??=self::$ENV_ROOT;
		return UFileSystem::save($path.$filename,$textContent);
	}

	/**
	 * Adds a content array to an existing env file and saves it to disk.
	 *
	 * @param array $content
	 * @param string|null $path
	 * @param string $filename
	 * @return false|int
	 */
	public static function addAndSave(array $content,?string $path=null, string $filename='.env') {
		$path??=self::$ENV_ROOT;
		$result=self::load($path,$filename);
		$result=\array_replace_recursive($result,$content);
		return self::save($result,$path,$filename);
	}

	/**
	 * Loads an env file an returns an array of key/value pairs.
	 *
	 * @param string|null $path
	 * @param string $filename
	 * @return array
	 */
	public static function load(?string $path=null, string $filename='.env'): array {
		$path??=self::$ENV_ROOT;
		if(\file_exists($path.$filename)) {
			return Dotenv::createUnsafeMutable($path,$filename)->load();
		}
		return [];
	}
	
	public static function loadContent(?string $path=null, string $filename='.env'): string {
		$path??=self::$ENV_ROOT;
		if(\file_exists($path.$filename)) {
			return \file_get_contents($path.$filename);
		}
		return '';
	}

}