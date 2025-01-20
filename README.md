# Laravel-Groquel
A system of handlers used to make data access from I/O sources more efficient for Laravel PHP apps

## Usage

```php
<?php

use App\Models\User;
use Groquel\Laravel\QueryRepository\SQLDatabaseTableRepository;
//use lluminate\Support\Facades\DB;

use Illuminate\Database\Query\Builder as QueryBuilder;

final class UserTableRepository extends   {
  /**
    * 
    */
  public function getTableName(): string {
    return User::getTableName();
  }

  /**
    * 
    */
  protected function getQueryBuilder(): QueryBuilder {
    //return DB::table($this->users);
    return User::query();
  }

  public final getAllActiveUsers () {
    $context = $this;
    $queryOneBuilder = User::where(function (Builder $query) {
      $query->whereNot('status', '=', 'suspended')
    });

    $this->executeGetOnQuery(
      $queryOneBuilder->sharedLock()
    )->setQueryTaskName("step1");

    $this->executeGetOnQuery(
      function (array $arguments) use (&$context) {
        $innerQueryBuilder = $this->getQueryBuilder()
        return $innerQueryBuilder;
      }
    )->setQueryTaskName("step2");

    $results = $this->executeAllAndReturnResults();
    return $results["step2"];
  }
}

?>
```
