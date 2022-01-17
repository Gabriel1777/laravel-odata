<?php 

namespace OData\OData;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;

trait ODataModel {

	protected $pdf;

	protected $tab;

	protected $top;

	protected $all;

	protected $page;

	protected $odata;
	
	protected $model;

	protected $query;

	protected $filter;

	protected $select;

	protected $export;

	protected $include;

	protected $trashed;

	protected $orderBy;

	protected $forceAll;

	protected $conditions;

	protected $orderByDesc;

	protected $query_count;

	public function setInstance(Model $model){
		$this->model = $model;
		$this->query = $this->model;
		$this->tab = $this->model->table;
		$this->odata = new ODataSchema();
		$this->conditions = [':', '!', '/', '>', '<'];
	}

	protected function setParams($params){
		if (array_key_exists('top', $params))
			$this->top = $params['top'];

		if (array_key_exists('pdf', $params))
			$this->pdf = true;

		if (array_key_exists('all', $params))
			$this->all = true;

		if (array_key_exists('page', $params))
			$this->page = $params['page'];

		if (array_key_exists('filter', $params))
			$this->filter = $params['filter'];

		if (array_key_exists('select', $params))
			$this->select = $params['select'];

		if (array_key_exists('export', $params))
			$this->export = true;

		if (array_key_exists('trashed', $params))
			$this->trashed = true;

		if (array_key_exists('include', $params))
			$this->include = $params['include'];

		if (array_key_exists('orderBy', $params))
			$this->orderBy = $params['orderBy'];

		if (array_key_exists('forceAll', $params))
			$this->forceAll = true;

		if (array_key_exists('orderByDesc', $params))
			$this->orderByDesc = $params['orderByDesc'];
	}

	protected function getCollection(){
        if ($this->filter)
            return $this->filterData();
        elseif ($this->select)
            return $this->filterSelectData();
        elseif ($this->trashed || $this->all)
            return $this->getTrashedData();
        else
            return $this->getAll();
	}

	protected function setDefaultQuery(){
		if (
			(method_exists($this->model, 'defaultQuery') || method_exists($this->model, 'scopeDefaultQuery')) 
			&& !$this->forceAll
		){
			$query = $this->query->defaultQuery()->select("{$this->tab}.*");
			$this->query = $this->model->whereIn(
			    "{$this->tab}.id",
			    $query->pluck("id")->toArray()
		    );
		}
	}

	protected function orderBy($data){
		if ($this->orderBy)
            $data = $data->sortBy($this->orderBy);
        elseif  ($this->orderByDesc)
            $data = $data->sortByDesc($this->orderByDesc);
        return $data;
	}

	protected function paginate($data){
		if ($this->top){
        	$page = $this->page ? $this->page : 1;
        	$data = $data->forPage($page, $this->top);
        }
        return $data;
	}

	protected function setIncludes(){
		if ($this->include){
			$includes = explode(',', $this->include);
		    foreach ($includes as $include){
		    	if (method_exists($this->model, explode('.', $include)[0]))
		    		$this->query = $this->query->with($include);
		    }
		}
	}

	protected function trashedQuery($required = false){
		if ($this->hasSoftDelete()){
			if ($this->trashed)
			    $this->query = $this->model->onlyTrashed();
		    else if ($this->all)
			    $this->query = $this->model->withTrashed();
			else if ($required)
				$this->query = $this->model;
		}
		else if ($required)
			$this->query = $this->model;
	}

	protected function getTrashedData(){
		$this->trashedQuery();
		return $this->getQueryData();
	}

	protected function export(){
		if ($this->filter)
			$data = $this->filterData();
		else
			$data = $this->getQueryData();

		$export = $this->model->export["instance"];
		$fileName = $this->model->export["fileName"];

		return Excel::download(new $export($data),$fileName);
	}

	protected function getPdf(){
		if ($this->filter)
			$data = $this->filterData();
		else
		    $data = $this->getQueryData();

		return $this->model->generatePdf($data, isset($_GET["download"]));
	}

	protected function getAll(){
		return $this->getQueryData();
	}

	protected function filterData(){
		$and = explode(',', $this->filter);
		$or = explode('|', $this->filter);

		$this->trashedQuery();

		if (count($and) > 0 && count($or) <= 1){
			$this->filterByOperator($and, '|', 'and');
		}

		else if (count($or) > 1){
			$this->filterByOperator($or, ',', 'or');
		}

		if ($this->select){
			$this->filterSelect();
		}

		return $this->getQueryData();
	}

	private function getQueryData(){
		$this->setIncludes();
		$this->setDefaultQuery();
		$data = $this->query->get();
		$this->query_count = $data->count();
		$data = $this->orderBy($data);
        $data = $this->paginate($data);
		return $data;
	}

	protected function filterSelectData(){
		$this->filterSelect();
		return $this->getQueryData();
	}

	private function filterSelect(){
		$and = explode(',', $this->select);
		$or = explode('|', $this->select);

		if ($this->filter){
		    $select = DB::table(DB::raw("({$this->query->toSql()}) as query"));
		    $this->query = $select->mergeBindings($this->query->getQuery());
	    }

	    $this->trashedQuery();

		if (count($and) > 0 && count($or) <= 1){
			$this->filterSelectOperation($and, '|', 'and');
		}

		else if (count($or) > 1){
			$this->filterSelectOperation($or, ',', 'or');
		}

		$ids = $this->query->get()->pluck("id")->toArray();
		$this->trashedQuery(true);
		$this->query = $this->query->whereIn("{$this->tab}.id", $ids);
	}

