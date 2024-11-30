<?php
/**
 * aapje.php Examples
 * 
 * This is an example file demonstrating how you can use aapje.php.
 * It covers routing, middleware, CORS, database operations, helper functions, 
 * setting custom headers, handling different content types, and more.
 *
 * Ensure that `aapje.php` is in the same directory or adjust the path accordingly.
 */

require 'aapje.php';

// Global Configuration
aapje::setConfig([
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
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];
    $ip = $request->ip();
    error_log("[$method] $uri from $ip");
}

aapje::middleware('loggingMiddleware');

// Example: Authentication Middleware
function authMiddleware($request, $response) {
    $authHeader = $request->header('Authorization');
    if ($authHeader !== 'Bearer exampletoken') {
        $response->statusCode(401)->echo(['error' => 'Unauthorized']);
    }
}

aapje::middleware('authMiddleware');

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
 */
aapje::route('GET', '/users', function () {
    $users = aapje::selectAll('users', '*', [], [
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
 * 4. Get User by ID
 * 
 * Retrieves a single user based on the provided ID.
 */
aapje::route('GET', '/user/@id', function ($id) {
    $user = aapje::select('users', '*', ['id' => $id]);
    if ($user) {
        aapje::response()->echo($user);
    } else {
        aapje::response()->statusCode(404)->echo(['error' => 'User not found']);
    }
});

/**
 * 5. Get Last 5 Users Ordered by ID
 * 
 * Retrieves the last 5 users ordered in descending order by ID.
 */
aapje::route('GET', '/users/last5', function () {
    $users = aapje::selectAll('users', '*', [], [
        'orderBy' => 'id',
        'sort' => 'DESC',
        'limit' => 5
    ]);
    aapje::response()->echo($users);
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
    if (!$data['email'] || !$data['name']) {
        aapje::response()->statusCode(400)->echo(['error' => 'Email and name are required']);
    }

    try {
        $id = aapje::insert('users', $data);
        aapje::response()->statusCode(201)->echo(['created_user_id' => $id]);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to create user']);
    }
});

/**
 * 7. Update a User
 * 
 * Updates the 'name' and/or 'email' of a user based on ID.
 */
aapje::route('PUT', '/users/@id', function ($id) {
    $user = aapje::select('users', '*', ['id' => $id]);

    if (!$user) {
        aapje::response()->statusCode(404)->echo(['error' => 'User not found']);
    }

    $input = aapje::request()->input();

    // Warning: Directly passing user $input to $data can introduce security issues
    // Bad practice:
    // aapje::update('users', $input, ['id' => $id]);

    // Example of security issue:
    // If a user includes 'id' in $input, they could attempt to change the ID of the user

    // Instead, only allow specific fields to be updated
    $allowedFields = ['email', 'name'];
    $data = array_intersect_key($input, array_flip($allowedFields));

    if (empty($data)) {
        aapje::response()->statusCode(400)->echo(['error' => 'No valid fields to update']);
    }

    try {
        aapje::update('users', $data, ['id' => $id]);
        aapje::response()->echo(['updated_user_id' => $id]);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to update user']);
    }
});

/**
 * 8. Delete a User
 * 
 * Deletes a user based on the provided ID. Requires authentication.
 */
aapje::route('DELETE', '/users/@id', function ($id) {
    try {
        aapje::delete('users', ['id' => $id]);
        aapje::response()->echo(['deleted_user_id' => $id]);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to delete user']);
    }
});

/**
 * 9. Search Users
 * 
 * Searches for users based on 'name' and/or 'email' criteria.
 */
aapje::route('POST', '/users/search', function () {
    $input = aapje::request()->input();
    $conditions = [];

    if (!empty($input['name'])) {
        $conditions['name'] = $input['name'];
    }
    if (!empty($input['email'])) {
        $conditions['email'] = $input['email'];
    }

    if (empty($conditions)) {
        aapje::response()->statusCode(400)->echo(['error' => 'Please provide search criteria']);
    }

    try {
        $results = aapje::selectAll('users', ['id', 'name', 'email'], $conditions, [
            'orderBy' => 'name',
            'sort' => 'ASC',
            'limit' => 20
        ]);
        aapje::response()->echo($results);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Search failed']);
    }
});

/**
 * 10. Upload a Single File
 * 
 * Handles file uploads and stores them in the 'uploads' directory.
 */
aapje::route('POST', '/users/upload', function () {
    $file = aapje::request()->file('file');
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filePath = $uploadDir . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            aapje::response()->echo(['uploaded_file' => $file['name']]);
        } else {
            aapje::response()->statusCode(500)->echo(['error' => 'Failed to move uploaded file']);
        }
    } else {
        aapje::response()->statusCode(400)->echo(['error' => 'No file uploaded or upload error']);
    }
});

/**
 * 11. Set Content-Type to HTML and Echo an HTML Page
 * 
 * Demonstrates setting custom headers and handling different content types.
 */
aapje::route('GET', '/html', function () {
    aapje::response()->header('Content-Type', 'text/html')->echo('<h1>Hello, world!</h1>');
});

/**
 * 12. Get All Request Headers
 * 
 * Retrieves and returns all HTTP request headers.
 */
aapje::route('GET', '/headers', function () {
    $headers = aapje::request()->headers();
    aapje::response()->echo($headers);
});

/**
 * 13. Get All Cookies
 * 
 * Retrieves and returns all cookies sent with the request.
 */
aapje::route('GET', '/cookies', function () {
    $cookies = aapje::request()->cookies();
    aapje::response()->echo($cookies);
});

/**
 * 14. Get All GET Parameters
 * 
 * Retrieves and returns all GET parameters from the request.
 */
aapje::route('GET', '/get-params', function () {
    $params = aapje::request()->getParams();
    aapje::response()->echo($params);
});

/**
 * 15. Get All POST Parameters
 * 
 * Retrieves and returns all POST parameters from the request.
 */
aapje::route('POST', '/post-params', function () {
    $params = aapje::request()->postParams();
    aapje::response()->echo($params);
});

/**
 * 16. Get Client IP and User Agent
 * 
 * Retrieves and returns the client's IP address and User-Agent string.
 */
aapje::route('GET', '/client-info', function () {
    $ip = aapje::request()->ip();
    $userAgent = aapje::request()->userAgent();
    aapje::response()->echo(['ip' => $ip, 'user_agent' => $userAgent]);
});

/**
 * 17. Set a Cookie
 * 
 * Sets a cookie named 'example_cookie' with a value and options.
 */
aapje::route('GET', '/set-cookie', function () {
    aapje::response()->cookie('example_cookie', 'cookie_value', [
        'expires' => time() + 3600, // 1 hour
        'path' => '/',
        'httponly' => true,
    ])->echo(['message' => 'Cookie set']);
});

/**
 * 18. Upload Multiple Files
 * 
 * Handles multiple file uploads and stores them in the 'uploads' directory.
 */
aapje::route('POST', '/upload-files', function () {
    $files = aapje::request()->files();
    $uploadedFiles = [];
    $uploadDir = __DIR__ . '/uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    foreach ($files as $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $filePath = $uploadDir . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $uploadedFiles[] = $file['name'];
            }
        }
    }

    if (!empty($uploadedFiles)) {
        aapje::response()->echo(['uploaded_files' => $uploadedFiles]);
    } else {
        aapje::response()->statusCode(400)->echo(['error' => 'No files uploaded']);
    }
});

