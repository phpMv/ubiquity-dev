<?php


namespace Ubiquity\scaffolding\creators;


use Ubiquity\utils\base\UFileSystem;
use Ubiquity\utils\base\UString;

class ClassCreator {
	private string $template;
	private string $classname;
	private string $namespace;
	private string $uses;
	private string $extendsOrImplements;
	private string $classContent;

	private function getTemplateDir() {
		return \dirname(__DIR__) . "/scaffolding/templates/";
	}

	public function __construct($classname,$uses,$namespace,$extendsOrImplements='',$classContent=''){
		$this->classname=$classname;
		$this->uses=$uses;
		$this->namespace=$namespace;
		$this->extendsOrImplements=$extendsOrImplements;
		$this->classContent=$classContent;
		$this->template='class.tpl';
	}

	public function generate():bool{
		$namespaceVar = '';
		if (UString::isNotNull($this->namespace)) {
			$namespaceVar = "namespace {$this->namespace};";
		}
		$variables = [
			'%classname%' => $this->classname,
			'%namespace%' => $namespaceVar,
			'%uses%' => $this->uses,
			'%extendsOrImplements%' => $this->extendsOrImplements,
			'%classContent%' => $this->classContent
		];
		$templateDir = $this->getTemplateDir();
		$directory = UFileSystem::getDirFromNamespace($this->namespace);
		UFileSystem::safeMkdir($directory);
		$filename = UFileSystem::cleanFilePathname($directory . \DS . $this->classname . '.php');
		if (! file_exists($filename)) {
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
}