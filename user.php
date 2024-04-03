<?php
require_once __DIR__ . '/database.php';
require_once  __DIR__ . '/config/actions.php';


function getSuperiors(): ?array
{
    // get admin and gods
    return Database::getInstance()->query('SELECT * FROM '. DB_TABLE_USERS
        .' WHERE ' . DB_USER_MODE . '=' . GOD_USER . ' OR ' . DB_USER_MODE . '=' . ADMIN_USER);
}

function isSuperior(&$user): bool {
    return $user[DB_USER_MODE] == ADMIN_USER || $user[DB_USER_MODE] == GOD_USER;
}

function getCertainUsers(int $user_mode): ?array {
    return Database::getInstance()->query('SELECT * FROM '. DB_TABLE_USERS
        .' WHERE ' . DB_USER_MODE . '=:mode', array('mode' => $user_mode));
}

function getTeacherGroup($teacher_id): ?array {
    return Database::getInstance()->query('SELECT * FROM '. DB_TABLE_USERS
        .' WHERE ' . DB_ITEM_TEACHER_ID . '=:teacher_id', array('teacher_id' => $teacher_id));
}

function getUser($id, string $username=null): array{
    $db = Database::getInstance();
    $user = $db->query('SELECT * FROM '. DB_TABLE_USERS .' WHERE ' . DB_ITEM_ID . '=:id LIMIT 1',
        array('id' => $id));
    if(count($user)) {
        if($username) $username = "@$username";

        if($username != ($user[DB_USER_USERNAME] ?? '')) {
            updateUserField($id, $username, DB_USER_USERNAME);
            $user[0][DB_USER_USERNAME] = $username;
        }
    
        return $user[0];
    }

    // its a new user
    $fields = DB_ITEM_ID;
    $params = array('id' => $id);
    $values = ':id';
    if($username) {
        $fields .= ',' . DB_USER_USERNAME;
        $params['username'] = $username;
        $values .= ", :username";
    }
    $db->insert('INSERT INTO '. DB_TABLE_USERS . " ($fields) VALUES ($values)", $params);
    // TODO: error check?
    return array(DB_ITEM_ID => $id, DB_USER_MODE => NORMAL_USER, DB_USER_ACTION => ACTION_NONE,
        DB_USER_CACHE => null, DB_USER_USERNAME => $username);
}

function getAllUsers(bool $get_all_columns=false): array {
    $columns = !$get_all_columns ? "(" . DB_ITEM_ID . ")" : "*";
    return Database::getInstance()->query("SELECT $columns FROM " . DB_TABLE_USERS, null, !$get_all_columns ? DB_ITEM_ID : null);
}

function updateAction($id, int $action, bool $reset_cache = false) {
    $query = 'UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_ACTION . '=:action';
    if($reset_cache)
        $query .= ', ' . DB_USER_CACHE . '=NULL';
    return Database::getInstance()->update("$query WHERE " . DB_ITEM_ID . '=:id',
        array('id' => $id, 'action' => $action));
}

function updateUserMode($id, int $mode, $teacher_id=null, ?string $predefined_name=null, $course_id=null) {
    $query = 'UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_MODE . '=:mode';
    $params = array('id' => $id, 'mode' => $mode);
    if($teacher_id) {
        $params['teacher_id'] = $teacher_id;
        $query .= ", " . DB_ITEM_TEACHER_ID . "=:teacher_id";
        if($predefined_name) {
            $query .= ", " . DB_ITEM_NAME . "=:name";
            $params['name'] = $predefined_name;
        }
    }
    // TODO: what about course_id ?hmmm...
    $query .= 'WHERE ' . DB_ITEM_ID . '=:id';
    return Database::getInstance()->update($query, $params);
}

function downgradeUser($id) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_MODE . '=' . NORMAL_USER
            . ',' . DB_ITEM_TEACHER_ID . '=NULL,' . DB_ITEM_NAME . '=NULL WHERE ' . DB_ITEM_ID . '=:id', array('id' => $id));

}

function updateActionCache($id, $cache) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_CACHE . '=:cache WHERE ' . DB_ITEM_ID . '=:id',
        array('id' => $id, 'cache' => $cache));
}

function setActionAndCache($id, int $action, $cache) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . ' SET ' . DB_USER_ACTION . '=:action,'
            . DB_USER_CACHE . '=:cache WHERE ' . DB_ITEM_ID . '=:id',
        array('id' => $id, 'action' => $action, 'cache' => $cache));
}

function resetAction($id): bool
{
    return updateAction($id, ACTION_NONE, true);
}

function saveMessage($sender_id, $message_id, ?int $target_group=null) {
    $fields = implode(',', [DB_ITEM_ID, DB_MESSAGES_SENDER_ID, DB_MESSAGES_TARGET_GROUP]);
    return Database::getInstance()->insert('INSERT INTO '. DB_TABLE_MESSAGES
        . " ($fields) VALUES (:message_id, :sender_id, :target)", array(
            'message_id' => $message_id, 'sender_id' => $sender_id, 'target' => $target_group
    ));
}

