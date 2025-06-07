<?php

namespace Groquel\Laravel\QueryRepository;

use Closure;
use \Exception as Exception;

use Illuminate\Database\Eloquent\Model as RootModel;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

use Groquel\Laravel\QueryHandlerTasks\EloquentQueryBuilderTask;
use Groquel\Laravel\QueryHandlers\StorageQueryTaskHandler;
use Groquel\Laravel\QueryHandlerSupport\StorageQueryTaskHandlersManager;

use Illuminate\Support\Facades\DB;

final class FluentSQLQueryBuilderExecutor {
  /**
   * @var QueryBuilderTask[] $queryBuilderTasks
   */
  private $queryBuilderTasks;

  /**
   * @var StorageQueryTaskHandlersManager $queryManager
   */
  private $queryManager;

  /**
   * @param StorageQueryTaskHandlersManager $queryManager
   */
  public function __construct(StorageQueryTaskHandlersManager $queryManager) {
    $this->queryBuilderTasks = [];
    $this->queryManager = $queryManager;
  }

  /**
   * @return void
   */
  public function __destruct() {
    $this->queryBuilderTasks = NULL;
    $this->queryManager = NULL;
  }

  /**
   *
   * @param StorageQueryTaskHandler $newRootHandler
   * @return void
   */
  public function setNewRootHandler(StorageQueryTaskHandler $newRootHandler): void {
    $this->queryBuilderTasks = [];
    $this->queryManager->swapRootHandler($newRootHandler);
  }

  /**
   *
   * @param QueryBuilder|Closure $queryBuilder
   * @param string $nameOfDeferedMethodToCall
   * @param array $defferedMethodArguments
   *
   * @return mixed
   */
   public function runExectorBuildTask(QueryBuilder|Closure $queryBuilder, string $nameOfDeferedMethodToCall, array $defferedMethodArguments = []) {
     return $context->queryManager->execute(
       $this->recordExecutorBuilderTask($queryBuilder, $nameOfDeferedMethodToCall, $defferedMethodArguments)
     );
   }

  /**
   *
   * @param QueryBuilder|Closure $queryBuilder
   * @param string $nameOfDeferedMethodToCall
   * @param array $defferedMethodArguments
   *
   * @return EloquentQueryBuilderTask
   */
  public function recordExecutorBuilderTask (QueryBuilder|Closure $queryBuilder, string $nameOfDeferedMethodToCall, array $defferedMethodArguments = []): EloquentQueryBuilderTask {
    $queryBuilderTask = new EloquentQueryBuilderTask(
      $queryBuilder,
      $nameOfDeferedMethodToCall,
      $defferedMethodArguments
    );
    $this->queryBuilderTasks[] = $queryBuilderTask;

    return $queryBuilderTask;
  }

  /**
   * @return string
   */
  public function getLastExecutedSQLQueryAsString (): string {
    $lastElementKey = end(array_keys($this->queryBuilderTask));

    while ($lastElementKey > 0) {
      $lastQueryBuilderTask = $this->queryBuilderTasks[$lastElementKey];
      if ($lastQueryBuilderTask->isExecuted()) {
        return $lastQueryBuilderTask->getQueryBuilderSqlString();
      }
      --$lastElementKey;
    }

    return "";
  }

  /**
   *
   * @throws Exception
   *
   * @return mixed[]
   */
  public function extractExecutorResults () {
    $context = &$this;

    $results = DB::transaction(function () use ($context) {
      $executorResults = array();

      foreach ($context->queryBuilderTasks as $queryBuilderTask) {
        if ($queryBuilderTask->isExecuted()) {
          continue;
        }

        if (count($executorResults) > 0) {
          $queryBuilderTask->setCallbackArguments($executorResults);
        }

        $queryTaskName = $queryBuilderTask->getQueryTaskName();
        if ($queryTaskName === "") {
          $executorResults[] = $context->queryManager->execute(
            $queryBuilderTask
          );
        } else {
          $executorResults[$queryTaskName] = $context->queryManager->execute(
            $queryBuilderTask
          );
        }
      }

      return $executorResults;
    });

    return $results;
  }
}



abstract class SQLDatabaseTableRepository {
  /**
   * @var FluentSQLQueryBuilderExecutor $executor
   */
  private $executor;

  /**
   * @var RootModel|null $dataModel
   */
  private $dataModel;

  /**
   * @param StorageQueryTaskHandler[] $storageQueryHandlers
   * @throws Exception
   */
  public function __construct(array $storageQueryHandlers = [], RootModel $dataModel = null) {
    $queryManager = new StorageQueryHandlersManager($storageQueryHandlers);
    $this->executor = new FluentSQLQueryBuilderExecutor($queryManager);
    $this->dataModel = is_null($dataModel) ? $dataModel : $this->checkRootModelFullClassname($dataModel);
  }

