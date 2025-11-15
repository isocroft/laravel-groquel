<?php

namespace Groquel\Laravel\QueryHandlers;

use \Exception as Exception;

use Groquel\Laravel\QueryHandlers\Contracts\StorageQueryTask;

/* @INFO: This abstract class implements the Chain-of-Responsibility design pattern */

abstract class StorageQueryTaskHandler {
  /**
   * @var string $message
   */
  private $message;

  /**
   * @var StorageQueryTaskHandler|null $nextHandler
   */
  private $nextHandler;

  /**
   * @param string $skipHandlerErrorMessage
   */
  public function __construct(string $skipHandlerErrorMessage = "") {
    $this->message = $skipHandlerErrorMessage;
    $this->nextHandler = NULL;
  }

  /**
   * @return void
   */
  public function __destruct() {
    $this->unsetNextHandler();
    $this->message = NULL;
  }

  /**
   * @param StorageQueryTaskHandler $handler
   * @return void
   */
  final public function setNextHandler(StorageQueryTaskHandler $handler): void {
    $this->nextHandler = $handler;
  }

  /**
   * @return void
   */
  final public function unsetNextHandler(): void {
    if ($this->nextHandler !== NULL) {
      $this->nextHandler->unsetNextHandler();
      $this->nextHandler = NULL;
    }
  }

  /**
    * @param StorageQueryTask $queryTask
    * @throws Exception
    *
    * @return string|array|object
    */
  protected abstract function beginProcessing(StorageQueryTask $queryTask): string|array|object;

  /**
    * @param StorageQueryTask $queryTask
    * @throws Exception
    *
    * @return void
    */
  protected abstract function finalizeProcessing(StorageQueryTask $queryTask, $result): void;

  /**
    * @param StorageQueryTask $queryTask
    * @param Exception $error
    * @throws Exception
    *
    * @return void
    */
  protected abstract function finalizeProcessingWithError(StorageQueryTask $queryTask, Exception $error): void;

  /**
    * @param StorageQueryTask $queryTask
    * @throws Exception
    *
    * @return string|array|object
    */
  protected abstract function alternateProcessing(StorageQueryTask $queryTask): string|array|object;

  /**
   * @param Exception|null $error
   * @throws Exception
   *
   * @return void
   */
  final public function skipHandlerProcessing(Exception $error = null): void {
    if ($this->nextHandler === NULL) {
      if ($error !== NULL) {
        throw $error;
      } else {
        throw new Exception("Cannot skip; next handler after this handler: '".get_called_class()."' is NULL");
      }
    }
    throw new Exception($this->message);
  }

  /**
   * @param string $message
   * @throws Exception
   */
  public function skipHandlerProcessingWithCustomMessage(string $message): void {
    throw new Exception($message);
  }

  /**
   * @param StorageQueryTask $queryTask
   *
   * @return StorageQueryTask
   */
  protected function migrateQueryTask(StorageQueryTask $queryTask): StorageQueryTask {
    return $queryTask;
  }

  /**
   * @param StorageQueryTask $queryTask
   * @throws Exception
   *
   * @return mixed
   */
  final public function handle(StorageQueryTask $queryTask) {
    $result = null;
    $processingError = null;
    $noResult = true;
    $hasError = false;

    /* @HINT:
    
        Using the template method pattern to ensure each handler 
        > doesn't forget to call the next handler in the chain.
    */
    try {
      /* @NOTE:
      
         that the manager for the storage query task handler
        > sets up each handler to have a reference to the next handler
        > in the chain of handlers.
      */
      try {
        $result = $this->beginProcessing(
          $this->migrateQueryTask($queryTask)
        );
        $noResult = false;
        return $result;
      } catch (Exception $error) {
        /* @HINT:

           If `->skipHandlerProcessing(..)` is called,
           > then call the next handler in the chain.
        */
        if ($error->getMessage() === $this->message
          && $this->nextHandler !== null) {
            $result = $this->nextHandler->handle($queryTask);
            $noResult = false;
            return $result;
        } else {
            $hasError = true;
            $processingError = $error;
            throw $error;
        }
      } finally {
        if (!$hasError) {
          $this->finalizeProcessing(
            $queryTask,
            $result
          );
        } else {
          $this->finalizeProcessingWithError(
            $queryTask,
            $processingError
          );

          if ($noResult
            && $processingError->getMessage() === $this->message) {
            $processingError = null;
            /* @HINT:
               
               If there's no result and we have an error, try to 
               > get a result from an alternate process
            */
            $result = $this->alternateProcessing(
              $queryTask
            );
            $noResult = false;
            return $result;
          }
        }
      }
    } catch (Exception $error) {
      if ($hasError && !$noResult && is_null($processingError)) {
        $hasError = false;
        $this->finalizeProcessing(
          $queryTask,
          $result
        );
      } else {
        /* @TODO [Debug Logs]: log error here in debug/test mode */
        throw $error;
      }
    }

    return $result;
  }
}

?>
