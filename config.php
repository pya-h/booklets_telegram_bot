<?php
$env = parse_ini_file('.env');

defined('DB_HOST') or define('DB_HOST', 'localhost');
defined('DB_USER') or define('DB_USER', "$env[DB_USER]");
defined('DB_PASSWORD') or define('DB_PASSWORD', "$env[DB_PASSWORD]");
defined('DB_NAME') or define('DB_NAME', "$env[DB_NAME]");
defined('TOKEN') or define('TOKEN', "$env[TOKEN]");

//database: god mode
defined('GOD_NAME') or define('GOD_NAME', "$env[GOD_NAME]");
defined('GOD_SECRET') or define('GOD_SECRET', "$env[GOD_SECRET]");