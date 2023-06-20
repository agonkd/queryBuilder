# QueryBuilder

QueryBuilder is a powerful PHP library that provides an elegant and intuitive way to build and execute SQL queries. It offers a fluent interface for constructing queries, making database interactions a breeze.

## Features

- Easy and intuitive query building
- Supports SELECT, INSERT, UPDATE, and DELETE statements
- Flexible WHERE clause with various operators
- Join tables effortlessly with INNER JOIN and LEFT JOIN
- Sorting results with ORDER BY
- Limit and offset for pagination
- Grouping and aggregation with GROUP BY and HAVING
- Raw query execution for advanced use cases
- Secure parameter binding to prevent SQL injection attacks

## Usage

Here's an example of how you can use QueryBuilder to retrieve data from a database:

```php
use Your\Namespace\Database;
use Your\Namespace\QueryBuilder;

// Create a new database connection
$database = new Database('localhost', 'mydatabase', 'username', 'password');

// Create a QueryBuilder object
$queryBuilder = new QueryBuilder($database->getPdo(), 'users');

// Build a SELECT query
$queryBuilder->select(['id', 'name', 'email'])
    ->where('status', 'active')
    ->orderBy('name', 'ASC')
    ->limit(10);

// Execute the query
$results = $queryBuilder->execute();

// Process the results
foreach ($results as $row) {
    echo $row['name'] . ' - ' . $row['email'] . '<br>';
}
```

## Contributing

Contributions are welcome! If you find any issues or have suggestions for improvements, please open an issue or submit a pull request.

## License

This project is licensed under the [MIT License](https://opensource.org/licenses/MIT). You are free to use, modify, and distribute this library while acknowledging the original creator.

## Author

Agon Kadriu

- LinkedIn: [Agon Kadriu](https://www.linkedin.com/in/agon-kadriu-425531235/)
- Instagram: [@agonkd](https://www.instagram.com/agonkd/)
