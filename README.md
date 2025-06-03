# Laravel-Groquel
A basic chain of handlers used to make access to any database via an Eloquent Model repository more efficient and simple for Laravel v11+ PHP apps.

This package makes setting up caching of database queries much more easier and you can easily override parts you don't like and tune the storage query task handlers to your taste.

## Usage

Firstly, create an data repository for a specific eloquent moddel (in this case `App\Models\User`) to access data through the chain of handlers.

```php
<?php

use App\Models\User;
use Groquel\Laravel\QueryRepository\SQLDatabaseTableRepository;
//use lluminate\Support\Facades\DB;

use Illuminate\Database\Query\Builder as QueryBuilder;

final class UserTableRepository extends SQLDatabaseTableRepository {

  public function getAllActiveUsers () {
    $context = &$this;
    $queryOneBuilder = User::where(function (Builder $query) {
      $query->whereNot('status', '=', 'suspended');
    });
    $tableName = User::query()->getModel()->getTable();

    $this->executeGetOnQuery(
      $queryOneBuilder->sharedLock()
    )->setQueryTaskName("step1|".$tableName);

    $this->executeGetOnQuery(
      function (array $arguments) use ($context) {
        $innerQueryBuilder = $context->getQueryBuilder();

        return $innerQueryBuilder->orderBy('created_at', 'desc')->groupBy('status');
      }
    )->setQueryTaskName("step2|".$tableName);

    $results = $this->executeAllAndReturnResults();
    return $results["step2|".$tableName];
  }
}

?>
```

Create custom service provider using the data repository created above

```php

namespace App\Providers;
 
//use Illuminate\Contracts\Foundation\Application;
use Groquel\Laravel\GroquelServiceProvider;

class AllRepositoriesServiceProvider extends GroquelServiceProvider {
  /**
   * Register any application services.
   */
  public function register(): void {

    parent::register();

    $this->app->singleton(UserTableRepository::class, function (/*Application*/$app) {
      return new UserTableRepository($app['QueryHandlersList'], $app->make('App\Models\User'));
    });
  }
}
```
