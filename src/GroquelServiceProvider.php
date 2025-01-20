<?php
namespace Groquel\Laravel;

use Groquel\Laravel\QueryHandlerTasks\EloquentQueryBuilderTask;

use Illuminate\Support\ServiceProvider;

class GroquelServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->bind(EloquentQueryBuilderTask::class, function (Container $app) {
          return new MongoBatchRepository(
            
          )
        });
    }
?>
