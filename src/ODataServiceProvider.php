<?php 

namespace OData\OData;

use OData\OData\Commands\ODataConfig;
use Illuminate\Support\ServiceProvider;

class ODataServiceProvider extends ServiceProvider
{
	public function boot()
	{
		if ($this->app->runningInConsole()) {
            $this->commands([
                ODataConfig::class,
            ]);
        }
	}
}

//documentación: https://laravel.com/docs/8.x/packages