<?php

class aapje {
    public static $version = '0.1';
    private static $routes = [];
    private static $dbConfig = [];
    private static $pdo = null;
    private static $request = null;
    private static $response = null;

    public static function route(string $method, string $pattern, callable $callback) {
        self::$routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'callback' => $callback
        ];
    }

    public static function request() {
        if (self::$request === null) {
            self::$request = new Request();
        }
        return self::$request;
    }

    public static function response() {
        if (self::$response === null) {
            self::$response = new Response();
        }
        return self::$response;
    }

    public static function run() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        try {
            foreach (self::$routes as $route) {
                if ($route['method'] === $requestMethod) {
                    $pattern = preg_replace('/@([\w]+)/', '(?P<$1>[^/]+)', $route['pattern']);
                    $pattern = '#^' . $pattern . '$#';
                    if (preg_match($pattern, $requestUri, $matches)) {
                        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                        call_user_func_array($route['callback'], $params);
                        return;
                    }
                }
            }
            self::response()->statusCode(404)->echo(['error' => 'Not Found']);
        } catch (Exception $e) {
            self::response()->statusCode(418)->echo(['error' => $e->getMessage()]);
        }
    }

    public static function setDbConfig(array $config) {
        self::$dbConfig = $config;
    }

    private static function connectDb() {
        if (self::$pdo === null) {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4",
                self::$dbConfig['host'],
                self::$dbConfig['dbname']
            );
            self::$pdo = new PDO($dsn, self::$dbConfig['user'], self::$dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
    }

    public static function query(string $query, array $params = []) {
        self::connectDb();
        $stmt = self::$pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    private static function checkQuery(array $options) {
        foreach (['table', 'columns', 'orderBy'] as $key) {
            if (isset($options[$key])) {
                if ($key === 'columns' && $options[$key] === '*') {
                    continue; // Allow '*'
                }
                $identifiers = is_array($options[$key]) ? $options[$key] : [$options[$key]];
                foreach ($identifiers as $identifier) {
                    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $identifier)) {
                        throw new Exception("Invalid identifier: {$identifier}");
                    }
                }
            }
        }
        if (isset($options['limit']) && (!is_int($options['limit']) || $options['limit'] <= 0)) {
            throw new Exception("Invalid limit: {$options['limit']}");
        }
        if (isset($options['sort']) && !in_array(strtoupper($options['sort']), ['ASC', 'DESC'], true)) {
            throw new Exception("Invalid sort: {$options['sort']}");
        }
    }

    public static function insert(string $table, array $data) {
        self::checkQuery(['table' => $table, 'columns' => array_keys($data)]);
        $keys = implode(',', array_map(function($key) { return "$key"; }, array_keys($data)));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $query = "INSERT INTO $table ($keys) VALUES ($placeholders)";
        self::query($query, array_values($data));
        return self::$pdo->lastInsertId();
    }

    public static function update(string $table, array $data, array $conditions = []) {
        self::checkQuery(['table' => $table, 'columns' => array_keys($data)]);
        $set = implode(',', array_map(function($key) { return "$key = ?"; }, array_keys($data)));

        $params = array_values($data);

        if (!empty($conditions)) {
            $wheres = [];
            foreach ($conditions as $key => $value) {
                self::checkQuery(['columns' => $key]);
                $wheres[] = "$key = ?";
                $params[] = $value;
            }
            $whereClause = implode(' AND ', $wheres);
            $query = "UPDATE $table SET $set WHERE $whereClause";
        } else {
            $query = "UPDATE $table SET $set";
        }

        self::query($query, $params);
    }

    public static function delete(string $table, array $conditions = []) {
        self::checkQuery(['table' => $table]);
        $query = "DELETE FROM $table";

        $params = [];
        if (!empty($conditions)) {
            $wheres = [];
            foreach ($conditions as $key => $value) {
                self::checkQuery(['columns' => $key]);
                $wheres[] = "$key = ?";
                $params[] = $value;
            }
            $whereClause = implode(' AND ', $wheres);
            $query .= " WHERE $whereClause";
        }

        self::query($query, $params);
    }

    public static function select(string $table, $columns = '*', array $conditions = [], array $options = []) {
        if ($columns !== '*') {
            if (!is_array($columns)) {
                throw new Exception("Columns must be '*' or an array of columns");
            }
            self::checkQuery(['table' => $table, 'columns' => $columns]);
            $cols = implode(',', array_map(function($col) { return "$col"; }, $columns));
        } else {
            $cols = '*';
        }
        self::checkQuery([
            'table' => $table,
            'orderBy' => $options['orderBy'] ?? null,
            'sort' => $options['sort'] ?? null,
        ]);
        $query = "SELECT $cols FROM $table";

        $params = [];
        if (!empty($conditions)) {
            $wheres = [];
            foreach ($conditions as $key => $value) {
                self::checkQuery(['columns' => $key]);
                $wheres[] = "$key = ?";
                $params[] = $value;
            }
            $whereClause = implode(' AND ', $wheres);
            $query .= " WHERE $whereClause";
        }

        if (isset($options['orderBy'])) {
            $orderBy = $options['orderBy'];
            $query .= " ORDER BY $orderBy";
            if (isset($options['sort'])) {
                $query .= " " . strtoupper($options['sort']);
            }
        }

        $query .= " LIMIT 1";

        $stmt = self::query($query, $params);
        return $stmt->fetch();
    }

    public static function selectAll(string $table, $columns = '*', array $conditions = [], array $options = []) {
        if ($columns !== '*') {
            if (!is_array($columns)) {
                throw new Exception("Columns must be '*' or an array of columns");
            }
            self::checkQuery(['table' => $table, 'columns' => $columns]);
            $cols = implode(',', array_map(function($col) { return "$col"; }, $columns));
        } else {
            $cols = '*';
        }
        self::checkQuery([
            'table' => $table,
            'orderBy' => $options['orderBy'] ?? null,
            'sort' => $options['sort'] ?? null,
            'limit' => $options['limit'] ?? null,
        ]);
        $query = "SELECT $cols FROM $table";

        $params = [];
        if (!empty($conditions)) {
            $wheres = [];
            foreach ($conditions as $key => $value) {
                self::checkQuery(['columns' => $key]);
                $wheres[] = "$key = ?";
                $params[] = $value;
            }
            $whereClause = implode(' AND ', $wheres);
            $query .= " WHERE $whereClause";
        }

        if (isset($options['orderBy'])) {
            $orderBy = $options['orderBy'];
            $query .= " ORDER BY $orderBy";
            if (isset($options['sort'])) {
                $query .= " " . strtoupper($options['sort']);
            }
        }

        if (isset($options['limit'])) {
            $query .= " LIMIT " . intval($options['limit']);
        }

        $stmt = self::query($query, $params);
        return $stmt->fetchAll();
    }
}

