<?php
%namespace%

%uses%
use Ubiquity\controllers\rest\formatters\ResponseFormatter;
use Ubiquity\controllers\rest\formatters\JsonApiResponseFormatter;

%restAnnot%
%route%
class %controllerName% extends %baseClass% {
	protected function getResponseFormatter(): ResponseFormatter {
		return new JsonApiResponseFormatter('%routePath%');
	}
	
		/**
	 * Returns all the instances from the model $resource.
	 * Query parameters:
	 * - **include**: A string of associated members to load, comma separated (e.g. users,groups,organization...), or a boolean: true for all members, false for none (default: true).
	 * - **filter**: The filter to apply to the query (where part of an SQL query) (default: 1=1).
	 * - **page[number]**: The page to display (in this case, the page size is set to 1).
	 * - **page[size]**: The page size (count of instance per page) (default: 1).
	 *
	 * @route("{resource}/","methods"=>["get"],"priority"=>0)
	 */
	public function all($resource){
		$this->getAll_($resource);
	}

	/**
	 * Returns an instance of $resource, by primary key $id.
	 *
	 * @param string $resource The resource (model) to use
	 * @param string $id The primary key value(s), if the primary key is composite, use a comma to separate the values (e.g. 1,115,AB)
	 *
	 * @route("{resource}/{id}/","methods"=>["get"],"priority"=>1000)
	 */
	public function one($resource,$id){
		$this->getOne_($resource,$id);
	}

	/**
	 * Deletes an existing instance of $resource.
	 *
	 * @param string $resource The resource (model) to use
	 * @param string $ids The primary key value(s), if the primary key is composite, use a comma to separate the values (e.g. 1,115,AB)
	 *
	 * @route("{resource}/{id}/","methods"=>["delete"],"priority"=>0)
	 * @authorization
	 */
	public function delete($resource,...$id){
		$this->delete_($resource,...$id);
	}

	/**
	* Route for CORS
	*
	* @route("{resource}","methods"=>["options"],"priority"=>3000)
	*/
	public function options(...$resource) {
	}

	/**
	* Inserts a new instance of $resource.
	* Data attributes are send in request body (in JSON format)
	*
	* @param string $resource The resource (model) to use
	* @route("{resource}/","methods"=>["post"],"priority"=>0)
	* @authorization
	*/
	public function add($resource) {
		parent::add_($resource);
	}

	/**
	 * Updates an existing instance of $resource.
	 * Data attributes are send in data[attributes] request body (in JSON format)
	 *
	 * @param string $resource The resource (model) to use
	 *
	 * @route("{resource}/{id}","methods"=>["patch"],"priority"=>0)
	 * @authorization
	 */
	public function update($resource,...$id ){
		parent::update_($resource,...$id);
	}
}
