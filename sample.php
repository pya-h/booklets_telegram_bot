<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/bot.php';

function addSample(&$user, array &$file): array
{
    if (!isset($user[DB_USER_CACHE]))
        return array('id' => null, 'err' => 'درس مربوطه به درستی انتخاب نشده است! دوباره تلاش کنید...');

    $categories = json_decode($user[DB_USER_CACHE], true);
    if (isset($categories['err'])) {
        return array(DB_ITEM_ID => null, 'err' => $categories['err']);
    }

    $err = null;
    $item_id = null;
    if (isset($file[FILE_ID]) && isset($file['tag'])) {
        // now It's ready for insertion
        $fields = implode(',', array(DB_ITEM_COURSE_ID, DB_ITEM_FILE_ID, DB_ITEM_NAME, DB_ITEM_FILE_TYPE));

        $item_id = Database::getInstance()->insert(
            'INSERT INTO ' . DB_TABLE_SAMPLES . " ($fields) " . ' VALUES (:course_id, :file_id, :title, :type)',
            array('course_id' => $categories[DB_ITEM_COURSE_ID], 'file_id' => $file[FILE_ID], 'title' => !empty($file[CAPTION_TAG]) ? $file[CAPTION_TAG] : 'بدون عنوان', 'type' => $file['tag'])
        );
        if (!$item_id || !resetAction($user[DB_ITEM_ID]))
            $err = 'مشکلی حین ثبت نمونه سوال پیش اومد. لطفا دوباره تلاش کن!';
    } else $err = 'فایل موردنظر به درستی توسط ربات دریافت نشده است. لطفا دوباره تلاش کنید!';

    $categories[DB_ITEM_ID] = $item_id;
    $categories['err'] = $err;

    return $categories;
}

function backupSample(&$user, ?string $new_title = null): ?string
{
    $db = Database::getInstance();
    $sample_data = json_decode($user[DB_USER_CACHE], true);

    $err = '';
    if ($new_title) {
        if (
            !$db->update(
                'UPDATE ' . DB_TABLE_SAMPLES . ' SET ' . DB_ITEM_NAME . '=:title WHERE ' . DB_ITEM_ID . '=:id',
                array('id' => $sample_data[DB_ITEM_ID], 'title' => $new_title)
            )
        )
            $err .= 'تغییر کپشن ناموفق بود!';
    }
    $sample = $db->query(
        'SELECT * FROM ' . DB_TABLE_SAMPLES . ' WHERE ' . DB_ITEM_ID  . '=:id LIMIT 1',
        array('id' => $sample_data[DB_ITEM_ID])
    );
    if (isset($sample[0])) {
        // send to channel
        updateAction($user[DB_ITEM_ID], ACTION_SENDING_SAMPLE_FILE);
        callMethod(
            'send' . ucfirst($sample[0][DB_ITEM_FILE_TYPE]),
            CHAT_ID,
            BACKUP_CHANNEL_ID,
            $sample[0][DB_ITEM_FILE_TYPE],
            $sample[0][DB_ITEM_FILE_ID],
            CAPTION_TAG,
            'نمونه سوال: ' . $sample[0][DB_ITEM_NAME]
        );
    } else $err .= ' ارسال نمونه سوال به کانال بک آپ ناموفق بود!';
    return strlen($err) ? 'خطاها: ' . $err : null;
}

function getSamples($course_id, $sample_id = null, bool $increaseDownloads = false): ?array
{
    $filter = DB_TABLE_SAMPLES . '.'
        . ($sample_id && $sample_id >= 0 ? DB_ITEM_ID . "=$sample_id"
            : DB_ITEM_COURSE_ID . "=$course_id");
    $db = Database::getInstance();
    if ($increaseDownloads)
        $db->update('UPDATE ' . DB_TABLE_SAMPLES . ' SET ' . DB_ITEM_DOWNLOADS . '=' . DB_ITEM_DOWNLOADS . " + 1 WHERE $filter");

    return $db->query(
        'SELECT ' . DB_TABLE_SAMPLES . '.*,' . DB_TABLE_COURSES . '.' . DB_ITEM_NAME . ' as course FROM ' . DB_TABLE_SAMPLES . ' JOIN ' . DB_TABLE_COURSES .
            ' ON ' . DB_TABLE_COURSES . '.' . DB_ITEM_ID . '=' . DB_ITEM_COURSE_ID . " WHERE $filter"
    );
}
