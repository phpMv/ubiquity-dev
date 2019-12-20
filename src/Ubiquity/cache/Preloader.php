<?php
namespace Ubiquity\cache;

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
		'ubiquity-dev' => 'phpmv/ubiquity-dev/src/Ubiquity/',
		'ubiquity-webtools' => 'phpmv/ubiquity-webtools/src/Ubiquity/',
		'ubiquity-mailer' => 'phpmv/ubiquity-mailer/src/Ubiquity/',
		'ubiquity-swoole' => 'phpmv/ubiquity-swoole/src/Ubiquity/',
		'ubiquity-workerman' => 'phpmv/ubiquity-workerman/src/Ubiquity/',
		'ubiquity-tarantool' => 'phpmv/ubiquity-tarantool/src/Ubiquity/',
		'ubiquity-mysqli' => 'phpmv/ubiquity-mysqli/src/Ubiquity/',
		'phpmv-ui' => 'phpmv/php-mv-ui/Ajax/'
	];

	private $excludeds = [];

	private static $count = 0;

	private $classes = [];

	private $loader;

	public function __construct($appRoot) {
		$this->vendorDir = $appRoot . './../vendor/';
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

	public function addClasses(array $classes) {
		foreach ($classes as $class) {
			$this->addClass($class);
		}
	}

	public function load() {
		foreach ($this->classes as $class => $file) {
			if (! $this->isExcluded($class)) {
				$this->loadClass($class, $file);
			}
		}
	}

	public function generateClassesFiles() {
		$ret = [];
		foreach ($this->classes as $class => $file) {
			if (! $this->isExcluded($class)) {
				$ret[$class] = \realpath($file);
			}
		}
		return $ret;
	}

	public function generateToFile($filename, $preserve = true) {
		$array = [];
		if ($preserve && \file_exists($filename)) {
			$array = include $filename;
		}
		$array['classes-files'] = $this->generateClassesFiles();
		$content = "<?php\nreturn " . \var_export($array, true) . ";";
		return \file_put_contents($filename, $content);
	}

	public function addDir($dirname) {
		$files = $this->glob_recursive($dirname . DIRECTORY_SEPARATOR . '*.php');
		foreach ($files as $file) {
			$class = $this->getClassFullNameFromFile($file);
			if (isset($class)) {
				$this->addClassFile($class, $file);
			}
		}
		return $this;
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

	public function addUbiquityControllers() {
		$this->addLibraryPart('ubiquity', 'controllers');
		return $this;
	}

	public function addUbiquityCache() {
		$this->addLibraryPart('ubiquity', 'cache');
		return $this;
	}

	public function addUbiquityPdo() {
		$this->addClass('Ubiquity\\db\\Database');
		$this->addLibraryPart('ubiquity', 'db/providers');
		return $this;
	}

	public function addUbiquityORM() {
		$this->addLibraryPart('ubiquity', 'orm');
		return $this;
	}

	public function addUbiquityHttpUtils() {
		$this->addLibraryPart('ubiquity', 'utils/http');
		return $this;
	}

	public function addUbiquityViews() {
		$this->addClass('Ubiquity\\views\\engine\\micro\\MicroTemplateEngine');
		return $this;
	}

	public function addUbiquityTranslations() {
		$this->addLibraryPart('ubiquity', 'translation');
		return $this;
	}

	public function addUbiquityWorkerman() {
		$this->addLibraryPart('ubiquity-workerman');
		return $this;
	}

	public function addUbiquityTwig() {
		$this->addClasses([
			'Ubiquity\\views\\engine\\Twig',
			'Twig\Cache\FilesystemCache',
			'Twig\Extension\CoreExtension',
			'Twig\Extension\EscaperExtension',
			'Twig\Extension\OptimizerExtension',
			'Twig\Extension\StagingExtension',
			'Twig\ExtensionSet',
			'Twig\Template',
			'Twig\TemplateWrapper'
		]);
		return $this;
	}

	public static function fromFile(string $appRoot, string $filename): bool {
		if (\file_exists($filename)) {
			$array = include $filename;
			return self::fromArray($appRoot, $array);
		}
		return false;
	}

	public static function fromArray(string $appRoot, array $array): bool {
		$pre = new self($appRoot);
		self::$count = 0;
		if (isset($array['classes-files'])) {
			$pre->classes = $array['classes-files'];
		}
		if (isset($array['excludeds'])) {
			$pre->excludeds = $array['excludeds'];
		}
		if (isset($array['paths'])) {
			foreach ($array['paths'] as $path) {
				$pre->addDir($path);
			}
		}
		if (isset($array['classes'])) {
			foreach ($array['classes'] as $class) {
				$pre->addClass($class);
			}
		}
		if (isset($array['libraries-parts'])) {
			foreach ($array['libraries-parts'] as $library => $parts) {
				foreach ($parts as $part) {
					$pre->addLibraryPart($library, $part);
				}
			}
		}
		if (isset($array['callback'])) {
			if (\is_callable($array['callback'])) {
				$call = $array['callback'];
				$call($pre);
			}
		}
		$pre->load();
		return self::$count > 0;
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
		require_once $file;
		self::$count ++;
	}

	private function isExcluded(string $name): bool {
		foreach ($this->excludeds as $excluded) {
			if (\strpos($name, $excluded) === 0) {
				return true;
			}
		}
		return false;
	}

	private function glob_recursive($pattern, $flags = 0) {
		$files = \glob($pattern, $flags);
		foreach (\glob(\dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
			$files = \array_merge($files, $this->glob_recursive($dir . '/' . \basename($pattern), $flags));
		}
		return $files;
	}

	private function getClassFullNameFromFile($filePathName, $backSlash = false) {
		$phpCode = \file_get_contents($filePathName);
		$ns = $this->getClassNamespaceFromPhpCode($phpCode);
		if ($backSlash && $ns != null) {
			$ns = "\\" . $ns;
		}
		return $ns . '\\' . $this->getClassNameFromPhpCode($phpCode);
	}

	private function getClassNamespaceFromPhpCode($phpCode) {
		$tokens = \token_get_all($phpCode);
		$count = \count($tokens);
		$i = 0;
		$namespace = '';
		$namespace_ok = false;
		while ($i < $count) {
			$token = $tokens[$i];
			if (\is_array($token) && $token[0] === T_NAMESPACE) {
				// Found namespace declaration
				while (++ $i < $count) {
					if ($tokens[$i] === ';') {
						$namespace_ok = true;
						$namespace = \trim($namespace);
						break;
					}
					$namespace .= \is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
				}
				break;
			}
			$i ++;
		}
		if (! $namespace_ok) {
			return null;
		}
		return $namespace;
	}

	private function getClassNameFromPhpCode($phpCode) {
		$classes = array();
		$tokens = \token_get_all($phpCode);
		$count = count($tokens);
		for ($i = 2; $i < $count; $i ++) {
			if ($tokens[$i - 2][0] == T_CLASS && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
				$class_name = $tokens[$i][1];
				$classes[] = $class_name;
			}
		}
		if (isset($classes[0]))
			return $classes[0];
		return null;
	}
}

