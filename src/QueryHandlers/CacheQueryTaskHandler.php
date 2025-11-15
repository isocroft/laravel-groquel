<?php
namespace Groquel\Laravel\QueryHandlers;

use \Exception as Exception;

use Groquel\Laravel\QueryHandlers\Contracts\StorageQueryTask;
use Groquel\Laravel\QueryHandlers\StorageQueryTaskHandler;
use Groquel\Laravel\QueryHandlerTasks\EloquentQueryBuilderTask;

use Illuminate\Support\Facades\Cache as QueryCache;
use Illuminate\Support\Facades\Config as QueryConfig;
use Illuminate\Support\Facades\Redis;

final class CacheQueryTaskHandler extends StorageQueryTaskHandler {
  /**
    * @param string $queryKey
    * @return boolean
    */
  private function canQuery(string $queryKey): boolean {
    return QueryCache::has($queryKey);
  }

  /**
   * @param StorageQueryTask $queryTask
   * @throws Exception
   *
   * @return StorageQueryTask
   */
  protected function migrateQueryTask(StorageQueryTask $queryTask): StorageQueryTask {
    if ($queryTask instanceof EloquentQueryBuilderTask) {
      return $queryTask;
    }

    $this->skipHandlerProcessing();
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @throws Exception
    *
    * @return string|array|object
    */
  protected function beginProcessing(EloquentQueryBuilderTask $queryTask): string|array|object {
    $canProceedWithProcessing = false;
    $isSQLDatabaseQueryTask = false;

    /* @HINT: $sql = "select * from users|users" */
    $sql = $queryTask->getQuerySqlString();

    if ($sql !== "|") {
      /* @NOTE: Cannot cache select queries that contain JOIN clauses as caching those would be error-prone */
      $canProceedWithProcessing = (strtolower(substr($sql, 0, 6)) === "select" or strtolower(substr($sql, 0, 10)) === "db_select:")
        and (strpos(strtolower($sql), "with_join") === false);
      $isSQLDatabaseQueryTask = true
    }

    if (!canProceedWithProcessing or !$isSQLDatabaseQueryTask) {
      $this->skipHandlerProcessing();
      return [];
    }

    /* @HINT: $queryHash = "19a1f14efc0f221d30afcb1e1344bebd|users" */
    $queryHash = md5(substr($sql, 0, strpos($sql, "|"))). "|" .$queryTask->getQueryBuilderTableName();
    $isCacheHit = $this->canQuery($queryHash);

    if ($isCacheHit) {
      return QueryCache::get($queryHash);
    }

    $this->skipHandlerProcessing();
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @param mixed $result
    * @throws Exception
    *
    * @return void
    */
  protected function finalizeProcessing(EloquentQueryBuilderTask $queryTask, $result): void {
    $canProceedWithProcessing = false;
    $isSQLDatabaseQueryTask = false;

    /* @HINT: $sql = "select * from users|users" */
    $sql = $queryTask->getQuerySqlString();

    if ($sql !== "|" and strpos(strtolower($sql), "select") === false) {
        /* @HINT: This might be a mutation query (e.g. `insert`, `delete` or `update` query) */
        /* @NOTE: mutation queries trigger an invalidation of queries based on table names */
        $prefix = QueryConfig::get("cache.prefix", "laravel_cache:");
        $pattern = "|".$queryTask->getQueryBuilderTableName();
        $redis = QueryCache::getStore()->client();
        
        // @INFO: Use the KEYS command in develpoment/small datasets & use SCAN for production/large datasets
        $storedKeys = $redis->keys(
            $prefix .'*'. $pattern
        );
        
        foreach ($storedKeys as $key) {
            // @HINT: Extract the original key name
            $originalKey = str_replace($redis->getOption(Redis::OPT_PREFIX), '', $key);
            QueryCache::forget($originalKey);
        }
        return;
    }

    if ($sql !== "|" and isset($result)) {
      $canProceedWithProcessing = (strtolower(substr($sql, 0, 6)) === "select" or strtolower(substr($sql, 0, 10)) === "db_select:")
        and (strpos(strtolower($sql), "with_join") === false);
      $isSQLDatabaseQueryTask = true
    }

    if (!canProceedWithProcessing or !$isSQLDatabaseQueryTask) {
      return;
    }

    /* @HINT: $queryHash = "19a1f14efc0f221d30afcb1e1344bebd|users" */
    $queryHash = md5(substr($sql, 0, strpos($sql, "|"))). "|" .$queryTask->getQueryBuilderTableName();
    $ttl = QueryConfig::get("groquel.handler.cache.ttl", 2300); // @HINT: integer value
    $isCacheMiss = !$this->canQuery($queryHash);

    if (isCacheMiss) {
      QueryCache::put($queryHash, $result, $ttl);
    }
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @param Exception $error
    * @throws Exception
    *
    * @return void
    */
  protected function finalizeProcessingWithError(EloquentQueryBuilderTask $queryTask, Exception $error): void {
    $queryKey = $queryTask->getQueryKey();
    throw new Exception("Cache query task='".$queryKey."' failed; reason: ('".$error->getMessage()."')");
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @throws Exception
    *
    * @return string|array|object
    */
  protected function alternateProcessing(EloquentQueryBuilderTask $queryTask) {
    $queryKey = $queryTask->getQueryKey();
    throw new Exception("Cache query task='".$queryKey."' failed; reason: unknown");
  }
}

?>
