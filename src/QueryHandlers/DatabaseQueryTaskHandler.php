<?php
namespace Groquel\Laravel\QueryHandlers;

use \Exception as Exception;

use Groquel\Laravel\QueryHandlers\Contracts\StorageQueryTask;
use Groquel\Laravel\QueryHandlers\StorageQueryTaskHandler;
use Groquel\Laravel\QueryHandlerTasks\EloquentQueryBuilderTask;

use Illuminate\Support\Facades\DB;

final class DatabaseQueryTaskHandler extends StorageQueryTaskHandler {
  /**
   * @var PDO|null $pdo
   */
  private $pdo;

  /**
   * @param string $skipHandlerErrorMessage
   * @param PDO|null $pdoInstance
   */
  public function __construct(string $skipHandlerErrorMessage, $pdoInstance = null) {
    parent::__construct($skipHandlerErrorMessage);
    $this->pdo = is_null($pdoInstance) ? DB::connection()->getPdo() : $pdoInstance;
  }

  /**
   * @return void
   */
  public function __destruct() {
    parent::__destruct();
    $this->pdo = NULL;
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

    return $this->skipHandlerProcessing();
  }
 
  /**
   * @param EloquentQueryBuilderTask $queryTask
   * @throws Exception
   *
   * @return mixed
   */
  protected function beginProcessing(EloquentQueryBuilderTask $queryTask) {
    return $queryTask->getQueryTaskResult();
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @throws Exception
    *
    * @return void
    */
  protected function finalizeProcessing(EloquentQueryBuilderTask $queryTask): void {
    $queryTask->setAsCompletelyExecuted();
    //$this->pdo->exec('USE another_db');
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
    throw new Exception("Database query task='".$queryName."' failed; reason: ('".$error->getMessage()."')");
  }

  /**
    * @param EloquentQueryBuilderTask $queryTask
    * @throws Exception
    *
    * @return mixed
    */
  protected function alternateProcessing(EloquentQueryBuilderTask $queryTask) {
    $queryName = $queryTask->getQueryTaskName();
    throw new Exception("Database query task='".$queryName."' failed; reason: unknown");
  }
}

?>
