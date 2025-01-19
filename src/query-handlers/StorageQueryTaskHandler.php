<?php

interface StorageQueryTask {
  public function getQueryTaskResult (): array | string | int
}

/* @INFO: This abstract class implements the Chain-of-Responsibility design pattern */

abstract class StorageQueryTaskHandler {
  /**
    * @var string $message
    * @var StorageQueryTaskHandler|null $nextHandler
    */
  private $message;
  private $nextHandler;

  /**
    * @param string $skipHandlerErrorMessage
    */
  public function __construct(string $skipHandlerErrorMessage = "") {
    $this->message = $skipHandlerErrorMessage;
    $this->nextHandler = NULL;
  }

  public function __destruct() {
    $this->nextHandler = NULL;
    $this->message = "";
  }

  /**
    * @param StorageQueryTaskHandler $handler
    * @return void
    */
  public final function setNextHandler(StorageQueryTaskHandler $handler): void {
    $this->nextHandler = $handler;
  }

  /**
    * @param StorageQueryTask $queryTask
    * @return mixed
    * @throws Exception
    */
  protected abstract function beginProcessing(StorageQueryTask $queryTask);

  /**
    * @param StorageQueryTask $queryTask
    * @return void
    * @throws Exception
    */
  protected abstract function finalizeProcessing(StorageQueryTask $queryTask, $result): void;

  /**
    * @param StorageQueryTask $queryTask
    * @param Exception $error
    * @return void
    * @throws Exception
    */
  protected abstract function finalizeProcessingWithError(StorageQueryTask $queryTask, Exception $error): void;

  /**
    * @param StorageQueryTask $queryTask
    * @return mixed
    * @throws Exception
    */
  protected abstract function alternateProcessing(StorageQueryTask $queryTask);

  /**
    * @param Exception $error
    * @throws Exception
    */
  public final function skipHandlerProcessing(Exception $error): void {
    if ($this->nextHandler === null) {
      throw $error;
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
    * @return StorageQueryTask
    * @throws Exception
    */
  protected function migrateQueryTask(StorageQueryTask $queryTask): StorageQueryTask {
    return $queryTask;
  }

  /**
    * @param StorageQueryTask $queryTask
    * @return mixed
    * @throws Exception
    */
  public final function handle(StorageQueryTask $queryTask) {
    $result = null;
    $processingError = null;
    $noResult = true;
    $hasError = false;

    try {
      try {
        $result = $this->beginProcessing(
          $this->migrateQueryTask($queryTask)
        );
        $noResult = false;
        return $result;
      } catch (Exception $error) {
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
            $this->migrateQueryTask($queryTask),
            $result
          );
        } else {
          $this->finalizeProcessingWithError(
            $queryTask,
            $processingError
          );
          if ($noResult
            && $processingError->getMessage() === $this->message) {
            /* @HINT: If there's no result and we have an error, try to get a result from an alternate process */
            $result = $this->alternateProcessing(
              $this->migrateQueryTask($queryTask)
            );
            $noResult = false;
            return $result;
          }
        }
      }
    } catch (Exception $error) {
      throw $error;
    }

    return $result;
  }
}

?>