  /**
   * @return  void
   */
  public function __destruct() {
    $this->executor = NULL;
    $this->dataModel = NULL;
  }

  /**
   * @param RootModel $rootDataModel
   * @throws Exception
   *
   * @return RootModel
   */
   private function checkRootModelFullClassname($rootDataModel) {
     $classNameString = $this->getRootModelFullClassName();

     if (get_class($rootDataModel) !== $classNameString) {
       throw new Exception("RootModel object is not an instance of class: ".$classNameString);
     }

     return $rootDataModel;
   }

   /**
    *
    * @return string
    */
   abstract public function getRootModelFullClassName(): string;

  /**
   *
   * @return string
   */
  final protected function getLastExecutedSQLQueryAsString(): string {
    return $this->executor->getLastExecutedSQLQueryAsString();
  }

  /**
    * 
    * @throws Exception
    *
    * @return string
    */
  public function getTableName(): string {
    if (is_null($this->dataModel)) {
      throw new Exception("RootModel instance for repository: '".get_called_class()."' is null");
    }

    return $this->dataModel->getTable();
  }

  /**
    * 
    * @throws Exception
    *
    * @return QueryBuilder
    */
  protected function getQueryBuilder(): QueryBuilder {
    if (is_null($this->dataModel)) {
      throw new Exception("RootModel instance for repository: '".get_called_class()."' is null");
    }

    $brandNewModelInstance = $this->dataModel->newInstance();
    return $brandNewModelInstance->newQuery();
  }

  /**
    * @param StorageQueryTaskHandler $newRootHandler
    * @throws Exception
    *
    * @return void
    */
  final public function setNewRootHandler(StorageQueryTaskHandler $newRootHandler): void {
    $this->executor->setNewRootHandler($newRootHandler);
  }

  /**
   *
   * @param QueryBuilder $queryBuilder
   * @param array $columns
   * @param string $operator
   * @param mixed $value
   *
   * @return QueryBuilder
   */
  final protected function whereAll(QueryBuilder $queryBuilder, array $columns = [], string $operator = "like", $value = "%.*%"): QueryBuilder {
    return !empty($columns) ? $queryBuilder->whereAll($columns, $operator, $value) : $queryBuilder;
  }

  /**
   *
   * @param QueryBuilder $queryBuilder
   * @param array $columns
   * @param string $operator
   * @param mixed $value
   *
   * @return QueryBuilder
   */
  final protected function whereNone(QueryBuilder $queryBuilder, array $columns = [], string $operator = "like", $value = "%.*%"): QueryBuilder {
    return !empty($columns) ? $queryBuilder->whereNone($columns, $operator, $value) : $queryBuilder;
  }

  /**
   *
   * @param QueryBuilder $queryBuilder
   * @param array $columns
   * @param string $operator
   * @param mixed $value
   *
   * @return QueryBuilder
   */
  final protected function whereAny(QueryBuilder $queryBuilder, array $columns = [], string $operator = "like", $value = "%.*%"): QueryBuilder {  
    return !empty($columns) ? $queryBuilder->whereAny($columns, $operator, $value) : $queryBuilder;
  }

  /**
   *
   * @param QueryBuilder $queryBuilder
   * @param Closure|null $whereClausesCallback
   *
   * @return QueryBuilder
   */
  final protected function addWhereClauses(QueryBuilder $queryBuilder, Closure $whereClausesCallback = null): QueryBuilder {
    return is_callable($whereClausesCallback) ? $queryBuilder->where($whereClausesCallback) : $queryBuilder;
  }

  /**
   *
   * @param QueryBuilder $queryBuilder
   * @param Closure|null $whereClausesCallback
   *
   * @return QueryBuilder
   */
  final protected function addOrWhereClauses(QueryBuilder $queryBuilder, Closure $whereClausesCallback = null): QueryBuilder {
    return is_callable($whereClausesCallback) ? $queryBuilder->orWhere($whereClausesCallback) : $queryBuilder;
  }

  /**
   *
   * @param QueryBuilder $queryBuilder
   * @param mixed[] $whereClauseDetails
   *
   * @return QueryBuilder
   */
  final protected function addWhereClause(QueryBuilder $queryBuilder, array $whereClauseDetails = []): QueryBuilder {
    return is_callable($whereClausesCallback)
      ? call_user_func_array(
          array($queryBuilder, 'where'),
          $whereClauseDetails
        )
      : $queryBuilder;
  }

