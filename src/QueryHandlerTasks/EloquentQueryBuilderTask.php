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
    $this->queryTaskName = "";
    $this->executed = FALSE;
  }

  /**
   * @return string
   */
  public function getQueryBuilderSqlString (): string {
    return Str::replaceArray('?', $this->queryBuilder->getBindings(), $this->queryBuilder->toSql());
  }

  /**
   * @param string $newQueryTaskName
   * @return void
   */
  public function setQueryTaskName (string $newQueryTaskName): void {
    $this->queryTaskName = $newQueryTaskName;
  }

  /**
   * @return string
   */
  public function getQueryTaskName (): string {
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
      return $this->getQueryTaskName();
    }

    if (method_exists($this->queryBuilder, 'toSql')) {
      return strtolower($this->getQueryBuilderSqlString())."|".strtolower($this->queryBuilder->getModel()->getTable());
    }

    return "|";
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
      $this->executed = TRUE;
      return $result;
    }

    if ($canCallTrigger
      && method_exists($queryBuilder, $trigger)
        && method_exists($queryBuilder 'toSql')) {
      if (count($this->methodArguments) === 0) {
        $this->executed = TRUE;
        $result = $queryBuilder->{$trigger}();
      } else {
        $this->executed = TRUE;
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
