<?php
/**
 * aapje.php index example
 * 
 * This is an example index file running aapje.
 * 
 * You can fork/clone the repository and start coding in this file.
 * Look at the readme or the examples.php file to get started.
 *
 */
require 'aapje.php';

// Home route
aapje::route('GET', '/', function () {
    aapje::response()->echo('Hello, world! On v' . aapje::$version);
});

// Run the app
aapje::run();