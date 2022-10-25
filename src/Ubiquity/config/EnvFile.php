<?php


namespace Ubiquity\config;


use Dotenv\Dotenv;
use Ubiquity\utils\base\UFileSystem;
use Ubiquity\utils\base\UString;

class EnvFile {

	public static  string $ENV_ROOT=\ROOT.'../';

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

	public static function save(array $content,?string $path=null, string $filename='.env'){
		$result=[];
		$path??=self::$ENV_ROOT;
		foreach ($content as $k=>$v){
			$result[]=$k.'='.self::parseValue($v);
		}
		$result= \implode("\n",$result);
		return UFileSystem::save($path.$filename,$result);
	}

	public static function addAndSave(array $content,?string $path=null, string $filename='.env'){
		$path??=self::$ENV_ROOT;
		$result=self::load($path,$filename);
		$result=\array_replace_recursive($result,$content);
		return self::save($result,$path,$filename);
	}

	public static function load(?string $path=null, string $filename='.env'): array {
		$path??=self::$ENV_ROOT;
		if(\file_exists($path.$filename)) {
			return Dotenv::createUnsafeMutable($path,$filename)->load();
		}
		return [];
	}

}