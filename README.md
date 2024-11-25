
# aapje.php

aapje.php is a lightweight, single-file PHP framework for building simple APIs. It provides easy routing, request and response handling, and database operations using PDO.

<p align="center">
  <img src="https://img.shields.io/github/release/ssl/aapje.php?style=flat">
  <img src="https://img.shields.io/github/issues/ssl/aapje.php?style=flat">
  <img src="https://img.shields.io/github/forks/ssl/aapje.php?style=flat">
  <img src="https://img.shields.io/github/stars/ssl/aapje.php?style=flat">
  <img src="https://img.shields.io/github/license/ssl/aapje.php?style=flat">
</p>

## Features

- **Routing**: Define routes with placeholders for dynamic parameters.
- **Request Handling**: Access headers, cookies, files, input data, query parameters, and more.
- **Response Handling**: Set headers, cookies, status codes, and send responses.
- **Database Operations**: Perform basic CRUD operations securely using prepared statements.
- **Lightweight**: Single-file framework that's easy to set up and use.

## Getting Started

### Requirements

- PHP 7.0 or higher

### Installation

Since aapje.php is a single file, you can simply include it in your project:

```php
require 'aapje.php';
````

Make sure all requests to your website (or folder) using aapje.php are passed through your routing file

```
RewriteEngine On
RewriteRule ^(.*)$ example.php [L]
```

## Basic Usage

### Setting Up

```php
<?php
require 'aapje.php';

// Set up database configuration (optional)
aapje::setDbConfig([
    'host' => 'localhost',
    'dbname' => 'your_database',
    'user' => 'your_username',
    'password' => 'your_password',
]);
```

### Defining Routes

Define routes using the `aapje::route()` method:

```php
// Home route
aapje::route('GET', '/', function () {
    aapje::response()->echo('Hello, world!');
});

// Route with a parameter
aapje::route('GET', '/hello/@name', function ($name) {
    aapje::response()->echo("Hello, $name!");
});
```

### Running the Application

Start the application with:

```php
aapje::run();
```

### Full Example

```php
<?php
require 'aapje.php';

// Database Configuration
aapje::setDbConfig([
    'host' => 'localhost',
    'dbname' => 'test_db',
    'user' => 'root',
    'password' => '',
]);

// Routes
aapje::route('GET', '/', function () {
    aapje::response()->echo('Hello world!');
});

aapje::route('GET', '/user/@id', function ($id) {
    // Fetch user from database
    $user = aapje::select('users', '*', ['id' => $id]);
    if ($user) {
        aapje::response()->echo($user);
    } else {
        aapje::response()->statusCode(404)->echo(['error' => 'User not found']);
    }
});

aapje::route('POST', '/user', function () {
    $input = aapje::request()->input();
    $data = [
        'name' => $input['name'] ?? null,
        'email' => $input['email'] ?? null,
    ];

    if (!$data['name'] || !$data['email']) {
        aapje::response()->statusCode(400)->echo(['error' => 'Name and email are required']);
    }

    $id = aapje::insert('users', $data);
    aapje::response()->echo(['created_user_id' => $id]);
});

aapje::run();
```

more detailed examples can be found in the [example.php](example.php) file of this repo.

## Database Examples

### Connecting to the Database

Set your database configuration:

```php
aapje::setDbConfig([
    'host' => 'localhost',
    'dbname' => 'your_database',
    'user' => 'your_username',
    'password' => 'your_password',
]);
```

### Performing CRUD Operations

Aapje provides simple methods for database operations: `insert`, `select`, `update`, and `delete`. All these methods use prepared statements to prevent SQL injection and ensure security.

#### Insert Data

```php
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
];

