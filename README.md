# Laravel-Groquel
A system of handlers used to make data access from I/O sources more efficient for Laravel v10+ PHP apps

## Usage

```php
<?php

use App\Models\User;
use Groquel\Laravel\QueryRepository\SQLDatabaseTableRepository;
//use lluminate\Support\Facades\DB;

use Illuminate\Database\Query\Builder as QueryBuilder;

final class UserTableRepository extends SQLDatabaseTableRepository {

  public final getAllActiveUsers () {
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
        $innerQueryBuilder = $context->getQueryBuilder()

        $innerQueryBuilder->orderBy('created_at', 'desc')->groupBy('status')
        return $innerQueryBuilder;
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
      return new UserTableRepository($app['QueryHandlersList'], $app->make('App\Models\User');
    });
  }
}
```
