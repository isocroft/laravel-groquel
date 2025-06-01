<?php

namespace Groquel\Laravel\QueryHandlerSupport;

use \Exception as Exception;

use Groquel\Laravel\QueryHandlers\StorageQueryTaskHandler;
use Groquel\Laravel\QueryHandlers\Contracts\StorageQueryTask;

/* @HINT: This is a storage query task manger for all data access an storage needs of a data repository. */

final class StorageQueryTaskHandlersManager {

  /**
   * @var StorageQueryTaskHandler $rootTaskHandler
   */
  private $rootTaskHandler;

  /**
    * @param StorageQueryTaskHandler[] $storageQueryHandlers
    * @throws Exception
    */
  public function __construct(array $storageQueryHandlers) {
    $totalCount = count($storageQueryHandlers);

    if ($totalCount === 0) {
      throw new Exception("Cannot proceed with manager action; No storage query task handlers found");
    }

    for ($count = 0; $count + 1 < $totalCount; $count++) {
      $previousQueryTaskHandler = $storageQueryHandlers[$count];
      $nextQueryTaskHandler = $storageQueryHandlers[$count + 1];

      if ($previousQueryTaskHandler instanceof StorageQueryTaskHandler) {
        if ($nextQueryTaskHandler instanceof StorageQueryTaskHandler) {
          $previousQueryTaskHandler->setNextHandler($nextQueryTaskHandler);
        } else {
          throw new Exception("Cannot proceed with manager action; list of storage query task handlers doesn't contain a valid instance");
        }
      } else {
        throw new Exception("Cannot proceed with manager action; list of storage query task handlers doesn't contain a valid instance");
      }
    }

    $this->rootTaskHandler = $storageQueryHandlers[0];
  }

  /**
   * @result void
   */
  public function __destruct() {
    $this->rootTaskHandler->unsetNextHandler();
    $this->rootTaskHandler = NULL;
  }

  /**
    * @param StorageQueryTask $queryTask
    * @throws Exception
    *
    * @return mixed
    */
  public function execute(StorageQueryTask $queryTask) {
    return $this->rootTaskHandler->handle($queryTask);
  }

  /**
    * @param StorageQueryTaskHandler $newRootTaskHandler
    * @throws Exception
    *
    * @return void
    */
  public function swapRootHandler(StorageQueryTaskHandler $newRootTaskHandler) {
    $formerRootTaskHandler = &$this->rootTaskHandler;

    $this->rootTaskHandler = &$newRootTaskHandler;
    $this->rootTaskHandler->setNextHandler($formerRootTaskHandler);
  }
}

?>
