<?php
/**
 * aapje.php Examples
 *
 * This is an example file demonstrating how you can use aapje.php.
 * It covers routing, middleware, CORS, database operations, helper functions,
 * setting custom headers, handling different content types, and more.
 *
 * Ensure that `aapje.php` is in the same directory or adjust the path accordingly.
 *
 * The database examples expect a table like this:
 *
 *   CREATE TABLE users (
 *       id INT AUTO_INCREMENT PRIMARY KEY,
 *       name VARCHAR(255) NOT NULL,
 *       email VARCHAR(255) NOT NULL UNIQUE,
 *       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 *   );
 */

require 'aapje.php';

// Global Configuration
aapje::setConfig([
    // Set to true while developing to see exception messages in 500 responses
    'debug' => false,
    'database' => [
        'host' => '127.0.0.1',
        'dbname' => 'aapje_1',
        'user' => 'aapje',
        'password' => 'password',
    ],
    'cors' => [
        'enabled' => true,
        'origins' => ['https://example.com', 'https://example.org'], // Allowed origins
        'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'], // Allowed HTTP methods
        'headers' => ['Content-Type', 'Authorization'], // Allowed headers
        'credentials' => true, // Allow credentials
    ],
    'default_headers' => [
        'X-Powered-By' => 'aapje.php',
    ],
]);

// Register Middleware
// Example: Logging Middleware
function loggingMiddleware($request, $response) {
    $method = $request->method();
    $path = $request->path();
    $ip = $request->ip();
    error_log("[$method] $path from $ip");
}

aapje::middleware('loggingMiddleware');

// Example: Authentication Middleware
// Replace the token with your own secret, and keep it out of your code (use an environment variable)
function authMiddleware($request, $response) {
    $authHeader = $request->header('Authorization') ?? '';
    if (!hash_equals('Bearer exampletoken', $authHeader)) {
        $response->statusCode(401)->echo(['error' => 'Unauthorized']);
    }
}

aapje::middleware('authMiddleware');

/**
 * Store an uploaded file and return its new name, or null if it was refused.
 *
 * Uploaded files are untrusted: never keep the name the client sent and never
 * store them where the web server will execute them. This saves them in a
 * directory next to (not inside) the web root, under a random name, and only
 * accepts a fixed list of extensions.
 */
function storeUpload(array $file): ?string {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK || !in_array($extension, $allowedExtensions, true)) {
        return null;
    }

    $uploadDir = dirname(__DIR__) . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $extension;
    return move_uploaded_file($file['tmp_name'], $uploadDir . $name) ? $name : null;
}

// Routes

/**
 * 1. Home Route
 *
 * Responds to all HTTP methods.
 */
aapje::route('*', '/', function () {
    aapje::response()->echo(['message' => 'Welcome to aapje.php API!', 'version' => aapje::$version]);
});

/**
 * 2. Get All Users
 *
 * Retrieves all users from the 'users' table.
 * Select the columns you want to expose instead of '*', so a column added
 * later (like a password hash) does not end up in the response.
 */
aapje::route('GET', '/users', function () {
    $users = aapje::selectAll('users', ['id', 'name', 'email'], [], [
        'orderBy' => 'id',
        'sort' => 'ASC',
        'limit' => 100
    ]);
    aapje::response()->echo($users);
});

/**
 * 3. Get User Emails
 *
 * Retrieves only 'id' and 'email' fields for all users.
 */
aapje::route('GET', '/users/emails', function () {
    $users = aapje::selectAll('users', ['id', 'email'], [], [
        'orderBy' => 'email',
        'sort' => 'DESC'
    ]);
    aapje::response()->echo($users);
});

/**
 * 4. Get Users Page by Page
 *
 * Retrieves 5 users per page, newest first, using 'limit' and 'offset'.
 * Example: /users/page/2
 */
aapje::route('GET', '/users/page/@page', function ($page) {
    $page = max(1, (int) $page);
    $users = aapje::selectAll('users', ['id', 'name', 'email'], [], [
        'orderBy' => 'id',
        'sort' => 'DESC',
        'limit' => 5,
        'offset' => ($page - 1) * 5
    ]);
    aapje::response()->echo($users);
});

/**
 * 5. Get User by ID
 *
 * Retrieves a single user based on the provided ID.
 */
aapje::route('GET', '/user/@id', function ($id) {
    $user = aapje::select('users', ['id', 'name', 'email'], ['id' => $id]);
    if ($user) {
        aapje::response()->echo($user);
    } else {
        aapje::response()->statusCode(404)->echo(['error' => 'User not found']);
    }
});

/**
 * 6. Create a New User
 *
 * Creates a new user with 'name' and 'email'.
 */
