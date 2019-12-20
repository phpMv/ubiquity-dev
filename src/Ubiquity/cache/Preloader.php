<?php
namespace Ubiquity\cache;

use Ubiquity\utils\base\UFileSystem;

/**
 * Ubiquity\cache$Preloader
 * This class is part of Ubiquity
 *
 * @author jcheron <myaddressmail@gmail.com>
 * @version 1.0.0
 *
 */
class Preloader {

	private $vendorDir;

	private $libraries = [
		'ubiquity' => 'phpmv/ubiquity/src/Ubiquity/',
		'ubiquity-dev' => 'phpmv/ubiquity/src/Ubiquity-dev/',
		'phpmv-ui' => 'phpmv/php-mv-ui/Ajax/'
	];

	private $excludeds = [];

	private static $count = 0;

	private $paths;

	private $classes = [];

	private $loader;

	public function __construct() {
		$this->vendorDir = \ROOT . './../vendor/';
		$this->loader = require $this->vendorDir . 'autoload.php';
	}

	public function paths(string ...$paths): Preloader {
		foreach ($paths as $path) {
			$this->addDir($path);
		}
		return $this;
	}

	public function exclude(string ...$names): Preloader {
		$this->excludeds = \array_merge($this->excludeds, $names);
		return $this;
	}

	public function addClass($class): bool {
		if (! $this->isExcluded($class)) {
			if (! isset($this->classes[$class])) {
				$path = $this->getPathFromClass($class);
				if (isset($path)) {
					$this->classes[$class] = $path;
					return true;
				}
			}
		}
		return false;
	}

	public function load() {
		foreach ($this->classes as $class => $file) {
			if (! $this->isExcluded($class)) {
				$this->loadClass($class, $file);
			}
		}
	}

	public function addDir($dirname) {
		$files = UFileSystem::glob_recursive($dirname . DIRECTORY_SEPARATOR . '*.php');
		foreach ($files as $file) {
			$class = ClassUtils::getClassFullNameFromFile($file);
			if (isset($class)) {
				$this->addClassFile($class, $file);
			}
		}
	}

	public function addLibraryPart($library, $part = ''): bool {
		if (isset($this->libraries[$library])) {
			$dir = $this->vendorDir . $this->libraries[$library] . $part;
			if (\file_exists($dir)) {
				$this->addDir($this->vendorDir . $this->libraries[$library] . $part);
				return true;
			}
		}
		return false;
	}

	private function addClassFile($class, $file) {
		if (! isset($this->classes[$class])) {
			$this->classes[$class] = $file;
		}
	}

	private function loadClass($class, $file = null) {
		if (! \class_exists($class, false)) {
			$file = $file ?? $this->getPathFromClass($class);
			if (isset($file)) {
				$this->loadFile($file);
			}
		}
		if (\class_exists($class, false)) {
			echo "$class loaded !<br>";
		}
	}

	private function getPathFromClass(string $class): ?string {
		$classPath = $this->loader->findFile($class);
		if (false !== $classPath) {
			return \realpath($classPath);
		}
		return null;
	}

	private function loadFile(string $file): void {
		try {
			require_once $file;
			self::$count ++;
		} catch (\Throwable $e) {
			echo $e->getMessage();
		}
	}

	private function isExcluded(?string $name): bool {
		if ($name === null) {
			return true;
		}

		foreach ($this->excludeds as $excluded) {
			if (\strpos($name, $excluded) === 0) {
				return true;
			}
		}

		return false;
	}
}