$id = aapje::insert('users', $data);
aapje::response()->echo(['created_user_id' => $id]);
```

- **Explanation**:
  - `insert($table, $data)` inserts a new record into the specified table.
  - `$table`: The name of the table.
  - `$data`: An associative array where keys are column names and values are the data to insert.

#### Select Data

```php
$users = aapje::select(
    'users',                // Table name
    ['id', 'name', 'email'],// Columns to select (or '*' for all columns)
    ['status' => 'active'], // Conditions (WHERE clause)
    [
        'orderBy' => 'id',  // Column to order by
        'sort'    => 'DESC',// Sort direction ('ASC' or 'DESC')
        'limit'   => 10,    // Limit the number of results
    ]
);
aapje::response()->echo($users);
```

- **Explanation**:
  - `select($table, $columns = '*', $conditions = [], $options = [])` retrieves records from the specified table.
    - `$table`: The name of the table.
    - `$columns`: An array of columns to select, or '\*' to select all columns.
    - `$conditions`: An associative array of conditions for the WHERE clause.
    - `$options`: An associative array of options like 'orderBy', 'sort', and 'limit'.

- **Options**:
  - **Conditions (WHERE clause)**:
    - Provide conditions as an associative array. For example, `['status' => 'active', 'age' => 30]` translates to `WHERE status = 'active' AND age = 30`.
  - **Ordering**:
    - `'orderBy'`: Specify the column to order by.
    - `'sort'`: Specify the sort direction, either `'ASC'` (ascending) or `'DESC'` (descending).
  - **Limit**:
    - `'limit'`: Specify the maximum number of records to retrieve.

#### Update Data

```php
$updateData = ['email' => 'john.new@example.com'];
$conditions = ['id' => $id];

aapje::update('users', $updateData, $conditions);
aapje::response()->echo(['updated_user_id' => $id]);
```

- **Explanation**:
  - `update($table, $data, $conditions = [])` updates records in the specified table.
    - `$table`: The name of the table.
    - `$data`: An associative array of columns and their new values.
    - `$conditions`: An associative array of conditions for the WHERE clause.

#### Delete Data

```php
$conditions = ['id' => $id];

aapje::delete('users', $conditions);
aapje::response()->echo(['deleted_user_id' => $id]);
```

- **Explanation**:
  - `delete($table, $conditions = [])` deletes records from the specified table.
    - `$table`: The name of the table.
    - `$conditions`: An associative array of conditions for the WHERE clause.


### Advanced Database Usage

#### Complex Conditions

You can specify multiple conditions in the `$conditions` array:

```php
$conditions = [
    'status' => 'active',
    'age'    => 30,
];

$users = aapje::select('users', '*', $conditions);
```

This will generate a WHERE clause like:

```sql
WHERE status = 'active' AND age = 30
```

#### Using `LIKE` and Other Operators

Currently, the `select`, `update`, and `delete` methods support only the `=` operator in conditions. For more complex queries involving different operators, you can use the `query` method directly:

```php
$stmt = aapje::query(
    'SELECT * FROM users WHERE name LIKE ? OR age > ?',
    ['%John%', 25]
);
$users = $stmt->fetchAll();
```

#### Ordering and Limiting Results

You can order and limit the results using the `$options` parameter in the `select` method:

```php
$options = [
    'orderBy' => 'created_at',
    'sort'    => 'DESC',
    'limit'   => 5,
];

$recentUsers = aapje::select('users', '*', [], $options);
```

#### Full `select` Method Signature

```php
$results = aapje::select(
    $table,     // string: Table name
    $columns,   // string|array: Columns to select ('*' or array of column names)
    $conditions,// array: Conditions for WHERE clause
    $options    // array: Additional options ('orderBy', 'sort', 'limit')
);
```

#### Examples with Explanations

**Example 1: Select specific columns with conditions**

```php
$users = aapje::select(
    'users',
    ['id', 'name', 'email'],
    ['status' => 'active']
);
```

- Selects the `id`, `name`, and `email` columns from the `users` table where `status` is `'active'`.

**Example 2: Select all columns with ordering and limit**

```php
$users = aapje::select(
    'users',
    '*',
    [],
    [
        'orderBy' => 'id',
        'sort'    => 'DESC',
        'limit'   => 10,
    ]
);
```

- Selects all columns from the `users` table, orders the results by `id` in descending order, and limits the results to 10 records.

**Example 3: Update multiple records**

```php
$data = ['status' => 'inactive'];
$conditions = ['last_login' => '2021-01-01'];

aapje::update('users', $data, $conditions);
```

- Updates the `status` to `'inactive'` for all users whose `last_login` date is `'2021-01-01'`.

**Example 4: Delete records with conditions**

```php
$conditions = [
    'status' => 'inactive',
    'age'    => 35,
];