aapje::route('POST', '/users', function () {
    $input = aapje::request()->input();

    // Validate input fields
    $data = [
        'email' => $input['email'] ?? null,
        'name'  => $input['name'] ?? null,
    ];

    // Check for required fields
    if (!is_string($data['email']) || !is_string($data['name']) || $data['email'] === '' || $data['name'] === '') {
        aapje::response()->statusCode(400)->echo(['error' => 'Email and name are required']);
    }

    try {
        $id = aapje::insert('users', $data);
        aapje::response()->statusCode(201)->echo(['created_user_id' => $id]);
    } catch (PDOException $e) {
        // Log the details, do not send the database message to the client
        error_log($e->getMessage());
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to create user']);
    }
});

/**
 * 7. Update a User
 *
 * Updates the 'name' and/or 'email' of a user based on ID.
 */
aapje::route('PUT', '/users/@id', function ($id) {
    $input = aapje::request()->input();

    if (!is_array($input)) {
        aapje::response()->statusCode(400)->echo(['error' => 'Invalid JSON body']);
    }

    // Warning: Directly passing user $input to $data can introduce security issues
    // Bad practice:
    // aapje::update('users', $input, ['id' => $id]);

    // Example of security issue:
    // If a user includes 'id' in $input, they could attempt to change the ID of the user

    // Instead, only allow specific fields to be updated
    $allowedFields = ['email', 'name'];
    $data = array_filter(array_intersect_key($input, array_flip($allowedFields)), 'is_string');

    if (empty($data)) {
        aapje::response()->statusCode(400)->echo(['error' => 'No valid fields to update']);
    }

    if (!aapje::select('users', ['id'], ['id' => $id])) {
        aapje::response()->statusCode(404)->echo(['error' => 'User not found']);
    }

    try {
        aapje::update('users', $data, ['id' => $id]);
        aapje::response()->echo(['updated_user_id' => $id]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to update user']);
    }
});

/**
 * 8. Delete a User
 *
 * Deletes a user based on the provided ID.
 * delete() returns the number of deleted rows.
 */
aapje::route('DELETE', '/users/@id', function ($id) {
    try {
        $deleted = aapje::delete('users', ['id' => $id]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to delete user']);
    }

    if ($deleted === 0) {
        aapje::response()->statusCode(404)->echo(['error' => 'User not found']);
    }
    aapje::response()->echo(['deleted_user_id' => $id]);
});

/**
 * 9. Search Users
 *
 * Searches for users based on 'name' and/or 'email' criteria,
 * with sorting and limiting of the results.
 */
aapje::route('POST', '/users/search', function () {
    $input = aapje::request()->input();
    $conditions = [];

    if (!empty($input['name']) && is_string($input['name'])) {
        $conditions['name'] = $input['name'];
    }
    if (!empty($input['email']) && is_string($input['email'])) {
        $conditions['email'] = $input['email'];
    }

    if (empty($conditions)) {
        aapje::response()->statusCode(400)->echo(['error' => 'Please provide search criteria']);
    }

    try {
        $results = aapje::selectAll('users', ['id', 'name', 'email'], $conditions, [
            'orderBy' => 'created_at',
            'sort' => 'DESC',
            'limit' => 20
        ]);
        aapje::response()->echo($results);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        aapje::response()->statusCode(500)->echo(['error' => 'Search failed']);
    }
});

/**
 * 10. Raw SQL Query
 *
 * Uses query() for anything the helper methods cannot express.
 * Always pass values as parameters, never put them in the SQL string.
 */
aapje::route('GET', '/users/find', function () {
    $search = aapje::request()->getParam('q');

    if ($search === null || $search === '') {
        aapje::response()->statusCode(400)->echo(['error' => 'Please provide a search term']);
    }

    $users = aapje::query(
        'SELECT id, name, email FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY name LIMIT 20',
        ["%$search%", "%$search%"]
    )->fetchAll();
    aapje::response()->echo($users);
});

/**
 * 11. Upload a Single File
 *
 * Handles a file upload and responds with a custom header.
 * See storeUpload() at the top of this file for how the file is stored.
 */
aapje::route('POST', '/upload', function () {
    $file = aapje::request()->file('file');

    if (!$file || is_array($file['name'])) {
        aapje::response()->statusCode(400)->echo(['error' => 'No file uploaded']);
    }

    $name = storeUpload($file);
    if ($name === null) {
        aapje::response()->statusCode(400)->echo(['error' => 'Upload failed or file type not allowed']);
    }
    aapje::response()->header('X-Upload-Status', 'Success')->echo(['uploaded_file' => $name]);
});

/**
 * 12. Upload Multiple Files
 *
 * Handles multiple file uploads sent as files[].
 */
aapje::route('POST', '/upload-files', function () {
    $files = aapje::request()->file('files');
    $uploadedFiles = [];

    if (!$files || !is_array($files['name'])) {
        aapje::response()->statusCode(400)->echo(['error' => 'No files uploaded']);
    }

    // PHP groups files[] per field (name, tmp_name, error), not per file
    foreach (array_keys($files['name']) as $i) {
        $name = storeUpload([
            'name' => $files['name'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
        ]);
        if ($name !== null) {
            $uploadedFiles[] = $name;
        }
    }

    if (!empty($uploadedFiles)) {
        aapje::response()->echo(['uploaded_files' => $uploadedFiles]);
    } else {
        aapje::response()->statusCode(400)->echo(['error' => 'No files uploaded']);
    }
});

/**
 * 13. Echo an HTML Page
 *
 * Sets the Content-Type to HTML and sends the content as it is (second argument false).
 * Anything that comes from the request has to be escaped with Helpers::esc().
 * Example: /html?name=aapje
 */
aapje::route('GET', '/html', function () {
    $name = aapje::request()->getParam('name') ?? 'world';
    aapje::response()
        ->header('Content-Type', 'text/html; charset=utf-8')
        ->echo('<h1>Hello, ' . Helpers::esc($name) . '!</h1>', false);
});

/**
 * 14. Get All Request Headers
 *
 * Retrieves and returns all HTTP request headers.
 */
aapje::route('GET', '/headers', function () {
    $headers = aapje::request()->headers();
    aapje::response()->echo($headers);
});

/**
 * 15. Get All Cookies
 *
 * Retrieves and returns all cookies sent with the request.
 */
aapje::route('GET', '/cookies', function () {
    $cookies = aapje::request()->cookies();
    aapje::response()->echo($cookies);
});

/**
 * 16. Get All GET Parameters
 *
 * Retrieves and returns all GET parameters from the request.
 */
aapje::route('GET', '/get-params', function () {
    $params = aapje::request()->getParams();
    aapje::response()->echo($params);
});

/**
 * 17. Get All POST Parameters
 *
 * Retrieves and returns all POST parameters from the request.
 */
aapje::route('POST', '/post-params', function () {
    $params = aapje::request()->postParams();
    aapje::response()->echo($params);
});

/**
 * 18. Get Client IP and User Agent
 *
 * Retrieves and returns the client's IP address and User-Agent string.
 */
aapje::route('GET', '/client-info', function () {
    $ip = aapje::request()->ip();
    $userAgent = aapje::request()->userAgent();
    aapje::response()->echo(['ip' => $ip, 'user_agent' => $userAgent]);
});

/**
 * 19. Set a Cookie
 *
 * Sets a cookie named 'example_cookie' with a value and options.
 */
aapje::route('GET', '/set-cookie', function () {
    aapje::response()->cookie('example_cookie', 'cookie_value', [
        'expires' => time() + 3600, // 1 hour
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ])->echo(['message' => 'Cookie set']);
});

/**
 * 20. Use Helper Functions: getFile and putFile
 *
 * Demonstrates using helper functions to read from and write to files.
 * Both throw an Exception when the file cannot be read or written.
 */
aapje::route('GET', '/file/read', function () {
    $filePath = __DIR__ . '/sample.txt';
    if (file_exists($filePath)) {
        $content = Helpers::getFile($filePath);
        aapje::response()->echo(['file_content' => $content]);
    } else {
        aapje::response()->statusCode(404)->echo(['error' => 'File not found']);
    }
});

aapje::route('POST', '/file/write', function () {
    $input = aapje::request()->input();
    $content = $input['content'] ?? '';

    if (!is_string($content) || $content === '') {
        aapje::response()->statusCode(400)->echo(['error' => 'No content provided']);
    }

    $filePath = __DIR__ . '/sample.txt';
    try {
        Helpers::putFile($filePath, $content);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to write file']);
    }
    aapje::response()->echo(['message' => 'File written successfully']);
});

/**
 * 21. Set Multiple Custom Headers
 *
 * Demonstrates setting multiple custom headers in the response.
 */
aapje::route('GET', '/custom-headers', function () {
    aapje::response()->headers([
        'X-Custom-Header-1' => 'Value1',
        'X-Custom-Header-2' => 'Value2',
    ])->echo(['message' => 'Custom headers set']);
});

/**
 * 22. Handle Different Content Types
 *
 * Responds with XML, plain text and JSON.
 */
aapje::route('GET', '/data/xml', function () {
    $users = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];

    // Convert array to XML
    $xml = new SimpleXMLElement('<response/>');
    foreach ($users as $user) {
        $node = $xml->addChild('user');
        $node->addChild('id', (string) $user['id']);
        $node->addChild('name', Helpers::esc($user['name']));
    }

    aapje::response()->header('Content-Type', 'application/xml')->echo($xml->asXML(), false);
});

aapje::route('GET', '/text', function () {
    aapje::response()->header('Content-Type', 'text/plain')->echo('This is plain text content.', false);
});

aapje::route('GET', '/json', function () {
    aapje::response()->echo(['message' => 'This is JSON content.']);
});

/**
 * 23. Redirect to Another URL
 *
 * Demonstrates how to redirect the client to another URL.
 */
aapje::route('GET', '/redirect', function () {
    aapje::response()->header('Location', 'https://www.example.com')->statusCode(302)->echo(null, false);
});

// Run the application
aapje::run();
