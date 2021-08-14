<?php
%namespace%

%uses%

%restAnnot%
%route%
class %controllerName% extends %baseClass% {

	/**
	 * Returns all links for this controller.
	 * 
	 * @get("/links","priority"=>3000)
	*/
	public function index() {
		parent::index ();
	}

	/**
	* Returns a list of objects from the server.
	*
	* @get("/", "priority"=> 0)
	*/
	public function all() {
		$this->_getAll ();
	}

	/**
	* Get the first object corresponding to the $keyValues.
	*
	* @param string $keyValues primary key(s) value(s) or condition
	* @get("{keyValues}", "priority"=> -1)
	*/
	public function one($keyValues) {
		$this->_getOne ( $keyValues, $this->getRequestParam ( 'include', false ), $useCache );
	}

	/**
	* Update an instance of $model selected by the primary key $keyValues.
	* Require members values in $_POST array
	* Requires an authorization with access token
	*
	* @param array $keyValues
	* @authorization
	* @put("{keyValues}")
	*/
	public function update(...$keyValues) {
		$this->_update ( ...$keyValues );
	}

	/**
	* Insert a new instance of $model.
	* Require members values in $_POST array
	* Requires an authorization with access token
	*
	* @authorization
	* @post("/")
	*/
	public function add() {
		$this->_add ();
	}

	/**
	* Delete the instance of $model selected by the primary key $keyValues.
	* Requires an authorization with access token
	*
	* @param array $keyValues
	* @delete("{keyValues}")
	* @authorization
	*/
	public function delete(...$keyValues) {
		$this->_delete ( ...$keyValues );
	}

	/**
	* Route for CORS.
	*
	* @options("{resource}")
	*/
	public function options(...$resource) {}
}
