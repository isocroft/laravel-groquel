<?php

namespace Groquel\Laravel\QueryHandlerTasks;

use Closure;
use Groquel\Laravel\QueryHandlers\StorageQueryTaskHandler;
use Groquel\Laravel\QueryHandlers\Contracts\StorageQueryTask;

use Illuminate\Database\Query\Builder as QueryBuilder;

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

  public function __construct (QueryBuilder|Clousre $queryBuilder, string $nameOfMethodToCall = "", array $methodArguments = []) {
    $this->queryBuilder = $queryBuilder;
    $this->trigger = $nameOfMethodToCall;
    $this->methodArguments = $methodArguments;
    $this->callbackArguments = [];
    $this->queryTaskName = "";
  }

  public function setQueryTaskName (string $newQueryTaskName): void {
    $this->queryTaskName = $newQueryTaskName;
  }

  public function getQueryTaskName (): string {
    return $this->queryTaskName;
  }

  public function setCallbackArguments (array $newCallbackArguments): void {
    $this->callbackArguments = $newCallbackArguments;
  }

  public function getQuerySql (): string {
    if ($this->queryBuilder instanceof Closure) {
      return "";
    }

    if (method_exists($this->queryBuilder, 'toSql')) {
      return $this->queryBuilder->toSql();
    }
    return "";
  }

  public function getTaskResult () {
    $trigger = $this->trigger;
    $canCallTrigger = $trigger !== "";

    $queryBuilder = $this->queryBuilder;

    if (is_callable($this->queryBuilder)) {
      $queryBuilder = ($this->queryBuilder)($this->callbackArguments);
    }

    if (is_array($queryBuilder)) {
      return $queryBuilder;
    }

    if ($canCallTrigger
      && method_exists($queryBuilder, $trigger)
        && method_exists($queryBuilder 'toSql')) {
      if (count($this->methodArguments) === 0) {
        return $queryBuilder->{$trigger}();
      } else {
        return call_user_func_array(
          array($queryBuilder, $trigger),
          $this->methodArguments
        );
      }
    }
    return $queryBuilder;
  }
}

?>
