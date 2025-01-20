<?php
namespace Groquel\Laravel;

use Groquel\Laravel\QueryHandlers\CacheQueryTaskHandler;
use Groquel\Laravel\QueryHandlers\DatabaseQueryTaskHandler;

use Illuminate\Support\ServiceProvider;

abstract class GroquelServiceProvider extends ServiceProvider
{
    public function getHanddlerInstances () {
       return array(
         'default_cache_handler_instance' => new CacheQueryTaskHandler("");
         'default_db_handler_instance' => new DatabaseQueryTaskHandler("");
       );
    }
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->bind(DatabaseQueryTaskHandler::class, function (Container $app) {
          return new DatabaseQueryTaskHandler(
            ""
          )
        });
        $this->app->bind(CacheQueryTaskHandler::class, function (Container $app) {
          return new CacheQueryTaskHandler(
            ""
          )
        });
    }
}
?>
