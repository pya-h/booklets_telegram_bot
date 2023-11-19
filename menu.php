<?php
require_once './database.php';
require_once './user.php';
require_once './telegram_api.php';

// UI constants
defined('MAX_COLUMN_LENGTH') or define('MAX_COLUMN_LENGTH', 30);
defined('MAX_LINKED_LIST_LENGTH') or define('MAX_LINKED_LIST_LENGTH', 10);
defined('CMD_GOD_ACCESS') or define('CMD_GOD_ACCESS', '/godAccess');

// USER MODE SPECIFIC MENUS
    // god mode options
    defined('CMD_ADD_ADMIN') or define('CMD_ADD_ADMIN', 'افزودن ادمین 💂‍♂️');
    defined('CMD_REMOVE_ADMIN') or define('CMD_REMOVE_ADMIN', 'حذف ادمین ❌');

    // god & admin options
    defined('CMD_ADD_COURSE') or define('CMD_ADD_COURSE', 'افزودن درس 📚');
    defined('CMD_ADD_TEACHER') or define('CMD_ADD_TEACHER', 'افزودن استاد 👨‍🏫');
    defined('CMD_UPLOAD_BOOKLET') or define('CMD_UPLOAD_BOOKLET', 'آپلود جزوه 📤');
    defined('CMD_EDIT_BOOKLET') or define('CMD_EDIT_BOOKLET', 'ویرایش ✏️');
    defined('CMD_EDIT_BOOKLET_CAPTION') or define('CMD_EDIT_BOOKLET_CAPTION', 'ویرایش کپشن 🪶');
    defined('CMD_EDIT_BOOKLET_FILE') or define('CMD_EDIT_BOOKLET_FILE', 'ویرایش فایل 📝');
    defined('CMD_TEACHER_INTRODUCTION') or define('CMD_TEACHER_INTRODUCTION', 'معرفی استاد 📝');
    defined('CMD_STATISTICS') or define('CMD_STATISTICS', 'آمار 🧮');
    defined('CMD_SEND_POST_TO_CHANNEL') or define('CMD_SEND_POST_TO_CHANNEL', 'پست 📯');
    defined('CMD_NOTIFICATION') or define('CMD_NOTIFICATION', 'خبررسانی 📯');
    defined('CMD_LINK_TEACHER') or define('CMD_LINK_TEACHER', 'ارتقا به استاد 🔗');

    // teacher mode options
    defined('CMD_INTRODUCE_TA') or define('CMD_INTRODUCE_TA', 'معرفی TA 👩‍🎓');
    defined('CMD_REMOVE_TA') or define('CMD_REMOVE_TA', 'حذف TA ❌');

// COMMON MENU
    defined('CMD_DOWNLOAD_BY_COURSE') or define('CMD_DOWNLOAD_BY_COURSE', 'جست و جو بر اساس نام درس 📖');
    defined('CMD_DOWNLOAD_BY_TEACHER') or define('CMD_DOWNLOAD_BY_TEACHER', 'جست و جو بر اساس نام استاد 👨‍🏫');
    defined('CMD_DOWNLOAD_BY_MOST_DOWNLOADED_TEACHER') or define('CMD_DOWNLOAD_BY_MOST_DOWNLOADED_TEACHER', 'به ترتیب پردانلودترین استاد 👨‍🏫');
    defined('CMD_DOWNLOAD_BY_MOST_DOWNLOADED_COURSE') or define('CMD_DOWNLOAD_BY_MOST_DOWNLOADED_COURSE', 'به ترتیب پردانلودترین درس 📖');

    defined('CMD_DOWNLOAD_BOOKLET') or define('CMD_DOWNLOAD_BOOKLET', 'جزوه ها 📖');
    defined('CMD_MESSAGE_TO_ADMIN') or define('CMD_MESSAGE_TO_ADMIN', 'پشتیبانی 💬');
    defined('CMD_MESSAGE_TO_TEACHER') or define('CMD_MESSAGE_TO_TEACHER', 'ارتباط با استاد 💭👨‍🏫');
    defined('CMD_TEACHER_BIOS') or define('CMD_TEACHER_BIOS', 'معارفه 💭👨‍🏫');
    defined('CMD_MAIN_MENU') or define('CMD_MAIN_MENU', 'بازگشت به منو ↪️');
    defined('CMD_FAVORITES') or define('CMD_FAVORITES', 'علاقه مندی ها ❤️');
    defined('CMD_GET_BOOKLET_PREFIX') or define('CMD_GET_BOOKLET_PREFIX', '/bk');
    defined('CMD_COMMAND_PARAM_SEPARATOR') or define('CMD_COMMAND_PARAM_SEPARATOR', '_');

    # ORDERS
        defined('ORDER_NONE') or define('ORDER_NONE', 0);
        defined('ORDER_BY_MOST_DOWNLOADED_TEACHER') or define('ORDER_BY_MOST_DOWNLOADED_TEACHER', 1);
        defined('ORDER_BY_MOST_DOWNLOADED_COURSE') or define('ORDER_BY_MOST_DOWNLOADED_COURSE', 2);
        defined('ORDER_BY_MOST_DOWNLOADED_BOTH') or define('ORDER_BY_MOST_DOWNLOADED_BOTH', 3);


