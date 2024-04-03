<?php
require_once __DIR__ . '/config/actions.php';

// FIXME: CHECK THE resetAction method effect afterall (espo. after the function validateCallbackData returns an answer.
// TODO: I think when we apply the structural change, there is no need to use resetAction, we dont even need the user acton most of the cases.
function handleCallbackQuery(&$update)
{
    $callback_id = $update[CALLBACK_QUERY]['id'];
    $chat_id = $update[CALLBACK_QUERY]['message']['chat']['id'];
    $message_id = $update[CALLBACK_QUERY]['message'][MESSAGE_ID_TAG];
    $user_id = $update[CALLBACK_QUERY]['from']['id'];
    $raw_data = $update[CALLBACK_QUERY]['data'];
    $text = $update[CALLBACK_QUERY]['message']['text'];
    $answer = null;
    $keyboard = null;
    $username = $update['message']['from']['username'] ?? null;
    $user = getUser($user_id, $username);
    $data = strtolower($raw_data);
    if ($raw_data == -1) {
        // check membership is ok
        // because if it wasn't ok, this function couldn't be called
        $answer = 'مرسی که عضو کانال های ما شدی :)';
        callMethod(METH_SEND_MESSAGE,
            CHAT_ID, $chat_id,
            TEXT_TAG, 'چه کاری میتونم برات انجام بدم؟',
            KEYBOARD, getMainMenu($user[DB_USER_MODE])
        );
    } else if (strpos($data, DB_TABLE_MESSAGES) !== false) {
        $data = json_decode($data, true);

        $action = $data['a'] ?? null; // TODO: => must be action
        $params = $data['p'] ?? null;
        $state = $data['s'] ?? null;

        switch ($action) {
            case IA_UPLOAD_BOOKLET:
                // TODO: Create the first menu in message_handler.php
            if($user[DB_USER_MODE] != ADMIN_USER || $user[DB_USER_MODE] != GOD_USER)
                {
                    $answer = 'شما اجازه انجام چنین کاری را ندارید!';
                    break;
                }
                if(($answer = validateCategoricalCallbackData($params)) !== null)
                    break;
                if(!$state) {
                    if($params['t'] !== 'cr' && $params['t'] !== 'tc')
                        $answer = 'متاسفانه به دلیلی نامشخص فرایند آپلود در حالت اشتباهی اتظیم شده است. لطفا از دوباره تلاش کنند. اگر بازهم به این مشکل برخوردید با دولوپر در میان بگذارید.';
                    else {
                        $keyboard = createCategoricalMenu(IA_UPLOAD_BOOKLET, null, $params, false);
                        $answer = $params['t'] === 'cr' ? 'از بین اساتید ارائه کننده این درس استاد مورد نظر خود را انتخاب کنید:'
                            : 'از بین درس های ارایه شده توسط استاد یکی را انتخاب کنید:';
                    }

                } else {
                    // bot categories are selected:
                    // the if below, sets user action and its cache to prepare for getting the booklet
                    $categories = extractCategories([$params, $state]);
                    if (setActionAndCache($user_id, ACTION_SENDING_BOOKLET_FILE, json_encode($categories))) {
                        $answer = 'جزوه مورد نظرت رو همراه با کپشن بفرست:';
                        callMethod(METH_SEND_MESSAGE,
                            CHAT_ID, $chat_id,
                            MESSAGE_ID_TAG, $message_id,
                            TEXT_TAG, $answer,
                            KEYBOARD, backToMainMenuKeyboard()
                        );
                        callMethod('answerCallbackQuery',
                            'callback_query_id', $callback_id,
                            TEXT_TAG, 'فرایند آپلود جزوات این درس آغاز شد.',
                            'show_alert', false
                        );
                        exit();
                    }
                    $answer = 'مشکلی حین ثبت اطلاعات پیش آمده. لطفا از اول تلاش کن :|';
                    resetAction($user_id);
                }
                break;
            case IA_UPLOAD_SAMPLE:
                // TODO: Create the first menu in message_handler.php
                if($user[DB_USER_MODE] != ADMIN_USER || $user[DB_USER_MODE] != GOD_USER)
                {
                    $answer = 'شما اجازه انجام چنین کاری را ندارید!';
                    break;
                }
                if(!isset($params['t']) || $params['t'] !== 'cr' || !isset($params['id']) || $params['id'] < 0) {
                    $answer = '.برای آپلود نمونه سوال باید درس مربوطه از منو انتخاب شود. متاسفانه شما این مرحله را به درستی طی نکرده اید. لطفا دوباره تلاش کنید..';
                    break;
                }

                $answer = 'نمونه سوال مورد نظر خود را همراه با کپشن بفرست:';
                if (!setActionAndCache($user_id, ACTION_SENDING_SAMPLE_FILE, $params['id']))
                    $answer = 'مشکلی حین ورود به حالت آپلود نمونه سوال پیش آمد! لحظاتی دیگر دوباره تلاش کنید...';

                break;
            case IA_LIST_SAMPLES:
                // FIXME: update create menu function
                if(!isset($params['t']) || $params['t'] !== 'cr' || !isset($params['id']) || $params['id'] < 0) {
                    $answer = 'برای مشاهده لیست نمونه سوالات هر درس باید درس مربوطه را انتخاب کنید وای به نظر می رسد به دلیلی نامعلوم درسی انتخاب نشده است! لطفا دوباره تلاش کنید ...';
                    break;
                }
                $course_id = $params['id'];
                $samples = getSamples(DB_TABLE_SAMPLES . '.' . DB_ITEM_COURSE_ID . "=$course_id");
                if (isset($samples[0])) {
                    // if there is some booklets
                    $answer = 'نمونه سوال موردنظر خود را از لیست زیر انتخاب کن:';
                    if (updateAction($user_id, ACTION_SELECT_SAMPLE_TO_GET)) {
                        $keyboard = createSamplesMenu($samples);
                        if (isSuperior($user)) {
                            $downloads = 0;
                            foreach ($samples as &$sample) {
                                $downloads += $sample[DB_ITEM_DOWNLOADS];
                            }

                            $answer = appendStatsToMessage($answer, $downloads);
                        }
                    } else {
                        $answer = 'مشکلی حین دریافت اطلاعات پیش آمد! لطفا لحظاتی بعد دوباره تلاش کنید.';
                        resetAction($user_id);
                    }

                } else {
                    $answer = 'هنوز نمونه سوالی توسط ادمین ها ثبت نشده است!';
                    resetAction($user_id);
                }
                break;
            case IA_LIST_BOOKLETS:
                if(!$state) {
                    // TODO: Create the first menu in message_handler.php
                    if(($answer = validateCategoricalCallbackData($params)) !== null)
                        break;

                    if($params['t'] !== 'cr' && $params['t'] !== 'tc')
                        $answer = 'متاسفانه به دلیلی نامشخص فرایند دانلود در حالت اشتباهی تنظیم شده است. لطفا از دوباره تلاش کنند. اگر بازهم به این مشکل برخوردید با دولوپر در میان بگذارید.';
                    else {
                        $answer = $params['t'] === 'cr' ? 'از بین اساتید ارائه کننده این درس استاد مورد نظر خود را انتخاب کنید:'
                            : 'از بین درس های ارایه شده توسط استاد یکی را انتخاب کنید:';
                        $extra = $data['x'] ?? ORDER_BY_NAME;
                        $keyboard = createCategoricalMenu(IA_LIST_BOOKLETS, null, $params,
                            true, $extra);

                        if (isSuperior($user) && $keyboard) {
                            $answer = appendStatsToMessage($answer, getDownloadStatistics(null, $params[1]));
                        }
                    }
                    break;
                }
            case IA_EDIT_BOOKLET_CAPTION:
            case IA_EDIT_BOOKLET_FILE:
                if(($answer = validateCategoricalCallbackData($params)) !== null)
                    break;
                $categories = extractCategories([$params, $state], $data['x']);
                if ($categories['options'] == '0' || $categories['options'] == '1') {
                    if (isset($categories['err'])) {
                        $answer = $categories['err'];
                    } else {
                        $booklets = getBooklets(
                            selectBookletByCategoriesCondition($categories[DB_ITEM_TEACHER_ID], $categories[DB_ITEM_COURSE_ID])
                        );
                        if (isset($booklets[0])) {
                            // if there is some booklets
                            $answer = 'استاد ' . $booklets[0]['teacher'] . ' - ' . $booklets[0]['course'] . "\n\n جزوه ی موردنظرتو از لیست زیر انتخاب کن:";
                            if ($user[DB_USER_ACTION] == IA_LIST_BOOKLETS) {

                                $keyboard = createSessionsMenu(IA_GET_BOOKLET, $booklets, $categories);
                                if (isSuperior($user)) {
                                    $downloads = 0;
                                    foreach ($booklets as &$booklet) {
                                        $downloads += $booklet[DB_ITEM_DOWNLOADS];
                                    }

                                    $answer = appendStatsToMessage($answer, $downloads);
                                }

                            } else {
                                // FIXME: Change this
                                if (setActionAndCache($user_id, ACTION_SELECT_BOOKLET_TO_EDIT, $user[DB_USER_ACTION])) {
                                    $keyboard = createSessionsMenu($booklets, $categories, false);
                                } else {
                                    $answer = 'مشکلی حین دریافت اطلاعات پیش آمد! لطفا لحظاتی بعد دوباره تلاش کنید.';
                                    resetAction($user_id);
                                }
                            }
                        } else {
                            $answer = 'هنوز جزوه ای آپلود نشده!';
                            resetAction($user_id);
                        }
                    }
                } else {
                    $answer = 'طبقه بندی جزوه ها بر اساس:';
                    $keyboard = createClassifyByMenu($user_id, $categories, $data);
                }
                break;
            case IA_SHOW_MESSAGE:
                // user wants to see admin message
                ;
                // if data is invalid: show the validation error message
                if (($answer = validateInlineData($params, 'msg', 'admin', 'rpm')) !== null) {
                    callMethod('answerCallbackQuery',
                        'callback_query_id', $callback_id,
                        TEXT_TAG, $answer,
                        'show_alert', true
                    );
                } else {
                    callMethod(
                        METH_COPY_MESSAGE,
                        MESSAGE_ID_TAG, $params['msg'],
                        CHAT_ID, $chat_id,
                        'from_chat_id', $params['admin'],
                        'reply_to_message_id', $params['rpm']
                    );
                    callMethod(METH_DELETE_MESSAGE,
                        MESSAGE_ID_TAG, $message_id,
                        CHAT_ID, $chat_id
                    ); // remove the show message box
                }
                exit(); // I did this because we dont want to edit this message an drmeove the "SHOW" button!
            // Removing that button will deny any possible chance to retrieve the message
            case IA_REPLY_MESSAGE:
                $answer = validateInlineData($params, 'msg');
                if (!$answer) {
                    // user is attempting to answer a message
                    if (setActionAndCache($user_id, ACTION_WRITE_REPLY_TO_USER, $params['msg'])) {
                        $answer = 'پاسخ خودتو بنویس: (لغو /cancel)';
                        if (isMessageAnswered($params['msg'])) {
                            callMethod('answerCallbackQuery',
                                'callback_query_id', $callback_id,
                                TEXT_TAG, 'این پیام قبلا پاسخ داده شده است!',
                                'show_alert', true
                            );
                        }

                        callMethod(
                            METH_SEND_MESSAGE,
                            CHAT_ID, $chat_id,
                            TEXT_TAG, $answer,
                            'reply_to_message_id', $message_id,
                            KEYBOARD, backToMainMenuKeyboard()
                        );
                    }
                } else {
                    callMethod('answerCallbackQuery',
                        'callback_query_id', $callback_id,
                        TEXT_TAG, $answer,
                        'show_alert', true
                    );
                }
                exit();

            case IA_LIST_FAVORITES:
                if(($answer = validateInlineData($params, 'fav')) !== null)
                    break;

                $favs = getFavoritesList($user_id);
                $fav_id = $params['fav'];
                $keyboard_options = array();
                if ($fav_id > 0) {
                    $keyboard_options[] = array(TEXT_TAG => 'قبلی', CALLBACK_DATA => DB_TABLE_FAVORITES . RELATED_DATA_SEPARATOR . ($fav_id - 1));
                }

                if (($fav_id + 1) * MAX_LINKED_LIST_LENGTH < count($favs)) {
                    $keyboard_options[] = array(TEXT_TAG => 'بعدی', CALLBACK_DATA => DB_TABLE_FAVORITES . RELATED_DATA_SEPARATOR . ($fav_id + 1));
                }

                $keyboard = array(INLINE_KEYBOARD => array($keyboard_options));
                $answer = createLinkedList($favs, $fav_id);
                break;
            case IA_GET_BOOKLET:
            case IA_GET_SAMPLE:
                if (($answer = validateInlineData($params, "sel")) !== null) {
                    break;
                }

                $downloads = 0;
                $selections = $params;
                $choice = $selections['id'];
                $course_id = $state['cr'];

                if ($action === IA_GET_BOOKLET) {
                    $teacher_id = $state['tc'];
                    $filter = $choice >= 0
                        ? DB_ITEM_ID . "=$choice"
                        : DB_ITEM_TEACHER_ID . "=$teacher_id" . ' AND ' . DB_ITEM_COURSE_ID . "=$course_id";
                    $items = getBooklets($filter, true);
                    $teacher = $items[0]['teacher'] ?? null;
                    $course = $items[0]['course'] ?? null;
                    $get_caption = fn(array $item) => $item[DB_BOOKLETS_INDEX] . ': ' . $item[DB_BOOKLETS_CAPTION];
                    $answer = "جزوه (ها)ی انتخابی درس $course - استاد $teacher:\n";
                } else {
                    $filter = $choice >= 0
                        ? DB_ITEM_ID . "=$choice"
                        : DB_ITEM_COURSE_ID . "=$course_id";
                    $items = getSamples($filter, true);
                    $course = $items[0]['course'] ?? null;
                    $get_caption = fn(array $item) => $item[DB_ITEM_NAME];
                    $answer = "نمونه سوالات درس $course:\n";
                }

                if (count($items)) { // at least has one booklet
                    foreach ($items as &$item) {
                        $downloads += $item[DB_ITEM_DOWNLOADS];
                        callMethod(
                            'send' . ucfirst($item[DB_ITEM_FILE_TYPE]),
                            CHAT_ID, $chat_id,
                            $item[DB_ITEM_FILE_TYPE], $item[DB_ITEM_FILE_ID],
                            CAPTION_TAG, $get_caption($item)
                        );
                    }
                }
                if (isSuperior($user)) {
                    $answer = appendStatsToMessage($answer, $downloads);
                }

                resetAction($user_id);
                break;

            case IA_DOWNGRADE_USER:
                // TODO: Check What piece of codes are using this? Is teacher downgrading TA, or admin downgrading teacher with this?
                if($user[DB_USER_MODE] == GOD_USER || $user[DB_USER_MODE] == ADMIN_USER) {
                    if(($answer = validateInlineData($params, 'crs')) !== null)
                        break;
                    if (downgradeUser($params['admin'])) {
                        $answer = 'کاربر موردنظر به دسترسی عادی بازگشت!';
                    } else {
                        $answer = 'مشکلی حین تغییر کاربری پیش آمد. لطفا دوباره تلاش کن!';
                    }
                } else {
                    $answer = 'شما مجوز انجام چنین کاری را ندارید!';
                }
                resetAction($user_id);
                break;

            case IA_UPLOAD_NEXT_SAMPLE:
                if(($answer = validateInlineData($params, 'crs')) !== null)
                    break;
                $answer = 'نمونه سوال بعدی را همراه با کپشن بفرست:';
                if (!setActionAndCache($user_id, ACTION_SENDING_SAMPLE_FILE, $params['crs'])) {
                    $answer = 'مشکلی حین ورود به حالت آپلود نمونه سوال بعدی پیش آمد! لحظاتی دیگر دوباره تلاش کنید...';
                }

                break;

            case IA_CONTACT_TEACHER:
                if(($answer = validateInlineData($params, 'user')) !== null)
                    break;
                if (setActionAndCache($user_id, ACTION_WRITE_MESSAGE, $params['teacher'])) {
                    $answer = 'متن خود را در قالب یک پیام ارسال کنید.📝';
                    callMethod(
                        METH_SEND_MESSAGE,
                        TEXT_TAG, $answer,
                        CHAT_ID, $chat_id,
                        KEYBOARD, backToMainMenuKeyboard()
                    );
                    callMethod(METH_DELETE_MESSAGE,
                        MESSAGE_ID_TAG, $message_id,
                        CHAT_ID, $chat_id
                    );
                    exit();
                } else {
                    $answer = 'حین ورود به حالت ارسال پیام مشکلی پیش آمد. لطفا دوباره تلاش کن!';
                    resetAction($user_id);
                }
                break;
            default:
                $answer = "دستور موردنظر شناسایی نشد!";
                break;
        }
    } else if ($user[DB_USER_ACTION] == ACTION_SET_BOOKLET_CAPTION) {
        // FIXME: This can be moved to witch-case too. for now i leave it like this
        if (!$raw_data) {
            $answer = backupBooklet($user);
            if (!$answer) {
                $answer = 'کپشن فایل به عنوان کپشن جزوه ثبت شد!';
                callMethod(METH_SEND_MESSAGE,
                    CHAT_ID, $chat_id,
                    MESSAGE_ID_TAG, $message_id,
                    TEXT_TAG, "حالا جزوه بعدی رو بفرست: \nنکته: برای اتمام فرایند آپلود جزوات این درس از گزینه بازگشت به منو استفاده کنید یا روی دستور زیر کلیک کنید:\n /cancel",
                    KEYBOARD, backToMainMenuKeyboard()
                );
            } else {
                resetAction($user_id);
            }

        } else {
            $answer = 'کپشن موردنظرتو وارد کن:';
        }

    } else if ($user[DB_USER_ACTION] == ACTION_SET_SAMPLE_TITLE) {
        // FIXME: This can be moved to witch-case too. for now i leave it like this
        if (!$raw_data) {
            $params = explode(RELATED_DATA_SEPARATOR, $user[DB_USER_CACHE]);
            if (isset($params[1])) {
                $answer = backupSample($params[1]);
            } else {
                $answer = 'مشکلی حین ارسال نمونه سوال به کانال بک آپ پیش آمد!';
            }

            if (!$answer) {
                $answer = 'کپشن فایل به جای عنوان نمونه سوال ثبت شد!';
                $keyboard = array(
                    INLINE_KEYBOARD => array(array(array(TEXT_TAG => 'آپلود نمونه سوال بعدی درس',
                        CALLBACK_DATA => 'ns' . RELATED_DATA_SEPARATOR . $params[0]))),
                );
                callMethod(
                    METH_SEND_MESSAGE,
                    CHAT_ID, $chat_id,
                    TEXT_TAG, 'خب چی بکنیم؟',
                    KEYBOARD, getMainMenu($user[DB_USER_MODE])
                );
            }
        } else {
            $answer = 'دستور موردنظر اشتباه است!';
        }

        resetAction($user_id);
    } else if ($user[DB_USER_ACTION] == ACTION_SELECT_BOOKLET_TO_EDIT) {
        $edit_type = $user[DB_USER_CACHE];
        $booklets = Database::getInstance()->query('SELECT * FROM ' . DB_TABLE_BOOKLETS . ' WHERE ' . $data . ' LIMIT 1');
        if ($booklets && count($booklets)) {
            if ($edit_type == ACTION_EDIT_BOOKLET_CAPTION) {
                $answer = "کپشن کنونی:\n" . $booklets[0][DB_BOOKLETS_INDEX] . ': ' . $booklets[0][DB_BOOKLETS_CAPTION] . "\n\nکپشن جدید را وارد کنید:";
                if (!setActionAndCache($user_id, ACTION_SET_BOOKLET_CAPTION, $booklets[0][DB_ITEM_ID])) {
                    $answer = 'حین ورود به حالت ویرایش کپشن مشکلی پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
                    resetAction($user_id);
                }
            } else {
                // file edit
                $answer = 'فایل جدید را ارسال کنید:';
                if (!setActionAndCache($user_id, ACTION_EDIT_BOOKLET_FILE, $booklets[0][DB_ITEM_ID])) {
                    $answer = 'حین ورود به حالت ویرایش فایل مشکلی پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
                    resetAction($user_id);
                }
            }
        } else {
            $answer = 'جزوه مورد نظر در دیتابیس موجود نبود.';
            resetAction($user_id);
        }
    } else {
        $params = explode(RELATED_DATA_SEPARATOR, $data);
        if (count($params) === 2) {
            // second menus: courses/teachers or yes/no menu for removing admins;
            // or no second menu (upload sample)
            switch ($params[0]) {
                case DB_TABLE_TEACHERS:
                    switch ($user[DB_USER_ACTION]) {
                        case ACTION_LINK_TEACHER:
                            $answer = 'یوزرنیم استاد مورد نظر را وارد کنید یا یک پیام از او داخل ربات فوروارد کنید:';
                            if (!updateActionCache($user_id, $params[1])) {
                                $answer = 'مشکلی حین ورود به حالت لینک اکانت استاد پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
                            }

                            break;
                        case ACTION_INTRODUCE_TEACHER:
                            $answer = "حالا متن معرفی استاد رو تایپ کنید. همچنین میتونی داخل متن لینک ویدیو هم قرار بدی. \n درصورتی که میخواهید معرفی نامه استاد را حذف کنید کافی ست کاراکتر خط تیره `-` را ارسال کنید.";
                            if (!updateActionCache($user_id, $params[1])) {
                                $answer = 'حین ورود به حالت دریافت متن معرفی مشکل پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                        case ACTION_SEE_TEACHER_BIOS:
                            $answer = getTeachersField($params[1], DB_TEACHER_BIO);
                            resetAction($user_id);
                            break;
                        default:
                            $answer = 'از بین دروس ارائه شده توسط این استاد درس مورد نظر خود را انتخاب کنید:';
                            if ($user[DB_USER_ACTION] == ACTION_DOWNLOAD_BOOKLET) {
                                $keyboard = createCategoricalMenu(DB_TABLE_COURSES, $data, DB_ITEM_TEACHER_ID . "=$params[1]",
                                    DB_ITEM_COURSE_ID, $user[DB_USER_CACHE] ? ORDER_BY_MOST_DOWNLOADED_BOTH : ORDER_BY_NAME
                                );
                                if (isSuperior($user) && $keyboard) {
                                    $answer = appendStatsToMessage($answer, getDownloadStatistics($params[1]));
                                }

                            } else {
                                $keyboard = createCategoricalMenu(DB_TABLE_COURSES, $data);
                            }

                            if (!$keyboard) //means there is no option to select because of filtering
                            {
                                $answer = 'موردی یافت نشد!';
                            }

                            break;
                    }
                    break;

            }
        } else {
            $answer = 'گزینه انتخاب شده اشتباه است!';
            resetAction($user_id);
        }

    }
    if ($keyboard) {
        callMethod(METH_EDIT_MESSAGE,
            CHAT_ID, $chat_id,
            MESSAGE_ID_TAG, $message_id,
            TEXT_TAG, $answer,
            KEYBOARD, $keyboard
        );
    } else {
        callMethod(METH_EDIT_MESSAGE,
            CHAT_ID, $chat_id,
            MESSAGE_ID_TAG, $message_id,
            TEXT_TAG, $answer
        );
    }

}
