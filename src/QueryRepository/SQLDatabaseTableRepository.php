<?php

namespace Groquel\Laravel\QueryRepository;

use Closure;
use Exception;

use Illuminate\Database\Eloquent\Model as RootModel;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;

use Groquel\Laravel\QueryHandlerTasks\EloquentQueryBuilderTask;
use Groquel\Laravel\QueryHandlers\StorageQueryTaskHandler;
use Groquel\Laravel\QueryHandlerSupport\StorageQueryTaskHandlersManager;

use lluminate\Support\Facades\DB;

final class FluentSQLQueryBuilderExecutor {
  /**
   * @var QueryBuilderTask[] $queryBuilderTasks
   */
  private $queryBuilderTasks;

  /**
   * @var StorageQueryTaskHandlersManager $queryManager
   */
  private $queryManager;

  public function __construct (StorageQueryTaskHandlersManager $queryManager) {
    $this->queryBuilderTasks = [];
    $this->queryManager = $queryManager;
  }

  public function __destruct () {
    $this->queryBuilderTasks = null;
    $this->queryManager = null;
  }

  /**
   *
   * @param StorageQueryTaskHandler $newRootHandler
   * @return void
   */
  public function setNewRootHandler(StorageQueryTaskHandler $newRootHandler): void {
    $this->queryManager->swapRootHandler($newRootHandler);
  }

