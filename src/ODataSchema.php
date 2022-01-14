<?php 

namespace OData\OData;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ODataSchema {

	public $key;

	public $map;

	public $time;

	public $tables;

	public $schema;

	private $driver;

	public $database;

	public $connection;

	public function __construct()
	{
		$this->setConfig();
		$this->setQueries();
	}

	public function setConfig()
	{
		$this->time = 600000000;
		$this->key = 'odata_schema';
		$this->driver = new ODataDriver();
		$this->connection = config('database.default');
		$this->database = config("database.connections.{$this->connection}.database");
	}

	public function setQueries()
	{
		$this->setSchema();
		$this->setTables();
		$this->setMap();
	}

	public function clearConfig()
	{
		Cache::set($this->key, null);
		Cache::set('odata_map', null);
		Cache::set('odata_' . $this->database, null);
	}

	public function setSchema()
	{
		$key = Cache::get($this->key);

		if ($key){
			$this->schema = $key;
			return true;
		}

		$this->schema = Cache::remember($this->key, $this->time, function(){
			$query = $this->driver->getSchemaRelations($this->database, $this->connection);
			$collection = new Collection($query);
			return $collection;
		});
	}

	public function getSchema()
	{
		$this->schema = Cache::get($this->key);
		return $this->schema;
	}

	public function setTables()
	{
		$key = Cache::get('odata_' . $this->database);

		if ($key){
			$this->tables = $key;
			return true;
		}

		$this->tables = Cache::remember("odata_" . $this->database, $this->time, function(){
			$data = [];
			$query = $this->driver->getTablesFromDatabase($this->database, $this->connection);
			$collection = new Collection($query);
			foreach ($collection->pluck("table_name") as $table){
				$data[$table] = $this->setColumns($table);
			}

			return new Collection($data);
		});
	}

	public function getTables()
	{
		$this->tables = Cache::get('odata_' . $this->database);
		return $this->tables;
	}

	public function setColumns(String $table)
	{
		$query = $this->driver->getColumnsFromTable($this->database, $table, $this->connection);
		$collection = new Collection($query);
		return $collection->pluck("column_name");
	}

	public function setMap()
	{
		$key = Cache::get('odata_map');

		if ($key){
			$this->map = $key;
			return true;
		}

		$this->map = Cache::remember('odata_map', $this->time, function(){
			$map = [];
			foreach ($this->tables as $table => $value){

		        $ignone = [$table];
			    $keys = $this->setKeys($table, $ignone);
			    $map[$table] = $this->tables[$table]->toArray();

		        for ($a = 0; $a < count($keys); $a++){

			        $indexA = $keys[$a];
			        $ignone = [$table, $indexA];
			        $map[$table][$indexA] = $this->tables[$indexA]->toArray();

			        $keysA = $this->setKeys($indexA, $ignone);
		        }
		    }
		    return $map;
		});
	}

	public function setKeys(String $table, Array $ignone)
	{
		$items = [];
		$data = $this->schema->filter(function($item) use ($table){
			return $item->table_name == $table || $item->referenced_table_name == $table;
		});

		$keys = array_filter(
			array_unique(
			    array_merge(
				    $data->pluck("table_name")->toArray(), 
				    $data->pluck("referenced_table_name")->toArray()
			    )
		    ), 
		    function($v, $k) use ($ignone){
			    return !in_array($v, $ignone);
		    }, 
		    ARRAY_FILTER_USE_BOTH
		);

		foreach ($keys as $key){
			array_push($items, $key);
		}

		return $items;
	}

	public function hasRelation(String $table, String $referenced)
	{
		return $this->schema->filter(function($item) use ($table, $referenced){
			return ($item->table_name == $table && $item->referenced_table_name == $referenced) || ($item->table_name == $referenced && $item->referenced_table_name == $table);
		})->first();
	}

	public function hasManyTable(String $table, String $referenced)
	{
		return $this->schema->filter(function($item) use ($table, $referenced){
			return $item->table_name == $referenced && $item->referenced_table_name == $table;
		})->first();
	}

	public function hasBelongsToTable(String $table, String $referenced)
	{
		return $this->schema->filter(function($item) use ($table, $referenced){
			return $item->table_name == $table && $item->referenced_table_name == $referenced;
		})->first();
	}

}