# aapje.php

**aapje.php** is a lightweight, single-file PHP framework designed for building simple and efficient APIs. It offers essential features such as routing, database interactions, middleware support, CORS handling, and utility helper functions, all while maintaining a minimalistic and easy-to-use structure.

<p align="center">
  <img src="https://img.shields.io/github/release/ssl/aapje.php?style=flat">
  <img src="https://img.shields.io/github/issues/ssl/aapje.php?style=flat">
  <img src="https://img.shields.io/github/forks/ssl/aapje.php?style=flat">
  <img src="https://img.shields.io/github/stars/ssl/aapje.php?style=flat">
  <img src="https://img.shields.io/github/license/ssl/aapje.php?style=flat">
  <br><br>
  <b>⚠️ aapje.php is currently in beta. bugs or other issues can be expected. do not use in production. ⚠️</b>
</p>

## 🚀 Features

- **Routing:** Define routes with dynamic parameters supporting various HTTP methods.
- **Database Integration:** Simplified PDO-based database interactions with CRUD operations.
- **Middleware Support:** Execute custom logic before or after route handling.
- **CORS Support:** Built-in Cross-Origin Resource Sharing configuration for secure API access.
- **Helpers Class:** Utility functions to streamline common tasks.
- **Single-File Architecture:** Easy setup and deployment without external dependencies.

## 📦 Installation

1. **Download `aapje.php`**

2. **Include in Your Project:**

   Place `aapje.php` in your project directory and include it in your PHP scripts.

   ```php
   <?php
   require 'aapje.php';
   ```

3. **Web Server Configuration:**

   Ensure that all requests to your website (or specific folder) using `aapje.php` are passed through your routing file.

   ```apache
   # Example Apache .htaccess file
   RewriteEngine On
   RewriteRule ^(.*)$ index.php [L]
   ```

Alternatively, you can fork or clone this repository, sync it to a web host, and directly start coding your API inside the `index.php` file.

## 🛠️ Configuration

Configuration is optional and **aapje.php** can operate without a database. CORS is disabled by default. When enabling CORS by setting `'enabled'` to `true`, the `origins`, `headers`, and `methods` will default to `'*'` unless explicitly specified.

Configure **aapje.php** using the `setConfig` method. You can set database credentials, CORS settings, and default headers.

```php
aapje::setConfig([
    'database' => [
        'host' => 'localhost',
        'dbname' => 'your_database',
        'user' => 'your_username',
        'password' => 'your_password',
    ],
    'cors' => [
        'enabled' => true,
        'origins' => ['https://example.com', 'https://example.org'], // Allowed origins
        'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], // Allowed methods
        'headers' => ['Content-Type', 'Authorization'], // Allowed headers
        'credentials' => true, // Allow credentials
    ],
    'default_headers' => [
        'X-Powered-By' => 'aapje.php',
    ],
]);
```

## 📚 Usage

### 1. **Defining Routes**

Define routes using the `route` method, specifying the HTTP method, URL pattern, and callback function.

```php
// Responds to all HTTP methods
aapje::route('*', '/', function () {
    aapje::response()->echo(['message' => 'Welcome to aapje.php!']);
});

// GET route with dynamic parameter
aapje::route('GET', '/user/@id', function ($id) {
    $user = aapje::select('users', '*', ['id' => $id]);
    if ($user) {
        aapje::response()->echo($user);
    } else {
        aapje::response()->statusCode(404)->echo(['error' => 'User not found']);
    }
});
```

### 2. **Using Middleware**

Register middleware functions to execute custom logic before route handling, such as logging or authentication.

```php
// Define a middleware function
function loggingMiddleware($request, $response) {
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];
    $ip = $request->ip();
    error_log("[$method] $uri from $ip");
}

// Register the middleware
aapje::middleware('loggingMiddleware');
```

### 3. **Utilizing Helpers**

Use the `Helpers` class for common utility functions like escaping HTML or handling files.

```php
aapje::route('GET', '/escape', function () {
    $unsafeString = '<script>alert("XSS")</script>';
    $safeString = Helpers::esc($unsafeString);
    aapje::response()->echo(['escaped' => $safeString]);
});
```

### 4. **Handling CORS**

CORS settings are managed via the `setConfig` method. When enabled, **aapje.php** automatically sets the necessary headers and handles preflight `OPTIONS` requests.