  /**
   * 
   * @return EloquentQueryBuilderTask
   */
  public function recordExecutorBuilderTask (QueryBuilder|Closure $queryBuilder, string $nameOfDeferedMethodToCall, array $defferedMethodArguments = []) {
    $queryBuilderTask = new EloquentQueryBuilderTask(
      $queryBuilder,
      $nameOfDeferedMethodToCall,
      $defferedMethodArguments
    );
    $this->queryBuilderTasks[] = $queryBuilderTask;

    return $queryBuilderTask;
  }

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
   * @throws Exception
   * @return array
   */
  public function extractExecutorResults () {
    $context = &$this;
    $result = DB::transaction(function () use ($context) {
      $executorResults = array();
      foreach ($context->queryBuilderTasks as $queryBuilderTask) {
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
      $context->queryBuilderTasks = [];
      return $executorResults;
    });
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
  public function __construct (array $storageQueryHandlers = [], RootModel $dataModel = null) {
    $queryManager = new StorageQueryHandlersManager($storageQueryHandlers);
    $this->executor = new FluentSQLQueryBuilderExecutor($queryManager);
    $this->dataModel = $dataModel;
    $this->queriesExcecutedQueue = [];
  }

  public function __destruct () {
    $this->executor = null;
    $this->dataModel = null;
  }

  /**
   *
   * @param QueryBuilder $queryBuilder
   * @return string
   */
  protected function getLastExecutedSQLQueryAsString (): string {
    return $this->executor->getLastExecutedSQLQueryAsString();
  }

  /**
    * 
    * @return string
    * @throws Exception
    */
  public function getTableName(): string {
    if (is_null($this->dataModel)) {
      throw new Exception("Model instance for repository: '".get_called_class()."' is null");
    }

    return $this->dataModel->getTable();
  }

  /**
    * 
    * @return QueryBuilder
    * @throws Exception
    */
  protected function getQueryBuilder(): QueryBuilder {
    if (is_null($this->dataModel)) {
      throw new Exception("Model instance for repository: '".get_called_class()."' is null");
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
  final protected function setNewRootHandler(StorageQueryTaskHandler $newRootHandler): void {
    $this->executor->setNewRootHandler($newRootHandler);
  }

  /**
   * @param QueryBuilder $queryBuilder
   * @param string|null $conflictColumn
   *
   * @return QueryBuidler
   */
  final protected function whenConflict(QueryBuilder $queryBuilder, string $conflictColumn = null): QueryBuilder {
    return $conflictColumn !== null ? $queryBuilder->onConflict($conflictColumn) : $queryBuilder;
  }

  /**
   *
   * @return QueryBuilder
   */
  final protected function merge(QueryBuilder $queryBuilder, array $columnsToMerge = []): QueryBuilder {  
    return !empty($columnsToMerge) ? $queryBuilder->merge($columnsToMerge) : $queryBuilder->merge();
  }

  /**
   *
   * @return QueryBuilder
   */
  final protected function addWhereClauses(QueryBuilder $queryBuilder, Closure $whereClausesCallback = null): QueryBuilder {
    return is_callable($whereClausesCallback) ? $queryBuilder->where($whereClausesCallback) : $queryBuilder;
  }

  /**
   *
   * @return QueryBuilder
   */
  final protected function addOrWhereClauses(QueryBuilder $queryBuilder, Closure $whereClausesCallback = null): QueryBuilder {
    return is_callable($whereClausesCallback) ? $queryBuilder->orWhere($whereClausesCallback) : $queryBuilder;
  }

  /**
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
    * @param QueryBuilder $queryBuilder
    *
    * @throws Exception
    */
  final protected function withRelation (QueryBuilder $queryBuilder): QueryBuilder {
    // @TODO: ...
  }

  /**
    * @param QueryBuilder $queryBuilder
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  final protected function  executeGetOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'get');
  }

  /**
    * @param QueryBuilder $queryBuilder
    *
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  final protected function executeExiststOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'exists');
  }

  /**
    * @param QueryBuilder $queryBuilder
    *
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  final protected function executeDoesntExistOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'doesntExist');
  }

  /**
    * @param QueryBuilder $queryBuilder
    *
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  final protected function  executeCountOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'count');
  }

  /**
    * @param QueryBuilder $queryBuilder
    *
    * @return EloquentQueryBuilderTask
    * @throws Exception
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
   *
   * @return EloquentQueryBuilderTask
   * @throws Exception
   */
  protected final function executeUpdateOnQuery (QueryBuilder $queryBuilder, $arguments = []): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask(
      $queryBuilder,
      'update',
      $arguments
    );
  }

  /**
   * @param QueryBuilder $queryBuilder
   * @param mixed[] $arguments
   *
   * @return QueryBuilderTask
   * @throws Exception
   */
  protected final function executeInsertOnQuery (QueryBuilder $queryBuilder, $arguments = []): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask(
      $queryBuilder,
      'insert',
      $arguments
    );
  }

  /**
    * @param string[] $columnsToFetch
    * @param Closure|null $whereClausesCallback
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  public final function fetchAllWhereCallback(array $columnsToFetch = ["*"], Closure|null $whereClausesCallback = null): EloquentQueryBuilderTask {
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
    *
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  public final function modify(array $rowsToUpdate = [], Closure|null $whereClausesCallback = null): EloquentQueryBuilderTask {
    $queryBuilder = $this->getQueryBuilder();
    $addWhereClauses = [$this, 'addWhereClauses'];

    if (empty($rowsToUpdate)) {
      throw new Exception("Cannot proceed: No rows to update found");
    }

    $modifiedQueryBuilder = $addWhereClauses($queryBuilder, $whereClausesCallback);

    return $this->executeUpdateOnQuery(
      $modifiedQueryBuilder,
      [$rowsToUpdate]
    )
  }

  /**
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  public final function fetchFirst(): EloquentQueryBuilderTask {
    $queryBuilder = $this->getQueryBuilder();

    return $this->executeFirstOnQuery(
      $queryBuilder
    );
  }

  /**
   * @return mixed
   * @throws Exception
   */
  protected final function executeAllAndReturnResults () {
    return $this->executor->extractExecutorResults();
  }

  /**
    * @param Closure|null $whereClausesCallback
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  public final function addWhereCallback(Closure|null $whereClausesCallback = null): EloquentQueryBuilderTask {
    $queryBuilder = $this->getQueryBuilder();
    $addWhereClauses = [$this, 'addWhereClauses'];

    $modifiedQueryBuilder = $addWhereClauses($queryBuilder, $whereClausesCallback);

    return $this->executeFirstOnQuery(
      $modifiedQueryBuilder
    );
  }
}

?>