// Request class
class Request {
    public function header(string $key): ?string {
        $header = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $_SERVER[$header] ?? null;
    }

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
        return $headers;
    }

    public function cookie(string $key): ?string {
        return $_COOKIE[$key] ?? null;
    }

    public function cookies(): array {
        return $_COOKIE;
    }

    public function file(string $key) {
        return $_FILES[$key] ?? null;
    }

    public function files(): array {
        return $_FILES;
    }

    public function input($decode = true) {
        $input = file_get_contents('php://input');
        if ($decode) {
            return json_decode($input, true);
        } else {
            return $input;
        }
    }

    public function getParam(string $key): ?string {
        return $_GET[$key] ?? null;
    }

    public function getParams(): array {
        return $_GET;
    }

    public function postParam(string $key): ?string {
        return $_POST[$key] ?? null;
    }

    public function postParams(): array {
        return $_POST;
    }

    public function ip(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): ?string {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}

// Response class
class Response {
    private $headers = ['Content-Type' => 'application/json'];
    private $statusCode = 200;

    public function header(string $key, string $value): self {
        $this->headers[$key] = $value;
        return $this;
    }

    public function headers(array $headers): self {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function cookie(string $name, string $value, array $options = []): self {
        setcookie($name, $value, $options);
        return $this;
    }

    public function cookies(array $cookies): self {
        foreach ($cookies as $name => $data) {
            $value = $data['value'] ?? '';
            $options = $data['options'] ?? [];
            setcookie($name, $value, $options);
        }
        return $this;
    }

    public function statusCode(int $code): self {
        $this->statusCode = $code;
        http_response_code($code);
        return $this;
    }

    public function echo($content, $json=true) {
        foreach ($this->headers as $key => $value) {
            header("$key: $value");
        }
        if (is_string($content) && $json) {
            $content = ['echo' => $content];
        }
        echo is_array($content) ? json_encode($content) : $content;
        exit;
    }
}