aapje::delete('users', $conditions);
```

- Deletes users from the `users` table where `status` is `'inactive'` and `age` is `35`.

### Using the `query` Method for Custom Queries

For queries that cannot be constructed using the provided methods, use the `query` method directly:

```php
$stmt = aapje::query(
    'SELECT * FROM users WHERE name LIKE ? AND age BETWEEN ? AND ? ORDER BY created_at DESC LIMIT ?',
    ['%Doe%', 20, 30, 5]
);
$users = $stmt->fetchAll();
```

- **Explanation**:
  - `query($query, $params = [])` executes a raw SQL query with parameter binding.
    - `$query`: The SQL query with placeholders (`?`).
    - `$params`: An array of parameters to bind to the placeholders.

### Security Considerations

- **Input Validation**: Always validate user input before using it in database operations.
- **Prepared Statements**: aapje.php uses prepared statements to prevent SQL injection attacks.
- **Identifier Validation**: aapje.php validates table and column names to prevent injection via identifiers.

### Limitations

- **Operators**: The `select`, `update`, and `delete` methods support only the `=` operator in conditions.
- **Complex Conditions**: For complex WHERE clauses (e.g., using `LIKE`, `IN`, `BETWEEN`), use the `query` method.

## Detailed Explanation of Database Functions

### `aapje::insert($table, $data)`

- **Description**: Inserts a new record into a table.
- **Parameters**:
  - `$table` (string): The table name.
  - `$data` (array): Associative array of column-value pairs.
- **Returns**: The last inserted ID.
- **Example**:

  ```php
  $id = aapje::insert('users', ['name' => 'Jane', 'email' => 'jane@example.com']);
  ```

### `aapje::select($table, $columns = '*', $conditions = [], $options = [])`

- **Description**: Retrieves records from a table.
- **Parameters**:
  - `$table` (string): The table name.
  - `$columns` (string|array): Columns to select ('\*' or an array of column names).
  - `$conditions` (array): Associative array of conditions for the WHERE clause.
  - `$options` (array): Additional options ('limit', 'orderBy', 'sort').
- **Returns**: An array of records.
- **Options Explained**:
  - **'limit'** (int): Maximum number of records to retrieve.
  - **'orderBy'** (string): Column name to order the results by.
  - **'sort'** (string): Sort direction, either 'ASC' or 'DESC'.
- **Example**:

  ```php
  $users = aapje::select('users', '*', ['status' => 'active'], ['limit' => 10, 'orderBy' => 'id', 'sort' => 'DESC']);
  ```

### `aapje::update($table, $data, $conditions = [])`

- **Description**: Updates records in a table.
- **Parameters**:
  - `$table` (string): The table name.
  - `$data` (array): Associative array of column-value pairs to update.
  - `$conditions` (array): Associative array of conditions for the WHERE clause.
- **Example**:

  ```php
  aapje::update('users', ['email' => 'new@example.com'], ['id' => $id]);
  ```

### `aapje::delete($table, $conditions = [])`

- **Description**: Deletes records from a table.
- **Parameters**:
  - `$table` (string): The table name.
  - `$conditions` (array): Associative array of conditions for the WHERE clause.
- **Example**:

  ```php
  aapje::delete('users', ['id' => $id]);
  ```

### `aapje::query($query, $params = [])`

- **Description**: Executes a raw SQL query with optional parameters.
- **Parameters**:
  - `$query` (string): The SQL query with placeholders (`?`).
  - `$params` (array): Parameters to bind in the query.
- **Returns**: PDOStatement object.
- **Example**:

  ```php
  $stmt = aapje::query('SELECT * FROM users WHERE email LIKE ?', ['%example.com']);
  $users = $stmt->fetchAll();
  ```


## Detailed Explanation of Functions

### Routing

#### `aapje::route($method, $pattern, $callback)`

- **Description**: Defines a route that responds to a specific HTTP method and URL pattern.
- **Parameters**:
  - `$method` (string): HTTP method (e.g., 'GET', 'POST').
  - `$pattern` (string): URL pattern. Use `@param` for dynamic segments.
  - `$callback` (callable): Function to execute when the route matches.
- **Example**:

  ```php
  aapje::route('GET', '/user/@id', function ($id) {
      // Your code here
  });
  ```

### Request Handling

Access the request object using `aapje::request()`.

#### `request()->header($key)`

- **Description**: Retrieves the value of a specific HTTP request header.
- **Parameters**:
  - `$key` (string): The header name.
- **Returns**: The header value or `null` if not found.
- **Example**:

  ```php
  $authToken = aapje::request()->header('Authorization');
  ```

#### `request()->headers()`

- **Description**: Retrieves all HTTP request headers.
- **Returns**: An associative array of headers.
- **Example**:

  ```php
  $headers = aapje::request()->headers();
  ```

#### `request()->cookie($key)`

- **Description**: Retrieves a specific cookie value.
- **Parameters**:
  - `$key` (string): The cookie name.
- **Returns**: The cookie value or `null` if not found.
- **Example**:

  ```php
  $session = aapje::request()->cookie('PHPSESSID');
  ```

#### `request()->cookies()`

- **Description**: Retrieves all cookies.
- **Returns**: An associative array of cookies.
- **Example**:

  ```php
  $cookies = aapje::request()->cookies();
  ```

#### `request()->file($key)`

- **Description**: Retrieves information about an uploaded file.
- **Parameters**:
  - `$key` (string): The name of the file input field.
- **Returns**: An array with file information or `null` if not found.
- **Example**:

  ```php
  $file = aapje::request()->file('avatar');
  ```

#### `request()->files()`

- **Description**: Retrieves all uploaded files.
- **Returns**: An array of files.
- **Example**:

  ```php
  $files = aapje::request()->files();
  ```

#### `request()->input()`

- **Description**: Retrieves the input data from the request body. Automatically decodes JSON input.
- **Returns**: The decoded input data.
- **Example**:

  ```php
  $data = aapje::request()->input();
  ```

#### `request()->getParam($key)`

- **Description**: Retrieves a specific GET parameter.
- **Parameters**:
  - `$key` (string): The parameter name.
- **Returns**: The parameter value or `null` if not found.
- **Example**:

  ```php
  $page = aapje::request()->getParam('page');
  ```

#### `request()->getParams()`

- **Description**: Retrieves all GET parameters.
- **Returns**: An associative array of GET parameters.
- **Example**:

  ```php
  $params = aapje::request()->getParams();
  ```

#### `request()->postParam($key)`

- **Description**: Retrieves a specific POST parameter.
- **Parameters**:
  - `$key` (string): The parameter name.
- **Returns**: The parameter value or `null` if not found.
- **Example**:

  ```php
  $username = aapje::request()->postParam('username');
  ```

#### `request()->postParams()`

- **Description**: Retrieves all POST parameters.
- **Returns**: An associative array of POST parameters.
- **Example**:

  ```php
  $params = aapje::request()->postParams();
  ```

#### `request()->ip()`

- **Description**: Retrieves the client's IP address.
- **Returns**: The IP address as a string.
- **Example**:

  ```php
  $ipAddress = aapje::request()->ip();
  ```

#### `request()->userAgent()`

- **Description**: Retrieves the client's User-Agent string.
- **Returns**: The User-Agent string or `null` if not available.
- **Example**:

  ```php
  $userAgent = aapje::request()->userAgent();
  ```

### Response Handling

Access the response object using `aapje::response()`.

#### `response()->header($key, $value)`

- **Description**: Sets an HTTP response header.
- **Parameters**:
  - `$key` (string): The header name.
  - `$value` (string): The header value.
- **Returns**: The response object (for chaining).
- **Example**:

  ```php
  aapje::response()->header('Content-Type', 'application/json');
  ```

#### `response()->headers($headers)`

- **Description**: Sets multiple HTTP response headers.
- **Parameters**:
  - `$headers` (array): Associative array of headers.
- **Returns**: The response object (for chaining).
- **Example**:

  ```php
  aapje::response()->headers([
      'Content-Type' => 'application/json',
      'Cache-Control' => 'no-cache',
  ]);
  ```

#### `response()->cookie($name, $value, $options = [])`

- **Description**: Sets a cookie.
- **Parameters**:
  - `$name` (string): The cookie name.
  - `$value` (string): The cookie value.
  - `$options` (array): Additional cookie options (e.g., 'expires', 'path').
- **Returns**: The response object (for chaining).
- **Example**:

  ```php
  aapje::response()->cookie('session', 'abc123', ['expires' => time() + 3600]);
  ```

#### `response()->cookies($cookies)`

- **Description**: Sets multiple cookies.
- **Parameters**:
  - `$cookies` (array): Associative array of cookies.
- **Returns**: The response object (for chaining).
- **Example**:

  ```php
  aapje::response()->cookies([
      'user' => ['value' => 'john_doe', 'options' => ['path' => '/']],
      'token' => ['value' => 'xyz789', 'options' => ['expires' => time() + 3600]],
  ]);
  ```

#### `response()->statusCode($code)`

- **Description**: Sets the HTTP status code for the response.
- **Parameters**:
  - `$code` (int): The status code (e.g., 200, 404).
- **Returns**: The response object (for chaining).
- **Example**:

  ```php
  aapje::response()->statusCode(404);
  ```

#### `response()->echo($content)`

- **Description**: Sends the response to the client.
- **Parameters**:
  - `$content` (mixed): The content to send. If it's an array, it will be JSON-encoded.
- **Example**:

  ```php
  aapje::response()->echo(['message' => 'Success']);
  ```

### Database Functions

#### `aapje::setDbConfig($config)`

- **Description**: Sets the database configuration.
- **Parameters**:
  - `$config` (array): Database configuration parameters ('host', 'dbname', 'user', 'password').
- **Example**:

  ```php
  aapje::setDbConfig([
      'host' => 'localhost',
      'dbname' => 'test_db',
      'user' => 'root',
      'password' => '',
  ]);
  ```

#### `aapje::query($query, $params = [])`

- **Description**: Executes a raw SQL query with optional parameters.
- **Parameters**:
  - `$query` (string): The SQL query.
  - `$params` (array): Parameters to bind in the query.
- **Returns**: PDOStatement object.
- **Example**:

  ```php
  $stmt = aapje::query('SELECT * FROM users WHERE id = ?', [$id]);
  $user = $stmt->fetch();
  ```

#### `aapje::insert($table, $data)`

- **Description**: Inserts a new record into a table.
- **Parameters**:
  - `$table` (string): The table name.
  - `$data` (array): Associative array of column-value pairs.
- **Returns**: The last inserted ID.
- **Example**:

  ```php
  $id = aapje::insert('users', ['name' => 'Jane', 'email' => 'jane@example.com']);
  ```

#### `aapje::update($table, $data, $conditions = [])`

- **Description**: Updates records in a table.
- **Parameters**:
  - `$table` (string): The table name.
  - `$data` (array): Associative array of column-value pairs to update.
  - `$conditions` (array): Associative array of conditions for the WHERE clause.
- **Example**:

  ```php
  aapje::update('users', ['email' => 'new@example.com'], ['id' => $id]);
  ```

#### `aapje::delete($table, $conditions = [])`

- **Description**: Deletes records from a table.
- **Parameters**:
  - `$table` (string): The table name.
  - `$conditions` (array): Associative array of conditions for the WHERE clause.
- **Example**:

  ```php
  aapje::delete('users', ['id' => $id]);
  ```

#### `aapje::select($table, $columns = '*', $conditions = [], $options = [])`

- **Description**: Retrieves records from a table.
- **Parameters**:
  - `$table` (string): The table name.
  - `$columns` (string|array): Columns to select ('\*' or an array of column names).
  - `$conditions` (array): Associative array of conditions for the WHERE clause.
  - `$options` (array): Additional options ('limit', 'orderBy', 'sort').
- **Returns**: An array of records.
- **Example**:

  ```php
  $users = aapje::select('users', ['id', 'name'], ['status' => 'active'], ['limit' => 10]);
  ```

## Error Handling

Aapje handles exceptions thrown within routes and returns an HTTP 418 status code with an error message.

```php
try {
    // aapje.php routing
} catch (Exception $e) {
    aapje::response()->statusCode(418)->echo(['error' => $e->getMessage()]);
}
```
