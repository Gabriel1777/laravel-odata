<?php 

namespace OData\OData;

use Illuminate\Database\Eloquent\Model;

class ODataHelper {

	use ODataModel;

	protected $model;

	public function __construct(Model $model){
		$this->model = $model;
	}

	public function filter(Array $params){
		$this->setParams($params);
		$this->setInstance($this->model);
		return $this->getData();
	}

	protected function getData(){
		return $this->getCollection($this->model);
	}
}