  /**
   *
   * @param QueryBuilder $queryBuilder
   *
   * @throws Exception
   */
  final protected function withRelation (QueryBuilder $queryBuilder): QueryBuilder {
    // @TODO: ...
  }

  /**
   *
   * @param QueryBuilder $queryBuilder
   * @throws Exception
   *
   * @return EloquentQueryBuilderTask
   */
  final protected function executeDeleteOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'delete');
  }

  /**
   *
   * @param QueryBuilder $queryBuilder
   * @throws Exception
   *
   * @return EloquentQueryBuilderTask
   */
  final protected function executeGetOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'get');
  }

  /**
   *
   * @param QueryBuilder $queryBuilder
   * @throws Exception
   *
   * @return EloquentQueryBuilderTask
   */
  final protected function executeExistsOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'exists');
  }

  /**
    * @param QueryBuilder $queryBuilder
    * @throws Exception
    *
    * @return EloquentQueryBuilderTask
    */
  final protected function executeDoesntExistOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'doesntExist');
  }

  /**
    * @param QueryBuilder $queryBuilder
    * @throws Exception
    *
    * @return EloquentQueryBuilderTask
    */
  final protected function  executeCountOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'count');
  }

  /**
    * @param QueryBuilder $queryBuilder
    * @throws Exception
    *
    * @return EloquentQueryBuilderTask
    */
  final protected function executeFirstOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask(
      $queryBuilder,
      'first'
    );
  }

  /**
   * @param QueryBuilder $queryBuilder
   * @param mixed[] $arguments
   * @throws Exception
   *
   * @return EloquentQueryBuilderTask
   */
  final protected function executeUpdateOnQuery (QueryBuilder $queryBuilder, $arguments = []): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask(
      $queryBuilder,
      'update',
      $arguments
    );
  }

  /**
   * @param QueryBuilder $queryBuilder
   * @param mixed[] $arguments
   * @throws Exception
   *
   * @return QueryBuilderTask
   */
  final protected function executeInsertOnQuery (QueryBuilder $queryBuilder, $arguments = []): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask(
      $queryBuilder,
      'insert',
      $arguments
    );
  }

  /**
    * @param string[] $columnsToFetch
    * @param Closure|null $whereClausesCallback
    * @throws Exception
    *
    * @return EloquentQueryBuilderTask
    */
  final public function fetchAllWhereCallback(array $columnsToFetch = ["*"], Closure $whereClausesCallback = null): EloquentQueryBuilderTask {
    $queryBuilder = $this->getQueryBuilder();
    $addWhereClauses = [$this, 'addWhereClauses'];

    $columnsToFetch = empty($columnsToFetch) ? ["*"] : $columnsToFetch;
    $modifiedQueryBuilder = $addWhereClauses($queryBuilder, $whereClausesCallback);

    return $this->executeGetOnQuery(
      $modifiedQueryBuilder->select($columnsToFetch)
    );
  }

  /**
    * @param array $rowsToUpdate
    * @param Closure|null $whereClausesCallback
    * @throws Exception
    *
    * @return EloquentQueryBuilderTask
    */
  final public function modify(array $rowsToUpdate = [], Closure $whereClausesCallback = null): EloquentQueryBuilderTask {
    $queryBuilder = $this->getQueryBuilder();
    $addWhereClauses = [$this, 'addWhereClauses'];

    if (empty($rowsToUpdate)) {
      throw new Exception("Cannot proceed with sql query; No rows to update found");
    }

    $modifiedQueryBuilder = $addWhereClauses($queryBuilder, $whereClausesCallback);

    return $this->executeUpdateOnQuery(
      $modifiedQueryBuilder,
      [$rowsToUpdate]
    )
  }

  /**
   *
   * @throws Exception
   *
   * @return EloquentQueryBuilderTask
   */
  final public function fetchFirst(): EloquentQueryBuilderTask {
    $queryBuilder = $this->getQueryBuilder();

    return $this->executeFirstOnQuery(
      $queryBuilder
    );
  }

  /**
   *
   * @throws Exception
   *
   * @return mixed
   */
  final protected function executeAllAndReturnResults () {
    return $this->executor->extractExecutorResults();
  }

  /**
    * @param Closure|null $whereClausesCallback
    * @throws Exception
    *
    * @return EloquentQueryBuilderTask
    */
  final protected function addWhereCallback(Closure $whereClausesCallback = null): EloquentQueryBuilderTask {
    $queryBuilder = $this->getQueryBuilder();
    $addWhereClauses = [$this, 'addWhereClauses'];

    $modifiedQueryBuilder = $addWhereClauses($queryBuilder, $whereClausesCallback);

    return $this->executeFirstOnQuery(
      $modifiedQueryBuilder
    );
  }
}

?>
