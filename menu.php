<?php
require_once './database.php';
require_once './user.php';
require_once './telegram_api.php';

defined('MAX_COLUMN_LENGTH') or define('MAX_COLUMN_LENGTH', 30);
// UI constants
defined('CMD_ADD_COURSE') or define('CMD_ADD_COURSE', 'افزودن درس 📚');
defined('CMD_ADD_TEACHER') or define('CMD_ADD_TEACHER', 'افزودن استاد 👨‍🏫');
defined('CMD_UPLOAD_BOOKLET') or define('CMD_UPLOAD_BOOKLET', 'آپلود جزوه 📤');
defined('CMD_EDIT_BOOKLET') or define('CMD_EDIT_BOOKLET', 'ویرایش ✏️');
defined('CMD_EDIT_BOOKLET_CAPTION') or define('CMD_EDIT_BOOKLET_CAPTION', 'ویرایش کپشن 🪶');
defined('CMD_EDIT_BOOKLET_FILE') or define('CMD_EDIT_BOOKLET_FILE', 'ویرایش فایل 📝');
defined('CMD_STATISTICS') or define('CMD_STATISTICS', 'آمار 🧮');
defined('CMD_SEND_POST_TO_CHANNEL') or define('CMD_SEND_POST_TO_CHANNEL', 'ارسال پست 📯');
defined('CMD_ADD_ADMIN') or define('CMD_ADD_ADMIN', 'افزودن ادمین 💂‍♂️');
defined('CMD_REMOVE_ADMIN') or define('CMD_REMOVE_ADMIN', 'حذف ادمین ❌');
defined('CMD_LINK_TEACHER') or define('CMD_LINK_TEACHER', 'لینک اکانت استاد 🔗');
defined('CMD_INTRODUCE_TA') or define('CMD_INTRODUCE_TA', 'معرفی TA 👩‍🎓');
defined('CMD_REMOVE_TA') or define('CMD_REMOVE_TA', 'حذف TA ❌');

defined('CMD_DOWNLOAD_BY_COURSE') or define('CMD_DOWNLOAD_BY_COURSE', 'جست و جو بر اساس نام درس 📖');
defined('CMD_DOWNLOAD_BY_TEACHER') or define('CMD_DOWNLOAD_BY_TEACHER', 'جست و جو بر اساس نام استاد 👨‍🏫');
defined('CMD_DOWNLOAD_BOOKLET') or define('CMD_DOWNLOAD_BOOKLET', 'دانلود جزوه 📖');
defined('CMD_MESSAGE_TO_ADMIN') or define('CMD_MESSAGE_TO_ADMIN', 'پشتیبانی 💬');
defined('CMD_MESSAGE_TO_TEACHER') or define('CMD_MESSAGE_TO_TEACHER', 'ارتباط با استاد 💭👨‍🏫');

defined('CMD_MAIN_MENU') or define('CMD_MAIN_MENU', 'بازگشت به منو ↪️');

defined('CMD_GOD_ACCESS') or define('CMD_GOD_ACCESS', '/godAccess');

function alignButtons(array &$items, string $related_column, string $data_prefix, string $callback_data_index=DB_ITEM_ID): ?array
{
    $buttons = array(array()); // an inline keyboard
    $current_row = 0;
    $column_length = 0;
    $no_valid_options = true;
    foreach($items as $item) {
        if(isset($item[$callback_data_index])) {
            array_unshift($buttons[$current_row], array(
                TEXT_TAG => $item[$related_column] ?? $item[$callback_data_index],
                CALLBACK_DATA => $data_prefix . $item[$callback_data_index]
            ));
            // buttons callback_data is as: type/id, type determines whether it's a course or a teacher;
            $column_length += strlen($item[$related_column]);
            if($column_length > MAX_COLUMN_LENGTH) {
                $column_length = 0;
                $current_row++;
                $buttons[] = array();
            }

            if($no_valid_options) $no_valid_options = false;
        }
    }

    return !$no_valid_options ? $buttons : null;
}

function createMenu(string $table_name, ?string $previous_data = null, ?string $filter_query = null, ?string $filter_index = null): ?array
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
        }
    }
    $options = alignButtons($items, DB_ITEM_NAME, $data_prefix);
    return $options ? array(INLINE_KEYBOARD => $options) : null;
}

function createUserList(string $filter_query, string $filter_index = DB_USER_ID): ?array
{
    $items = Database::getInstance()->query('SELECT * FROM ' . DB_TABLE_USERS . ' WHERE ' . $filter_query);
    $options = alignButtons($items, DB_ITEM_NAME, DB_TABLE_USERS . RELATED_DATA_SEPARATOR, $filter_index);
    return $options ? array(INLINE_KEYBOARD => $options) : null;
}

function createIndexMenu(array &$booklets, bool $by_caption = false, bool $all_items_option = true): array
{
    $options = alignButtons($booklets, !$by_caption ? DB_BOOKLETS_INDEX : DB_BOOKLETS_CAPTION, DB_ITEM_ID . '=');
    if(!$options) return null;
    if($all_items_option)
        $options[] = array(
            array(TEXT_TAG => 'همه', CALLBACK_DATA => DB_ITEM_TEACHER_ID . '=' . $booklets[0][DB_ITEM_TEACHER_ID] . ' AND ' . DB_ITEM_COURSE_ID . '=' . $booklets[0][DB_ITEM_COURSE_ID])
        );
    return array(INLINE_KEYBOARD => $options);
}

function getMainMenu(int $user_mode): array
{
    $keyboard = array('resize_keyboard' => true, 'one_time_keyboard' => true,
        'keyboard' => $user_mode == ADMIN_USER || $user_mode == GOD_USER ?
                        array(
                            array(CMD_DOWNLOAD_BOOKLET, CMD_STATISTICS, CMD_UPLOAD_BOOKLET), // casual keyboard
                            array(CMD_LINK_TEACHER, CMD_SEND_POST_TO_CHANNEL),
                            array(CMD_ADD_COURSE, CMD_EDIT_BOOKLET, CMD_ADD_TEACHER),
                        )
                    : array(array(CMD_MESSAGE_TO_ADMIN, CMD_DOWNLOAD_BOOKLET))
    );
    $keyboard['keyboard'][] = array(CMD_MESSAGE_TO_TEACHER);
    if($user_mode == GOD_USER)
        $keyboard['keyboard'][] = array(CMD_REMOVE_ADMIN, CMD_ADD_ADMIN);
    else if($user_mode == TEACHER_USER)
        $keyboard['keyboard'][] = array(CMD_REMOVE_TA, CMD_INTRODUCE_TA);
    return $keyboard;
}

function backToMainMenuKeyboard(?array $other_options=null): array {
    $keyboard = array('resize_keyboard' => true, 'one_time_keyboard' => true,
    'keyboard' => array(
            array(CMD_MAIN_MENU)
        )
    );
    if($other_options)
        array_unshift($keyboard['keyboard'], $other_options);

    return $keyboard;
}