	private function filterSelectOperation($items, $avoid, $operator){
		for ($i = 0; $i < count($items); $i++){
			for ($j = 0; $j < count($this->conditions); $j++){
				$cond = $this->conditions[$j];
				$condition = explode($cond, $items[$i]);

				$sql_cond = $this->getSqlConditional($cond);

			    if (count($condition) == 2){
				    $alias = $this->filter ? "query" : $this->tab;
				    $field = $condition[0];
				    $value = $condition[1];
				    $value = $this->getAuthUserValue($value);
				    $this->query =  $this->searchByOperator($this->query, $operator, "$alias.$field", $sql_cond , $sql_cond == "like" ? "%$value%" : $value);
			    }
			}
		}
	}

	private function getSqlConditional($cond){
		switch ($cond){
		    case ":":
		    	return "=";
		    break;
		    case "!":
		    	return "!=";
		    break;
		    case "/":
		    	return "like";
		    break;
		    case ">":
		    	return ">";
		    break;
		    case "<":
		    	return "<";
		    break;
		    default:
		    	return "=";
		}
	}

	private function hasSoftDelete(){
		return (
			method_exists($this->model, 'trashed') &&
			method_exists($this->model, 'forceDelete')
		);
	}

	protected function isRequestFile(){
		return $this->pdf || $this->export;
	}

	protected function getFile(){
		if ($this->pdf)
        	return $this->getPdf();
        else if ($this->export)
        	return $this->export();
	}

	protected function setDefaultProperties(Array $data){
		$data["length"] = $this->query_count;
		return $data;
	}

	private function filterByOperator($items, $avoid, $operator){
		for ($i = 0; $i < count($items); $i++){
			for ($j = 0; $j < count($this->conditions); $j++){
			    $this->filterByCondition(explode($this->conditions[$j], $items[$i]), $operator, $this->getSqlConditional($this->conditions[$j]) , $avoid, $i);
			}
		}
	}

	private function filterByCondition($condition, $operator, $cond, $avoid, $index){
		if (count($condition) > 1){
		    $key = $condition[0];
		    $value = explode($avoid, $condition[1])[0];
		    $value = $this->getAuthUserValue($value);
			
			if (count(explode('.', $key)) > 1){
				$tables = explode('.', $key);
				$field = $tables[(count($tables) - 1)];
				unset($tables[(count($tables) - 1)]);

				$this->executeInnerJoinQuery($operator, $cond, $index, $value, $field, $tables);
			}
			else{
				if ($this->odata->hasColumn($this->tab, $key)){
					$column = $this->tab.".$key";
				    $this->query = $this->searchByOperator($this->query, $operator, $column, $cond , $cond == "like" ? "%$value%" : $value);
				}
			}
	    }
	}

	private function executeInnerJoinQuery($operator, $cond, $index, $value, $field, $tables){
		for ($i = 0; $i < count($tables); $i++){

			$currentTable = $i == 0 ? $this->tab : $tables[($i - 1)];

			if ($this->odata->hasRelation($currentTable, $tables[$i])){

				$alias = $tables[$i] . "-$index";
				if ($relation = $this->odata->hasManyTable($currentTable, $tables[$i])){
				    $id = $relation->column_name;
					$foreign = $relation->referenced_column_name;
				}
				else{
				    $relation = $this->odata->hasBelongsToTable($currentTable, $tables[$i]);
				    $id = $relation->referenced_column_name;
				    $foreign = $relation->column_name;
				}

				if ($i == 0){
					$this->query = $this->query->join($tables[$i] . " as $alias", "$alias.$id", $this->tab . "." . $foreign);

					if (count($tables) == 1)
						$this->query =  $this->searchByOperator($this->query, $operator, "$alias.$field", $cond , $cond == "like" ? "%$value%" : $value)->select($this->tab.".*");
				}
				else{
					$parentAlias = $tables[($i - 1)] . "-$index";
					$this->query = $this->query->join($tables[$i] . " as $alias", "$alias.$id", $parentAlias . "." . $foreign);

					if ($i == (count($tables) - 1)){
						$this->query =  $this->searchByOperator($this->query, $operator, "$alias.$field", $cond , $cond == "like" ? "%$value%" : $value)->select($this->tab.".*");
						$uniqueData = $this->query->pluck("id")->toArray();
						$this->trashedQuery(true);
						$this->query = $this->query->whereIn("{$this->tab}.id", $uniqueData);
					}
				}
			}
		}
	}

	private function searchByOperator($query, $operator, $key, $condition, $value){
		switch ($operator) {
			case 'and':
				return $query->where($key, $condition, $value);
			break;
			case 'or':
			    return $query->orWhere($key, $condition, $value);
			break;
			default:
				return $query->where($key, $condition, $value);
			break;
		}
	}

    private function getAuthUserValue($value){
		$val = str_replace('{', '', $value);
		$val = str_replace('}', '', $val);
		$auth = explode("auth.user.", $val);
		
		if (count($auth) >= 2){
			$user = auth()->user();
			$attr = $auth[1];
			$result = $user->$attr;
			return $result ? $result : $value;
		} 

		return $value;
	}
}