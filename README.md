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

Best of all you can use 2 cache handlers (one for DB queries and another one for Retry Idempotence/REST API requests) in the same chain of handlers behind a single abstraction.

Lastly, you can also swap handlers in and out of the chain of handlers at runtime.

## Usage

So, instead of doing this (which, on the surface, looks simpler and seems to be less hassle and the right amount of code for setting up caching):

```php

namespace App\Services\Database;

use Illuminate\Supports\Facade\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

final class CacheService {

  public function mutationOperation (array $data, QueryBuilder $builder): Model {
    $didInsert = $builder->insert($data);

    if ($didInsert) {
      return $builder->getModel()->newInstance($data, true);
    }

    return $builder->getModel()->newInstance([]);
  }

  public function queryOperation (string $key, int $timeInMinutes, QueryBuilder $builder): Collection {
    $cacheData = NULL;
    /* @HINT: Combining the Write-around & Cache-aside strategies for the cache */
    if (Cache::has($key)) {
      $cacheData = Cache::get($key, $builder->getModel()->newCollection([]));
    } else {
      $cacheData = Cache::remember($key, $timeInMinutes, function() use ($builder) {
        return $builder->get(['*']);
      });
    }

    return $cacheData;
  }

  public function invalidateOperation (string $key): bool {
    if (Cache::has($key)) {
      Cache::delete($key);
      return true;
    }

    return false;
  }
}
```

The code above is a good start because you can pass in a query builder instance for any eloquent model and also presents a generic interface that can be easily adapted to sepcific situations where the cache service would be needed. However, the issue with the code above is that you have to manually call the methods at the right time and place inside your controller (instead of leveraging inversion-of-control). Additionally, there's the issue of a lack of robust error handling too and in the future if we need to extend the functionality here, it can take longer to implement without bugs.

Alternatively, a data repository can be created for a specific eloquent model (in this case `App\Models\User`) (or any eloquent model for that matter) to access data through the chain of handlers (while leveraging inversion-of-control and robust  error-handling).

```php
<?php

namespace App\Services\Database;

use App\Models\User;
use Groquel\Laravel\QueryRepository\SQLDatabaseTableRepository;

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
use App\Extensions\Helpers\RetryIdempotencyCacheStorageQueryHandler;
use Groquel\Laravel\GroquelServiceProvider;

class AllRepositoriesServiceProvider extends GroquelServiceProvider {
  /**
   * Register any application services.
   */
  public function register(): void {

    parent::register();

    $this->app->bind(RetryIdempotencyCacheStorageQueryHandler::class, function () {
      return new RetryIdempotencyCacheStorageQueryHandler("Error message for skipping handler");
    });

    $this->app->singleton(UserTableRepository::class, function (/*Application*/$app) {
      $newQueryHandlersList = [
        $app['QueryHandlersList'][0],
        /* This handler implements idempotency for HTTP requests */
        $app->make(RetryIdempotencyCacheStorageQueryHandler::class),
        $app['QueryHandlersList'][1]
      ];
      return new UserTableRepository($newQueryHandlersList, $app->make('App\Models\User'));
    });
  }
}
```