/**
 * 19. Use Helper Functions: getFile and putFile
 * 
 * Demonstrates using helper functions to read from and write to files.
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

    if (empty($content)) {
        aapje::response()->statusCode(400)->echo(['error' => 'No content provided']);
    }

    $filePath = __DIR__ . '/sample.txt';
    try {
        Helpers::putFile($filePath, $content);
        aapje::response()->echo(['message' => 'File written successfully']);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to write file']);
    }
});

/**
 * 20. Advanced Database Query: Search with Sorting and Limiting
 * 
 * Searches users by name or email with sorting and limiting the results.
 */
aapje::route('POST', '/users/advanced-search', function () {
    $input = aapje::request()->input();
    $conditions = [];

    if (!empty($input['name'])) {
        $conditions['name'] = $input['name'];
    }
    if (!empty($input['email'])) {
        $conditions['email'] = $input['email'];
    }

    if (empty($conditions)) {
        aapje::response()->statusCode(400)->echo(['error' => 'Please provide search criteria']);
    }

    try {
        $results = aapje::selectAll('users', ['id', 'name', 'email'], $conditions, [
            'orderBy' => 'created_at',
            'sort' => 'DESC',
            'limit' => 10
        ]);
        aapje::response()->echo($results);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Advanced search failed']);
    }
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
 * Responds with XML content type and returns data in XML format.
 */
aapje::route('GET', '/data/xml', function () {
    $data = [
        'users' => [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]
    ];

    // Convert array to XML
    $xml = new SimpleXMLElement('<response/>');
    array_walk_recursive($data, function($value, $key) use ($xml){
        $xml->addChild($key, $value);
    });
    $xmlContent = $xml->asXML();

    aapje::response()->header('Content-Type', 'application/xml')->echo($xmlContent, false);
});

/**
 * 23. Utilize Helper Function: esc()
 * 
 * Demonstrates using the 'esc' helper function to escape HTML.
 */
aapje::route('GET', '/escape-html', function () {
    $unsafeString = '<script>alert("XSS")</script>';
    $safeString = Helpers::esc($unsafeString);
    aapje::response()->echo(['escaped_string' => $safeString]);
});

/**
 * 24. Get Client Info and Set a Cookie
 * 
 * Retrieves client IP and User-Agent, sets a cookie, and returns the information.
 */
aapje::route('GET', '/client-info-cookie', function () {
    $ip = aapje::request()->ip();
    $userAgent = aapje::request()->userAgent();

    aapje::response()->cookie('client_ip', $ip, [
        'expires' => time() + 86400, // 1 day
        'path' => '/',
        'httponly' => true,
    ])->echo([
        'ip' => $ip,
        'user_agent' => $userAgent,
        'message' => 'Client IP has been set as a cookie'
    ]);
});

/**
 * 25. Read and Write Files Using Helper Functions
 * 
 * Demonstrates reading from and writing to files using helper functions.
 */
aapje::route('GET', '/file/read', function () {
    $filePath = __DIR__ . '/data.txt';
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

    if (empty($content)) {
        aapje::response()->statusCode(400)->echo(['error' => 'No content provided']);
    }

    $filePath = __DIR__ . '/data.txt';
    try {
        Helpers::putFile($filePath, $content);
        aapje::response()->echo(['message' => 'File written successfully']);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to write file']);
    }
});

/**
 * 26. Upload a File and Respond with Custom Header
 * 
 * Uploads a file and sets a custom header in the response.
 */
aapje::route('POST', '/upload-with-header', function () {
    $file = aapje::request()->file('file');
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filePath = $uploadDir . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            aapje::response()->header('X-Upload-Status', 'Success')->echo(['uploaded_file' => $file['name']]);
        } else {
            aapje::response()->statusCode(500)->echo(['error' => 'Failed to move uploaded file']);
        }
    } else {
        aapje::response()->statusCode(400)->echo(['error' => 'No file uploaded or upload error']);
    }
});

