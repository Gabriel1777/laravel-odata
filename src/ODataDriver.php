<?php 

namespace OData\OData;

use Illuminate\Support\Facades\DB;

class ODataDriver {

	public function getSchemaRelations($database, $driver)
	{
		switch ($driver) {
			case 'mysql':
				return DB::select("SELECT table_name, column_name, constraint_name, referenced_table_name, referenced_column_name 
				FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = '". $database ."';");
				break;
			case 'pgsql':
			    return DB::select("SELECT tc.table_schema, tc.constraint_name, tc.table_name, kcu.column_name, ccu.table_schema 
				    AS referenced_table_schema, ccu.table_name AS referenced_table_name, ccu.column_name AS referenced_column_name 
				    FROM information_schema.table_constraints AS tc JOIN information_schema.key_column_usage AS kcu
				    ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
				    JOIN information_schema.constraint_column_usage AS ccu ON ccu.constraint_name = tc.constraint_name
				    AND ccu.table_schema = tc.table_schema WHERE tc.table_schema = 'public' and ccu.table_schema = 'public' and kcu.column_name != 'id';");
			    break;
			default:
				return false;
				break;
		}
	}

	public function getTablesFromDatabase($database, $driver)
	{
		switch ($driver) {
			case 'mysql':
				return DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = '". $database ."';");
				break;
			case 'pgsql':
			    return DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
			    break;
			default:
				return false;
				break;
		}
	}

	public function getColumnsFromTable($database, $table, $driver)
	{	
		switch ($driver){
			case 'mysql':
			    return DB::select("SELECT column_name FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '". $database ."' AND TABLE_NAME = '". $table ."';");
			    break;
			case 'pgsql':
			    return DB::select("SELECT column_name FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'public' AND TABLE_NAME = '". $table ."';");
			    break;
			default:
			    return false; 
			    break;
		}
	}
}