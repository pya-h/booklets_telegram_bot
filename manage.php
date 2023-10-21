<?php
require_once './telegram_api.php';
require_once './database.php';
require_once './user.php';
require_once  './menu.php';
require_once './booklet.php';

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
    switch($user[DB_USER_ACTION]) {
        case ACTION_WHISPER_GODS_NAME:
            if($whisper === GOD_NAME) {
                $answer = 'God\'s Secret:';
                if(!updateAction($user[DB_USER_ID], ACTION_WHISPER_GODS_SECRET)) {
                    $answer = 'خطای غیرمنتظره پیش اومد! دوباره تلاش کن!';
                    resetAction($user[DB_USER_ID]);
                }
            }
            break;
        case ACTION_WHISPER_GODS_SECRET:
            if($whisper === GOD_SECRET && !isGodEnough()) {
                if(!updateUserMode($user[DB_USER_ID], GOD_USER))
                    $answer = 'خطایی حین ثبت اطلاعات پیش اومد. دوباره تلاش کن!';
                $user[DB_USER_MODE] = GOD_USER; // update the old user object
                resetAction($user[DB_USER_ID]);
                $answer = 'Now you\'re God Almighty :)!';
            }
            break;
    }
    return $answer;
}

function &startUpgradingUser($user_id, array &$message, int $mode, string $position_title, $teacher_id=null): string {
    $response = '';
    if(isset($message['forward_from'])) {
        $predefined_name = $mode != TEACHER_USER ? null : getTeachersName($teacher_id);
        $target_id = $message['forward_from']['id'];
        if(updateUserMode($target_id, $mode, $teacher_id, $predefined_name)) {
            $response = "اکانت موردنظر بعنوان $position_title ثبت شد!";
            
            // notify the target user
            callMethod(METH_SEND_MESSAGE,
                CHAT_ID, $target_id,
                TEXT_TAG, "تبریک! اکانت شما به دسترسی $position_title ارتقا پیدا کرد.",
                KEYBOARD, getMainMenu($mode)
            );
            if($mode != TEACHER_USER) { // teacher has predefined name
                // other modes take their related enitity's name
                if(setActionAndCache($user_id, ACTION_ASSIGN_USER_NAME, $target_id)) {
                    $response .= ' اسم کاربر مورد نظر را وارد کنید:';
                } else {
                    $response .= ' اما حین ورود به حالت تعیین اسم مشکلی پیش اومد!';
                    resetAction($user_id);
                }
            } else resetAction($user_id);
        } else {
            $response = "متاسفانه مشکلی حین ثبت اکانت بعنوان $position_title پیش اومده. لطفا دوباره تلاش کن!";
            resetAction($user_id);
        }
    } else {
        $response = "اکانت موردنظر حالت مخفی رو فعال کرده. برای ارتقا یافتن به $position_title باید موقتا این حالت رو غیرفعال کنه!";
        resetAction($user_id);
    }
    return $response;
}

