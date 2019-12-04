<?php
%namespace%

%uses%

 /**
 * Mailer %classname%
 **/
class %classname% extends %extendsOrImplements% {

	/**
	 *
	 * {@inheritdoc}
	 * @see \Ubiquity\mailer\AbstractMail::bodyText()
	 */
	public function bodyText() {
		return 'Message text';
	}

	public function __construct() {
		parent::__construct();
		$this->subject = 'Message title';
		$this->from(MailerManager::loadConfig()['from']??'from@organization');
		//$this->to($to);
	}

	/**
	 *
	 * {@inheritdoc}
	 * @see \Ubiquity\mailer\AbstractMail::body()
	 */
	public function body() {
		return '<h1>Message body</h1>';
	}
}
