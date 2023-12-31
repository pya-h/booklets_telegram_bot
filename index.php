<?php
require_once './config.php';
require_once './telegram_api.php';
require_once './handle.php';

$update = getUpdate();
// check user is a member in specified channels
if ($update != null) {
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
