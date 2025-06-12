# Laravel-Groquel
A basic chain of handlers used to make access to any database via an Eloquent Model repository more efficient and fault-tolerant for Laravel v11+ PHP apps.

This package makes setting up caching of database queries much more easier and you can easily override parts you don't like and tune the storage query task handlers to your taste.

## How To / Setup

Basically, this package abstracts the need for a cache or a set of caches and other data store types (e.g. PosgreSQL DB, MongoDB, Text File, REST API) into a  chain of handlers in a fault-tolerant way.

- One handler for a cache (Redis - could be read-only)
- One handler for the main database (PosgreSQL - could be write-only)
- One handler for a REST API service (Paystack Bank API - could also be read-only / only GET requests)
- One handler for a text file on disk (JSON text file for country names and country codes - could be read/write)
- One handler for a custome materialized view database (MongoDB - read-only obviously)

And all these handlers can be put into a chain (i.e. chain-of-responsibility) to process data access requests in the shape of DB queries or REST API requests all behind a single common data repository interface.

Best of all you can use 2 cache handlers (one for DB queries and another one for REST API requests) in the same chain of handlers behind a single abstraction.

Lastly, you can also swap handlers in and out of the chain of handlers at runtime.

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
