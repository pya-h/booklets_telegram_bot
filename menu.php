<?php
require_once './database.php';
require_once './user.php';

defined('MAX_COLUMN_LENGTH') or define('MAX_COLUMN_LENGTH', 30);
// UI constants
defined('CMD_ADD_COURSE') or define('CMD_ADD_COURSE', 'افزودن درس');
defined('CMD_ADD_TEACHER') or define('CMD_ADD_TEACHER', 'افزودن استاد');
defined('CMD_UPLOAD_BOOKLET') or define('CMD_UPLOAD_BOOKLET', 'آپلود جزوه');
defined('CMD_STATISTICS') or define('CMD_STATISTICS', 'آمار ربات');
defined('CMD_ADD_ADMIN') or define('CMD_ADD_ADMIN', 'افزودن ادمین');
defined('CMD_REMOVE_ADMIN') or define('CMD_REMOVE_ADMIN', 'حذف ادمین');

defined('CMD_DOWNLOAD_BY_COURSE') or define('CMD_DOWNLOAD_BY_COURSE', 'جست و جو بر اساس نام درس📖');
defined('CMD_DOWNLOAD_BY_TEACHER') or define('CMD_DOWNLOAD_BY_TEACHER', 'جست و جو بر اساس نام استاد👨‍🏫');
defined('CMD_DOWNLOAD_BOOKLET') or define('CMD_DOWNLOAD_BOOKLET', 'دانلود جزوه📖');
defined('CMD_MESSAGE_TO_ADMIN') or define('CMD_MESSAGE_TO_ADMIN', 'پشتیبانی 💬');

defined('CMD_MAIN_MENU') or define('CMD_MAIN_MENU', 'بازگشت به منو ↪️');

defined('CMD_GOD_ACCESS') or define('CMD_GOD_ACCESS', '/godAccess');

function alignButtons($items, $related_column, $data_prefix): array
{
    $buttons = array(array()); // an inline keyboard
    $current_row = 0;
    $column_length = 0;
    foreach($items as $item) {
        array_unshift($buttons[$current_row], array(
            TEXT_TAG => $item[$related_column] ? $item[$related_column] : $item[DB_ITEM_ID],
            CALLBACK_DATA => $data_prefix . $item[DB_ITEM_ID]
        ));
        // buttons callback_data is as: type/id, type determines whether it's a course or a teacher;
        $column_length += strlen($item[$related_column]);
        if($column_length > MAX_COLUMN_LENGTH) {
            $column_length = 0;
            $current_row++;
            $buttons[] = array();
        }
    }
    return $buttons;
}

function createMenu($table_name, $previous_data = null, $filter_query = null, $filter_index = null): ?array
{
    $query = 'SELECT * FROM ' . $table_name;
    if(!$previous_data && $filter_query && !$filter_index) // this condition just happens for remove admin menu
        $query .= ' WHERE ' . $filter_query;
    $items = Database::getInstance()->query($query);

    $data_prefix = $table_name . RELATED_DATA_SEPARATOR;
    if($previous_data) {
        $data_prefix = $previous_data . DATA_JOIN_SIGN . $data_prefix;

        if($filter_query && $filter_index) {
            $booklets = Database::getInstance()->query(
                "SELECT $filter_index FROM " . DB_TABLE_BOOKLETS . " WHERE $filter_query", null, $filter_index);
            $items = array_filter($items, function($item) use ($booklets) {
                return in_array($item[DB_ITEM_ID], $booklets);
            });
            if(!count($items))
                return null;
        }
    }

    return array(INLINE_KEYBOARD => alignButtons($items, DB_ITEM_NAME, $data_prefix));
}

function createIndexMenu($booklets, $by_caption = false): array
{
    $menu_keyboard = alignButtons($booklets, !$by_caption ? DB_BOOKLETS_INDEX : DB_BOOKLETS_CAPTION, DB_ITEM_ID . '=');
    $menu_keyboard[] = array(
        array(TEXT_TAG => 'همه', CALLBACK_DATA => DB_BOOKLETS_TEACHER_ID . '=' . $booklets[0][DB_BOOKLETS_TEACHER_ID] . ' AND ' . DB_BOOKLETS_COURSE_ID . '=' . $booklets[0][DB_BOOKLETS_COURSE_ID])
    );
    return array(INLINE_KEYBOARD => $menu_keyboard);
}

function getMainMenu($user_mode): array
{
    $keyboard = array('resize_keyboard' => true, 'one_time_keyboard' => true,
            'keyboard' => $user_mode == NORMAL_USER
                ? array(array(CMD_MESSAGE_TO_ADMIN, CMD_DOWNLOAD_BOOKLET))
                : array(
                    array(CMD_DOWNLOAD_BOOKLET, CMD_UPLOAD_BOOKLET), // casual keyboard
                    array(CMD_ADD_COURSE, CMD_ADD_TEACHER),
                    array(CMD_STATISTICS)
                ));
    if($user_mode == GOD_USER)
        $keyboard['keyboard'][] = array(CMD_REMOVE_ADMIN, CMD_ADD_ADMIN);
    return $keyboard;
}

function backToMainMenuKeyboard(): array
{
    return array('resize_keyboard' => true, 'one_time_keyboard' => true,
        'keyboard' => array(
            array(CMD_MAIN_MENU)
        )
    );
}