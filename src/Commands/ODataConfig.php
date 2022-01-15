<?php

namespace OData\OData\Commands;

use OData\OData\ODataSchema;
use Illuminate\Console\Command;

class ODataConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'odata:config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obtener el esquema de base de datos y almacenarlo en cache';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $odata = new ODataSchema();
        $odata->clearConfig();
        $odata->setConfig();
        $odata->setQueries();
        echo "Database schema stored successfully!";
    }
}