function alignButtons(array &$items, string $related_column, string $data_prefix, string $callback_data_index=DB_ITEM_ID, string $other_related_column=null, $numbering=true): ?array
{
    $buttons = array(array()); // an inline keyboard
    $current_row = 0;
    $column_length = 0;
    $no_valid_options = true;
    foreach($items as $count => &$item) {
        if(isset($item[$callback_data_index]) || isset($item[$other_related_column])) {
            array_unshift($buttons[$current_row], array(
                TEXT_TAG => ($numbering ? ($count+1) . ' ' : '') . ($item[$related_column] ?? $item[$other_related_column] ?? $item[$callback_data_index]),
                CALLBACK_DATA => $data_prefix . ($item[$callback_data_index] ?? $item[DB_ITEM_ID])
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

function createMenu(string $table_name, ?string $previous_data = null, ?string $filter_query = null, ?string $filter_index = null, int $order_by=ORDER_NONE): ?array
{
    $query = 'SELECT ' . DB_ITEM_ID . ', ' . DB_ITEM_NAME . ' FROM ' . $table_name;
    if(!$previous_data && $filter_query && !$filter_index) // this condition just happens for remove admin menu
        $query .= ' WHERE ' . $filter_query;
    if(!$order_by) {
        // default order: alphabetic
        $query .= ' ORDER BY ' . DB_ITEM_NAME;
    }
    else if($table_name == DB_TABLE_COURSES || $table_name == DB_TABLE_TEACHERS) {
        $qc = null;
        if($order_by != ORDER_BY_MOST_DOWNLOADED_BOTH) {
            $order_conditions = [
                DB_TABLE_BOOKLETS . '.' . DB_ITEM_TEACHER_ID . '=' . DB_TABLE_TEACHERS . '.' . DB_ITEM_ID,
                DB_TABLE_BOOKLETS . '.' . DB_ITEM_COURSE_ID . '=' . DB_TABLE_COURSES . '.' . DB_ITEM_ID
            ];
            $qc = $order_conditions[$order_by - 1];
        } else {
            $params = explode(RELATED_DATA_SEPARATOR, $previous_data);
            if(count($params) != 2)// sth is wrong
                return null;
            // this part is a little twisted, figure it out yourself, I don't feel explaining right now
            
            $query = "SELECT $table_name." . DB_ITEM_ID . ", $table_name." . DB_ITEM_NAME . ", $params[0]." . DB_ITEM_ID
                . ' as xid FROM ' . DB_TABLE_TEACHERS . ', ' . DB_TABLE_COURSES . " WHERE $params[0]." . DB_ITEM_ID . "=$params[1]";
            $qc = DB_TABLE_BOOKLETS . '.' . DB_ITEM_TEACHER_ID . '=' . DB_TABLE_TEACHERS . '.' . DB_ITEM_ID
                . ' AND ' . DB_TABLE_BOOKLETS . '.' . DB_ITEM_COURSE_ID . '=' . DB_TABLE_COURSES . '.' . DB_ITEM_ID;
        }

        $query .= ' ORDER BY (SELECT SUM(' . DB_TABLE_BOOKLETS . '.' . DB_BOOKLETS_DOWNLOADS . ') FROM ' . DB_TABLE_BOOKLETS . " WHERE $qc) DESC";
        // /*comment this*/    logText($query);

    }
    
    $items = Database::getInstance()->query($query);

    $data_prefix = $table_name . RELATED_DATA_SEPARATOR;

    // TODO: EDIT THIS SECTION TO USE ONLY SWL QUERIES
    if($previous_data) {
        $data_prefix = $previous_data . DATA_JOIN_SIGN . $data_prefix;

        if($filter_query && $filter_index) {
            $booklets = Database::getInstance()->query(
                "SELECT $filter_index FROM " . DB_TABLE_BOOKLETS . " WHERE $filter_query", null, $filter_index);
            $items = array_values(array_filter($items, function($item) use ($booklets) {
                return in_array($item[DB_ITEM_ID], $booklets);
            }));
        }
    }
    $options = alignButtons($items, DB_ITEM_NAME, $data_prefix);
    return $options ? array(INLINE_KEYBOARD => $options) : null;
}

function createUsersMenu(string $filter_query, string $filter_index = DB_ITEM_ID): ?array
{
    $fields = implode(',', [DB_ITEM_ID, DB_ITEM_NAME, DB_USER_USERNAME]);
    $items = Database::getInstance()->query("SELECT $fields FROM " . DB_TABLE_USERS . " WHERE $filter_query ORDER BY " . DB_ITEM_NAME);
    $options = alignButtons($items, DB_ITEM_NAME, DB_TABLE_USERS . RELATED_DATA_SEPARATOR, $filter_index, DB_USER_USERNAME);
    return $options ? array(INLINE_KEYBOARD => $options) : null;
}

function createSessionsMenu(array &$booklets, bool $by_caption = false, bool $all_items_option = true): ?array
{
    $options = alignButtons($booklets, !$by_caption ? DB_BOOKLETS_INDEX : DB_BOOKLETS_CAPTION,
        DB_TABLE_BOOKLETS . '.' . DB_ITEM_ID . '=', DB_ITEM_ID, null, $by_caption);
    if(!$options) return null;
    if($all_items_option)
        $options[] = array(
            array(TEXT_TAG => 'همه', CALLBACK_DATA => DB_TABLE_BOOKLETS . '.' . DB_ITEM_TEACHER_ID . '=' . $booklets[0][DB_ITEM_TEACHER_ID] 
                . ' AND ' . DB_TABLE_BOOKLETS . '.' . DB_ITEM_COURSE_ID . '=' . $booklets[0][DB_ITEM_COURSE_ID])
        );
    return array(INLINE_KEYBOARD => $options);
}

function getMainMenu(int $user_mode): array
{
    // TODO: changed this fucked up peace
    $keyboard = array('resize_keyboard' => true, 'one_time_keyboard' => true,
        'keyboard' => $user_mode == ADMIN_USER || $user_mode == GOD_USER ?
                        [ // admin or god
                            [CMD_DOWNLOAD_BOOKLET, CMD_STATISTICS, CMD_UPLOAD_BOOKLET], // casual keyboard
                            [CMD_ADD_COURSE, CMD_EDIT_BOOKLET, CMD_ADD_TEACHER],
                            [CMD_MESSAGE_TO_TEACHER, CMD_TEACHER_BIOS],
                            [CMD_LINK_TEACHER, CMD_SEND_POST_TO_CHANNEL, CMD_NOTIFICATION],
                            [CMD_FAVORITES]
                        ]
                    : [ // teacher, ta, normal user
                        [CMD_MESSAGE_TO_ADMIN, CMD_TEACHER_BIOS, CMD_DOWNLOAD_BOOKLET],
                        [CMD_MESSAGE_TO_TEACHER, CMD_FAVORITES]
                    ]
    );

    if($user_mode == GOD_USER)
        $keyboard['keyboard'][] = [CMD_REMOVE_ADMIN, CMD_ADD_ADMIN];
    else if($user_mode == TEACHER_USER)
        $keyboard['keyboard'][] = [CMD_REMOVE_TA, CMD_STATISTICS, CMD_INTRODUCE_TA];
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

function getDownloadOptions(): array {
    return array('resize_keyboard' => true, 'one_time_keyboard' => true,
        'keyboard' => [
            [CMD_DOWNLOAD_BY_TEACHER],
            [CMD_DOWNLOAD_BY_COURSE],
            [CMD_DOWNLOAD_BY_MOST_DOWNLOADED_TEACHER],
            [CMD_DOWNLOAD_BY_MOST_DOWNLOADED_COURSE],
            [CMD_MAIN_MENU]
        ]
    );
}

function createLinkedList(array $booklets = array(), $page=0): string {
    $list = '';
    $end = ($page + 1) * MAX_LINKED_LIST_LENGTH;
    if(!isset($booklets[$end-1]))
        $end = count($booklets);
    for($i=$page*MAX_LINKED_LIST_LENGTH; $i < $end; $i++) {
        $list .= ($i + 1) . '. ' . $booklets[$i]['teacher'] . ' - ' . $booklets[$i]['course'] . "\t"
            . CMD_GET_BOOKLET_PREFIX . CMD_COMMAND_PARAM_SEPARATOR . $booklets[$i][DB_ITEM_TEACHER_ID]
            . CMD_COMMAND_PARAM_SEPARATOR . $booklets[$i][DB_ITEM_COURSE_ID];
        if($i < $end - 1)
            $list .= "\n〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️\n";
    }
    return !empty($list) ? $list : 'شما هنوز چیزی به علاقه مندی های خود اضافه نکرده اید!';
}

function createClassifyByMenu($user_id, &$categories): array {
    $is_in_favs = isInFavoritesList($user_id, $categories);
    $data = makeCategoryString($categories[DB_ITEM_COURSE_ID], $categories[DB_ITEM_TEACHER_ID]);
    switch($categories['options']) {
        case '+f':
            if(!$is_in_favs)
                updateFavoritesList($user_id, $categories);
            $is_in_favs = true;
            break;
        case '-f':
            if($is_in_favs)
                updateFavoritesList($user_id, $categories, true);
            $is_in_favs = false;
            break;
    }
    return array(
        INLINE_KEYBOARD => array(
            array(
                array(TEXT_TAG => 'شماره جزوه', CALLBACK_DATA => $data . DATA_JOIN_SIGN . '0'),
                array(TEXT_TAG => 'عنوان جزوه', CALLBACK_DATA => $data . DATA_JOIN_SIGN . '1'),
            ),
            array(
                !$is_in_favs ? array(TEXT_TAG => 'افزودن به علاقه مندی ها ❤️',  CALLBACK_DATA => $data . DATA_JOIN_SIGN . '+f')
                    :  array(TEXT_TAG => 'حذف از علاقه مندی ها ❌',  CALLBACK_DATA => $data . DATA_JOIN_SIGN . '-f')
            )
        )
    );
}