<?php
require_once './database.php';
require_once './telegram_api.php';
require_once './user.php';


function addCategory(string $category_name, string $value, $performer_id) {
    $item_id = Database::getInstance()->insert('INSERT INTO ' . $category_name . ' ('. DB_ITEM_NAME . ') VALUES (:name)', array('name' => $value));
    // TODO:? check if name is unique?
    // TODO: update user action to none

    return $item_id && resetAction($performer_id) ? $item_id : null; // null means error
}

function extractBookletIndexAndCaption(string &$text): array
{
    $index = 'بدون نام';
    $caption = 'بدون عنوان';
    if(strlen($text) > 0) {
        $captionAndIndex = explode(INDEX_SEPARATOR, $text);
        if(count($captionAndIndex) >= 1) {
            $index = $captionAndIndex[0];
            if(count($captionAndIndex) >= 2) {
                $caption = $captionAndIndex[1];
            }
        }
    }
    return [$index, $caption];
}

function makeCategoryString($course_id, $teacher_id): string{
    return DB_TABLE_COURSES . RELATED_DATA_SEPARATOR . $course_id . DATA_JOIN_SIGN 
        . DB_TABLE_TEACHERS . RELATED_DATA_SEPARATOR . $teacher_id;
}

function addBooklet(&$user, array &$file): array
{
    $categories = extractCategories($user[DB_USER_ACTION_CACHE]);

    if(isset($categories['err']))
        return array('id' => null, 'err' => $categories['err']);
    $err = null; $item_id = null;
    if(isset($file[FILE_ID]) && isset($file['tag'])) {
        // now its ready for insertion
        $fields = implode(',', array(DB_ITEM_TEACHER_ID, DB_ITEM_COURSE_ID, DB_BOOKLETS_FILE_ID, DB_BOOKLETS_CAPTION, DB_BOOKLETS_INDEX, DB_BOOKLETS_TYPE));
        // separate index and caption
        $identifiers = extractBookletIndexAndCaption($file[CAPTION_TAG]);
        $item_id = Database::getInstance()->insert(
            'INSERT INTO ' . DB_TABLE_BOOKLETS . " ($fields)" . ' VALUES (:teacher_id, :course_id, :file_id, :caption, :index, :type)',
                array('teacher_id' => $categories[DB_ITEM_TEACHER_ID],
                    'course_id' => $categories[DB_ITEM_COURSE_ID], 'file_id' => $file[FILE_ID],
                    'caption' => $identifiers[1], 'index' => $identifiers[0], 'type' => $file['tag'])
        );
        if(!$item_id || !resetAction($user[DB_ITEM_ID]))
            $err = 'مشکلی حین ثبت جزوه پیش اومد. لطفا دوباره تلاش کن!';
    } else $err = 'فایل موردنظر به درستی توسط ربات دریافت نشده است. لطفا دوباره تلاش کنید!';
    return array('id' => $item_id, 'err' => $err);
}

function backupBooklet(&$user, ?string $new_caption = null): ?string
{
    $db = Database::getInstance();
    $err = '';
    if($new_caption) {
        $identifiers = extractBookletIndexAndCaption($new_caption);
        if (
            !$db->update('UPDATE ' . DB_TABLE_BOOKLETS . ' SET ' . DB_BOOKLETS_CAPTION . '=:caption, ' . DB_BOOKLETS_INDEX . '=:index WHERE ' . DB_ITEM_ID . '=:id',
                array('id' => $user[DB_USER_ACTION_CACHE], 'caption' => $identifiers[1], 'index' => $identifiers[0]))
        )
            $err .= 'تغییر کپشن ناموفق بود!';

    }
    $booklet = $db->query(
        'SELECT * FROM '. DB_TABLE_BOOKLETS .' WHERE ' . DB_ITEM_ID  . '=:id LIMIT 1', array(
            'id' => $user[DB_USER_ACTION_CACHE]
        )
    );
    if(isset($booklet[0])) {
        // save current booklet's teacher id and course id, for next upload
        setActionAndCache($user[DB_ITEM_ID], ACTION_SENDING_BOOKLET_FILE,
                makeCategoryString($booklet[0][DB_ITEM_COURSE_ID], $booklet[0][DB_ITEM_TEACHER_ID]));
        // send to channel
        callMethod(
            'send' . ucfirst($booklet[0][DB_BOOKLETS_TYPE]),
            CHAT_ID, BACKUP_CHANNEL_ID,
            $booklet[0][DB_BOOKLETS_TYPE], $booklet[0][DB_BOOKLETS_FILE_ID],
            CAPTION_TAG, $booklet[0][DB_BOOKLETS_INDEX] . ': '. $booklet[0][DB_BOOKLETS_CAPTION]
        );
    } else $err .= ' ارسال جزوه به کانال ناموفق بود!';

    return strlen($err) ? 'خطاها: ' . $err : null;
}

