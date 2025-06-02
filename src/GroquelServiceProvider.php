<?php
namespace Groquel\Laravel;

use Groquel\Laravel\QueryHandlers\CacheQueryTaskHandler;
use Groquel\Laravel\QueryHandlers\DatabaseQueryTaskHandler;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;

abstract class GroquelServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->bind(DatabaseQueryTaskHandler::class, function () {
          return new DatabaseQueryTaskHandler(
            "database: error message to skip processing"
          );
        });
        $this->app->bind(CacheQueryTaskHandler::class, function () {
          return new CacheQueryTaskHandler(
            "cache: error message to skip processing"
          );
        });
        $this->app->singleton('QueryHandlersList', function (Container $app) {
          return [$app->make(CacheQueryTaskHandler::class), $app->make(DatabaseQueryTaskHandler::class)];
        });
    }
}
?>
