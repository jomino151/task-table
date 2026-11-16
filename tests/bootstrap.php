<?php
/**
 * PHPUnit bootstrap: loads the plugin's pure-logic files without WordPress.
 * WordPress functions are stubbed per test with Brain Monkey.
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';
require __DIR__ . '/unit/FakeWpdb.php';

define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );

// Brain Monkey defines add_action() and friends; the file registers its hooks at load time.
Brain\Monkey\setUp();
require dirname( __DIR__ ) . '/src/api.php';
Brain\Monkey\tearDown();
