<?php
// Creates an isolated test database; never points at the configured application database.
putenv('DB_DATABASE=loyalty_terranova_test');
require dirname(__DIR__).'/bin/setup.php';