function handleCasualMessage(&$update) {
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
    $user = getUser($user_id);
    $message = $update['message'];
    $message_id = $update['message'][MESSAGE_ID_TAG];
    $data = $message[TEXT_TAG] ?? null;

    if($data) {
        // most common options
        switch($data) {
            case '/start':
                $response = 'خب! چه کاری میتونم برات انجام بدم؟';
                resetAction($user_id);
                break;
            case '/cancel':
                resetAction($user_id);
                $response = 'لغو شد!';
                break;
            case CMD_MAIN_MENU:
                $response = 'خب! چی بکنیم؟';
                resetAction($user_id);
                break;
            default:
                if($user[DB_USER_ACTION] == ACTION_WRITE_MESSAGE) {
                    $target_group_id = $user[DB_USER_ACTION_CACHE] ?? null; // null => admins, int => teachers and their TAs
                    saveMessage($user_id, $message_id, $target_group_id);
                    if($target_group_id) {
                        $targets = getTeacherGroup($target_group_id);
                        $group_name = 'این استاد یا دستیار حل تمرین وی';
                    } else {
                        $targets = getSuperiors();
                        $group_name = 'تیم پشتیبانی';
                    }
        
                    foreach($targets as $target) {
                        callMethod(
                            METH_FORWARD_MESSAGE,
                            CHAT_ID, $target[DB_USER_ID],
                            'from_chat_id', $chat_id,
                            MESSAGE_ID_TAG, $message_id
                        );
                        callMethod(METH_SEND_MESSAGE,
                            CHAT_ID, $target[DB_USER_ID],
                            TEXT_TAG, 'برای پاسخ به پیام بالا میتونی از گزینه زیر استفاده کنی',
                            KEYBOARD, array(
                                INLINE_KEYBOARD => array(
                                    array(array(TEXT_TAG => 'پاسخ', CALLBACK_DATA => DB_TABLE_MESSAGES . DATA_JOIN_SIGN . $message_id))
                                )
                            )
                        );
                    }
                    $response = "پیام شما با موفقیت ارسال شد✅ \n در صورت لزوم، $group_name پاسخ را از طریق همین بات به شما اعلام خواهد کرد.";
                    resetAction($user_id);
                } else if($user[DB_USER_ACTION] == ACTION_WRITE_REPLY_TO_USER && $user[DB_USER_MODE] != NORMAL_USER) {
                    $msg = getMessage($user[DB_USER_ACTION_CACHE]);
                    $answer_made_by = 'ادمین';
                    if($user[DB_USER_MODE] == TEACHER_USER) $answer_made_by = 'استاد';
                    else if($user[DB_USER_MODE] == TA_USER) $answer_made_by = 'حل تمرین استاد';
        
                    if($msg) {
                        callMethod(METH_SEND_MESSAGE,
                            CHAT_ID, $msg[DB_MESSAGES_SENDER_ID],
                            TEXT_TAG, "$answer_made_by پیام شما را پاسخ داد.",
                            'reply_to_message_id', $msg[DB_ITEM_ID],
                            KEYBOARD, array(
                                INLINE_KEYBOARD => array(
                                    array(
                                        array(TEXT_TAG => 'مشاهده',
                                            CALLBACK_DATA => 'show' . RELATED_DATA_SEPARATOR . $message_id
                                                . RELATED_DATA_SEPARATOR . $chat_id . RELATED_DATA_SEPARATOR . $msg[DB_ITEM_ID]
                                        )
                                    )
                                )
                            )
                        );
                        markMessageAsAnswered($user[DB_USER_ACTION_CACHE]);
                        $response = 'پاسخ شما با موفقیت ارسال شد.';
                    } else $response = 'چنین پیامی اصلا وجود نداره که بخوای جوابش رو بدی!';
                    
                    resetAction($user_id);
                } else $response = handleGospel($user, $data);
                break;
        }

    }

    $keyboard = null; //getMainMenu($user[DB_USER_MODE]);

    if(!$response) {
        switch($data) {
            case CMD_DOWNLOAD_BOOKLET:
                $response = 'یکی از دسته بندی های زیر را انتحاب کنید:';
                $keyboard = array('resize_keyboard' => true, 'one_time_keyboard' => true,
                    'keyboard' => array(
                        array(CMD_DOWNLOAD_BY_TEACHER),
                        array(CMD_DOWNLOAD_BY_COURSE),
                        array(CMD_MAIN_MENU)
                    )
                );
                break;
            case CMD_GOD_ACCESS:
                if(!isGodEnough()) {
                    $response = 'God\'s Name:';
                    if(!updateAction($user_id, ACTION_WHISPER_GODS_NAME)) {
                        $response = 'خطای غیرمنتظره پیش اومد! دوباره تلاش کن!';
                        resetAction($user_id);
                    }
                }
                break;
            case CMD_DOWNLOAD_BY_COURSE:
                if(updateAction($user_id, ACTION_DOWNLOAD_BOOKLET, true)) {
                    $response = "درس مورد نظر خود را از لیست زیر انتخاب کنید:";
                    $keyboard = createMenu(DB_TABLE_COURSES);
                } else {
                    $response = 'خطای غیرمنتظره پیش اومد! دوباره تلاش کن!';
                    resetAction($user_id);
                }
                break;
            case CMD_DOWNLOAD_BY_TEACHER:
                if(updateAction($user_id, ACTION_DOWNLOAD_BOOKLET, true)) {
                    $response = "استاد مورد نظر خود را از لیست زیر انتخاب کنید:";
                    $keyboard = createMenu(DB_TABLE_TEACHERS);
                } else {
                    $response = 'خطای غیرمنتظره پیش اومد! دوباره تلاش کن!';
                    resetAction($user_id);
                }
                break;
            case CMD_MESSAGE_TO_ADMIN:
                if(updateAction($user_id, ACTION_WRITE_MESSAGE)) {
                    $response = 'متن خود را در قالب یک پیام ارسال کنید.📝';
                    $keyboard = backToMainMenuKeyboard();
                } else {
                    $response = 'حین ورود به حالت ارسال پیام مشکلی پیش اومد. لطفا دوباره تلاش کن!';
                    resetAction($user_id);
                }
                break;
            case CMD_MESSAGE_TO_TEACHER:
                if(updateAction($user_id, ACTION_SELECT_TEACHER_TO_CONTACT)) {
                    $keyboard = createUserList(DB_USER_MODE . '=' . TEACHER_USER, DB_ITEM_TEACHER_ID);
                    if($keyboard)
                        $response = 'استادهای زیر در بات فعال هستند و می توانید به آن ها پیام دهید:';
                    else $response = 'در حال حاضر هیچ استادی در بات فعالیت ندارد!';
                } else $response = 'متاسفانه در حال حاضر ربات قادر به ارسال پیام برای هیچ استادی نیست! لطفا بعدا امتحان کنید.';
                break;
            default:
                $response = null;
                break;
        }
    }

    if(!$response) {
        switch($user[DB_USER_MODE]) {
            // case TEACHER_USER:
            //     // special code
            // case TA_USER:
            // case NORMAL_USER:
                
            //     break;
            case GOD_USER:
                if($data === CMD_ADD_ADMIN) {
                    if(updateAction($user_id, ACTION_ADD_ADMIN)) {
                        $response = 'یک پیام از اکانت موردنظرت فوروارد کن:';
                        $keyboard = backToMainMenuKeyboard();
                    } else {
                        $response = 'مشکلی حین ورود به حالت اضافه کردن ادمین پیش اومده. لطفا دوباره تلاش کن!';
                        resetAction($user_id);
                    }
                    break;
                } else if($user[DB_USER_ACTION] == ACTION_ADD_ADMIN) {
                    $response = startUpgradingUser($user_id, $message, ADMIN_USER, 'ادمین');
                    break;
                } else if($data === CMD_REMOVE_ADMIN) {
                    if(updateAction($user_id, ACTION_REMOVE_ADMIN)) {
                        $keyboard = createUserList(DB_USER_MODE . '=' . ADMIN_USER);
                        if($keyboard)
                            $response = 'روی شخص موردنظرت کلیک کن تا از حالت ادمین خارج بشه:';
                        else $response = 'هیج ادمینی یافت نشد!';
                    } else {
                        $response = 'مشکلی حین ورود به حالت حذف ادمین پیش اومده. لطفا دوباره تلاش کن!';
                        resetAction($user_id);
                    }
                    break;
                }
            case ADMIN_USER:
                // get admin's action value
                if(!$user[DB_USER_ACTION]) {
                    // if action value is none
                    switch($data) {
                        case CMD_UPLOAD_BOOKLET:
                        case CMD_EDIT_BOOKLET_FILE:
                        case CMD_EDIT_BOOKLET_CAPTION:
                            if(updateAction($user_id, $data == CMD_UPLOAD_BOOKLET ? ACTION_UPLOAD_BOOKLET 
                                                        : ($data == CMD_EDIT_BOOKLET_CAPTION ? ACTION_EDIT_BOOKLET_CAPTION : ACTION_EDIT_BOOKLET_FILE))) {
                                $response = 'از لیست زیر درس موردنظرت رو انتخاب کن:';
                                $keyboard = createMenu(DB_TABLE_COURSES);
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کن!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_STATISTICS:
                            $response = "آماره ربات: \n";
                            foreach(getStatistics() as $field=>$stat) {
                                $response .= "{$stat['fa']}: {$stat['total']} \n";
                            }
                            break;
                        case CMD_SEND_POST_TO_CHANNEL:
                            if(updateAction($user_id, ACTION_SEND_POST_TO_CHANNEL)) {
                                $response = 'متن پست مورد موردنظرت رو تایپ کن:';
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کن!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_EDIT_BOOKLET:
                            $response = 'بسیار خب! چیو میخوای ویرایش کنی؟';
                            $keyboard = backToMainMenuKeyboard(array(CMD_EDIT_BOOKLET_CAPTION, CMD_EDIT_BOOKLET_FILE));
                            break;
                        case CMD_ADD_COURSE:
                            if(updateAction($user_id, ACTION_ADD_COURSE)) {
                                $response = 'عنوان درس جدید رو وارد کن:';
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کن!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_ADD_TEACHER:
                            if(updateAction($user_id, ACTION_ADD_TEACHER)) {
                                $response = 'اسم کامل استاد جدید رو وارد کن:';
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کن!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_LINK_TEACHER:
                            if(updateAction($user_id, ACTION_LINK_TEACHER, true)) {
                                $response = "استاد مورد نظر خود را از لیست زیر انتخاب کنید:";
                                $keyboard = createMenu(DB_TABLE_TEACHERS);
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کن!';
                                resetAction($user_id);
                            }
                            break;
                        default:
                            $response = 'دستور مورد نظر صحیح نیست!';
                            resetAction($user_id);
                            break;
                    }
                } else {
                    switch($user[DB_USER_ACTION]) {
                        case ACTION_SENDING_BOOKLET_FILE:
                            $file = getFileFrom($message);
                            $keyboard = backToMainMenuKeyboard();
                            if(!$file) $response = 'هیچ فایلی ارسال نشده. دوباره ارسال کن!';
                            else {
                                $result = addBooklet($user, $file);
                                if(isset($result['err'])) $response = $result['err'];
                                else {
                                    if(setActionAndCache($user_id, ACTION_SET_BOOKLET_CAPTION, $result['id'])) {
                                        $response = 'جزوه مورد نظر با موفقیت ارسال شد. حالا کپشن جزوه رو مشخص کن:';
                                        $keyboard = array(
                                            INLINE_KEYBOARD => array(
                                                array(
                                                    //columns:
                                                    array(TEXT_TAG => 'کپشن فایل', CALLBACK_DATA => 0),
                                                    array(TEXT_TAG => 'وارد کردن کپشن', CALLBACK_DATA => 1)
                                                )
                                            )
                                        );
                                    } else $response = 'جزوه ثبت شد ولی مشکلی حین ورود به حالت تعیین کپشن پیش اومد!';
                                }
                            }
                            break;
                        case ACTION_SET_BOOKLET_CAPTION:
                            $response = backupBooklet($user, $data);
                            if(!$response) {
                                $response = "کپشن موردنظر با موفقیت ثبت شد! حالا جزوه بعدی رو بفرست: \nنکته: برای اتمام فرایند آپلود جزوات این درس از گزینه بازگشت به منو استفاده کنید یا روی دستور زیر کلیک کنید:\n /cancel";
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                resetAction($user_id);
                            }
                            break;
                        case ACTION_EDIT_BOOKLET_FILE:
                            $file = getFileFrom($message);
                            if(!$file) {
                                $response = 'هیچ فایلی ارسال نشده. دوباره ارسال کن!';
                                $keyboard = backToMainMenuKeyboard();
                            } else if(changeBookletFile($user[DB_USER_ACTION_CACHE], $file)){
                                $response = backupBooklet($user);
                                if(!$response) {
                                    $response = "ویرایش فایل این جزوه با موفقیت انجام شد! حالا جزوه بعدی رو بفرست: \nنکته: برای اتمام فرایند آپلود جزوات این درس از گزینه بازگشت به منو استفاده کنید یا روی دستور زیر کلیک کنید:\n /cancel";
                                    $keyboard = backToMainMenuKeyboard();
                                } else {
                                    resetAction($user_id);
                                }
                            }
                            break;
                        case ACTION_SEND_POST_TO_CHANNEL:
                            if($data) {
                                callMethod(
                                    METH_SEND_MESSAGE,
                                    CHAT_ID, FIRST_2_JOIN_CHANNEL_ID,
                                    TEXT_TAG, $data,
                                    KEYBOARD, array(
                                        INLINE_KEYBOARD => array(
                                            array(
                                                array(TEXT_TAG => 'برای دانلود جزوات کلیک کنید', INLINE_URL_TAG => PERSIAN_COLLEGE_BOT_LINK)
                                            )
                                        )
                                    )
                                );
                                $response = 'پست مورد نظر با مورفقیت در کانال قرار گرفت.';
                            } else {
                                $response = 'فقط پیام های متنی پشتیبانی می شوند. پیام های حاوی فایل یا عکس نمی توانند لینک شیشه ای داشته باشند.';
                            }
                            resetAction($user_id);
                            break;
                        case ACTION_ADD_COURSE:
                            $result = addCategory(DB_TABLE_COURSES, $data, $user_id);
                            $response = $result ? "درس جدید با ایدی $result موفقیت ثبت شد!"
                                                : "خطایی هنگام ثبت بوجود آمد. لطفا دوباره نام رو وارد کن.";
                            break;
                        case ACTION_ADD_TEACHER:
                            $result = addCategory(DB_TABLE_TEACHERS, $data, $user_id);
                            $response = $result ? "استاد جدید با ایدی $result موفقیت ثبت شد!"
                                : "خطایی هنگام ثبت بوجود آمد. لطفا دوباره نام رو وارد کن.";
                            break;
                        case ACTION_LINK_TEACHER:
                            if($user[DB_USER_ACTION_CACHE])
                                $response = startUpgradingUser($user_id, $message, TEACHER_USER, 'استاد', $user[DB_USER_ACTION_CACHE]);
                            else {
                                $response = 'ابتدا باید استاد موردنظر از لیست اساتید انتخاب شود. اگر لیستی مشاهده میکنید لطفا دوباره روی گزینه ' .
                                    CMD_LINK_TEACHER . ' کلیک کنید.';
                                resetAction($user_id);
                            }
                            break;
                        case ACTION_ASSIGN_USER_NAME:
                            // set message text as the name for the admin
                            // cache is the target user id
                            $response = assignUserName($user[DB_USER_ACTION_CACHE], $data) ? 'اسم کاربر با موفقیت ثبت شد.'
                                : 'مشکلی در ثبت اسم کاربر پیش اومد!';
                            resetAction($user_id);
                            break;
                        default:
                            $response = 'عملیات موردنظر تعریف نشده است!';
                            resetAction($user_id);
                            break;
                    }
                }
                break;
        }
    }
    if(!$response) {
        $response = 'متوجه نشدم! لطفا دوباره تلاش کنید...';
        resetAction($user_id);
    }

    callMethod(
        METH_SEND_MESSAGE,
        CHAT_ID, $chat_id,
        TEXT_TAG, $response,
        'reply_to_message_id', $message_id,
        KEYBOARD, $keyboard ?? getMainMenu($user[DB_USER_MODE])
    );
}

function handleCallbackQuery(&$update) {
    $callback_id = $update[CALLBACK_QUERY]['id'];
    $chat_id = $update[CALLBACK_QUERY]['message']['chat']['id'];
    $message_id = $update[CALLBACK_QUERY]['message'][MESSAGE_ID_TAG];
    $user_id = $update[CALLBACK_QUERY]['from']['id'];
    $raw_data = $update[CALLBACK_QUERY]['data'];
    $text = $update[CALLBACK_QUERY]['message']['text'];
    $answer = null;
    $keyboard = null;
    $user = getUser($user_id);
    $data = strtolower($raw_data);
    if($raw_data == -1) {
        // check membership is ok
        // because if it wasn't ok, this function couldn't be called
        $answer = 'مرسی که عضو کانال های ما شدی :)';
        callMethod(METH_SEND_MESSAGE,
            CHAT_ID, $chat_id,
            TEXT_TAG, 'چه کاری میتونم برات انجام بدم؟',
            KEYBOARD,  getMainMenu($user[DB_USER_MODE])
        );
    } else if($user[DB_USER_ACTION] == ACTION_SELECT_BOOKLET_TO_GET) {
        if(strpos($data, DATA_JOIN_SIGN) !== false) {
            // have in mind resetting in action
            // make link list
        } else {        
            $answer = 'جزوه (ها)ی مورد نظر شما:';
            $booklets = Database::getInstance()->query('SELECT * FROM '. DB_TABLE_BOOKLETS .' WHERE ' . $data); // $callback_data here is actually the sql conditions
            foreach($booklets as $booklet)
                callMethod(
                    'send' . ucfirst($booklet[DB_BOOKLETS_TYPE]),
                    CHAT_ID, $chat_id,
                    $booklet[DB_BOOKLETS_TYPE], $booklet[DB_BOOKLETS_FILE_ID],
                    CAPTION_TAG, $booklet[DB_BOOKLETS_INDEX] . ': '. $booklet[DB_BOOKLETS_CAPTION]
                );
            resetAction($user_id);
        }
    } else if($user[DB_USER_ACTION] == ACTION_SET_BOOKLET_CAPTION) {
        if(!$raw_data) {
            $answer = backupBooklet($user);
            if(!$answer) {
                $answer = "کپشن فایل به عنوان کپشن جزوه ثبت شد! حالا جزوه بعدی رو بفرست: \nنکته: برای اتمام فرایند آپلود جزوات این درس از گزینه بازگشت به منو استفاده کنید یا روی دستور زیر کلیک کنید:\n /cancel";
                $keyboard = backToMainMenuKeyboard();
            } else resetAction($user_id);     
        } else $answer = 'کپشن موردنظرتو وارد کن:';
        
    } else if(strpos($data, DATA_JOIN_SIGN) !== false) {
        switch($user[DB_USER_ACTION]) {
            case ACTION_UPLOAD_BOOKLET:
                // the if below, sets user action and its cache to prepare for getting the booklet
                if(setActionAndCache($user_id, ACTION_SENDING_BOOKLET_FILE, $data)) {
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
                $answer = 'مشکلی حین ثبت اطلاعات پیش اومده. لطفا از اول تلاش کن :|';
                resetAction($user_id);
                break;

            case ACTION_DOWNLOAD_BOOKLET:
            case ACTION_EDIT_BOOKLET_CAPTION:
            case ACTION_EDIT_BOOKLET_FILE:
                if(count(explode(DATA_JOIN_SIGN, $data)) < 3) {
                    $answer = 'طبقه بندی جزوه ها بر اساس:';
                    $keyboard = array(
                        INLINE_KEYBOARD => array(
                            array(
                                array(TEXT_TAG => 'شماره جزوه', CALLBACK_DATA => $data . DATA_JOIN_SIGN . 0),
                                array(TEXT_TAG => 'عنوان جزوه', CALLBACK_DATA => $data . DATA_JOIN_SIGN . 1),
                            )
                        )
                    );
                } else {
                    $categories = extractCategories($data);
                    if(isset($categories['err']))
                        $answer = $categories['err'];
                    else {
                        $booklets = Database::getInstance()->query(
                            'SELECT * FROM '. DB_TABLE_BOOKLETS .' WHERE ' . DB_ITEM_TEACHER_ID . '=:teacher_id AND '
                                . DB_ITEM_COURSE_ID . '=:course_id', array(
                                    'teacher_id' => $categories[DB_ITEM_TEACHER_ID], 'course_id' => $categories[DB_ITEM_COURSE_ID]
                            )
                        );
                        if(count($booklets)) {
                            // if there is some booklets
                            $answer = 'جزوه ی موردنظرتو از لیست زیر انتخاب کن:';
                            if($user[DB_USER_ACTION] == ACTION_DOWNLOAD_BOOKLET) {
                                if(updateAction($user_id, ACTION_SELECT_BOOKLET_TO_GET)) {
                                    $keyboard = createIndexMenu($booklets, $categories['list_by']);
                                    /*array_unshift($keyboard[INLINE_KEYBOARD], array(
                                        array(
                                            TEXT_TAG => 'Linked List',
                                            CALLBACK_DATA => $data
                                        )
                                    ));*/
                                } else {
                                    $answer = 'مشکلی حین دریافت اطلاعات پیش اومده. لطفا از اول تلاش کن :|';
                                    resetAction($user_id);
                                }
                            } else {
                                if(setActionAndCache($user_id, ACTION_SELECT_BOOKLET_TO_EDIT, $user[DB_USER_ACTION])) {
                                    $keyboard = createIndexMenu($booklets, $categories['list_by'], false);
                                } else {
                                    $answer = 'مشکلی حین دریافت اطلاعات پیش اومده. لطفا از اول تلاش کن :|';
                                    resetAction($user_id);
                                }
                            }
                        } else {
                            $answer = 'هنوز جزوه ای آپلود نشده!';
                            resetAction($user_id);
                        }
                    }
                }
                break;
            default:
                // check if its not a user message:
                $temp = explode(DATA_JOIN_SIGN, $data);
                if($temp[0] == DB_TABLE_MESSAGES && count($temp) >= 2) {
                    // admin is attempting to answer a message
                    if(setActionAndCache($user_id, ACTION_WRITE_REPLY_TO_USER, $temp[1])) {
                        $answer = 'پاسخ خودتو بنویس: (لغو /cancel)';
                        if (isMessageAnswered($temp[1]))
                            callMethod('answerCallbackQuery',
                                'callback_query_id', $callback_id,
                                TEXT_TAG, 'این پیام قبلا پاسخ داده شده است!',
                                'show_alert', true
                            );
                        callMethod(
                            METH_SEND_MESSAGE,
                            CHAT_ID, $chat_id,
                            TEXT_TAG, $answer,
                            'reply_to_message_id', $message_id,
                            KEYBOARD, backToMainMenuKeyboard()
                        );
                    } else {
                        callMethod('answerCallbackQuery',
                            'callback_query_id', $callback_id,
                            TEXT_TAG, 'حین ورود به حالت پاسخ دهی مشکلی پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!',
                            'show_alert', true
                        );
                    }
                    exit();
                } else
                    resetAction($user_id);
                // TODO: need to sth else?
                break;
        }

    } else if($user[DB_USER_ACTION] == ACTION_SELECT_BOOKLET_TO_EDIT) {
        $edit_type = $user[DB_USER_ACTION_CACHE];
        $booklets = Database::getInstance()->query('SELECT * FROM ' . DB_TABLE_BOOKLETS . ' WHERE ' . $data . ' LIMIT 1');
        if($booklets && count($booklets)) {
            if($edit_type == ACTION_EDIT_BOOKLET_CAPTION) {
                $answer = "کپشن کنونی:\n" . $booklets[0][DB_BOOKLETS_INDEX] . ': ' . $booklets[0][DB_BOOKLETS_CAPTION] . "\n\nکپشن جدید را وارد کنید:";
                if (!setActionAndCache($user_id, ACTION_SET_BOOKLET_CAPTION, $booklets[0][DB_ITEM_ID]))
                    $answer = 'حین ورود به حالت ویرایش کپشن مشکلی پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
            } else {
                // file edit
                $answer = 'فایل جدید را ارسال کنید:';
                if (!setActionAndCache($user_id, ACTION_EDIT_BOOKLET_FILE, $booklets[0][DB_ITEM_ID]))
                    $answer = 'حین ورود به حالت ویرایش فایل مشکلی پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
            }
        } else $answer = 'جزوه مورد نظر در دیتابیس موجود نبود.';
    } else {
        // this means that it's time to create the second menu
        // second menus: courses/teachers or yes/no menu for removing admins
        $params = explode(RELATED_DATA_SEPARATOR, $data);
        if($params[0] === 'show') {
            // user wants to see admin message
            // data is as: show/message_id/admin_id/reply_to_mesg_id
            if(count($params) === 4) {
                callMethod(
                    METH_COPY_MESSAGE,
                    MESSAGE_ID_TAG, $params[1],
                    CHAT_ID, $chat_id,
                    'from_chat_id', $params[2],
                    'reply_to_message_id', $params[3]
                );
                callMethod(METH_DELETE_MESSAGE,
                    MESSAGE_ID_TAG, $message_id,
                    CHAT_ID, $chat_id
                ); // remove the show message box
            } else
                $answer = 'خطای غیرمنتظره حین باز کردن پیام اتفاق افتاد!';
        } else if(count($params) === 2) {
            if($params[0] === DB_TABLE_COURSES) {
                $keyboard = $user[DB_USER_ACTION] == ACTION_DOWNLOAD_BOOKLET
                    ? createMenu(DB_TABLE_TEACHERS, $data, DB_ITEM_COURSE_ID . "=$params[1]", DB_ITEM_TEACHER_ID)
                    : createMenu(DB_TABLE_TEACHERS, $data);
                $answer = 'از بین اساتید ارائه کننده این درس استاد مورد نظر خود را انتخاب کنید:';
                if(!$keyboard)
                    //means there is no option to select because of filtering
                    $answer = 'موردی یافت نشد!';
            } else if($params[0] === DB_TABLE_TEACHERS) {
                if($user[DB_USER_ACTION] != ACTION_LINK_TEACHER) {
                    $keyboard = $user[DB_USER_ACTION] == ACTION_DOWNLOAD_BOOKLET
                    ? createMenu(DB_TABLE_COURSES, $data, DB_ITEM_TEACHER_ID . "=$params[1]", DB_ITEM_COURSE_ID)
                    : createMenu(DB_TABLE_COURSES, $data);
                    $answer = 'از بین دروس ارائه شده توسط این استاد درس مورد نظر خود را انتخاب کنید:';
                    if(!$keyboard)
                        //means there is no option to select because of filtering
                        $answer = 'موردی یافت نشد!';
                } else {
                    $answer = 'حالا یک پیام از استاداکانت استاد مربوطه فوروارد کنید:';
                    if(!updateActionCache($user_id, $params[1]))
                        $answer = 'مشکلی حین ورود به حالت لینک اکانت استاد پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
                }
            } else if($params[0] === DB_TABLE_USERS) {
                // NOTE: like remove admin
                switch($user[DB_USER_ACTION]) {
                    case ACTION_REMOVE_ADMIN:
                        if(updateUserMode($params[1], NORMAL_USER)) {
                            $answer = 'کاربر موردنظر به دسترسی عادی بازگشت!';
                        } else {
                            $answer = 'مشکلی حین تغییر کاربری پیش اومد. لطفا دوباره تلاش کن!';
                        }
                        resetAction($user_id);
                        break;
                    case ACTION_SELECT_TEACHER_TO_CONTACT:
                        if(isset($params[1]) && setActionAndCache($user_id, ACTION_WRITE_MESSAGE, $params[1])) {
                            $answer = 'متن خود را در قالب یک پیام ارسال کنید.📝';
                            $keyboard = backToMainMenuKeyboard();
                            callMethod(
                                METH_SEND_MESSAGE,
                                TEXT_TAG, $answer,
                                CHAT_ID, $chat_id,
                                KEYBOARD, $keyboard
                            );
                            callMethod(METH_DELETE_MESSAGE,
                                MESSAGE_ID_TAG, $message_id,
                                CHAT_ID, $chat_id
                            );
                            exit();
                        } else {
                            $answer = 'حین ورود به حالت ارسال پیام مشکلی پیش اومد. لطفا دوباره تلاش کن!';
                            resetAction($user_id);
                        }
                        break;
                    default:
                        $answer = 'گزینه انتخاب شده اشتباه است!';
                        resetAction($user_id);
                        break;
                }
            }
        } else {
            $answer = 'گزینه انتخاب شده اشتباه است!';
            resetAction($user_id);
        }

    }
    if($keyboard)
        callMethod(METH_EDIT_MESSAGE,
            CHAT_ID, $chat_id,
            MESSAGE_ID_TAG, $message_id,
            TEXT_TAG, $answer,
            KEYBOARD, $keyboard
        );
    else
        callMethod(METH_EDIT_MESSAGE,
            CHAT_ID, $chat_id,
            MESSAGE_ID_TAG, $message_id,
            TEXT_TAG, $answer
        );
}
