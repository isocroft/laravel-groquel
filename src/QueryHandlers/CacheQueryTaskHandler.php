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
    * @return mixed
    * @throws Exception
    */
  protected function beginProcessing(EloquentQueryBuilderTask $queryTask) {
    $canProceedWithProcessing = false;
    $isSQLDatabaseQueryTask = false;
    $sql = $queryTask->getQuerySql();

    if ($sql !== "") {
      $canProceedWithProcessing = (strtolower(substr($sql, 0, 6)) === "select" or strtolower(substr($sql, 0, 10)) === "db_select:")
        and (strpos(strtolower($sql), "join") === false or strpos(strtolower($sql), ";join") === false);
      $isSQLDatabaseQueryTask = true
    }

    if (!canProceedWithProcessing or !$isSQLDatabaseQueryTask) {
      return $this->skipHandlerProcessing();
    }

    $queryHash = md5($sql);
    $isCacheHit = $this->canQuery($queryHash);

    if ($isCacheHit) {
      return QueryCache::get($queryHash);
    }

    return $this->skipHandlerProcessing();
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @param mixed $result
    * @return void
    * @throws Exception
    */
  protected function finalizeProcessing(EloquentQueryBuilderTask $queryTask, $result): void {
      
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @param Exception $error
    * @return void
    * @throws Exception
    */
  protected function finalizeProcessingWithError(EloquentQueryBuilderTask $queryTask, Exception $error): void {
    $queryName = $queryTask->getQueryTaskName();
    throw new Exception("Caching query task='".$queryName."' failed; reason: ('".$error->getMessage()."')")
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @return mixed
    * @throws Exception
    */
  protected function alternateProcessing(EloquentQueryBuilderTask $queryTask) {
    
  }
}

?>
