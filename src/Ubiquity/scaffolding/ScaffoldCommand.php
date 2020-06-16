<?php
namespace Ubiquity\scaffolding;

use Ubiquity\utils\base\UFileSystem;
use Ubiquity\utils\base\UArray;

/**
 * Create a new command
 * Ubiquity\scaffolding$ScaffoldCommand
 * This class is part of Ubiquity
 *
 * @author jc
 * @version 1.0.0
 *
 */
class ScaffoldCommand {

	protected $name;

	protected $description;

	protected $value;

	protected $aliases;

	protected $parameters;

	protected $uses = [];

	public function __construct($name, $value, $description, $aliases = "", $parameters = "") {
		$this->name = $name;
		$this->value = $value;
		$this->description = $description;
		$this->aliases = $aliases;
		$this->parameters = $parameters;
	}

	protected function createParameters() {
		$result = [];
		$parameters = \explode(',', $this->parameters);
		foreach ($parameters as $param) {
			$result[] = "'{$param}' => Parameter::create('{$param}LongName', 'The {$param} description.', [])";
		}
		if (\count($result) > 0) {
			$this->uses[] = 'Ubiquity\\devtools\\cmd\\Parameter';
		}
		return '[' . \implode(",\n", $result) . ']';
	}

	protected function addUses() {
		$result = [];
		foreach ($this->uses as $use) {
			$result[] = "use {$use};";
		}
		return \implode("\n", $result);
	}

	public function createNamespace($directory) {
		if ($directory != null) {
			return 'namespace ' . \str_replace(\DS, '\\', $directory) . ';';
		}
		return '';
	}

	public function getTemplateDir() {
		return \dirname(__DIR__) . "/scaffolding/templates/";
	}

	public function create($pattern, &$cmdPath) {
		@list ($directory, $ext) = \explode('*', $pattern);
		$directory ??= 'commands';
		$ext ??= '.cmd.php';
		$template = 'createCommand.tpl';
		$classname = \ucfirst($this->name) . $ext;
		$aliases = \explode(',', $this->aliases);
		$variables = [
			'%classname%' => $classname,
			'%aliases' => UArray::asPhpArray($aliases, 'array'),
			'%parameters%' => $this->createParameters(),
			'%value%' => $this->value,
			'%description%' => $this->description,
			'%uses%' => $this->addUses(),
			'%namespace' => $this->createNamespace($directory)
		];
		$cmdPath = \ROOT . \DS . $directory . $classname;
		return UFileSystem::openReplaceWriteFromTemplateFile($this->getTemplateDir() . $template, $cmdPath, $variables);
	}
}

