<?php
/**
 * aapje.php examples
 * 
 * This is an example file demonstrating how you can use aapje.
 *
 */
require 'aapje.php';

// Database Configuration
aapje::setDbConfig([
    'host' => '127.0.0.1',
    'dbname' => 'aapje_1',
    'user' => 'aapje',
    'password' => 'password',
]);

// Routes

// Home route
aapje::route('GET', '/', function () {
    aapje::response()->echo('Hello, world! On v' . aapje::$version);
});

// Get all users
aapje::route('GET', '/users', function () {
    $users = aapje::selectAll('users', '*');
    aapje::response()->echo($users);
});

// Get users' emails
aapje::route('GET', '/users/mails', function () {
    $users = aapje::selectAll('users', ['id', 'email']);
    aapje::response()->echo($users);
});

// Get user by ID
aapje::route('GET', '/user/@id', function ($id) {
    $user = aapje::select('users', '*', ['id' => $id]);
    aapje::response()->echo($user);
});

// Get last 5 users ordered by ID
aapje::route('GET', '/users/limited', function () {
    $users = aapje::selectAll('users', '*', [], ['limit' => 5, 'orderBy' => 'id', 'sort' => 'DESC']);
    aapje::response()->echo($users);
});

// Create a new user
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

    $id = aapje::insert('users', $data);
    aapje::response()->echo(['created_user_id' => $id]);
});

// Update user
aapje::route('PUT', '/users/@id', function ($id) {
    $user = aapje::select('users', '*', ['id' => $id]);

    if(!$user) {
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

    aapje::update('users', $data, ['id' => $id]);
    aapje::response()->echo(['updated_user_id' => $id]);
});

// Delete user
aapje::route('DELETE', '/users/@id', function ($id) {
    $authHeader = aapje::request()->header('Authentication');
    if ($authHeader !== 'exampletoken') {
        aapje::response()->statusCode(403)->echo(['error' => 'Unauthorized']);
    }
    aapje::delete('users', ['id' => $id]);
    aapje::response()->echo(['deleted_user_id' => $id]);
});

// Search users
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

    $results = aapje::selectAll('users', ['id'], $conditions);
    aapje::response()->echo($results);
});

// Upload file
aapje::route('POST', '/users/upload', function () {
    $file = aapje::request()->file('file');
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $file['name']);
        aapje::response()->echo(['uploaded_file' => $file['name']]);
    } else {
        aapje::response()->statusCode(400)->echo(['error' => 'No file uploaded']);
    }
});

// Set Content-Type to HTML and echo an HTML page
aapje::route('GET', '/html', function () {
    aapje::response()->header('Content-Type', 'text/html')->echo('<h1>Hello, world!</h1>');
});

// Get all request headers
aapje::route('GET', '/headers', function () {
    $headers = aapje::request()->headers();
    aapje::response()->echo($headers);
});

// Get all cookies
aapje::route('GET', '/cookies', function () {
    $cookies = aapje::request()->cookies();
    aapje::response()->echo($cookies);
});

// Get all GET parameters
aapje::route('GET', '/get-params', function () {
    $params = aapje::request()->getParams();
    aapje::response()->echo($params);
});

// Get all POST parameters
aapje::route('POST', '/post-params', function () {
    $params = aapje::request()->postParams();
    aapje::response()->echo($params);
});

// Get user IP and User Agent
aapje::route('GET', '/client-info', function () {
    $ip = aapje::request()->ip();
    $userAgent = aapje::request()->userAgent();
    aapje::response()->echo(['ip' => $ip, 'user_agent' => $userAgent]);
});

// Set a cookie
aapje::route('GET', '/set-cookie', function () {
    aapje::response()->cookie('example_cookie', 'cookie_value', [
        'expires' => time() + 3600,
        'path' => '/',
        'httponly' => true,
    ])->echo(['message' => 'Cookie set']);
});

// Upload multiple files
aapje::route('POST', '/upload-files', function () {
    $files = aapje::request()->files();
    $uploadedFiles = [];

    foreach ($files as $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $file['name']);
            $uploadedFiles[] = $file['name'];
        }
    }

    if (!empty($uploadedFiles)) {
        aapje::response()->echo(['uploaded_files' => $uploadedFiles]);
    } else {
        aapje::response()->statusCode(400)->echo(['error' => 'No files uploaded']);
    }
});

// Run the app
aapje::run();