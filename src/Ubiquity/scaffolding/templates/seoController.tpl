<?php
%namespace%

%uses%

class %controllerName% extends \Ubiquity\controllers\seo\SeoController {

	public function __construct(){
		parent::__construct();
		$this->urlsKey="%urlsFile%";
		$this->seoTemplateFilename="%sitemapTemplate%";
	}
	
	%route%
	public function index(){
		return parent::index();
	}
}
