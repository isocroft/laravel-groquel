# Laravel-Groquel
A simple chain of query-handlers which ensures access to any data source (e.g. database, cache, REST API endpoint) is easy, efficient and fault-tolerant for Laravel v11+ PHP apps

Basically, this package is the [**Tanstack Query**](https://tanstack.com/query) for every Laravel v11.x+ backend with a lot more flexibility.

Also, this package makes setting up caching of database queries a breeze using a chain of query-handler class objects. It can also easily be customized to override/swap out query-handlers you don't like while customizing the query-handlers functions (or storage query task handlers) to your taste.

## How To / Setup

Basically, this package abstracts the need for a cache or a set of ata sources (e.g. PosgreSQL DB, MongoDB, Redis, a JSON file on disk, REST API) into a chain of query-handlers in a fault-tolerant way.

- One handler for a cache (Redis - could be read-only)
- One handler for the main database (PosgreSQL - could be write-only or read-write)
- One handler for a REST API service (Paystack Bank API - could also be read-only / only GET requests)
- One handler for a text file on disk (JSON text file for country names and country codes - could be read/write)
- One handler for a custom materialized view database (MongoDB - read-only obviously)

And all these query-handlers can be put into a chain (i.e. using the chain-of-responsibility pattern) to process data query/mutation requests in the shape of DB queries or REST API requests all behind a single common data repository public interface.

Best of all you can use 2 cache handlers (one for DB queries and another one for Retry Idempotence/REST API requests) in the same chain of handlers behind a single abstraction.

Lastly, you can also swap handlers in and out of the chain of handlers at runtime.

## Usage

So, instead of doing this (which, on the surface, looks simpler and seems to be less hassle and the right amount of code for setting up caching):

```php
<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

final class CacheThroughService {

  public function insertOperation (array $data, QueryBuilder $builder): Model {
    $didInsert = $builder->insert($data);

    if ($didInsert) {
      return $builder->getModel()->newInstance($data, true);
    }

    return $builder->getModel()->newInstance([]);
  }

  public function updateOperation (array $data, QueryBuilder $builder): Model {
    $affectedRows = $builder->update($data);
    $didUdpate = $affectedRows > 0;

    if ($didUdpate) {
      return $builder->getModel()->where($data)->first();
    }

    return $builder->getModel()->newInstance([]);
  }

  public function queryOperation (string $key, QueryBuilder $builder, int $timeInMinutes = 5): Collection {
    $cacheData = NULL;
    
    /* @HINT: Combining the write-around & cache-through strategies for this cache */
    if (Cache::has($key)) {
      $cacheData = Cache::get($key, $builder->getModel()->newCollection([]));
    } else {
      // @HINT: cache-through logic implemented here...
      $cacheData = Cache::remember($key, $timeInMinutes, function() use ($builder) {
        return $builder->get(['*']);
      });
    }

    return $cacheData;
  }

  public function invalidateCacheContent (string $key, QueryBuilder $builder): bool {
    if (Cache::has($key)) {
      Cache::delete($key);
      /* @HINT:
                After clearing the cache using `$key` above, refetch from database
                and hydrate the cache using the same key.
      */
      $this->queryOperation($key, $builder, 3);
      return true;
    }

    return false;
  }
}

?>
```

The code above is a good start because you can pass in a query builder instance for any eloquent model and it also presents a generic interface that can be easily adapted to sepcific situations where the cache-through service would be needed. However, the issue with the code above is that you have to manually call the methods at the right time and place inside your controller (instead of leveraging inversion-of-control). Additionally, there's the issue of a lack of robust error handling too and so in the future if we need to extend the functionality here, it can take longer to implement without bugs.

See an example of the above abstraction (i.e. `CacheThroughService`) in action below:

```php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Services\Storage\CacheThroughService;
use App\Models\Post;

class PostController extends Controller
{

    protected $cacheService;

    /**
     * Inject the CacheThroughService via the constructor.
     */
    public function __construct(CacheThroughService $cacheThroughService)
    {
        $this->cacheService = $cacheThroughService;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updatePostViewsMetadata (Request $request, $id)
    {
        // @HINT: Assume, failure at first until determined otherwise.
        $payload = [
            'status' => 'failure',
            'data' => []
        ];
        $status = 422;
        
        $builder = Post::query()->where('id', $id);
        
        $data = array(
            'last_viewed_at' => now(),
            'read_session_duration' => $request->input('session_duration'),
            'reads_count' => $request->input('scrolled_to_bottom') === 'yes'
                ? DB::raw('reads_count + 1')
                : DB::raw('reads_count + 0')
        );

        $post = $this->cacheThroughService->updateOperation($data, $builder);
        
        if (count($post->toArray()) > 0) {
            /* @HINT:
                    Just like in Tanstack Query where immediately after a mutation, `queryClient.invalidateQueries(...)` is called,
                    the `$this->cacheThroughService->invalidateCacheContentOperation(...)` does the same to clear the cache of any
                    previous entry using the `md5(...)` hash of the `toRawSql()` as the query key.
            */ 
            $this->cacheService->invalidateCacheContent(
                // @NOTE: `->toRawSql()` was added in Laravel v10.15+ 
                md5(Post::query()->select(['*'])->where('id', $id)->toRawSql()),
                Post::query()->where('id', $id)
            );
            $payload = [
                'status' => 'success',
                'data' => $post
            ];
            $status = 200;
        }

        return response()->json($payload, $status);
    }
}

?>
```
From the above code snippet of a **Posts** controller, you can see that invalidating the cache is a simple call but it takes manual effort because the **Posts** controller knows about the existence of the cache service via dependency injection as well as the fact that the logic to invalidate the cache content has to be repeated in every controller action. This is largely sub optimal.

Alternatively, a data repository can be created for a specific eloquent model (in this case `App\Models\User`) (or any eloquent model for that matter) to access data through the chain of handlers (while leveraging already packaged and abstracted inversion-of-control and robust error-handling). **Groquel** makes use of query keys similar to [**Tanstack Query**](https://tanstack.com/query).

See below:

```php
<?php

namespace App\Services\Storage;

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
    )->setQueryKey("db_select|".$tableName."|with_lock");

    $this->executeGetOnQuery(
      function (array $arguments) use ($context) {
        $innerQueryBuilder = $context->getQueryBuilder();

        return $innerQueryBuilder->orderBy('created_at', 'desc')->groupBy('status');
      }
    )->setQueryKey("db_select|".$tableName."|with_modifiers");

    $results = $this->executeAllAndReturnResults();
    return $results["db_select|".$tableName."|with_modifiers"];
  }
}

?>
```

Create custom service provider using the data repository created above

```php
<?php

namespace App\Providers;

use App\Services\Storage\UserTableRepository;
use App\Extensions\Helpers\RetryIdempotencyStorageQueryHandler;
use Groquel\Laravel\GroquelServiceProvider;

class AllRepositoriesServiceProvider extends GroquelServiceProvider {
  /**
   * Register any application services.
   */
  public function register(): void {

    parent::register();

    $this->app->bind(RetryIdempotencyStorageQueryHandler::class, function () {
      // @HINT: This is for setting up an idempotency store for Laravel backends powered by
      //        the `Idempotency-Key` and `If-Unmodified-Since` HTTP request headers.
      return new RetryIdempotencyCacheStorageQueryHandler("Error message for skipping handler");
    });

    $this->app->singleton(UserTableRepository::class, function ($app) {
      $newQueryHandlersList = [
        $app['QueryHandlersList'][0],
        $app->make(RetryIdempotencyCacheStorageQueryHandler::class),
        $app['QueryHandlersList'][1]
      ];
      return new UserTableRepository($newQueryHandlersList, $app->make('App\Models\User'));
    });
  }
}

?>
```