/**
 * 27. Advanced Database Insertion with Error Handling
 * 
 * Inserts a new user and handles potential database errors gracefully.
 */
aapje::route('POST', '/users/advanced-create', function () {
    $input = aapje::request()->input();

    // Validate input fields
    $data = [
        'email' => $input['email'] ?? null,
        'name'  => $input['name'] ?? null,
    ];

    // Check for required fields
    if (!$data['email'] || !$data['name']) {
        aapje::response()->statusCode(400)->echo(['error' => 'Email and name are required']);
    }

    try {
        $id = aapje::insert('users', $data);
        aapje::response()->statusCode(201)->echo(['created_user_id' => $id]);
    } catch (PDOException $e) {
        // Handle duplicate entry or other database-specific errors
        aapje::response()->statusCode(500)->echo(['error' => 'Database error: ' . $e->getMessage()]);
    }
});

/**
 * 28. Use Helper Function: getFile and putFile
 * 
 * Demonstrates reading from and writing to files using helper functions.
 */
aapje::route('GET', '/files/read', function () {
    $filePath = __DIR__ . '/example.txt';
    if (file_exists($filePath)) {
        $content = Helpers::getFile($filePath);
        aapje::response()->echo(['file_content' => $content]);
    } else {
        aapje::response()->statusCode(404)->echo(['error' => 'File not found']);
    }
});

aapje::route('POST', '/files/write', function () {
    $input = aapje::request()->input();
    $content = $input['content'] ?? '';

    if (empty($content)) {
        aapje::response()->statusCode(400)->echo(['error' => 'No content provided']);
    }

    $filePath = __DIR__ . '/example.txt';
    try {
        Helpers::putFile($filePath, $content);
        aapje::response()->echo(['message' => 'File written successfully']);
    } catch (Exception $e) {
        aapje::response()->statusCode(500)->echo(['error' => 'Failed to write file']);
    }
});

/**
 * 29. Respond with Different Content Types
 * 
 * Demonstrates responding with plain text and JSON.
 */
aapje::route('GET', '/text', function () {
    aapje::response()->header('Content-Type', 'text/plain')->echo('This is plain text content.', false);
});

aapje::route('GET', '/json', function () {
    aapje::response()->header('Content-Type', 'application/json')->echo(['message' => 'This is JSON content.']);
});

/**
 * 30. Redirect to Another URL
 * 
 * Demonstrates how to redirect the client to another URL.
 */
aapje::route('GET', '/redirect', function () {
    aapje::response()->header('Location', 'https://www.example.com')->statusCode(302)->echo(null, false);
});

// Run the application
aapje::run();
