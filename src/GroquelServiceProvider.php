<?php
namespace Groquel\Laravel;

use Groquel\Laravel\QueryHandlers\CacheQueryTaskHandler;
use Groquel\Laravel\QueryHandlers\DatabaseQueryTaskHandler;

//use Illuminate\Container\Container;
//use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

abstract class GroquelServiceProvider extends ServiceProvider {
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void {
      $this->publishes([
        __DIR__.'/../config/groquel.php' => config_path('groquel.php'),
      ]);
    }

    /**
     * Register groquel application services.
     *
     * @return void
     */
    public function register(): void {
        $this->mergeConfigFrom(
          __DIR__.'/../config/groquel.php', 'groquel'
        );

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

        $this->app->singleton('QueryHandlersList', function (/*Application|Container*/$app) {
          return [$app->make(CacheQueryTaskHandler::class), $app->make(DatabaseQueryTaskHandler::class)];
        });
    }
}
?>
