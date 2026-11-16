<?php
/**
 * aapje.php
 * 
 * A lightweight, single-file PHP framework for building simple APIs.
 * 
 * https://github.com/ssl/aapje.php
 * Version: 0.3
 * Requires: PHP 8.0+
 * License: MIT
 * 
 * The MIT License (MIT)
 * 
 * Copyright (c) 2024 Elyesa (aapje.php)
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software
 * and associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

class aapje {
    public static $version = '0.3';
    private static $routes = [];
    private static $middleware = [];
    private static $config = [
        'debug' => false,
        'database' => [
            'host' => '',
            'port' => '',
            'dbname' => '',
            'user' => '',
            'password' => ''
        ],
        'cors' => [
            'enabled' => false,
            'origins' => ['*'],
            'methods' => ['*'],
            'headers' => ['*'],
            'credentials' => false,
        ],
        'default_headers' => []
    ];
    private static $request = null;
    private static $response = null;
    private static $pdo = null;

    /**
     * Register a middleware function.
     *
     * @param callable $callback Middleware function accepting ($request, $response)
     */
    public static function middleware(callable $callback) {
        self::$middleware[] = $callback;
    }

    /**
     * Define a route with a specific HTTP method or all methods using '*'.
     *
     * @param string $method  HTTP method (e.g., 'GET', 'POST', '*')
     * @param string $pattern URL pattern with optional parameters (e.g., '/user/@id')
     * @param callable $callback Function to execute when the route matches
     */
    public static function route(string $method, string $pattern, callable $callback) {
        self::$routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'callback' => $callback
        ];
    }

    /**
     * Access the request object.
     *
     * @return Request
     */
    public static function request() {
        if (self::$request === null) {
            self::$request = new Request();
        }
        return self::$request;
    }

    /**
     * Access the response object.
     *
     * @return Response
     */
    public static function response() {
        if (self::$response === null) {
            self::$response = new Response();
            // Apply default headers if any
            foreach (self::$config['default_headers'] as $key => $value) {
                self::$response->header($key, $value);
            }
        }
        return self::$response;
    }

    /**
     * Set global configuration options.
     *
     * @param array $config Configuration array
     */
    public static function setConfig(array $config) {
        foreach ($config as $key => $value) {
            // Debug mode: show exception messages in error responses
            if ($key === 'debug') {
                self::$config['debug'] = (bool) $value;
            }

            // CORS Configuration
            if ($key === 'cors' && is_array($value)) {
                self::$config['cors'] = array_merge(self::$config['cors'], $value);
            }

            // Database Configuration
            if ($key === 'database' && is_array($value)) {
                if (!empty($value['host'])) {
                    self::$config['database'] = array_merge(self::$config['database'], $value);
                }
            }

            // Default Headers Configuration
            if ($key === 'default_headers' && is_array($value)) {
                self::$config['default_headers'] = array_merge(self::$config['default_headers'], $value);
                if (self::$response !== null) {
                    foreach ($value as $headerKey => $headerValue) {
                        self::$response->header($headerKey, $headerValue);
                    }
                }
            }
        }
    }

    /**
     * Start processing the incoming request.
     */
    public static function run() {
        try {
            $request = self::request();
            $response = self::response();
            $method = $request->method();
            $path = $request->path();

            // Handle CORS first, so preflight requests and middleware responses get the headers
            if (self::$config['cors']['enabled']) {
                self::handleCORS($request, $response);
            }

            // Execute Middleware
            foreach (self::$middleware as $middleware) {
                $middleware($request, $response);
            }

            $allowedMethods = [];
            $headFallback = null;
            foreach (self::$routes as $route) {
                $pattern = preg_replace('/@([\w]+)/', '(?P<$1>[^/]+)', $route['pattern']);
                if (!preg_match('#^' . $pattern . '$#D', $path, $matches)) {
                    continue;
                }
                if ($route['method'] === '*' || $route['method'] === $method) {
                    self::dispatch($route['callback'], $matches);
                    return;
                }
                // A HEAD request falls back to the first matching GET route
                if ($method === 'HEAD' && $route['method'] === 'GET' && $headFallback === null) {
                    $headFallback = [$route['callback'], $matches];
                }
                $allowedMethods[$route['method']] = true;
            }

            if ($headFallback !== null) {
                self::dispatch($headFallback[0], $headFallback[1]);
                return;
            }

            if (!empty($allowedMethods)) {
                // The path exists, but not for this method
                $response->header('Allow', implode(', ', array_keys($allowedMethods)));
                $response->statusCode(405)->echo(['error' => 'Method Not Allowed']);
            }
            $response->statusCode(404)->echo(['error' => 'Not Found']);
        } catch (\Throwable $e) {
            self::handleError($e);
        }
    }

    /**
     * Call a route callback with the parameters matched from the URL.
     *
     * Parameters are passed by name when the callback declares all of them,
     * and in the order they appear in the pattern otherwise.
     *
     * @param callable $callback
     * @param array $matches Result of preg_match on the route pattern
     */
    private static function dispatch(callable $callback, array $matches) {
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = rawurldecode($value);
            }
        }

        $declared = [];
        foreach ((new ReflectionFunction(Closure::fromCallable($callback)))->getParameters() as $parameter) {
            $declared[] = $parameter->getName();
        }
        if (!empty(array_diff(array_keys($params), $declared))) {
            $params = array_values($params);
        }

        call_user_func_array($callback, $params);

        // The callback returned without sending a response: still send the headers
        if (!headers_sent() && !ob_get_length()) {
            self::response()->sendHeaders();
        }
    }

    /**
     * Log an uncaught error and answer with a 500.
     *
     * The message is only sent to the client when 'debug' is enabled.
     *
     * @param \Throwable $e
     */
    private static function handleError(\Throwable $e) {
        error_log((string) $e);
        $message = self::$config['debug'] ? $e->getMessage() : 'Internal Server Error';
        self::response()->statusCode(500)->echo(['error' => $message]);
    }

    /**
     * Handle CORS settings.
     *
     * @param Request $request
     * @param Response $response
     */
    private static function handleCORS($request, $response) {
        $corsConfig = self::$config['cors'];

        // Determine allowed origins
        $allowedOrigins = (array) $corsConfig['origins'];
        $origin = $request->header('Origin');

        if (in_array('*', $allowedOrigins, true)) {
            $response->header('Access-Control-Allow-Origin', '*');
        } else {
            // The response depends on the Origin header, tell caches about it
            $response->header('Vary', 'Origin');
            if ($origin && in_array($origin, $allowedOrigins, true)) {
                $response->header('Access-Control-Allow-Origin', $origin);
            }
        }

        // Set other CORS headers
        $response->header('Access-Control-Allow-Methods', implode(', ', (array) $corsConfig['methods']));
        $response->header('Access-Control-Allow-Headers', implode(', ', (array) $corsConfig['headers']));
        if ($corsConfig['credentials']) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        // Handle preflight OPTIONS request
        if ($request->method() === 'OPTIONS') {
            $response->statusCode(204)->echo(null, false);
        }
    }

    /**
     * Establish a database connection using PDO.
     */
    private static function connectDb() {
        if (self::$pdo === null) {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4",
                self::$config['database']['host'],
                self::$config['database']['dbname']
            );
            if (!empty(self::$config['database']['port'])) {
                $dsn .= ';port=' . (int) self::$config['database']['port'];
            }
            self::$pdo = new PDO($dsn, self::$config['database']['user'], self::$config['database']['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
    }

    /**
     * Execute a raw SQL query with optional parameters.
     *
     * @param string $query  SQL query with placeholders
     * @param array $params  Parameters to bind to the query
     * @return PDOStatement
     * @throws Exception     If a parameter is an array
     */
    public static function query(string $query, array $params = []) {
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                // PDO would bind false as an empty string
                $params[$key] = (int) $value;
            } elseif (is_array($value)) {
                throw new Exception('Query parameters cannot be arrays');
            }
        }
        self::connectDb();
        $stmt = self::$pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Validate and quote a table or column name.
     *
     * This checks the syntax of the name, it does not check that the caller
     * is allowed to use that table or column.
     *
     * @param mixed $name Identifier to validate
     * @return string     The identifier wrapped in backticks
     * @throws Exception  If the identifier is not valid
     */
    private static function identifier($name): string {
        if (!is_string($name) || !preg_match('/^[a-zA-Z0-9_\-]+\z/', $name)) {
            throw new Exception('Invalid identifier: ' . (is_scalar($name) ? $name : gettype($name)));
        }
        return '`' . $name . '`';
    }

    /**
     * Build a comma separated list of quoted column names.
     *
     * @param array $columns Column names
     * @return string
     * @throws Exception If the list is empty or contains an invalid name
     */
    private static function identifiers(array $columns): string {
        if (empty($columns)) {
            throw new Exception('At least one column is required');
        }
        return implode(',', array_map([self::class, 'identifier'], $columns));
    }

    /**
     * Build a WHERE clause from column-value pairs joined with AND.
     *
     * @param array $conditions Associative array of conditions (a null value becomes IS NULL)
     * @param array $params     Bound parameters, values are appended to it
     * @return string           The clause with a leading space, or '' without conditions
     */
    private static function where(array $conditions, array &$params): string {
        if (empty($conditions)) {
            return '';
        }
        $wheres = [];
        foreach ($conditions as $key => $value) {
            $column = self::identifier($key);
            if ($value === null) {
                $wheres[] = "$column IS NULL";
            } else {
                $wheres[] = "$column = ?";
                $params[] = $value;
            }
        }
        return ' WHERE ' . implode(' AND ', $wheres);
    }

    /**
     * Validate a 'limit' or 'offset' option.
     *
     * @param mixed $value Integer, or a string of digits
     * @param string $name Option name, for the error message
     * @param int $min     Lowest allowed value
     * @return int
     * @throws Exception   If the value is not a whole number or too low
     */
    private static function intOption($value, string $name, int $min): int {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }
        if (!is_int($value) || $value < $min) {
            throw new Exception("Invalid $name");
        }
        return $value;
    }

    /**
     * Build and run a SELECT query.
     *
     * @param string       $table
     * @param string|array $columns
     * @param array        $conditions
     * @param array        $options
     * @param bool         $single     Whether to force LIMIT 1
     * @return PDOStatement
     * @throws Exception
     */
    private static function runSelect(string $table, $columns, array $conditions, array $options, bool $single) {
        if ($columns === '*') {
            $cols = '*';
        } elseif (is_array($columns)) {
            $cols = self::identifiers($columns);
        } else {
            throw new Exception("Columns must be '*' or an array of columns");
        }

        $params = [];
        $query = "SELECT $cols FROM " . self::identifier($table) . self::where($conditions, $params);

        if (isset($options['orderBy'])) {
            $query .= ' ORDER BY ' . self::identifier($options['orderBy']);
            if (isset($options['sort'])) {
                $sort = is_string($options['sort']) ? strtoupper($options['sort']) : '';
                if ($sort !== 'ASC' && $sort !== 'DESC') {
                    throw new Exception('Invalid sort');
                }
                $query .= " $sort";
            }
        }

        if ($single) {
            $query .= ' LIMIT 1';
        } elseif (isset($options['limit'])) {
            $query .= ' LIMIT ' . self::intOption($options['limit'], 'limit', 1);
        }

        if (isset($options['offset'])) {
            if (!$single && !isset($options['limit'])) {
                throw new Exception('The offset option needs a limit');
            }
            $query .= ' OFFSET ' . self::intOption($options['offset'], 'offset', 0);
        }

        return self::query($query, $params);
    }

    /**
     * Insert a new record into a table.
     *
     * @param string $table Table name
     * @param array $data   Associative array of column-value pairs
     * @return string      Last inserted ID
     * @throws Exception   If query fails
     */
    public static function insert(string $table, array $data) {
        $keys = self::identifiers(array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $query = 'INSERT INTO ' . self::identifier($table) . " ($keys) VALUES ($placeholders)";
        self::query($query, array_values($data));
        return self::$pdo->lastInsertId();
    }

    /**
     * Update existing records in a table.
     *
     * @param string $table      Table name
     * @param array $data        Associative array of column-value pairs to update
     * @param array $conditions  Associative array of conditions for the WHERE clause
     * @param bool $allRows      Must be true to update every row when there are no conditions
     * @return int               Number of rows changed
     * @throws Exception         If query fails, or there are no conditions and $allRows is false
     */
    public static function update(string $table, array $data, array $conditions = [], bool $allRows = false) {
        if (empty($data)) {
            throw new Exception('At least one column is required');
        }
        if (empty($conditions) && !$allRows) {
            throw new Exception('update() without conditions changes every row, pass $allRows = true to allow it');
        }
        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = self::identifier($key) . ' = ?';
        }
        $params = array_values($data);
        $query = 'UPDATE ' . self::identifier($table) . ' SET ' . implode(',', $set) . self::where($conditions, $params);

        return self::query($query, $params)->rowCount();
    }

    /**
     * Delete records from a table.
     *
     * @param string $table      Table name
     * @param array $conditions  Associative array of conditions for the WHERE clause
     * @param bool $allRows      Must be true to delete every row when there are no conditions
     * @return int               Number of rows deleted
     * @throws Exception         If query fails, or there are no conditions and $allRows is false
     */
    public static function delete(string $table, array $conditions = [], bool $allRows = false) {
        if (empty($conditions) && !$allRows) {
            throw new Exception('delete() without conditions deletes every row, pass $allRows = true to allow it');
        }
        $params = [];
        $query = 'DELETE FROM ' . self::identifier($table) . self::where($conditions, $params);

        return self::query($query, $params)->rowCount();
    }

    /**
     * Select a single record from a table.
     *
     * @param string       $table      Table name
     * @param string|array $columns    Columns to select ('*' or array of column names)
     * @param array        $conditions Associative array of conditions for the WHERE clause
     * @param array        $options    Additional options ('orderBy', 'sort', 'offset')
     * @return array|false             Single record as an associative array or false if not found
     * @throws Exception               If query fails
     */
    public static function select(string $table, $columns = '*', array $conditions = [], array $options = []) {
        return self::runSelect($table, $columns, $conditions, $options, true)->fetch();
    }

    /**
     * Select multiple records from a table.
     *
     * @param string       $table      Table name
     * @param string|array $columns    Columns to select ('*' or array of column names)
     * @param array        $conditions Associative array of conditions for the WHERE clause
     * @param array        $options    Additional options ('orderBy', 'sort', 'limit', 'offset')
     * @return array                   Array of records
     * @throws Exception               If query fails
     */
    public static function selectAll(string $table, $columns = '*', array $conditions = [], array $options = []) {
        return self::runSelect($table, $columns, $conditions, $options, false)->fetchAll();
    }
}

