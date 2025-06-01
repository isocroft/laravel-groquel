<?php

namespace Groquel\Laravel\QueryHandlerSupport;

use Groquel\Laravel\QueryHandlers\StorageQueryTaskHandler;
use use Groquel\Laravel\QueryHandlers\Contracts\StorageQueryTask;

/* @HINT: This is a storage query task manger for all data access an storage needs of a data repository. */

final class StorageQueryTaskHandlersManager {

  /**
   * @var StorageQueryTaskHandler $rootTaskHandler
   */
  private $rootTaskHandler;

  /**
   * @var int $handlersCount
   */
  private $handlersCount;

  /**
    * @param StorageQueryTaskHandler[] $storageQueryHandlers
    * @throws Exception
    */
  public function __construct(array $storageQueryHandlers) {
    $totalCount = count($storageQueryHandlers);

    if ($totalCount === 0) {
      throw new Exception("Cannot proceed: No storage query task handlers found");
    }

    $this->handlersCount = $totalCount;

    for ($count = 0; $count + 1 < $totalCount; $count++) {
      $previousQueryTaskHandler = $storageQueryHandlers[$count];
      $nextQueryTaskHandler = $storageQueryHandlers[$count + 1];

      if ($previousQueryTaskHandler instanceof StorageQueryTaskHandler) {
        if ($nextQueryTaskHandler instanceof StorageQueryTaskHandler) {
          $previousQueryTaskHandler->setNextHandler($nextQueryTaskHandler);
        }
      }
    }

    $this->rootTaskHandler = $storageQueryHandlers[0];
  }

  public function __destruct() {
    $this->rootTaskHandler = NULL;
  }

  /**
    * @param StorageQueryTask $queryTask
    * @return mixed
    * @throws Exception
    */
  public function execute(StorageQueryTask $queryTask) {
    return $this->rootTaskHandler->handle($queryTask);
  }

  /**
    * @param StorageQueryTaskHandler $newRootTaskHandler
    * @throws Exception
    */
  public function swapRootHandler(StorageQueryTaskHandler $newRootTaskHandler) {
    $formerRootTaskHandler = &$this->rootTaskHandler;

    $this->rootTaskHandler = &$newRootTaskHandler;
    $this->rootTaskHandler->setNextHandler($formerRootTaskHandler);
  }
}

?>
