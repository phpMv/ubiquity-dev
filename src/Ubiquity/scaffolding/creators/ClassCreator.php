<?php


namespace Ubiquity\scaffolding\creators;


use Ubiquity\creator\HasUsesTrait;
use Ubiquity\utils\base\UFileSystem;
use Ubiquity\utils\base\UString;

class ClassCreator {
	use HasUsesTrait;
	
	private string $template;
	private string $classname;
	private string $namespace;
	private string $extendsOrImplements;
	private string $classContent;
	private array $classAttributes;

	private function getTemplateDir() {
		return \dirname(__DIR__) . "/templates/";
	}

	public function __construct($classname, $namespace, $extendsOrImplements='', $classContent='') {
		$this->classname=$classname;
		$this->namespace=$namespace;
		$this->extendsOrImplements=$extendsOrImplements;
		$this->classContent=$classContent;
		$this->template='class.tpl';
		$this->classAttributes=[];
		$this->uses=[];
	}

	public function generate(): bool {
		$namespaceVar = '';
		if (UString::isNotNull($this->namespace)) {
			$namespaceVar = "namespace {$this->namespace};";
		}
		$variables = [
			'%classname%' => $this->classname,
			'%namespace%' => $namespaceVar,
			'%uses%' => $this->getUsesStr(),
			'%extendsOrImplements%' => $this->extendsOrImplements,
			'%classContent%' => $this->classContent,
			'%classAttributes%'=>\implode("\n", $this->classAttributes)
		];
		$templateDir = $this->getTemplateDir();
		$directory = UFileSystem::getDirFromNamespace($this->namespace);
		UFileSystem::safeMkdir($directory);
		$filename = UFileSystem::cleanFilePathname($directory . \DS . $this->classname . '.php');
		if (! \file_exists($filename)) {
			UFileSystem::openReplaceWriteFromTemplateFile($templateDir . $this->template, $filename, $variables);
			return true;
		}
		return false;
	}

	/**
	 * @return string
	 */
	public function getTemplate(): string {
		return $this->template;
	}

	/**
	 * @param string $template
	 */
	public function setTemplate(string $template): void {
		$this->template = $template;
	}
	
	public function addClassAttribute($attribute) {
		$this->classAttributes[]=$attribute;
	}
}