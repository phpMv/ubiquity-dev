<?php
namespace Ubiquity\debug;


use Ubiquity\utils\http\URequest;

/**
 * Class for debug in dev mode.
 * Ubiquity\devtools\debug$Debug
 * This class is part of Ubiquity
 *
 * @author jc
 * @version 1.0.0
 * @deprecated replaced with LiveReload class in Ubiquity-debug package
 */
class Debug {
	/**
	 * 
	 * @param int $port
	 * @return string
	 * @deprecated replaced with LiveReload::start(int $port)
	 */
	public static function liveReload(int $port=35729):string{
		if(!URequest::isAjax()) {
			return '<script>document.write(\'<script src="http://\' + (location.host || \'localhost\').split(\':\')[0] +
				\':' . $port . '/livereload.js?snipver=1"></\' + \'script>\')</script>';
		}
		return '';
	}
	
	/**
	 * 
	 * @return bool
	 * @deprecated replaced with LiveReload::hasLiveReload()
	 */
	public static function hasLiveReload():bool{
		$exitCode=0;
		\exec('livereload --version', $_, $exitCode);
		return $exitCode == 1;
	}
}
