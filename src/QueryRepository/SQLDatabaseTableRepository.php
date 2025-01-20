<?php

namespace Groquel\Laravel\QueryRepository;

use Closure;
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

  public function __construct(StorageQueryTaskHandlersManager $queryManager) {
    $this->queryBuilderTasks = [];
    $this->queryManager = $queryManager;
  }

  public function setNewRootHandler(StorageQueryTaskHandler $newRootHandler): void {
    $this->queryManager->swapRootHandler($newRootHandler);
  }

  public function recordExecutorBuilderTask (QueryBuilder|Closure $queryBuilder, string $nameOfDefferedMethodToCall, array $defferedMethodArguments = []) {
    $queryBuilderTask = new EloquentQueryBuilderTask(
      $queryBuilder,
      $nameethodToCall,
      $defferedMethodArguments
    );
    $this->queryBuilderTasks[] = $queryBuilderTask;

    return $queryBuilderTask;
  }

  /**
   * @throws Exception
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
    * @param StorageQueryTaskHandler[] $storageQueryHandlers
    * @throws Exception
    */
  public function __construct(array $storageQueryHandlers = []) {
    $queryManager = new StorageQueryHandlersManager($storageQueryHandlers);
    $this->executor = new FluentSQLQueryBuilderExecutor($queryManager);
  }

  /**
    * 
    */
  public abstract function getTableName(): string;

  /**
    * 
    */
  protected abstract function getQueryBuilder(): QueryBuilder;

  /**
    * @param StorageQueryTaskHandler $newRootHandler
    * @throws Exception
    *
    * @return void
    */
  protected final function setNewRootHandler(StorageQueryTaskHandler $newRootHandler): void {
    $this->executor->setNewRootHandler($newRootHandler);
  }

  protected final function whenConflict(QueryBuilder $queryBuilder, string $conflictColumn = null): QueryBuilder {
    return $conflictColumn !== null ? $queryBuilder->onConflict($conflictColumn) : $queryBuilder;
  }

  protected final function merge(QueryBuilder $queryBuilder, array $columnsToMerge = []): QueryBuilder {  
    return !empty($columnsToMerge) ? $queryBuilder->merge($columnsToMerge) : $queryBuilder->merge();
  }

  protected final function addWhereClauses(QueryBuilder $queryBuilder, Closure $whereClausesCallback = null): QueryBuilder {
    return is_callable($whereClausesCallback) ? $queryBuilder->where($whereClausesCallback) : $queryBuilder;
  }

  protected final function addOrWhereClauses(QueryBuilder $queryBuilder, Closure $whereClausesCallback = null): QueryBuilder {
    return is_callable($whereClausesCallback) ? $queryBuilder->orWhere($whereClausesCallback) : $queryBuilder;
  }

  protected final function addWhereClause(QueryBuilder $queryBuilder, array $whereClauseDetails = []): QueryBuilder {
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
  protected final function withRelation (QueryBuilder $queryBuilder): QueryBuilder {
    // @TODO: ...
  }

  /**
    * @param QueryBuilder $queryBuilder
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  protected final executeGetOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'get')
  }

  /**
    * @param QueryBuilder $queryBuilder
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  protected final executeExiststOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'exists')
  }

  /**
    * @param QueryBuilder $queryBuilder
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  protected final executeDoesntExistOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'doesntExist')
  }

  /**
    * @param QueryBuilder $queryBuilder
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  protected final executeCountOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask($queryBuilder, 'count')
  }

  /**
    * @param QueryBuilder $queryBuilder
    * @return EloquentQueryBuilderTask
    * @throws Exception
    */
  protected final executeFirstOnQuery (QueryBuilder $queryBuilder): EloquentQueryBuilderTask {
    return $this->executor->recordExecutorBuilderTask(
      $queryBuilder,
      'first'
    );
  }

  /**
   * @param QueryBuilder $queryBuilder
   * @param mixed[] $arguments
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