```php
aapje::setConfig([
    'cors' => [
        'enabled' => true,
        'origins' => ['https://example.com'], // Allowed origins
        'methods' => ['GET', 'POST'], // Allowed HTTP methods
        'headers' => ['Content-Type', 'Authorization'], // Allowed headers
        'credentials' => true, // Allow credentials
    ],
]);

// Example route to test CORS
aapje::route('*', '/cors-test', function () {
    aapje::response()->echo(['message' => 'CORS is configured properly!']);
});
```

### 5. **Database Operations**

Perform CRUD operations using built-in methods. Below is an example that demonstrates creating, reading, updating, and deleting a user, along with setting custom headers and handling advanced database queries like sorting and limiting results.

```php
// Create a new user
aapje::route('POST', '/user', function () {
    $input = aapje::request()->input();
    $data = [
        'name' => $input['name'] ?? null,
        'email' => $input['email'] ?? null,
    ];

    if (!$data['name'] || !$data['email']) {
        aapje::response()->statusCode(400)->echo(['error' => 'Name and email are required']);
    }

    try {
        $id = aapje::insert('users', $data);
        aapje::response()->statusCode(201)->echo(['created_user_id' => $id]);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to create user']);
    }
});

// Retrieve all users with sorting and limit
aapje::route('GET', '/users', function () {
    $users = aapje::selectAll('users', '*', [], [
        'orderBy' => 'name',
        'sort' => 'ASC',
        'limit' => 10
    ]);
    aapje::response()->header('X-Custom-Header', 'CustomValue')->echo($users);
});

// Update a user
aapje::route('PUT', '/user/@id', function ($id) {
    $input = aapje::request()->input();
    $data = [
        'name' => $input['name'] ?? null,
        'email' => $input['email'] ?? null,
    ];

    if (!$data['name'] && !$data['email']) {
        aapje::response()->statusCode(400)->echo(['error' => 'At least one of name or email is required']);
    }

    try {
        aapje::update('users', $data, ['id' => $id]);
        aapje::response()->echo(['message' => 'User updated successfully']);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to update user']);
    }
});

// Delete a user
aapje::route('DELETE', '/user/@id', function ($id) {
    try {
        aapje::delete('users', ['id' => $id]);
        aapje::response()->echo(['message' => 'User deleted successfully']);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to delete user']);
    }
});
```

For more comprehensive examples, refer to the [`examples.php`](https://github.com/ssl/aapje.php/blob/main/examples.php) file in the repository.

## 🔧 API Reference

### **aapje Class**

- **`setConfig(array $config)`**

  Set global configuration options.

- **`route(string $method, string $pattern, callable $callback)`**

  Define a new route.

- **`middleware(callable $callback)`**

  Register a middleware function.

- **`run()`**

  Start processing incoming requests.

- **Database Methods:**

  - **`insert(string $table, array $data)`**: Insert a new record.
  - **`update(string $table, array $data, array $conditions = [])`**: Update existing records.
  - **`delete(string $table, array $conditions = [])`**: Delete records.
  - **`select(string $table, $columns = '*', array $conditions = [], array $options = [])`**: Select a single record.
  - **`selectAll(string $table, $columns = '*', array $conditions = [], array $options = [])`**: Select multiple records.

### **Request Class**

- **`header(string $key): ?string`**: Retrieve a specific request header.
- **`headers(): array`**: Get all request headers.
- **`input(bool $decode = true, bool $associative = true)`**: Get JSON input from the request body.
- **`getParam(string $key): ?string`**: Retrieve a specific GET parameter.
- **`getParams(): array`**: Get all GET parameters.
- **`postParam(string $key): ?string`**: Retrieve a specific POST parameter.
- **`postParams(): array`**: Get all POST parameters.
- **`ip(): string`**: Get the client's IP address.
- **`userAgent(): ?string`**: Get the client's User-Agent.

### **Response Class**

- **`header(string $key, string $value): self`**: Set a response header.
- **`headers(array $headers): self`**: Set multiple response headers.
- **`cookie(string $name, string $value, array $options = []): self`**: Set a cookie.
- **`cookies(array $cookies): self`**: Set multiple cookies.
- **`statusCode(int $code): self`**: Set the HTTP status code.
- **`echo($content, bool $json = true)`**: Send the response to the client.

### **Helpers Class**

- **`esc(string $string): string`**: Escape HTML special characters.
- **`getFile(string $file): string`**: Get the contents of a file.
- **`putFile(string $file, string $content): void`**: Write content to a file.

## 📄 License

**aapje.php** is open-source software licensed under the [MIT License](https://opensource.org/licenses/MIT).

---

Feel free to contribute to **aapje.php** by submitting issues or pull requests.