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
    $callback_data = $update[CALLBACK_QUERY]['data'];
    $text = $update[CALLBACK_QUERY]['message']['text'];
    $answer = null;
    $keyboard = null;
    $username = $update['message']['from']['username'] ?? null;
    $user = getUser($user_id, $username);

    $callback_data = json_decode($callback_data, true);

    $action = $callback_data['a'] ?? null; // TODO: => must be action
    $extra = $callback_data['x'] ?? null;
    $data = $callback_data['d'] ?? null;
    switch ($action) {
        case IA_LIST_BOOKLETS:
            if (!isset($data['t']) || !isset($data['c'])) {
                $answer = isset($data['c']) ? 'از بین اساتید ارائه کننده این درس استاد مورد نظر خود را انتخاب کنید:'
                    : 'از بین درس های ارایه شده توسط استاد یکی را انتخاب کنید:';
                $keyboard = createCategoricalMenu(
                    IA_LIST_BOOKLETS,
                    null,
                    $data,
                    true,
                    $extra ?? ORDER_BY_NAME
                );

                if (isSuperior($user) && $keyboard)
                    $answer = appendStatsToMessage($answer, getDownloadStatistics(null, $data[1]));

                break;
            } else {
                $categories = extractCategories($data, $extra);

                if (isset($categories['err'])) {
                    $answer = $categories['err'];
                    break;
                }
                if ($categories['options'] == 0 || $categories['options'] == 1) {
                    $booklets = getBooklets(
                        $categories[DB_ITEM_TEACHER_ID],
                        $categories[DB_ITEM_COURSE_ID]
                    );

                    if (!isset($booklets[0])) {
                        $answer = 'هنوز جزوه ای آپلود نشده!';
                        break;
                    }
                    // if there is some booklets
                    $answer = 'استاد ' . $booklets[0]['teacher'] . ' - ' . $booklets[0]['course'] . "\n\n جزوه ی موردنظرتو از لیست زیر انتخاب کن:";

                    $keyboard = createSessionsMenu(IA_GET_BOOKLET, $booklets, $categories);
                    if (isSuperior($user)) {
                        $downloads = 0;
                        foreach ($booklets as &$booklet) {
                            $downloads += $booklet[DB_ITEM_DOWNLOADS];
                        }

                        $answer = appendStatsToMessage($answer, $downloads);
                    }
                } else {
                    $answer = 'طبقه بندی جزوه ها بر اساس:';
                    $keyboard = createClassifyByMenu($user_id, $categories, $callback_data);
                }
            }
            break;

        case IA_GET_BOOKLET:
        case IA_GET_SAMPLE:
            $downloads = 0;

            if ($action === IA_GET_BOOKLET) {
                $choice = $data['b'];
                $course_id = $data['c'] ?? null;
                $teacher_id = $data['t'] ?? null;
                $items = getBooklets($teacher_id, $course_id, $choice, true);
                $teacher = $items[0]['teacher'] ?? null;
                $course = $items[0]['course'] ?? null;
                $get_caption = fn(array $item) => $item[DB_BOOKLETS_INDEX] . ': ' . $item[DB_BOOKLETS_CAPTION];
                $answer = "جزوه (ها)ی انتخابی درس $course - استاد $teacher:\n";
            } else {
                $choice = $data['sm'];
                $course_id = $data['c'] ?? null;
                $items = getSamples($course_id, $choice, true);
                $course = $items[0]['course'] ?? null;
                $get_caption = fn(array $item) => $item[DB_ITEM_NAME];
                $answer = "نمونه سوالات درس $course:\n";
            }

            foreach ($items as &$item) {
                if (isSuperior($user))
                    $downloads += $item[DB_ITEM_DOWNLOADS];
                callMethod(
                    'send' . ucfirst($item[DB_ITEM_FILE_TYPE]),
                    CHAT_ID,
                    $chat_id,
                    $item[DB_ITEM_FILE_TYPE],
                    $item[DB_ITEM_FILE_ID],
                    CAPTION_TAG,
                    $get_caption($item)
                );
            }
            if (isSuperior($user))
                $answer = appendStatsToMessage($answer, $downloads);

            break;
        case IA_LIST_FAVORITES:
            $favs = getFavoritesList($user_id);
            $page = $data['pg'];
            $keyboard_options = array();
            if ($page > 0) {
                $keyboard_options[] = [TEXT_TAG => 'قبلی', CALLBACK_DATA => jsonifyCallbackData(IA_LIST_FAVORITES, ['pg' => ($page - 1)])];
            }

            if (($page + 1) * LINKED_LIST_PAGE_LENGTH < count($favs)) {
                $keyboard_options[] = [TEXT_TAG => 'بعدی', CALLBACK_DATA => jsonifyCallbackData(IA_LIST_FAVORITES, ['pg' => ($page + 1)])];
            }

            $keyboard = [INLINE_KEYBOARD => [$keyboard_options]];
            $answer = createLinkedList($favs, $page);
            break;
        case IA_UPLOAD_BOOKLET:
        case IA_EDIT_BOOKLET_CAPTION:
        case IA_EDIT_BOOKLET_FILE:
            if (!isSuperior($user)) {
                $answer = 'شما اجازه انجام چنین کاری را ندارید!';
                break;
            }

            if (!isset($data['t']) || !isset($data['c'])) {
                $keyboard = createCategoricalMenu($action, null, $data, $action !== IA_UPLOAD_BOOKLET);
                $answer = $data['e'] === 'c' ? 'از بین اساتید ارائه کننده این درس استاد مورد نظر خود را انتخاب کنید:'
                    : 'از بین درس های ارایه شده توسط استاد یکی را انتخاب کنید:';

            } else if ($action === IA_UPLOAD_BOOKLET) {
                // bot categories are selected:
                // the if below, sets user action and its cache to prepare for getting the booklet
                $categories = extractCategories($data);
                if (setActionAndCache($user_id, ACTION_SENDING_BOOKLET_FILE, json_encode($categories))) {
                    $answer = 'جزوه مورد نظرت رو همراه با کپشن بفرست:';
                    callMethod(
                        METH_SEND_MESSAGE,
                        CHAT_ID,
                        $chat_id,
                        MESSAGE_ID_TAG,
                        $message_id,
                        TEXT_TAG,
                        $answer,
                        KEYBOARD,
                        backToMainMenuKeyboard()
                    );
                    callMethod(
                        'answerCallbackQuery',
                        'callback_query_id',
                        $callback_id,
                        TEXT_TAG,
                        'فرایند آپلود جزوات این درس آغاز شد.',
                        'show_alert',
                        false
                    );
                    exit();
                }
                $answer = 'مشکلی حین ثبت اطلاعات پیش آمده. لطفا از اول تلاش کن :|';
            } else {
                if (!isset($data['b'])) {
                    // it's on the Categorized by menu:
                    $categories = extractCategories($data, $extra);
                    if (isset($categories['err'])) {
                        $answer = $categories['err'];
                        break;
                    }

                    if ($categories['options'] == 0 || $categories['options'] == 1) {
                        $booklets = getBooklets(
                            $categories[DB_ITEM_TEACHER_ID],
                            $categories[DB_ITEM_COURSE_ID]
                        );

                        if (isset($booklets[0])) {
                            // if there is some booklets
                            $answer = 'استاد ' . $booklets[0]['teacher'] . ' - ' . $booklets[0]['course'] . "\n\n جزوه ی موردنظرتو از لیست زیر انتخاب کن:";
                            $keyboard = createSessionsMenu($action, $booklets, $categories, false);
                        } else {
                            $answer = 'هنوز جزوه ای آپلود نشده!';
                        }
                    } else {
                        // if Liked a booklet, or its the first time reaching this case
                        $answer = 'طبقه بندی جزوه ها بر اساس:';
                        $keyboard = createClassifyByMenu($user_id, $categories, $callback_data);
                    }
                } else {
                    $booklets = Database::getInstance()->query('SELECT * FROM ' . DB_TABLE_BOOKLETS . ' WHERE ' . $data . ' LIMIT 1');
                    if ($booklets && count($booklets)) {
                        if ($action == IA_EDIT_BOOKLET_CAPTION) {
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
                    }
                }
            }
            break;
        case IA_SET_CAPTION:
            $use_file_caption = $data['def'] ?? false;
            $is_booklet = $data['e'] !== 'sm'; // for sample key 's' is set
            $file_category_name = $is_booklet ? 'جزوه' : 'نمونه سوال';

            if (!$use_file_caption) {
                $answer = 'کپشن موردنظرتو وارد کن:';
                break;
            }
            $answer = $is_booklet ? backupBooklet($user) : backupSample($user);

            if ($answer) {
                resetAction($user_id);
                break;
            }

            $answer = "کپشن فایل به عنوان کپشن $file_category_name ثبت شد!";

            callMethod(
                METH_SEND_MESSAGE,
                CHAT_ID,
                $chat_id,
                MESSAGE_ID_TAG,
                $message_id,
                TEXT_TAG,
                "حالا $file_category_name بعدی رو بفرست: \nنکته: برای اتمام فرایند آپلود $file_category_name های این درس از گزینه بازگشت به منو استفاده کنید یا روی دستور زیر کلیک کنید:\n /cancel",
                KEYBOARD,
                backToMainMenuKeyboard()
            );

            break;
        case IA_UPLOAD_SAMPLE:
            if (!isSuperior($user)) {
                $answer = 'شما اجازه انجام چنین کاری را ندارید!';
                break;
            }
            if (!isset($data['c']) || $data['c'] < 0) {
                $answer = '.برای آپلود نمونه سوال باید درس مربوطه از منو انتخاب شود. متاسفانه شما این مرحله را به درستی طی نکرده اید. لطفا دوباره تلاش کنید..';
                break;
            }

            $answer = 'نمونه سوال مورد نظر خود را همراه با کپشن بفرست:';
            $sample_data = [DB_ITEM_COURSE_ID => $data['c']];
            if (!setActionAndCache($user_id, ACTION_SENDING_SAMPLE_FILE, json_encode($sample_data))) {
                $answer = 'مشکلی حین ورود به حالت آپلود نمونه سوال پیش آمد! لحظاتی دیگر دوباره تلاش کنید...';
            }

            break;
        case IA_LIST_SAMPLES:
            if (!isset($data['c']) || $data['c'] < 0) {
                $answer = 'برای مشاهده لیست نمونه سوالات هر درس باید درس مربوطه را انتخاب کنید وای به نظر می رسد به دلیلی نامعلوم درسی انتخاب نشده است! لطفا دوباره تلاش کنید ...';
                break;
            }

            $samples = getSamples($data['c']);
            if (!isset($samples[0])) {
                $answer = 'هنوز نمونه سوالی توسط ادمین ها ثبت نشده است!';
                break;
            }
            // if there is some booklets
            $answer = 'نمونه سوال موردنظر خود را از لیست زیر انتخاب کن:';
            $keyboard = createSamplesMenu(IA_GET_SAMPLE, $samples, $data['c']);
            if (isSuperior($user)) {
                $downloads = 0;
                foreach ($samples as &$sample) {
                    $downloads += $sample[DB_ITEM_DOWNLOADS];
                }

                $answer = appendStatsToMessage($answer, $downloads);
            }

            break;

        case IA_SHOW_MESSAGE:
            // user wants to see fc message

            callMethod(
                METH_COPY_MESSAGE,
                MESSAGE_ID_TAG,
                $data['m'],
                CHAT_ID,
                $chat_id,
                'from_chat_id',
                $data['fc'],
                'reply_to_message_id',
                $data['r2']
            );
            callMethod(
                METH_DELETE_MESSAGE,
                MESSAGE_ID_TAG,
                $message_id,
                CHAT_ID,
                $chat_id
            ); // remove the show message box

            exit(); // I did this because we dont want to edit this message an drmeove the "SHOW" button!

        case IA_REPLY_MESSAGE:
            if (!$answer) {
                // user is attempting to answer a message
                if (setActionAndCache($user_id, ACTION_WRITE_REPLY_TO_USER, $data['m'])) {
                    $answer = 'پاسخ خودتو بنویس: (لغو /cancel)';
                    if (isMessageAnswered($data['m'])) {
                        callMethod(
                            'answerCallbackQuery',
                            'callback_query_id',
                            $callback_id,
                            TEXT_TAG,
                            'این پیام قبلا پاسخ داده شده است!',
                            'show_alert',
                            true
                        );
                    }

                    callMethod(
                        METH_SEND_MESSAGE,
                        CHAT_ID,
                        $chat_id,
                        TEXT_TAG,
                        $answer,
                        'reply_to_message_id',
                        $message_id,
                        KEYBOARD,
                        backToMainMenuKeyboard()
                    );
                }
            } else {
                callMethod(
                    'answerCallbackQuery',
                    'callback_query_id',
                    $callback_id,
                    TEXT_TAG,
                    $answer,
                    'show_alert',
                    true
                );
            }
            exit();

        case IA_DOWNGRADE_ADMIN:
            // TODO: Check What piece of codes are using this? Is teacher downgrading TA, or admin downgrading teacher with this?
            if (isSuperior($user)) {

                if (downgradeUser($data['u']))
                    $answer = 'کاربر موردنظر به دسترسی عادی بازگشت!';
                else
                    $answer = 'مشکلی حین تغییر کاربری پیش آمد. لطفا دوباره تلاش کن!';
            } else {
                $answer = 'شما مجوز انجام چنین کاری را ندارید!';
            }
            break;

        case IA_CONTACT_TEACHER:
            if (setActionAndCache($user_id, ACTION_WRITE_MESSAGE, $data['u'])) {
                $answer = 'متن خود را در قالب یک پیام ارسال کنید.📝';
                callMethod(
                    METH_SEND_MESSAGE,
                    TEXT_TAG,
                    $answer,
                    CHAT_ID,
                    $chat_id,
                    KEYBOARD,
                    backToMainMenuKeyboard()
                );
                callMethod(
                    METH_DELETE_MESSAGE,
                    MESSAGE_ID_TAG,
                    $message_id,
                    CHAT_ID,
                    $chat_id
                );
                exit();
            } else {
                $answer = 'حین ورود به حالت ارسال پیام مشکلی پیش آمد. لطفا دوباره تلاش کن!';
                resetAction($user_id);
            }
            break;
        case IA_SELECT_TEACHER_OPTIONS:
            switch ($data['op']) {
                case 'lnk':
                    if (!isSuperior($user)) {
                        $answer = 'شما مجاز به انجام چنین عملی نیستید!';
                        break;
                    }
                    $answer = 'یوزرنیم استاد مورد نظر را وارد کنید یا یک پیام از او داخل ربات فوروارد کنید:';
                    if (!setActionAndCache($user_id, ACTION_LINK_TEACHER, $data['t'])) {
                        $answer = 'مشکلی حین ورود به حالت لینک اکانت استاد پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
                    }
                    break;
                case 'int':
                    if (!isSuperior($user)) {
                        $answer = 'شما مجاز به انجام چنین عملی نیستید!';
                        break;
                    }
                    $answer = "حالا متن معرفی استاد رو تایپ کنید. همچنین میتونی داخل متن لینک ویدیو هم قرار بدی. \n درصورتی که میخواهید معرفی نامه استاد را حذف کنید کافی ست کاراکتر خط تیره `-` را ارسال کنید.";
                    if (!setActionAndCache($user_id, ACTION_INTRODUCE_TEACHER, $data['t'])) {
                        $answer = 'حین ورود به حالت دریافت متن معرفی مشکل پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
                        resetAction($user_id);
                    }
                    break;
                case 'bio':
                    $answer = getTeachersField($data['t'], DB_ITEM_BIO) ?? 'هیچ معارفه ای برای این استاد ارائه نشده است.';
                    break;
                default:
                    $answer = 'گزینه انتخاب شده حاوی داده اشتباه است. لطفا مجددا از نو تلاش کنید...';
                    break;
            }
            break;

        case IA_REMOVE_TA:
            if (!isset($data['u'])) {
                $answer = 'مشکلی در روند حذف TA موردنظر پیش آمد. لطفا دوباره تلاش کنید و در صورت مواجهه مجدد با این پیام مشکل را با واحد پشتیبانی در میان بگذارید.';
                break;
            }

            $target_user = getUser($data['u']); // the user that is removing from TA list.
            if (
                ($user[DB_USER_MODE] == TEACHER_USER && $user[DB_ITEM_TEACHER_ID] === $target_user[DB_ITEM_TEACHER_ID])
                || isSuperior($user)
            ) {
                if (downgradeUser($data['u']))
                    $answer = 'کاربر انتخابی شما از لیست اساتید حل تمرین شما حذف شد.';
                else
                    $answer = 'مشکلی حین تغییر کاربری پیش آمد. لطفا دوباره تلاش کنید!';
            }
            break;
        case IA_CHECK_MEMBERSHIP:
            $answer = 'مرسی که عضو کانال های ما شدی :)';
            callMethod(
                METH_SEND_MESSAGE,
                CHAT_ID,
                $chat_id,
                TEXT_TAG,
                'چه کاری میتونم برات انجام بدم؟',
                KEYBOARD,
                getMainMenu($user[DB_USER_MODE])
            );
            break;
        default:
            $answer = "دستور موردنظر شناسایی نشد!";
            break;
    }


    if ($keyboard) {
        callMethod(
            METH_EDIT_MESSAGE,
            CHAT_ID,
            $chat_id,
            MESSAGE_ID_TAG,
            $message_id,
            TEXT_TAG,
            $answer,
            KEYBOARD,
            $keyboard
        );
    } else {
        callMethod(
            METH_EDIT_MESSAGE,
            CHAT_ID,
            $chat_id,
            MESSAGE_ID_TAG,
            $message_id,
            TEXT_TAG,
            $answer
        );
    }
}