function markMessageAsAnswered($message_id) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_MESSAGES . ' SET ' . DB_MESSAGES_ANSWERED . '=1 WHERE ' . DB_ITEM_ID . '=:id',
        array('id' => $message_id));
}

function isMessageAnswered($message_id): bool
{
    $msg = Database::getInstance()->query('SELECT ' . DB_MESSAGES_ANSWERED . ' FROM '. DB_TABLE_MESSAGES
         .' WHERE ' . DB_ITEM_ID . '=:id LIMIT 1', array('id' => $message_id), DB_MESSAGES_ANSWERED);
    return count($msg) > 0 && $msg[0];
}

function getMessage($message_id) {
    $msg = Database::getInstance()->query('SELECT * FROM '. DB_TABLE_MESSAGES
         .' WHERE ' . DB_ITEM_ID . '=:id LIMIT 1', array('id' => $message_id));
    return count($msg) ? $msg[0] : null;
}

function updateUserField($id, string $value, $field=DB_ITEM_NAME) {
    $value_tag = $value ? ":value" : "NULL";
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_USERS . " SET $field=$value_tag WHERE " . DB_ITEM_ID . '=:id',
        array('id' => $id, 'value' => $value));
}

function findByUsername(string &$username): ?string {
    if($username && strlen($username)) {
        if($username[0] != '@') $username = "@$username";
        $user = Database::getInstance()->query(
                'SELECT ' . DB_ITEM_ID . ' FROM '. DB_TABLE_USERS .' WHERE ' . DB_USER_USERNAME . '=:username LIMIT 1',
            array('username' => $username), DB_ITEM_ID);
        return count($user) ? $user[0] : null;
    }
    return null;
}

function isInFavoritesList($user_id, &$categories): bool {
    $fav = Database::getInstance()->query('SELECT ' . DB_ITEM_ID . ' FROM '. DB_TABLE_FAVORITES
        . ' WHERE ' . DB_ITEM_USER_ID . '=:user_id AND ' . DB_ITEM_TEACHER_ID 
        . '=:teacher_id AND ' . DB_ITEM_COURSE_ID . '=:course_id LIMIT 1', array(
            'user_id' => $user_id, 'teacher_id' => $categories[DB_ITEM_TEACHER_ID], 'course_id' => $categories[DB_ITEM_COURSE_ID]
        ), DB_ITEM_ID);
    return count($fav) > 0 && $fav[0];
}

function updateFavoritesList($user_id, array &$categories, bool $remove=false) {
    if($remove) {
        return Database::getInstance()->insert('DELETE FROM '. DB_TABLE_FAVORITES
            . ' WHERE ' . DB_ITEM_USER_ID . '=:user_id AND ' . DB_ITEM_TEACHER_ID 
            . '=:teacher_id AND ' . DB_ITEM_COURSE_ID . '=:course_id LIMIT 1', array(
                'user_id' => $user_id, 'teacher_id' => $categories[DB_ITEM_TEACHER_ID], 'course_id' => $categories[DB_ITEM_COURSE_ID]
        ));
    }
    $fields = implode(',', [DB_ITEM_USER_ID, DB_ITEM_TEACHER_ID, DB_ITEM_COURSE_ID]);
    return Database::getInstance()->insert('INSERT INTO '. DB_TABLE_FAVORITES
        . " ($fields) VALUES (:user_id, :teacher_id, :course_id)", array(
            'user_id' => $user_id, 'teacher_id' => $categories[DB_ITEM_TEACHER_ID], 'course_id' => $categories[DB_ITEM_COURSE_ID]
    ));
}

function getFavoritesList($user_id): ?array
{
    /* SELECT favorites.*, teachers.name as teacher, courses.name as course FROM `favorites`
        JOIN courses ON favorites.course_id=courses.id
        JOIN teachers ON favorites.teacher_id=teachers.id WHERE 1 */

    return Database::getInstance()->query('SELECT ' . DB_TABLE_FAVORITES . '.*, ' . DB_TABLE_TEACHERS . '.' . DB_ITEM_NAME
        . ' as teacher, ' . DB_TABLE_COURSES . '.' . DB_ITEM_NAME . ' as course FROM ' . DB_TABLE_FAVORITES
        . ' JOIN ' . DB_TABLE_COURSES . ' ON ' . DB_TABLE_FAVORITES . '.' . DB_ITEM_COURSE_ID . '=' . DB_TABLE_COURSES
        . '.' . DB_ITEM_ID . ' JOIN ' . DB_TABLE_TEACHERS . ' ON ' . DB_TABLE_FAVORITES . '.' . DB_ITEM_TEACHER_ID
        . '=' . DB_TABLE_TEACHERS . '.' . DB_ITEM_ID . ' WHERE ' . DB_ITEM_USER_ID . '=:user_id', array('user_id' => $user_id));
}