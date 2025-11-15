<?php

namespace Groquel\Laravel\QueryHandlerTasks;

use Closure;
use Groquel\Laravel\QueryHandlers\StorageQueryTaskHandler;
use Groquel\Laravel\QueryHandlers\Contracts\StorageQueryTask;

use Illuminate\Database\Query\Builder as QueryBuilder;

use Illuminate\Support\Facades\Str;

final class EloquentQueryBuilderTask implements StorageQueryTask {
  /**
   * @var QueryBuilder|Closure $queryBuilder
   */
  private $queryBuilder;

  /**
   * @var string $trigger
   */
  private $trigger;

  /**
   * @var array $methodArguments
   */
  private $methodArguments;

  /**
   * @var array callbackArguments
   */
  private callbackArguments;

  /**
   * @var string $queryTaskName
   */
  private $queryTaskName;

  /**
   * @var boolean $executed
   */
  private $executed;

  /**
   * @param QueryBuilder|Clousre $queryBuilder
   * @param string $nameOfMethodToCall
   * @param array $methodArguments
   */
  public function __construct (QueryBuilder|Clousre $queryBuilder, string $nameOfMethodToCall = "", array $methodArguments = []) {
    $this->queryBuilder = $queryBuilder;
    $this->trigger = $nameOfMethodToCall;
    $this->methodArguments = $methodArguments;
    $this->callbackArguments = [];
    $this->queryTaskName = "[anonymous]";
    $this->executed = FALSE;
  }

  /**
   * @return string
   */
  public function getQueryBuilderTableName (): string {
    if (!is_callable($this->queryBuilder)) {
      if (method_exists($this->queryBuilder, 'getModel')) {
        return $this->queryBuilder->getModel()->getTable();
      }
    }
  }

  /**
   * @return string
   */
  public function getQueryBuilderConnectionName (): string {
    if (!is_callable($this->queryBuilder)) {
      if (method_exists($this->queryBuilder, 'getModel')) {
        return $this->queryBuilder->getModel()->getConnectionName();
      }
    }
    return "";
  }

  /**
   * @return string
   */
  public function getQueryBuilderSqlString (): string {
    if (!is_callable($this->queryBuilder)) {
      if (!method_exists($this->queryBuilder, 'toRawSql')) {
        return Str::replaceArray('?', $this->queryBuilder->getBindings(), $this->queryBuilder->toSql());
      }
      // @NOTE: `toRawSql()` was added in Laravel v10.15.x+
      return $this->queryBuilder->toRawSql();
    }
    return "";
  }

  /**
   * @param string $newQueryTaskName
   * @return void
   */
  public function setQueryKey (string $newQueryTaskName): void {
    $this->queryTaskName = $newQueryTaskName;
  }

  /**
   * @return string
   */
  public function getQueryKey (): string {
    return $this->queryTaskName;
  }

  /**
   * @param array $newCallbackArguments
   * @return void
   */
  public function setCallbackArguments (array $newCallbackArguments): void {
    $this->callbackArguments = $newCallbackArguments;
  }

  /**
   * @return string
   */
  public function getQuerySqlString (): string {
    if ($this->queryBuilder instanceof Closure) {
      return $this->getQueryKey();
    }

    $sqlQueryString = $this->getQueryBuilderSqlString();
    $tableName = $this->getQueryBuilderTableName();

    if ($sqlQueryString !== "") {
      return strtolower($sqlQueryString)."|".strtolower($tableName);
    }

    return "|";
  }

  /**
   * @return void
   */
  public function setAsCompletelyExecuted (): void {
    $this->executed = TRUE;
  }

  /**
   * @return boolean
   */
  public function isExecuted (): boolean {
    return $this->executed;
  }

  /**
   * @return mixed
   */
  public function getQueryTaskResult () {
    $trigger = $this->trigger;
    $canCallTrigger = $trigger !== "";

    $queryBuilder = $this->queryBuilder;
    $result = NULL;

    if (is_callable(queryBuilder)) {
      $result = call_user_func_array(
        $queryBuilder,
        $this->callbackArguments
      );
    }

    if ($result !== NULL) {
      return $result;
    }

    if ($canCallTrigger
      && method_exists($queryBuilder, $trigger)
        && method_exists($queryBuilder 'toSql')) {
      if (count($this->methodArguments) === 0) {
        $result = $queryBuilder->{$trigger}();
      } else {
        $result = call_user_func_array(
          array($queryBuilder, $trigger),
          $this->methodArguments
        );
      }
    }

    return $result;
  }
}

?>
