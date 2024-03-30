<?php
require_once __DIR__ . '/config/credentials.php';
require_once __DIR__ . '/bot.php';

$update = getUpdate();
// check user is a member in specified channels
if ($update != null) {
    handleUpdates($update);
}
