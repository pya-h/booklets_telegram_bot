<?php
require_once __DIR__ . '/config/actions.php';
require_once __DIR__ . '/bot.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/booklet.php';
require_once __DIR__ . '/sample.php';
require_once __DIR__ . '/message_handler.php';
require_once __DIR__ . '/callback_query_handler.php';


function appendStatsToMessage($msg, int $stats): string
{
    return "$msg\n〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️\nتعداد دانلودهای این مورد: $stats";
}

function isGodEnough(): bool
{
    // just trying to be funny:|
    return count(
            Database::getInstance()->query(
                'SELECT * FROM ' . DB_TABLE_USERS . ' WHERE ' . DB_USER_MODE . '=' . GOD_USER
            )) >= MAX_GODS;
}

function handleGospel(&$user, string &$whisper): ?string
{
    // handle god login requests
    $answer = null;
    switch ($user[DB_USER_ACTION]) {
        case ACTION_WHISPER_GODS_NAME:
            if ($whisper === GOD_NAME) {
                $answer = 'God\'s Secret:';
                if (!updateAction($user[DB_ITEM_ID], ACTION_WHISPER_GODS_SECRET)) {
                    $answer = 'خطای غیرمنتظره پیش آمد! دوباره تلاش کن!';
                    resetAction($user[DB_ITEM_ID]);
                }
            }
            break;
        case ACTION_WHISPER_GODS_SECRET:
            if ($whisper === GOD_SECRET && !isGodEnough()) {
                if (!updateUserMode($user[DB_ITEM_ID], GOD_USER)) {
                    $answer = 'خطایی حین ثبت اطلاعات پیش آمد. دوباره تلاش کن!';
                }

                $user[DB_USER_MODE] = GOD_USER; // update the old user object
                resetAction($user[DB_ITEM_ID]);
                $answer = 'Now you\'re God Almighty :)!';
            }
            break;
    }
    return $answer;
}

function &startUpgradingUser($user_id, array &$message, int $mode, string $position_title, $teacher_id = null): string
{
    $response = '';
    $target_id = null;
    if (!isset($message['forward_from']) && ($message[TEXT_TAG] ?? ' ')[0] == '@') {
        // setting by username
        $target_id = findByUsername($message[TEXT_TAG]);
        if (!$target_id) {
            $response = "هیچ کاربری با این یوزرنیم یافت نشد. این خطا دو علت می تواند داشته باشد: \n1- یوزرنیم را به درستی وارد نکرده اید\n2-کاربر موردنظر هنوز شروع به استفاده از ربات نکرده است.";
        }

    } else if (isset($message['forward_from'])) {
        $target_id = $message['forward_from']['id'] ?? null;
    } else {
        $response = "اکانت موردنظر حالت مخفی رو فعال کرده. برای ارتقا یافتن به $position_title باید موقتا این حالت رو غیرفعال کنه!";
        resetAction($user_id);
    }
    if ($target_id) {
        $teacher_name = $mode != ADMIN_USER ? getTeachersField($teacher_id) : null;
        if (updateUserMode($target_id, $mode, $teacher_id, $mode == TEACHER_USER ? $teacher_name : null)) {
            $response = "اکانت موردنظر بعنوان $position_title ثبت شد!";
            // notify the target user
            callMethod(METH_SEND_MESSAGE,
                CHAT_ID, $target_id,
                TEXT_TAG, $mode != TA_USER ? "تبریک! اکانت شما به دسترسی $position_title ارتقا پیدا کرد." : "تبریک اکانت شما به عنوان حل تمرین استاد $teacher_name ثبت شد.",
                KEYBOARD, getMainMenu($mode)
            );
            if ($mode != TEACHER_USER) { // teacher has predefined name
                // other modes take their related entity's name
                if (setActionAndCache($user_id, ACTION_ASSIGN_USER_NAME, $target_id)) {
                    $response .= ' اسم کاربر مورد نظر را وارد کنید:';
                } else {
                    $response .= ' اما حین ورود به حالت تعیین اسم مشکلی پیش آمد!';
                    resetAction($user_id);
                }
            } else {
                resetAction($user_id);
            }

        } else {
            $response = "متاسفانه مشکلی حین ثبت اکانت بعنوان $position_title پیش آمده. لطفا دوباره تلاش کن!";
            resetAction($user_id);
        }
    }
    return $response;
}

function handleUpdates(&$update) {
    // Edit this list for your desired channels
    $channels = array(FIRST_2_JOIN_CHANNEL_ID => array('name' => "Persian College", INLINE_URL_TAG => FIRST_2_JOIN_CHANNEL_URL),
        SECOND_2_JOIN_CHANNEL_ID => array('name' => "Persian Project", INLINE_URL_TAG => SECOND_2_JOIN_CHANNEL_URL));

    $all_joined = true;
    $user_id = isset($update[CALLBACK_QUERY]) ? $update[CALLBACK_QUERY]['from']['id'] : $update['message']['from']['id'];
    $channel_list_menu = array(array());
    $current_row = 0;
    foreach($channels as $channel_id => $params) {
        $res = callMethod(
            METH_GET_CHAT_MEMBER,
            CHAT_ID, $channel_id,
            'user_id', $user_id
        );
        $res = json_decode($res, true);
        $all_joined = $all_joined && (strtolower($res['result']['status'] ?? USER_NOT_A_MEMBER) != USER_NOT_A_MEMBER);
        $channel_list_menu[$current_row][] = array(TEXT_TAG => $params['name'], INLINE_URL_TAG => $params[INLINE_URL_TAG]);
        if(count($channel_list_menu[$current_row]) >= 2) {
            $channel_list_menu[] = array();
            $current_row++;
        }
    }
    $channel_list_menu[] = array(array(TEXT_TAG => 'بررسی عضویت', CALLBACK_DATA => -1));
    if($all_joined) {
        if (isset($update['message']))
            handleCasualMessage($update);
        else if (isset($update[CALLBACK_QUERY]))
            handleCallbackQuery($update);
    } else {
        callMethod(METH_SEND_MESSAGE,
            CHAT_ID, $user_id,
            TEXT_TAG, 'قبل از هر چیزی لازمه که در کانال های ما جوین شی',
            KEYBOARD, array('remove_keyboard' => true)
        );
        callMethod(METH_SEND_MESSAGE,
            CHAT_ID, $user_id,
            TEXT_TAG, 'بعد از اینکه عضو کانال های زیر شدی، بررسی عضویت رو بزن:',
            KEYBOARD, array(INLINE_KEYBOARD => $channel_list_menu)
        );
    }
}

function validateCategoricalCallbackData($params): ?string {
    if(!validateInlineData($params, 't', 'id')) {
        if($params['id'] >= 0 && ($params['t'] === 'cr' || $params['t'] === 'tc' || $params['t' ] == 'bk'|| $params['t' ] == 'sm'))
            return null;
    }
    return 'فرایند انتخاب دسته بندی موردنظز دچار نقص اطلاعات شده است! لطفا از نو تلاش کنید.';

}