/**
 * Helpers Class
 * 
 * Provides utility functions for common tasks.
 */
class Helpers {
    /**
     * Escape HTML special characters.
     *
     * @param string $string The string to escape.
     * @return string        The escaped string.
     */
    public static function esc(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Get the contents of a file.
     * 
     * @param string $file Path to the file.
     * @return string      The file contents.
     * @throws Exception   If the file cannot be read.
     */
    public static function getFile(string $file): string {
        $content = @file_get_contents($file);
        if ($content === false) {
            throw new Exception('Unable to read file');
        }
        return $content;
    }

    /**
     * Put new content into a file.
     * @param string $file      Path to the file.
     * @param string $content  The new content.
     * @return void
     * @throws Exception       If the file cannot be written.
     */
    public static function putFile(string $file, string $content): void {
        if (@file_put_contents($file, $content) === false) {
            throw new Exception('Unable to write file');
        }
    }
}

/**
 * Request Class
 */
class Request {
    /**
     * Retrieve the HTTP request method, in upper case.
     *
     * @return string
     */
    public function method(): string {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Retrieve the request path, without the query string.
     *
     * This is the path routes are matched against. Repeated slashes are collapsed.
     *
     * @return string
     */
    public function path(): string {
        $path = explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0];
        if ($path === '' || $path[0] !== '/') {
            // Absolute-form request target (http://host/path)
            $path = parse_url($path, PHP_URL_PATH) ?: '/';
        }
        return preg_replace('#/{2,}#', '/', $path);
    }

