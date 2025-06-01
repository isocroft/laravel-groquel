<?php
namespace Groquel\Laravel\QueryHandlers;

use \Exception as Exception;

use Groquel\Laravel\QueryHandlers\StorageQueryTaskHanddler;
use Groquel\Laravel\QueryHandlerTasks\EloquentQueryBuilderTask;

final class DatabaseQueryTaskHandler extends StorageQueryTaskHanddler {
  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @return mixed
    * @throws Exception
    */
  protected function beginProcessing(EloquentQueryBuilderTask $queryTask) {
    return $queryTask->getQueryTaskResults();
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
    $queryName = $queryTask->getQueryTaskName();
    throw new Exception("Database query task='".$queryName."' failed; reason: ('".$error->getMessage()."')");
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @return mixed
    * @throws Exception
    */
  protected function alternateProcessing(EloquentQueryBuilderTask $queryTask) {
    $queryName = $queryTask->getQueryTaskName();
    throw new Exception("Database query task='".$queryName."' failed; reason: unknown");
  }
}

?>