function changeBookletFile($booklet_id, array $file) {
    if(!isset($file[FILE_ID]) || !isset($file['tag']))
        return null;
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_BOOKLETS . ' SET ' . DB_BOOKLETS_FILE_ID . '=:file_id, ' . DB_BOOKLETS_TYPE . '=:type WHERE ' . DB_ITEM_ID . '=:id',
            array('id' => $booklet_id, 'file_id' => $file[FILE_ID], 'type' => $file['tag']));
}

function getTeachersField($teacher_id, string $field=DB_ITEM_NAME) {
    $values = Database::getInstance()->query("SELECT $field FROM " . DB_TABLE_TEACHERS
         .' WHERE ' . DB_ITEM_ID . '=:id LIMIT 1', array('id' => $teacher_id), $field);
    return $values[0] ?? null;
}

function getBooklets(string $filter='1=1', bool $increaseDownloads=false) {
    $db = Database::getInstance();
    if($increaseDownloads)
        $db->update('UPDATE ' . DB_TABLE_BOOKLETS . ' SET ' . DB_BOOKLETS_DOWNLOADS . '=' . DB_BOOKLETS_DOWNLOADS . " + 1 WHERE $filter");
    return $db->query(
        'SELECT ' . DB_TABLE_BOOKLETS . '.*,' . DB_TABLE_COURSES . '.' . DB_ITEM_NAME . ' as course,' .
            DB_TABLE_TEACHERS . '.' . DB_ITEM_NAME . ' as teacher FROM '. DB_TABLE_BOOKLETS . ' JOIN ' . DB_TABLE_COURSES .
                ' ON ' . DB_TABLE_COURSES . '.' . DB_ITEM_ID . '=' . DB_ITEM_COURSE_ID . ' JOIN ' . DB_TABLE_TEACHERS .
                    ' ON ' . DB_TABLE_TEACHERS . '.' . DB_ITEM_ID . '=' . DB_ITEM_TEACHER_ID . " WHERE $filter"
    );
}

function introduceTeacher($teacher_id, ?string $bio=null) {
    return Database::getInstance()->update('UPDATE ' . DB_TABLE_TEACHERS . " SET " . DB_TEACHER_BIO . "=:bio WHERE " . DB_ITEM_ID . '=:teacher_id',
        array('teacher_id' => $teacher_id, 'bio' => $bio));
}

function getDownloadSatistics($teacher_id=null, $course_id=null, $booklet_id=null): int {
    $conditions = [];
    if($booklet_id) {
        $conditions[] = DB_ITEM_ID . "=$booklet_id";
    } else {
        if($teacher_id) $conditions[] = DB_ITEM_TEACHER_ID . "=$teacher_id";
        if($course_id)  $conditions[] = DB_ITEM_COURSE_ID . "=$course_id";
    }
    $condition = implode(' AND ', $conditions);
    
    $result = Database::getInstance()->query("SELECT SUM(" . DB_BOOKLETS_DOWNLOADS .") AS TOTAL FROM " . DB_TABLE_BOOKLETS . " WHERE $condition");
    return $result[0]['TOTAL'] ?? 0;
}

function getCourseName($course_id) {
    $values = Database::getInstance()->query("SELECT " . DB_ITEM_NAME . " FROM " . DB_TABLE_COURSES
         .' WHERE ' . DB_ITEM_ID . '=:id LIMIT 1', array('id' => $course_id), DB_ITEM_NAME);
    return $values[0] ?? null;
}

function &getTeachersFullDownloadStats($teacher_id): string {
    $teachers_booklets = Database::getInstance()->query("SELECT " . DB_BOOKLETS_DOWNLOADS . ", " . DB_ITEM_COURSE_ID . " FROM " 
        . DB_TABLE_BOOKLETS . " WHERE " . DB_ITEM_TEACHER_ID . "=$teacher_id");
    $courses = array();
    foreach($teachers_booklets as &$booklet) {
        if(!isset($courses[DB_ITEM_COURSE_ID])) {
            $courses[DB_ITEM_COURSE_ID] = array(
                'name' => getCourseName($booklet[DB_ITEM_COURSE_ID]) ?? "بدون عنوان!",
                'downloads' => 0
            );
        }
        $courses[DB_ITEM_COURSE_ID]['downloads'] += $booklet[DB_BOOKLETS_DOWNLOADS];
    }
    $stats = "تعداد دانلود جزوات شما:\n\n";
    foreach($courses as &$course) {
        $stats .= $course['name'] . ": " . $course['downloads'] . "\n";
    }
    return $stats;
}