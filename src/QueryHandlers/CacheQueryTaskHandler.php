<?php
namespace Groquel\Laravel\QueryHandlers;

use \Exception as Exception;

use Groquel\Laravel\QueryHandlers\StorageQueryTaskHandler;
use Groquel\Laravel\QueryHandlerTasks\EloquentQueryBuilderTask;

use Illuminate\Support\Facades\Cache as QueryCache;

final class CacheQueryTaskHandler extends StorageQueryTaskHanddler {
  /**
    * @param string $queryKey
    * @return boolean
    */
  private function canQuery(string $queryKey): boolean {
    return QueryCache::has($queryKey);
  }
  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @throws Exception
    *
    * @return mixed
    */
  protected function beginProcessing(EloquentQueryBuilderTask $queryTask) {
    $canProceedWithProcessing = false;
    $isSQLDatabaseQueryTask = false;
    $sql = $queryTask->getQuerySqlString();/* @HINT: $sql = "select * from users|users" */

    if ($sql !== "") {
      $canProceedWithProcessing = (strtolower(substr($sql, 0, 6)) === "select" or strtolower(substr($sql, 0, 10)) === "db_select:")
        and (strpos(strtolower($sql), "join") === false or strpos(strtolower($sql), ";join") === false);
      $isSQLDatabaseQueryTask = true
    }

    if (!canProceedWithProcessing or !$isSQLDatabaseQueryTask) {
      return $this->skipHandlerProcessing();
    }

    $queryHash = strpos($sql, "|") !== false
      ? md5(substr($sql, 0, strpos($sql, "|")))."|".substr($sql, strpos($sql, "|"), strlen($sql) - 1)
      : md5($sql);/* @HINT: $queryHash = "19a1f14efc0f221d30afcb1e1344bebd|users" */
    $isCacheHit = $this->canQuery($queryHash);

    if ($isCacheHit) {
      return QueryCache::get($queryHash);
    }

    return $this->skipHandlerProcessing();
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
    $sql = $queryTask->getQuerySqlString();/* @HINT: $sql = "select * from users|users" */

    if (isset($result) and $sql !== "") {
      $canProceedWithProcessing = (strtolower(substr($sql, 0, 6)) === "select" or strtolower(substr($sql, 0, 10)) === "db_select:")
        and (strpos(strtolower($sql), "join") === false or strpos(strtolower($sql), ";join") === false);
      $isSQLDatabaseQueryTask = true
    }

    if (!canProceedWithProcessing or !$isSQLDatabaseQueryTask) {
      return;
    }
      
    $queryHash = strpos($sql, "|") !== false
      ? md5(substr($sql, 0, strpos($sql, "|")))."|".substr($sql, strpos($sql, "|"), strlen($sql) - 1)
      : md5($sql);/* @HINT: $queryHash = "19a1f14efc0f221d30afcb1e1344bebd|users" */
    $ttl = 2300;
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
    $queryName = $queryTask->getQueryTaskName();
    throw new Exception("Cache query task='".$queryName."' failed; reason: ('".$error->getMessage()."')");
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @throws Exception
    *
    * @return mixed
    */
  protected function alternateProcessing(EloquentQueryBuilderTask $queryTask) {
    $queryName = $queryTask->getQueryTaskName();
    throw new Exception("Cache query task='".$queryName."' failed; reason: unknown");
  }
}

?>
