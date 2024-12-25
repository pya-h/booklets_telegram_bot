<?php
require_once __DIR__ . '/credentials.php';

// TELEGRAM API GENERAL CONSTANTS
defined('FILE_ID') or define('FILE_ID', 'file_id');
defined('TEXT_TAG') or define('TEXT_TAG', 'text');
defined('KEYBOARD') or define('KEYBOARD', 'reply_markup');
defined('INLINE_KEYBOARD') or define('INLINE_KEYBOARD', 'inline_keyboard');
defined('CHAT_ID') or define('CHAT_ID', 'chat_id');

defined('CAPTION_TAG') or define('CAPTION_TAG', 'caption');
defined('CALLBACK_DATA') or define('CALLBACK_DATA', 'callback_data');
defined('INLINE_URL_TAG') or define('INLINE_URL_TAG', 'url');
defined('CALLBACK_QUERY') or define('CALLBACK_QUERY', 'callback_query');

defined('MESSAGE_ID_TAG') or define('MESSAGE_ID_TAG', 'message_id');
defined('FILE_PHOTO') or define('FILE_PHOTO', 'photo');
defined('FILE_VOICE') or define('FILE_VOICE', 'voice');
defined('FILE_VIDEO') or define('FILE_VIDEO', 'video');
defined('FILE_AUDIO') or define('FILE_AUDIO', 'audio');
defined('FILE_DOCUMENT') or define('FILE_DOCUMENT', 'document');
defined('USER_NOT_A_MEMBER') or define('USER_NOT_A_MEMBER', 'left');

defined('METH_SEND_MESSAGE') or define('METH_SEND_MESSAGE', 'sendMessage');
defined('METH_SEND_PHOTO') or define('METH_SEND_PHOTO', 'sendPhoto');
defined('METH_SEND_VOICE') or define('METH_SEND_VOICE', 'sendVoice');
defined('METH_SEND_AUDIO') or define('METH_SEND_AUDIO', 'sendAudio');
defined('METH_SEND_VIDEO') or define('METH_SEND_VIDEO', 'sendVideo');
defined('METH_SEND_DOCUMENT') or define('METH_SEND_DOCUMENT', 'sendDocument');

defined('METH_SEND_LOCATION') or define('METH_SEND_LOCATION', 'sendLocation');
defined('METH_SEND_CONTACT') or define('METH_SEND_CONTACT', 'sendContact');
defined('METH_SEND_CHAT_ACTION') or define('METH_SEND_CHAT_ACTION', 'sendChatAction'); // typing..., sending video ..., that kind of thing
// lasts for 5 secs

defined('METH_FORWARD_MESSAGE') or define('METH_FORWARD_MESSAGE', 'forwardMessage');
defined('METH_COPY_MESSAGE') or define('METH_COPY_MESSAGE', 'copyMessage');
defined('METH_ANSWER_CALLBACK_QUERY') or define('METH_ANSWER_CALLBACK_QUERY', 'answerCallbackQuery');
defined('METH_EDIT_MESSAGE') or define('METH_EDIT_MESSAGE', 'editMessageText');
defined('METH_DELETE_MESSAGE') or define('METH_DELETE_MESSAGE', 'deleteMessage');
defined('METH_GET_CHAT_MEMBER') or define('METH_GET_CHAT_MEMBER', 'getChatMember');

// BOT SPECIFIC CONSTANTS
defined('URL_BASE') or define('URL_BASE', "https://api.telegram.org/bot" . TOKEN . "/");

defined('BACKUP_CHANNEL_ID') or define('BACKUP_CHANNEL_ID', -1002073799554);

defined('FIRST_2_JOIN_CHANNEL_URL') or define('FIRST_2_JOIN_CHANNEL_URL', 'https://t.me/pybutechan');
defined('FIRST_2_JOIN_CHANNEL_ID') or define('FIRST_2_JOIN_CHANNEL_ID', -1002073799554);

defined('SECOND_2_JOIN_CHANNEL_URL') or define('SECOND_2_JOIN_CHANNEL_URL', 'https://t.me/+xOSqZmrHI-llYWQ0');
defined('SECOND_2_JOIN_CHANNEL_ID') or define('SECOND_2_JOIN_CHANNEL_ID', -1001530157258);

defined('PERSIAN_COLLEGE_BOT_LINK') or define('PERSIAN_COLLEGE_BOT_LINK', 'https://t.me/Persian_collegebot');
defined('PERSIAN_COLLEGE_YOUTRUBE_LINK') or define('PERSIAN_COLLEGE_YOUTRUBE_LINK', 'https://youtube.com/@Persian_College?sub_confirmation=1');