    /**
     * Retrieve a specific HTTP request header.
     *
     * @param string $key Header name
     * @return string|null
     */
    public function header(string $key): ?string {
        $name = strtoupper(str_replace('-', '_', $key));
        $candidates = ['HTTP_' . $name];
        if ($name === 'CONTENT_TYPE' || $name === 'CONTENT_LENGTH') {
            // CGI passes these without the HTTP_ prefix
            $candidates[] = $name;
        }
        if ($name === 'AUTHORIZATION') {
            // Set by a rewrite rule on Apache CGI/FastCGI setups
            $candidates[] = 'REDIRECT_HTTP_AUTHORIZATION';
        }
        foreach ($candidates as $candidate) {
            if (isset($_SERVER[$candidate]) && is_string($_SERVER[$candidate]) && $_SERVER[$candidate] !== '') {
                return $_SERVER[$candidate];
            }
        }
        return null;
    }

    /**
     * Retrieve all HTTP request headers.
     *
     * @return array
     */
    public function headers(): array {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = str_replace(
                    ' ',
                    '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($key, 5))))
                );
                $headers[$header] = $value;
            }
        }
        foreach (['Content-Type', 'Content-Length', 'Authorization'] as $header) {
            if (!isset($headers[$header]) && $this->header($header) !== null) {
                $headers[$header] = $this->header($header);
            }
        }
        return $headers;
    }

    /**
     * Retrieve a specific cookie value.
     *
     * @param string $key Cookie name
     * @return string|null
     */
    public function cookie(string $key): ?string {
        return self::stringOrNull($_COOKIE[$key] ?? null);
    }

    /**
     * Retrieve all cookies.
     *
     * @return array
     */
    public function cookies(): array {
        return $_COOKIE;
    }

    /**
     * Retrieve information about an uploaded file.
     *
     * @param string $key File input name
     * @return array|null
     */
    public function file(string $key) {
        return $_FILES[$key] ?? null;
    }

    /**
     * Retrieve all uploaded files.
     *
     * @return array
     */
    public function files(): array {
        return $_FILES;
    }

    /**
     * Retrieve and decode JSON input from the request body.
     *
     * @param bool $decode Whether to decode JSON. If false, returns raw input.
     * @param bool $associative Whether to return an associative array or object
     * @return mixed Decoded JSON (null if the body is not valid JSON), or the raw body
     */
    public function input(bool $decode = true, bool $associative = true) {
        $input = file_get_contents('php://input');
        if ($decode) {
            return json_decode($input, $associative);
        } else {
            return $input;
        }
    }

    /**
     * Retrieve a specific GET parameter.
     *
     * @param string $key Parameter name
     * @return string|null Null if the parameter is missing or is an array (?key[]=)
     */
    public function getParam(string $key): ?string {
        return self::stringOrNull($_GET[$key] ?? null);
    }

    /**
     * Retrieve all GET parameters.
     *
     * @return array
     */
    public function getParams(): array {
        return $_GET;
    }

    /**
     * Retrieve a specific POST parameter.
     *
     * @param string $key Parameter name
     * @return string|null Null if the parameter is missing or is an array (key[]=)
     */
    public function postParam(string $key): ?string {
        return self::stringOrNull($_POST[$key] ?? null);
    }

    /**
     * Retrieve all POST parameters.
     *
     * @return array
     */
    public function postParams(): array {
        return $_POST;
    }

    /**
     * Retrieve the client's IP address.
     *
     * @return string
     */
    public function ip(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Retrieve the client's User-Agent string.
     *
     * @return string|null
     */
    public function userAgent(): ?string {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    /**
     * PHP parses key[]=value into an array, the single-value getters only return strings.
     *
     * @param mixed $value
     * @return string|null
     */
    private static function stringOrNull($value): ?string {
        return is_string($value) ? $value : null;
    }
}

/**
 * Response Class
 */
class Response {
    private $headers = ['Content-Type' => 'application/json'];

    /**
     * Set a single HTTP response header.
     *
     * @param string $key Header name
     * @param string $value Header value
     * @return self
     */
    public function header(string $key, string $value): self {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Set multiple HTTP response headers.
     *
     * @param array $headers Associative array of headers
     * @return self
     */
    public function headers(array $headers): self {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Set a cookie.
     *
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param array $options Additional options (e.g., 'expires', 'path')
     * @return self
     */
    public function cookie(string $name, string $value, array $options = []): self {
        setcookie($name, $value, $options);
        return $this;
    }

    /**
     * Set multiple cookies.
     *
     * @param array $cookies Associative array of cookies
     * @return self
     */
    public function cookies(array $cookies): self {
        foreach ($cookies as $name => $data) {
            $value = $data['value'] ?? '';
            $options = $data['options'] ?? [];
            setcookie($name, $value, $options);
        }
        return $this;
    }

    /**
     * Set the HTTP status code.
     *
     * @param int $code HTTP status code
     * @return self
     */
    public function statusCode(int $code): self {
        if (!headers_sent()) {
            http_response_code($code);
        }
        return $this;
    }

    /**
     * Send the response headers without a body.
     *
     * echo() does this for you, it is only needed when you write the body yourself.
     *
     * @return self
     */
    public function sendHeaders(): self {
        if (!headers_sent()) {
            foreach ($this->headers as $key => $value) {
                header("$key: $value");
            }
        }
        return $this;
    }

    /**
     * Send the response to the client and end the script.
     *
     * Arrays and objects are always sent as JSON, null sends an empty body.
     * With $json enabled, a string is wrapped as {"echo": "..."} and other
     * values (numbers, booleans) are JSON-encoded. With $json disabled,
     * strings and other scalar values are sent as they are.
     *
     * @param mixed $content Content to send
     * @param bool $json Whether to send strings and other scalar values as JSON
     * @throws JsonException If the content cannot be encoded
     */
    public function echo($content = null, bool $json = true) {
        if ($content === null) {
            $body = '';
        } elseif (is_scalar($content) && !$json) {
            $body = (string) $content;
        } else {
            if (is_string($content)) {
                $content = ['echo' => $content];
            }
            $body = json_encode($content, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        }

        $this->sendHeaders();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') {
            echo $body;
        }
        exit;
    }
}
