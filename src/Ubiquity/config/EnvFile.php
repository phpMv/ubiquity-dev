<?php


namespace Ubiquity\config;


use Dotenv\Dotenv;
use Ubiquity\utils\base\UFileSystem;
use Ubiquity\utils\base\UString;

class EnvFile {
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

	public static function save(array $content,string $path=\ROOT, string $filenames='.env'){
		$result=[];
		foreach ($content as $k=>$v){
			$result[]=$k.'='.self::parseValue($v);
		}
		$result= \implode("\n",$result);
		return UFileSystem::save($path.$filenames,$result);
	}

	public static function addAndSave(array $content,string $path=\ROOT, string $filenames='.env'){
		$result=self::load($path,$filenames);
		$result=\array_replace_recursive($result,$content);
		return self::save($result,$path,$filenames);
	}

	public static function load(string $path=\ROOT, string $filenames='.env'): array {
		if(\file_exists($path.$filenames)) {
			return Dotenv::createUnsafeMutable($path,$filenames)->load();
		}
		return [];
	}

}