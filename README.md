# aapje.php

A PHP framework for small JSON APIs that fits in one file. It does routing, middleware, CORS and has a thin PDO wrapper for MySQL. There is nothing to install and nothing to build: copy `aapje.php` next to your code and require it.

<p>
  <img src="https://img.shields.io/github/release/ssl/aapje.php?style=flat">
  <img src="https://img.shields.io/github/license/ssl/aapje.php?style=flat">
</p>

## Quick start

```php
<?php
require 'aapje.php';

aapje::route('GET', '/', function () {
    aapje::response()->echo(['hello' => 'world']);
});

aapje::route('GET', '/user/@id', function ($id) {
    aapje::response()->echo(['id' => $id]);
});

aapje::run();
```

Save that as `index.php` and try it with PHP's built-in server:

```bash
php -S localhost:8000 index.php
```

```bash
curl localhost:8000/user/42
```

## Install

You need PHP 8.0 or newer, and the `pdo_mysql` extension if you use the database methods.

Download [`aapje.php`](https://github.com/ssl/aapje.php/blob/main/aapje.php), put it in your project and `require` it. Then make your web server send every request to the file that calls `aapje::run()`.

Apache: use the [`.htaccess`](https://github.com/ssl/aapje.php/blob/main/.htaccess) from this repo. It rewrites everything to `index.php`, passes the `Authorization` header on to PHP and leaves ACME challenges alone.

nginx with PHP-FPM (adjust the socket path to your setup):

```nginx
location / {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
```

You can also clone the repo onto a host and write your API straight into `index.php`.

## Routes

```php
aapje::route($method, $pattern, $callback);
```

`$method` is an HTTP method, or `*` for all of them. A `HEAD` request uses the `GET` route when there is no `HEAD` route of its own.

`@name` in a pattern matches one path segment, URL-decoded. If the callback has an argument for every name, the values are passed by name and the order of the arguments does not matter. Otherwise they are passed in the order they appear in the pattern.

```php
aapje::route('GET', '/post/@slug/comment/@n', function ($n, $slug) {
    // GET /post/hello/comment/7  ->  $slug = 'hello', $n = '7'
});
```

The rest of the pattern is used as a regular expression, anchored at both ends. That means you can write your own groups, and it also means a `.` matches any character unless you escape it.

Routes are tried in the order you registered them and the first match wins. If no route matches the path you get a JSON 404. If the path matches but the method does not, you get a 405 with an `Allow` header.

Call `aapje::run()` once, after all routes and middleware are registered.

## Request

`aapje::request()` returns the request object.

| Method | Returns |
| --- | --- |
| `method()` | The HTTP method, in upper case |
| `path()` | The path routes are matched against: no query string, repeated slashes collapsed |
| `input()` | The request body decoded as JSON, as an associative array |
| `input(true, false)` | The same, as an object |
| `input(false)` | The raw request body |
| `getParam($key)` / `getParams()` | Query string values |
| `postParam($key)` / `postParams()` | Form body values |
| `header($key)` / `headers()` | Request headers |
| `cookie($key)` / `cookies()` | Cookies |
| `file($key)` / `files()` | Uploaded files, as PHP's `$_FILES` entries |
| `ip()` | `REMOTE_ADDR` |
| `userAgent()` | The User-Agent header |

The single-value getters return `null` when the key is missing, and also when PHP parsed it as an array (`?key[]=1`). `input()` returns `null` when the body is not valid JSON.

## Response

`aapje::response()` returns the response object. Everything except `echo()` can be chained.

```php
aapje::response()
    ->statusCode(201)
    ->header('X-Request-Id', $id)
    ->cookie('seen', '1', ['expires' => time() + 3600, 'path' => '/'])
    ->echo(['created' => true]);
```

`echo()` sends the headers and the body and then ends the script, so nothing after it runs. What it sends depends on what you give it:

| Call | Body |
| --- | --- |
| `echo(['a' => 1])` | `{"a":1}` |
| `echo('hi')` | `{"echo":"hi"}` |
| `echo('hi', false)` | `hi` |
| `echo(false)` | `false` |
| `echo(null)` | empty |

Arrays and objects are always sent as JSON. If the content cannot be encoded, `echo()` throws a `JsonException`.

The `Content-Type` is `application/json` unless you set another one:

```php
aapje::response()->header('Content-Type', 'text/plain')->echo('hi', false);
```

`cookie()` takes the same options array as PHP's `setcookie()`. `cookies()` sets several at once from `['name' => ['value' => ..., 'options' => [...]]]`.

## Middleware

Middleware runs on every request, before routing, in the order it was registered. It gets the request and response objects.

```php
aapje::middleware(function ($request, $response) {
    if ($request->header('Authorization') !== 'Bearer ' . getenv('API_TOKEN')) {
        $response->statusCode(401)->echo(['error' => 'Unauthorized']);
    }
});
```

To stop a request, send a response. To limit middleware to part of your API, check `$request->path()`. There is no hook that runs after the route handler, because the handler ends the script when it responds.

## Configuration

All of it is optional.

```php
aapje::setConfig([
    'debug' => false,
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'your_database',
        'user' => 'your_username',
        'password' => 'your_password',
    ],
    'cors' => [
        'enabled' => true,
        'origins' => ['https://example.com'],
        'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'headers' => ['Content-Type', 'Authorization'],
        'credentials' => true,
    ],
    'default_headers' => [
        'X-Powered-By' => 'aapje.php',
    ],
]);
```

`default_headers` are added to every response sent with `echo()`. The database `port` can be left out.

### Errors

An exception or PHP error that you don't catch is written to the PHP error log and answered with a 500 and `{"error":"Internal Server Error"}`. Set `debug` to `true` to get the real message in the response while developing. Leave it off on a public server, because those messages can contain SQL and file paths.

### CORS

CORS is off by default. If you only set `'enabled' => true`, origins, methods and headers all default to `*`. With a list of origins, the request's `Origin` is echoed back when it is on the list, and `Vary: Origin` is set. Browsers refuse `credentials` together with the `*` origin, so list your origins if you need credentials.

CORS is handled before middleware. Preflight `OPTIONS` requests are answered with a 204 without reaching your middleware or routes, and responses sent by middleware carry the CORS headers too.

CORS only tells browsers which sites may read a response. A request from an origin that is not on the list is still handled.

## Database

The database methods use PDO with MySQL. The connection is opened on the first query, so an API that never touches the database never connects.

```php
// Returns the new row's ID
$id = aapje::insert('users', ['name' => 'Ada', 'email' => 'ada@example.com']);

// One row, or false if there is none
$user = aapje::select('users', ['id', 'name', 'email'], ['id' => $id]);

// Many rows
$users = aapje::selectAll('users', ['id', 'email'], ['active' => 1], [
    'orderBy' => 'id',
    'sort' => 'DESC',
    'limit' => 10,
    'offset' => 20,
]);

// Both return the number of rows they changed
aapje::update('users', ['name' => 'Ada L.'], ['id' => $id]);
aapje::delete('users', ['id' => $id]);
```

Conditions are `column = value` pairs joined with `AND`; a `null` value becomes `IS NULL`. Pass `'*'` instead of a column list to select every column. For anything else, write the SQL yourself; `query()` prepares it, binds the parameters and returns the `PDOStatement`:

```php
$rows = aapje::query(
    'SELECT * FROM users WHERE created_at > ? OR role = ?',
    [$since, 'admin']
)->fetchAll();
```

Things to know:

- Values are always bound as parameters. Table and column names may only contain `a-z`, `A-Z`, `0-9`, `_` and `-`, and are quoted with backticks. That check is about syntax: it does not decide which columns a client may use, so don't pass request data as column names or as `orderBy` without your own list of allowed names.
- `update()` and `delete()` refuse to run without conditions. To change every row on purpose, pass `true` as the last argument: `aapje::delete('sessions', [], true)`.
- `update()` returns the number of rows that actually changed, which is 0 when the new values equal the old ones.
- `limit` and `offset` take whole numbers. `offset` needs a `limit`.
- Arrays are not accepted as values.
- Database errors are thrown as `PDOException`. See [Errors](#errors) for what happens when you don't catch them.

## Helpers

- `Helpers::esc($string)` escapes HTML special characters.
- `Helpers::getFile($path)` reads a file.
- `Helpers::putFile($path, $content)` writes a file.

`getFile()` and `putFile()` throw an `Exception` when the file cannot be read or written.

## Examples

[`examples.php`](https://github.com/ssl/aapje.php/blob/main/examples.php) has a longer API with users, pagination, raw queries, file uploads, cookies and different content types.

## License

MIT, see [`license.txt`](https://github.com/ssl/aapje.php/blob/main/license.txt).
