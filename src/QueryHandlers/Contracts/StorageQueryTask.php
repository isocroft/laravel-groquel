<?php

namespace Groquel\Laravel\QueryHandlers\Contracts;

interface StorageQueryTask {
  public function getQueryTaskName (): string;
  public function isExecuted (): boolean;
  public function setAsCompletelyExecuted (): void;
  public function getQueryTaskResult (): array|object;
}

?>
