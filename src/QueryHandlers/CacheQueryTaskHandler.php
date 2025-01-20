<?php
namespace Groquel\Laravel\QueryHandlers;

use Groquel\Laravel\QueryHandlers\StorageQueryTaskHandler;
use Groquel\Laravel\QueryHandlerTasks\EloquentQueryBuilderTask;

final class CacheQueryTaskHandler extends StorageQueryTaskHanddler {
  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @return mixed
    * @throws Exception
    */
  protected function beginProcessing(EloquentQueryBuilderTask $queryTask) {
    
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
