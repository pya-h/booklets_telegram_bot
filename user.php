<?php
require_once './database.php';

// admin actions:
defined('ACTION_NONE') or define('ACTION_NONE', 0);
defined('ACTION_DOWNLOAD_BOOKLET') or define('ACTION_DOWNLOAD_BOOKLET', 1);
defined('ACTION_UPLOAD_BOOKLET') or define('ACTION_UPLOAD_BOOKLET', 2);
defined('ACTION_SENDING_BOOKLET_FILE') or define('ACTION_SENDING_BOOKLET_FILE', 3);
defined('ACTION_ADD_COURSE') or define('ACTION_ADD_COURSE', 4);
defined('ACTION_ADD_TEACHER') or define('ACTION_ADD_TEACHER', 5); // EXTRA ACTION VALUE (TEACHER ID/COURSE_ID/ETC.)
defined('ACTION_WHISPER_GODS_NAME') or define('ACTION_WHISPER_GODS_NAME', 6); 
defined('ACTION_WHISPER_GODS_SECRET') or define('ACTION_WHISPER_GODS_SECRET', 7);
defined('ACTION_SELECT_BOOKLET_TO_GET') or define('ACTION_SELECT_BOOKLET_TO_GET', 8);
defined('ACTION_WRITE_MESSAGE_TO_ADMIN') or define('ACTION_WRITE_MESSAGE_TO_ADMIN', 9);
defined('ACTION_WRITE_REPLY_TO_USER') or define('ACTION_WRITE_REPLY_TO_USER', 10);
defined('ACTION_ADD_ADMIN') or define('ACTION_ADD_ADMIN', 11);
defined('ACTION_ASSIGN_USER_NAME') or define('ACTION_ASSIGN_USER_NAME', 12);
defined('ACTION_SET_BOOKLET_CAPTION') or define('ACTION_SET_BOOKLET_CAPTION', 13);
defined('ACTION_EDIT_BOOKLET_CAPTION') or define('ACTION_EDIT_BOOKLET_CAPTION', 14);
defined('ACTION_SELECT_BOOKLET_TO_EDIT') or define('ACTION_SELECT_BOOKLET_TO_EDIT', 15);
defined('ACTION_EDIT_BOOKLET_FILE') or define('ACTION_EDIT_BOOKLET_FILE', 17);
defined('ACTION_SEND_POST_TO_CHANNEL') or define('ACTION_SEND_POST_TO_CHANNEL', 16);

function getSuperiors(): ?array
{
    // get admin and gods
    return Database::getInstance()->query('SELECT * FROM '. DB_TABLE_USERS 
        .' WHERE ' . DB_USER_MODE . '=' . GOD_USER . ' OR ' . DB_USER_MODE . '=' . ADMIN_USER);
}

function getCertainUsers(int $user_mode): ?array {
    return Database::getInstance()->query('SELECT * FROM '. DB_TABLE_USERS 
        .' WHERE ' . DB_USER_MODE . '=:mode', array('mode' => $user_mode));
}

function getUser($id): array{
    $db = Database::getInstance();
    $user = $db->query('SELECT * FROM '. DB_TABLE_USERS .' WHERE ' . DB_USER_ID . '=:id LIMIT 1', 
        array('id' => $id));

    if(count($user) == 1)
        return $user[0];

    $db->insert('INSERT INTO '. DB_TABLE_USERS .' (' . DB_USER_ID . ') VALUES (:id)', array(
        'id' => $id
    ));
    // TODO: error check?
    return array(DB_USER_ID => $id, DB_USER_MODE => NORMAL_USER, DB_USER_ACTION => ACTION_NONE, DB_USER_ACTION_CACHE => null);
}

function updateAction($id, int $action, bool $reset_cache = false) {
    $query = 'UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_ACTION . '=:action';
    if($reset_cache)
        $query .= ', ' . DB_USER_ACTION_CACHE . '=NULL';
    return Database::getInstance()->update("$query WHERE " . DB_USER_ID . '=:id',
        array('id' => $id, 'action' => $action));
}


function updateUserMode($id, int $mode) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_MODE . '=:mode WHERE ' . DB_USER_ID . '=:id',
        array('id' => $id, 'mode' => $mode));
}

function updateActionCache($id, $cache) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_ACTION_CACHE . '=:cache WHERE ' . DB_USER_ID . '=:id',
        array('id' => $id, 'cache' => $cache));
}

function setActionAndCache($id, int $action, $cache) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_ACTION . '=:action,'
            . DB_USER_ACTION_CACHE . '=:cache WHERE ' . DB_USER_ID . '=:id',
        array('id' => $id, 'action' => $action, 'cache' => $cache));
}

function resetAction($id): bool
{
    return updateAction($id, ACTION_NONE, true);
}

function saveMessage($sender_id, $message_id) {
    Database::getInstance()->insert('INSERT INTO '. DB_TABLE_MESSAGES 
        . ' (' . DB_ITEM_ID . ', ' . DB_MESSAGES_SENDER_ID . ') VALUES (:message_id, :sender_id)', array(
            MESSAGE_ID_TAG => $message_id, 'sender_id' => $sender_id
    ));
}

function markMessageAsAnswered($message_id) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_MESSAGES . ' SET ' . DB_MESSAGES_ANSWERED . '=1 WHERE ' . DB_ITEM_ID . '=:id',
        array('id' => $message_id));
}

function isMessageAnswered($message_id): bool
{
    $msg = Database::getInstance()->query('SELECT (' . DB_MESSAGES_ANSWERED . ') FROM '. DB_TABLE_MESSAGES
         .' WHERE ' . DB_ITEM_ID . '=:id LIMIT 1', array('id' => $message_id), DB_MESSAGES_ANSWERED);
    return count($msg) > 0 && $msg[0];
}

function getMessage($message_id) {
    $msg = Database::getInstance()->query('SELECT * FROM '. DB_TABLE_MESSAGES
         .' WHERE ' . DB_ITEM_ID . '=:id LIMIT 1', array('id' => $message_id));
    return count($msg) ? $msg[0] : null;
}

function assignUserName($id, string &$name) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_ITEM_NAME . '=:name WHERE ' . DB_USER_ID . '=:id',
        array('id' => $id, 'name' => $name));
}