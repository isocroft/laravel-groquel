<?php
namespace Groquel\Laravel\QueryHandlers;

use Groquel\Laravel\QueryHandlers\StorageQueryTaskHandler;
use Groquel\Laravel\QueryHandlerTasks\EloquentQueryBuilderTask;

use Illuminate\Support\Facades\Cache as QueryCache;

final class CacheQueryTaskHandler extends StorageQueryTaskHanddler {
  /**
    * @param string $queryKey
    * @return boolean
    */
  private function canQuery(string $queryKey) {
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
      $canProceedWithProcessing = (strtolower(substr($sql, 0, 6)) === "select" || strtolower(substr($sql, 0, 10)) === "db_select:")
        && strpos(strtolower($sql), "join") === false;
      $isSQLDatabaseQueryTask = true
    }

    if (!canProceedWithProcessing) {
      return $this->skipHandlerProcessing();
    }

    /* @TODO: More code here... */
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
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
