<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

/**
 * Reusable Database Explorer operations for feature repositories.
 */
abstract class Repository
{
  /**
   * @param Explorer $database Shared Nette database service.
   * @param non-empty-string $tableName Explicit database table name.
   */
  public function __construct(
    protected readonly Explorer $database,
    protected readonly string $tableName,
  ) {
  }

  /**
   * Query for the repository's table.
   * @return Selection<ActiveRow> 
   * */
  protected function getTable(): Selection
  {
    return $this->database->table($this->tableName);
  }

  /** 
   * Unfiltered query; module-specific filters belong in subclasses.
   * @return Selection<ActiveRow>
   * */
  public function findAll(): Selection
  {
    return $this->getTable();
  }

  /**
   * @param array<string, mixed> $conditions Conditions for Nette's where().
   * @return Selection<ActiveRow> Query with the requested conditions.
   */
  public function findBy(array $conditions): Selection
  {
    return $this->getTable()->where($conditions);
  }

  /**
   * Find a row by its primary key (including composite primary-key arrays).
   *
   * @param int|string|array<string, int|string> $key Table primary key.
   * @return ActiveRow|null Matching row, if any.
   */
  public function findById(int|string|array $key): ?ActiveRow
  {
    return $this->getTable()->get($key);
  }
}
