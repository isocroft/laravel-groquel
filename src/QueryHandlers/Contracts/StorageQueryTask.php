<?php

namespace Groquel\Laravel\QueryHandlers\Contracts;

interface StorageQueryTask {
  public function getQueryTaskResult (): array|string|stdClass
}

?>
