<?php

class Database
{
  private PDO $pdo;

  /**
   * @throws Exception
   */
  public function __construct(string $host, string $database, string $username, string $password, string $charset = 'utf8mb4')
  {
    try {
      $this->pdo->beginTransaction();
      $dsn = "mysql:host=$host;dbname=$database;charset=$charset";
      $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ];
      $this->pdo = new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
      $this->pdo->rollBack();
      throw new Exception("Database connection failed: " . $e->getMessage());
    }
  }

  public function getPdo(): PDO
  {
    return $this->pdo;
  }
}

class QueryBuilder
{
  private PDO $pdo;
  private string $table;
  private string $query;
  private array $params;

  public function __construct(PDO $pdo, string $table)
  {
    $this->pdo = $pdo;
    $this->table = $table;
    $this->reset();
  }

  public function select(array $columns = ['*']): QueryBuilder
  {
    $this->query = 'SELECT ' . implode(', ', $columns) . ' FROM ' . $this->table;
    return $this;
  }

  public function from(string $table): QueryBuilder
  {
    $this->table = $table;
    return $this;
  }

  public function where(string $column, $value, string $operator = '='): QueryBuilder
  {
    $this->query .= $this->getQuerySeparator() . $column . ' ' . $operator . ' :value';
    $this->params['value'] = $value;
    return $this;
  }

  public function whereIn(string $column, array $values): QueryBuilder
  {
    $placeholders = rtrim(str_repeat(':value, ', count($values)), ', ');
    $this->query .= $this->getQuerySeparator() . $column . ' IN (' . $placeholders . ')';
    $this->params = array_merge($this->params, array_combine(array_map(function ($index) {
      return 'value' . $index;
    }, array_keys($values)), $values));
    return $this;
  }

  public function orderBy(string $column, string $direction = 'ASC'): QueryBuilder
  {
    $this->query .= ' ORDER BY ' . $column . ' ' . $direction;
    return $this;
  }

  public function limit(int $limit): QueryBuilder
  {
    $this->query .= ' LIMIT ' . $limit;
    return $this;
  }

  public function offset(int $offset): QueryBuilder
  {
    $this->query .= ' OFFSET ' . $offset;
    return $this;
  }

  /**
   * @throws Exception
   */
  public function execute(): array
  {
    try {
      $statement = $this->pdo->prepare($this->query);
      $statement->execute($this->params);
      $this->reset();
      return $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      throw new Exception("Query execution failed: " . $e->getMessage());
    }
  }

  private function getQuerySeparator(): string
  {
    return empty($this->query) ? ' WHERE ' : ' AND ';
  }

  public function join(string $table, string $foreignColumn, string $operator = '=', string $localColumn = null): QueryBuilder
  {
    if ($localColumn === null) {
      $localColumn = $this->table . '_id';
    }
    $this->query .= ' INNER JOIN ' . $table . ' ON ' . $this->table . '.' . $localColumn . ' ' . $operator . ' ' . $table . '.' . $foreignColumn;
    return $this;
  }

  public function leftJoin(string $table, string $foreignColumn, string $operator = '=', string $localColumn = null): QueryBuilder
  {
    if ($localColumn === null) {
      $localColumn = $this->table . '_id';
    }
    $this->query .= ' LEFT JOIN ' . $table . ' ON ' . $this->table . '.' . $localColumn . ' ' . $operator . ' ' . $table . '.' . $foreignColumn;
    return $this;
  }

  public function groupBy(string ...$columns): QueryBuilder
  {
    $this->query .= ' GROUP BY ' . implode(', ', $columns);
    return $this;
  }

  public function having(string $column, $value, string $operator = '='): QueryBuilder
  {
    $this->query .= ' HAVING ' . $column . ' ' . $operator . ' ?';
    $this->params[] = $value;
    return $this;
  }

  public function insert(array $data): bool
  {
    $columns = implode(', ', array_keys($data));
    $placeholders = rtrim(str_repeat('?, ', count($data)), ', ');
    $values = array_values($data);

    $query = "INSERT INTO $this->table ($columns) VALUES ($placeholders)";
    $statement = $this->pdo->prepare($query);
    $result = $statement->execute($values);
    $this->reset();
    return $result;
  }

  public function update(array $data): bool
  {
    $setStatements = [];
    $values = [];

    foreach ($data as $column => $value) {
      $setStatements[] = $column . ' = ?';
      $values[] = $value;
    }

    $setClause = implode(', ', $setStatements);
    $query = "UPDATE {$this->table} SET {$setClause}" . $this->getWhereClause();
    $statement = $this->pdo->prepare($query);
    $result = $statement->execute(array_merge($values, $this->params));
    $this->reset();
    return $result;
  }

  public function delete(): bool
  {
    $query = "DELETE FROM {$this->table}" . $this->getWhereClause();
    $statement = $this->pdo->prepare($query);
    $result = $statement->execute($this->params);
    $this->reset();
    return $result;
  }

  private function getWhereClause(): string
  {
    return empty($this->query) ? '' : ' WHERE ' . ltrim($this->query, ' AND');
  }

  public function count(string $column = '*'): int
  {
    $query = 'SELECT COUNT(' . $column . ') AS count FROM ' . $this->table . $this->getWhereClause();
    $statement = $this->pdo->prepare($query);
    $statement->execute($this->params);
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    $this->reset();
    return intval($result['count']);
  }

  public function exists(): bool
  {
    $query = 'SELECT EXISTS(' . $this->query . ') AS result FROM ' . $this->table . $this->getWhereClause();
    $statement = $this->pdo->prepare($query);
    $statement->execute($this->params);
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    $this->reset();
    return $result['result'] === '1';
  }

  public function executeRaw(string $query, array $params = []): array
  {
    $statement = $this->pdo->prepare($query);
    $statement->execute($params);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  public function getRawQuery(): string
  {
    return $this->query . ' FROM ' . $this->table . $this->getWhereClause();
  }

  public function reset(): QueryBuilder
  {
    $this->query = '';
    $this->params = [];
    return $this;
  }
}
