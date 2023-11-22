<?php
require_once './telegram_api.php';
require_once './database.php';
require_once './user.php';
require_once  './menu.php';
require_once './booklet.php';
require_once './sample.php';

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
                if(!updateAction($user[DB_ITEM_ID], ACTION_WHISPER_GODS_SECRET)) {
                    $answer = 'خطای غیرمنتظره پیش آمد! دوباره تلاش کن!';
                    resetAction($user[DB_ITEM_ID]);
                }
            }
            break;
        case ACTION_WHISPER_GODS_SECRET:
            if($whisper === GOD_SECRET && !isGodEnough()) {
                if(!updateUserMode($user[DB_ITEM_ID], GOD_USER))
                    $answer = 'خطایی حین ثبت اطلاعات پیش آمد. دوباره تلاش کن!';
                $user[DB_USER_MODE] = GOD_USER; // update the old user object
                resetAction($user[DB_ITEM_ID]);
                $answer = 'Now you\'re God Almighty :)!';
            }
            break;
    }
    return $answer;
}

function &startUpgradingUser($user_id, array &$message, int $mode, string $position_title, $teacher_id=null): string {
    $response = '';
    $target_id = null;
    if(!isset($message['forward_from']) && ($message[TEXT_TAG] ?? ' ')[0] == '@') {
        // setting by username
        $target_id = findByUsername($message[TEXT_TAG]);
        if(!$target_id)
            $response = "هیچ کاربری با این یوزرنیم یافت نشد. این خطا دو علت می تواند داشته باشد: \n1- یوزرنیم را به درستی وارد نکرده اید\n2-کاربر موردنظر هنوز شروع به استفاده از ربات نکرده است.";

    } else if(isset($message['forward_from'])) 
        $target_id = $message['forward_from']['id'] ?? null;  
    else {
        $response = "اکانت موردنظر حالت مخفی رو فعال کرده. برای ارتقا یافتن به $position_title باید موقتا این حالت رو غیرفعال کنه!";
        resetAction($user_id);
    }
    if($target_id) {
        $teacher_name = $mode != ADMIN_USER ? getTeachersField($teacher_id) : null;
        if(updateUserMode($target_id, $mode, $teacher_id, $mode == TEACHER_USER ? $teacher_name : null)) {
            $response = "اکانت موردنظر بعنوان $position_title ثبت شد!";
            // notify the target user
            callMethod(METH_SEND_MESSAGE,
                CHAT_ID, $target_id,
                TEXT_TAG, $mode != TA_USER ? "تبریک! اکانت شما به دسترسی $position_title ارتقا پیدا کرد." : "تبریک اکانت شما به عنوان حل تمرین استاد $teacher_name ثبت شد.",
                KEYBOARD, getMainMenu($mode)
            );
            if($mode != TEACHER_USER) { // teacher has predefined name
                // other modes take their related entity's name
                if(setActionAndCache($user_id, ACTION_ASSIGN_USER_NAME, $target_id)) {
                    $response .= ' اسم کاربر مورد نظر را وارد کنید:';
                } else {
                    $response .= ' اما حین ورود به حالت تعیین اسم مشکلی پیش آمد!';
                    resetAction($user_id);
                }
            } else resetAction($user_id);
        } else {
            $response = "متاسفانه مشکلی حین ثبت اکانت بعنوان $position_title پیش آمده. لطفا دوباره تلاش کن!";
            resetAction($user_id);
        }
    }
    return $response;
}

function appendStatsToMessage($msg, int $stats): string {
    return "$msg\n〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️〰️\nتعداد دانلودهای این مورد: $stats";
}

function handleCasualMessage(&$update) {
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
    $username =  $update['message']['from']['username'] ?? null;
    $user = getUser($user_id, $username);
    $message = $update['message'];
    $message_id = $update['message'][MESSAGE_ID_TAG];
    $data = $message[TEXT_TAG] ?? null;
    $response = $keyboard = null;

    if($data) {
        // most common options
        switch($data) {
            case '/start':
            case '/cancel':
            case CMD_MAIN_MENU:
                $response = 'خب! چه کاری میتونم برات انجام بدم؟';
                resetAction($user_id);
                break;
            default:
                if($user[DB_USER_ACTION] == ACTION_WRITE_MESSAGE) {
                    $target_group_id = $user[DB_USER_CACHE] ?? null; // null => admins, int => teachers and their TAs
                    saveMessage($user_id, $message_id, $target_group_id);
                    if($target_group_id) {
                        $targets = getTeacherGroup($target_group_id);
                        $group_name = 'این استاد یا دستیار حل تمرین وی';
                    } else {
                        $targets = getSuperiors();
                        $group_name = 'تیم پشتیبانی';
                    }

                    foreach($targets as &$target) {
                        callMethod(
                            METH_FORWARD_MESSAGE,
                            CHAT_ID, $target[DB_ITEM_ID],
                            'from_chat_id', $chat_id,
                            MESSAGE_ID_TAG, $message_id
                        );
                        callMethod(METH_SEND_MESSAGE,
                            CHAT_ID, $target[DB_ITEM_ID],
                            TEXT_TAG, 'برای پاسخ به پیام بالا میتونی از گزینه زیر استفاده کنید',
                            KEYBOARD, array(
                                INLINE_KEYBOARD => array(
                                    array(array(TEXT_TAG => 'پاسخ', CALLBACK_DATA => DB_TABLE_MESSAGES . RELATED_DATA_SEPARATOR 
                                        . 'rp' . RELATED_DATA_SEPARATOR . $message_id))
                                )
                            )
                        );
                    }
                    $response = "پیام شما با موفقیت ارسال شد✅ \n در صورت لزوم، $group_name پاسخ را از طریق همین بات به شما اعلام خواهد کرد.";
                    resetAction($user_id);
                } else if($user[DB_USER_ACTION] == ACTION_WRITE_REPLY_TO_USER && $user[DB_USER_MODE] != NORMAL_USER) {
                    $msg = getMessage($user[DB_USER_CACHE]);
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
                                            CALLBACK_DATA => DB_TABLE_MESSAGES . RELATED_DATA_SEPARATOR . 'sh'
                                                . RELATED_DATA_SEPARATOR . $message_id . RELATED_DATA_SEPARATOR . $chat_id
                                                . RELATED_DATA_SEPARATOR . $msg[DB_ITEM_ID]
                                        )
                                    )
                                )
                            )
                        );
                        markMessageAsAnswered($user[DB_USER_CACHE]);
                        $response = 'پاسخ شما با موفقیت ارسال شد.';
                    } else $response = 'چنین پیامی در دیتابیس وجود ندارد و امکان پاسخ دهی به آن نیست!';

                    resetAction($user_id);
                } else if(strpos($data, CMD_GET_BOOKLET_PREFIX) !== false) {
                    $params = explode(CMD_COMMAND_PARAM_SEPARATOR, $data);
                    if (count($params) === 3) {
                        if(updateAction($user_id, ACTION_DOWNLOAD_BOOKLET, true)) {
                            $response = 'طبقه بندی جزوه ها بر اساس:';
                            $categories = array(DB_ITEM_TEACHER_ID => $params[1], DB_ITEM_COURSE_ID => $params[2]);
                            $keyboard = createClassifyByMenu($user_id, $categories);
                        } else $response = 'مشکلی حین اجرای دستور موردنظر پیش آمد! لطفا لحظاتی دیگر دوباره تلاش کتید.';
                    } else $response = 'دستور مورد نظر شناسایی نشد!';
                } else $response = handleGospel($user, $data);
                break;
        }

    }

    if(!$response) {
        switch($data) {
            case CMD_DOWNLOAD_BOOKLET:
                $response = 'جست و جو بر اساس:';
                $keyboard = getDownloadOptions();
                break;
            case CMD_DOWNLOAD_SAMPLE:
                $orderBy = ORDER_BY_NAME; // TODO: Edit this 
                if(setActionAndCache($user_id, ACTION_DOWNLOAD_SAMPLE, $orderBy)) {
                    $response = "درس مورد نظر خود را از لیست زیر انتخاب کنید:";
                    $keyboard = createCategoricalMenu(DB_TABLE_COURSES, null,
                        entityIsReferencedInAnotherTableQuery(DB_TABLE_COURSES, DB_TABLE_SAMPLES, DB_ITEM_COURSE_ID), null, $orderBy);
                } else {
                    $response = 'خطای غیرمنتظره پیش آمد! دوباره تلاش کنید!';
                    resetAction($user_id);
                }
                break;
            case CMD_GOD_ACCESS:
                if(!isGodEnough()) {
                    $response = 'God\'s Name:';
                    if(!updateAction($user_id, ACTION_WHISPER_GODS_NAME)) {
                        $response = 'خطای غیرمنتظره پیش آمد! دوباره تلاش کنید!';
                        resetAction($user_id);
                    }
                }
                break;
            case CMD_DOWNLOAD_BY_COURSE:
            case CMD_DOWNLOAD_BY_MOST_DOWNLOADED_COURSE:
                $orderBy = $data == CMD_DOWNLOAD_BY_COURSE ? ORDER_BY_NAME : ORDER_BY_MOST_DOWNLOADED_COURSE;
                if(setActionAndCache($user_id, ACTION_DOWNLOAD_BOOKLET, $orderBy)) {
                    $response = "درس مورد نظر خود را از لیست زیر انتخاب کنید:";
                    $keyboard = createCategoricalMenu(DB_TABLE_COURSES, null, null, null, $orderBy);
                } else {
                    $response = 'خطای غیرمنتظره پیش آمد! دوباره تلاش کنید!';
                    resetAction($user_id);
                }
                break;
            case CMD_DOWNLOAD_BY_TEACHER:
            case CMD_DOWNLOAD_BY_MOST_DOWNLOADED_TEACHER:
                $orderBy = $data == CMD_DOWNLOAD_BY_TEACHER ? ORDER_BY_NAME : ORDER_BY_MOST_DOWNLOADED_TEACHER;
                if(setActionAndCache($user_id, ACTION_DOWNLOAD_BOOKLET, $orderBy)) {
                    $response = "استاد مورد نظر خود را از لیست زیر انتخاب کنید:";
                    $keyboard = createCategoricalMenu(DB_TABLE_TEACHERS, null, null, null, $orderBy);
                } else {
                    $response = 'خطای غیرمنتظره پیش آمد! دوباره تلاش کنید!';
                    resetAction($user_id);
                }
                break;
            case CMD_MESSAGE_TO_ADMIN:
                if(updateAction($user_id, ACTION_WRITE_MESSAGE)) {
                    $response = 'متن خود را در قالب یک پیام ارسال کنید.📝';
                    $keyboard = backToMainMenuKeyboard();
                } else {
                    $response = 'حین ورود به حالت ارسال پیام مشکلی پیش آمد. لطفا دوباره تلاش کنید!';
                    resetAction($user_id);
                }
                break;
            case CMD_MESSAGE_TO_TEACHER:
                if(updateAction($user_id, ACTION_SELECT_TEACHER_TO_CONTACT)) {
                    $keyboard = createUsersMenu(DB_USER_MODE . '=' . TEACHER_USER . ' AND ' . DB_ITEM_TEACHER_ID . ' IS NOT NULL', DB_ITEM_TEACHER_ID);
                    if($keyboard)
                        $response = 'استادهای زیر در بات فعال هستند و می توانید به آن ها پیام دهید:';
                    else {
                        $response = 'در حال حاضر هیچ استادی در بات فعالیت ندارد!';
                        resetAction($user_id);
                    }
                } else {
                    $response = 'متاسفانه در حال حاضر ربات قادر به ارسال پیام برای هیچ استادی نیست! لطفا بعدا امتحان کنید.';
                    resetAction($user_id);
                }
                break;
            case CMD_TEACHER_BIOS:
                if(updateAction($user_id, ACTION_SEE_TEACHER_BIOS, true)) {
                    $response = "شما می توانید معرفی نامه هر یک از اساتید زیر را با کلیک روی اسم وی مشاهده کنید.";
                    $keyboard = createCategoricalMenu(DB_TABLE_TEACHERS, null, DB_TEACHER_BIO . ' IS NOT NULL');
                    if(!$keyboard) {
                        $response = 'در حال حاضر هیچ معرفی نامه ای برای هیچ استادی ثبت نشده است!';
                        resetAction($user_id);
                    }
                } else {
                    $response = 'خطای غیرمنتظره پیش آمد! دوباره تلاش کنید!';
                    resetAction($user_id);
                }
                break;
            case CMD_FAVORITES:
                $favs = getFavoritesList($user_id);
                $response = createLinkedList($favs);
                if(count($favs) > MAX_LINKED_LIST_LENGTH) {
                    $keyboard = array(
                        INLINE_KEYBOARD => array(
                            array(
                                array(TEXT_TAG => "بعدی", CALLBACK_DATA => DB_TABLE_FAVORITES . RELATED_DATA_SEPARATOR . '1')
                            )
                        )
                    );
                }
                break;
            default:
                $response = null;
                break;
        }
    }

    if(!$response) {
        switch($user[DB_USER_MODE]) {
            case GOD_USER:
                if($data === CMD_ADD_ADMIN) {
                    if(updateAction($user_id, ACTION_ADD_ADMIN)) {
                        $response = 'یوزرنیم کاربر مورد نظر را وارد کنید یا یک پیام از او داخل ربات فوروارد کنید:';
                        $keyboard = backToMainMenuKeyboard();
                    } else {
                        $response = 'مشکلی حین ورود به حالت اضافه کردن ادمین پیش آمد. لطفا دوباره تلاش کنید!';
                        resetAction($user_id);
                    }
                    break;
                } else if($user[DB_USER_ACTION] == ACTION_ADD_ADMIN) {
                    $response = startUpgradingUser($user_id, $message, ADMIN_USER, 'ادمین');
                    break;
                } else if($data === CMD_REMOVE_ADMIN) {
                    if(updateAction($user_id, ACTION_DOWNGRADE_USER)) {
                        $keyboard = createUsersMenu(DB_USER_MODE . '=' . ADMIN_USER);
                        if($keyboard)
                            $response = 'روی شخص موردنظرت کلیک کن تا از حالت ادمین خارج شود:';
                        else {
                            $response = 'هیج ادمینی یافت نشد!';
                            resetAction($user_id);
                        }
                    } else {
                        $response = 'مشکلی حین ورود به حالت حذف ادمین پیش آمد. لطفا دوباره تلاش کنید!';
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
                                                        : ($data == CMD_EDIT_BOOKLET_CAPTION ? ACTION_EDIT_BOOKLET_CAPTION : ACTION_EDIT_BOOKLET_FILE), true)) {
                                $response = 'از لیست زیر درس موردنظر خود را انتخاب کنید:';
                                $keyboard = createCategoricalMenu(DB_TABLE_COURSES);
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_UPLOAD_SAMPLE:
                            if(updateAction($user_id, ACTION_UPLOAD_SAMPLE, true)) {
                                $response = 'از لیست زیر درس موردنظر خود را انتخاب کنید:';
                                $keyboard = createCategoricalMenu(DB_TABLE_COURSES);
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_TEACHER_INTRODUCTION:
                            if(updateAction($user_id, ACTION_INTRODUCE_TEACHER)) {
                                $response = 'از لیست زیر استاد موردنظر خود را انتخاب کنید:';
                                $keyboard = createCategoricalMenu(DB_TABLE_TEACHERS);
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_STATISTICS:
                            $response = "آمار ربات: \n";
                            foreach(getStatistics() as $field=>$stat) {
                                $response .= "{$stat['fa']}: {$stat['total']} \n";
                            }
                            break;
                        case CMD_SEND_POST_TO_CHANNEL:
                            if(updateAction($user_id, ACTION_SEND_POST_TO_CHANNEL)) {
                                $response = 'متن پست مورد موردنظرت رو تایپ کنید:';
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_EDIT_BOOKLET:
                            $response = 'بسیار خب! نوع ویرایش را انتخاب کنید.';
                            $keyboard = backToMainMenuKeyboard([CMD_EDIT_BOOKLET_CAPTION, CMD_EDIT_BOOKLET_FILE, CMD_TEACHER_INTRODUCTION]);
                            break;
                        case CMD_ADD_COURSE:
                            if(updateAction($user_id, ACTION_ADD_COURSE)) {
                                $response = 'عنوان درس جدید رو وارد کنید:';
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_ADD_TEACHER:
                            if(updateAction($user_id, ACTION_ADD_TEACHER)) {
                                $response = 'اسم کامل استاد جدید رو وارد کنید:';
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_LINK_TEACHER:
                            if(updateAction($user_id, ACTION_LINK_TEACHER, true)) {
                                $response = "استاد مورد نظر خود را از لیست زیر انتخاب کنید:";
                                $keyboard = createCategoricalMenu(DB_TABLE_TEACHERS);
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_NOTIFICATION:
                            if(updateAction($user_id, ACTION_SEND_NOTIFICATION, true)) {
                                $response = "متن پست را تایپ کنید ...";
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کنید!';
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
                            if(!$file) $response = 'هیچ فایلی ارسال نشده. دوباره ارسال کنید!';
                            else {
                                $result = addBooklet($user, $file);
                                if(isset($result['err'])) $response = $result['err'];
                                else {
                                    if(setActionAndCache($user_id, ACTION_SET_BOOKLET_CAPTION, $result['id'])) {
                                        $response = 'جزوه مورد نظر با موفقیت ارسال شد. حالا کپشن جزوه را مشخص کنید:';
                                        $keyboard = array(
                                            INLINE_KEYBOARD => array(
                                                array(
                                                    //columns:
                                                    array(TEXT_TAG => 'کپشن فایل', CALLBACK_DATA => 0),
                                                    array(TEXT_TAG => 'وارد کردن کپشن', CALLBACK_DATA => 1)
                                                )
                                            )
                                        );
                                    } else $response = 'جزوه ثبت شد ولی مشکلی حین ورود به حالت تعیین کپشن پیش آمد!';
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
                        case ACTION_SENDING_SAMPLE_FILE:
                            $file = getFileFrom($message);
                            $keyboard = backToMainMenuKeyboard();
                            if(!$file) $response = 'هیچ فایلی ارسال نشده. دوباره ارسال کنید!';
                            else {
                                $result = addSample($user, $file);
                                if(isset($result['err'])) $response = $result['err'];
                                else {
                                    if(setActionAndCache($user_id, ACTION_SET_SAMPLE_TITLE, $user[DB_USER_CACHE] . RELATED_DATA_SEPARATOR . $result['id'])) {
                                        $response = 'نمونه سوال مورد نظر با موفقیت ارسال شد. حالا عنوان آن را تایپ کنید:';
                                        $keyboard = array(INLINE_KEYBOARD => array(array(array(TEXT_TAG => 'استفاده از کپشن فایل', CALLBACK_DATA => 0))));
                                    } else $response = 'نمونه سوال ثبت شد ولی مشکلی حین ورود به حالت تعیین عنوان پیش آمد!';
                                }
                            }
                            break;
                        case ACTION_SET_SAMPLE_TITLE:
                            $params = explode(RELATED_DATA_SEPARATOR, $user[DB_USER_CACHE]);
                            if(isset($params[1]))
                                $response = backupSample($params[1], $data); // returns error
                            else $response = 'مشکلی حین ارسال نمونه سوال به کانال بک آپ پیش آمد!';

                            if(!$response) {
                                $response = 'خب چی بکنیم؟';
                                callMethod(METH_SEND_MESSAGE,
                                    CHAT_ID, $chat_id,
                                    TEXT_TAG, 'عنوان جدید برای نمونه سوال با موفقیت ثبت شد.',
                                    KEYBOARD, array(
                                        INLINE_KEYBOARD => array(array(array(TEXT_TAG => 'آپلود نمونه سوال بعدی درس',
                                            CALLBACK_DATA => 'ns' . RELATED_DATA_SEPARATOR . $params[0])))
                                    )
                                );
                            }
                            resetAction($user_id);
                            break;
                        case ACTION_EDIT_BOOKLET_FILE:
                            $file = getFileFrom($message);
                            if(!$file) {
                                $response = 'هیچ فایلی ارسال نشده. دوباره ارسال کنید!';
                                $keyboard = backToMainMenuKeyboard();
                            } else if(changeBookletFile($user[DB_USER_CACHE], $file)){
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
                                            ),
                                            array(
                                                array(TEXT_TAG => 'کانال یوتیوب ما', INLINE_URL_TAG => PERSIAN_COLLEGE_YOUTRUBE_LINK)
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
                                                : "خطایی هنگام ثبت بوجود آمد. لطفا دوباره نام رو وارد کنید.";
                            break;
                        case ACTION_ADD_TEACHER:
                            $result = addCategory(DB_TABLE_TEACHERS, $data, $user_id);
                            $response = $result ? "استاد جدید با ایدی $result موفقیت ثبت شد!"
                                : "خطایی هنگام ثبت بوجود آمد. لطفا دوباره نام رو وارد کنید.";
                            break;
                        case ACTION_LINK_TEACHER:
                            if($user[DB_USER_CACHE])
                                $response = startUpgradingUser($user_id, $message, TEACHER_USER, 'استاد', $user[DB_USER_CACHE]);
                            else {
                                $response = 'ابتدا باید استاد موردنظر از لیست اساتید انتخاب شود. اگر لیستی مشاهده نمیکنید لطفا دوباره روی گزینه ' .
                                    CMD_LINK_TEACHER . ' کلیک کنید.';
                            }
                            break;
                        case ACTION_ASSIGN_USER_NAME:
                            // set message text as the name for the admin
                            // cache is the target user id
                            $response = updateUserField($user[DB_USER_CACHE], $data) ? 'اسم کاربر با موفقیت ثبت شد.'
                                : 'مشکلی در ثبت اسم کاربر پیش آمد!';
                            resetAction($user_id);
                            break;
                        
                        case ACTION_INTRODUCE_TEACHER:
                            if($user[DB_USER_CACHE]) {
                                $response = 'معرفی نامه استاد با موفقیت به روزرسانی شد.';
                                if(!introduceTeacher($user[DB_USER_CACHE], $data != '-' ? $data : null))
                                    $response = 'حین ذخیره متن معرفی نامه خطای نامعلوم اتفاق افتاد! لطفا لحظاتی بعد دوباره تلاش کنید.';
                                resetAction($user_id);
                            } else {
                                $response = "ابتدا باید استاد موردنظر را انتخاب کنید و سپس اقدام به نوشتن معرفی نامه کنید. درصورتی که از این عملیات منصرف شده اید روی دستور لغو کلیک کنید:
                                \n/cancel";
                            }
                            break;
                        case ACTION_SEND_NOTIFICATION:
                            if($data) {
                                $users = getAllUsers();
                                $count = count($users);
                                $progress_trigger = (int) ($count / 20);
                                if(!$progress_trigger) $progress_trigger = 1;
                                $progress_text = "در حال ارسال پیام ... ";
                                $telegram_response = callMethod(
                                    METH_SEND_MESSAGE,
                                    CHAT_ID, $chat_id,
                                    TEXT_TAG, $progress_text,
                                    'reply_to_message_id', $message_id
                                );
                                $progress_msg_id = extractFromSentMessage($telegram_response);
                                for($i = 0; $i < $count; $i++) {
                                    $telegram_response = callMethod(
                                        METH_SEND_MESSAGE,
                                        TEXT_TAG, $data,
                                        CHAT_ID, $users[$i]
                                    );
                                    // update username of the user
                                    $dest = extractFromSentMessage($telegram_response, 'chat');
                                    if(isset($dest['username'])) {
                                        updateUserField($users[$i], '@' . $dest['username'], DB_USER_USERNAME);
                                    }
                                    if($i % $progress_trigger == 0) {
                                        $progress = sprintf("%.2f %%", 100 * $i / $count);
                                        callMethod(METH_EDIT_MESSAGE,
                                            CHAT_ID, $chat_id,
                                            MESSAGE_ID_TAG, $progress_msg_id,
                                            TEXT_TAG, "$progress_text $progress"
                                        );
                                    }
                                }
                                callMethod(METH_EDIT_MESSAGE,
                                    CHAT_ID, $chat_id,
                                    MESSAGE_ID_TAG, $progress_msg_id,
                                    TEXT_TAG, $progress_text . "100 % \nپیام با موفقیت برای کاربران ($count نفر) ارسال شد.",
                                    CHAT_ID, $chat_id
                                );
                                resetAction($user_id);
                                $response = 'اطلاع رسانی با موفقیت به پایان رسید.';
                            } else {
                                $response = 'پیام خبررسانی باید یک پیام متنی ساده باشد! لطفا دوباره تلاش کنید ...';
                                $keyboard = backToMainMenuKeyboard();
                            }
                            break;
                        default:
                            $response = 'عملیات موردنظر تعریف نشده است!';
                            resetAction($user_id);
                            break;
                    }
                }
                break;
            case TEACHER_USER:
                // double check if teacher_id is set 
                if($user[DB_USER_ACTION] == ACTION_INTRODUCE_TA) {
                    $response = startUpgradingUser($user_id, $message, TA_USER, 'حل تمرین شما', $user[DB_USER_CACHE]);
                } else if($user[DB_USER_ACTION] == ACTION_ASSIGN_USER_NAME) {
                    $response = updateUserField($user[DB_USER_CACHE], $data) ? "$data به عنوان حل تمرین شما ثبت شد. "
                        : 'مشکلی در ثبت اسم کاربر پیش آمد!';
                    resetAction($user_id);
                } else {
                    switch($data) {
                        case CMD_STATISTICS:
                            $response = getTeachersFullDownloadStats($user[DB_ITEM_TEACHER_ID]);
                            break;
                        case CMD_INTRODUCE_TA:
                            if(setActionAndCache($user_id, ACTION_INTRODUCE_TA, $user[DB_ITEM_TEACHER_ID])) {
                                $response = 'یوزرنیم کاربر مورد نظر را وارد کنید یا یک پیام از او داخل ربات فوروارد کنید:';
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                $response = 'مشکلی حین ورود به حالت معرفی TA پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_REMOVE_TA:
                            if(updateAction($user_id, ACTION_DOWNGRADE_USER)) {
                                $keyboard = createUsersMenu(DB_ITEM_TEACHER_ID . '=' . $user[DB_ITEM_TEACHER_ID] . ' AND ' . DB_USER_MODE . '=' . TA_USER);
                                if($keyboard)
                                    $response = 'روی شخص موردنظرت کلیک کن تا از لیست TA های شما خارج شود:';
                                else {
                                    $response = 'شما هنوز هیچ TA ای معرفی نکرده اید!';
                                    resetAction($user_id);
                                }
                            } else {
                                $response = 'مشکلی حین ورود به حالت حذف TA پیش آمد. لطفا دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                    }
                }
                break;
        }
    }

    if(!$response) {
        $response = "متوجه نشدم! لطفا دوباره تلاش کنید...";
        resetAction($user_id);
    }
    callMethod(
        METH_SEND_MESSAGE,
        CHAT_ID, $chat_id,
        TEXT_TAG, $response,
        'reply_to_message_id', $message_id,
        KEYBOARD, $keyboard ?? getMainMenu($user[DB_USER_MODE] ?? 0)
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
    $username =  $update['message']['from']['username'] ?? null;
    $user = getUser($user_id, $username);
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
    } else if(strpos($data, DB_TABLE_MESSAGES) !== false) {
        // check if it's not a user message:
        $params = explode(RELATED_DATA_SEPARATOR, $data);
        $command = $params[1] ?? null;
        switch($command) {
            case 'sh':
                // user wants to see admin message
                // data is as: messages/show/message_id/admin_id/reply_to_mesg_id
                if(count($params) === 5) {
                    callMethod(
                        METH_COPY_MESSAGE,
                        MESSAGE_ID_TAG, $params[2],
                        CHAT_ID, $chat_id,
                        'from_chat_id', $params[3],
                        'reply_to_message_id', $params[4]
                    );
                    callMethod(METH_DELETE_MESSAGE,
                        MESSAGE_ID_TAG, $message_id,
                        CHAT_ID, $chat_id
                    ); // remove the show message box
                } else
                    $answer = 'خطای غیرمنتظره حین باز کردن پیام اتفاق افتاد!';
                break;
            case 'rp':
                if(count($params) >= 3) {
                    // admin is attempting to answer a message
                    // data is as: messages/reply/message_id
                    if(setActionAndCache($user_id, ACTION_WRITE_REPLY_TO_USER, $params[2])) {
                        $answer = 'پاسخ خودتو بنویس: (لغو /cancel)';
                        if (isMessageAnswered($params[2]))
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
                    $answer = 'خطای غیرمنتظره حین پاسخ دادن به پیام اتفاق افتاد!';
                break;
            default:
                $answer = "دستور موردنظر شناسایی نشد!";
                break;
        }
    } else if(strpos($data, DB_TABLE_FAVORITES) !== false) {
        $favs = getFavoritesList($user_id);
        $current = (int)explode(RELATED_DATA_SEPARATOR, $data)[1];
        $keyboard_options = array();
        if($current > 0)
            $keyboard_options[] = array(TEXT_TAG => 'قبلی', CALLBACK_DATA => DB_TABLE_FAVORITES . RELATED_DATA_SEPARATOR . ($current - 1));
        if(($current+1) * MAX_LINKED_LIST_LENGTH < count($favs))
            $keyboard_options[] = array(TEXT_TAG => 'بعدی', CALLBACK_DATA => DB_TABLE_FAVORITES . RELATED_DATA_SEPARATOR . ($current + 1));
        $keyboard = array(INLINE_KEYBOARD => array($keyboard_options));
        $answer = createLinkedList($favs, $current);
    } else if($user[DB_USER_ACTION] == ACTION_SELECT_BOOKLET_TO_GET || $user[DB_USER_ACTION] == ACTION_SELECT_SAMPLE_TO_GET) {
        if(!strpos($data, DATA_JOIN_SIGN)) {
            // $callback_data here is actually the sql conditions
            $downloads = 0;
            $booklets = getBooklets($data, true);
            
            $teacher = $booklets[0]['teacher'] ?? null;
            $course = $booklets[0]['course'] ?? null;
            if(count($booklets)) { // at least has one booklet
                foreach($booklets as &$booklet) {
                    $downloads += $booklet[DB_ITEM_DOWNLOADS];
                    callMethod(
                        'send' . ucfirst($booklet[DB_ITEM_FILE_TYPE]),
                        CHAT_ID, $chat_id,
                        $booklet[DB_ITEM_FILE_TYPE], $booklet[DB_ITEM_FILE_ID],
                        CAPTION_TAG, $booklet[DB_BOOKLETS_INDEX] . ': '. $booklet[DB_BOOKLETS_CAPTION]
                    );
                }
            }
            $answer = 'جزوه (ها)ی انتخابی درس ' . $course . ' - استاد ' . $teacher . "\n";
            if($user[DB_USER_MODE] == GOD_USER || $user[DB_USER_MODE] == ADMIN_USER)
                $answer = appendStatsToMessage($answer, $downloads);
            resetAction($user_id);
        } /*else {
            // have in mind resetting in action
            // make link list
        }*/
    } else if($user[DB_USER_ACTION] == ACTION_SET_BOOKLET_CAPTION) {
        if(!$raw_data) {
            $answer = backupBooklet($user);
            if(!$answer) {
                $answer = 'کپشن فایل به عنوان کپشن جزوه ثبت شد!';
                callMethod(METH_SEND_MESSAGE,
                    CHAT_ID, $chat_id,
                    MESSAGE_ID_TAG, $message_id,
                    TEXT_TAG, "حالا جزوه بعدی رو بفرست: \nنکته: برای اتمام فرایند آپلود جزوات این درس از گزینه بازگشت به منو استفاده کنید یا روی دستور زیر کلیک کنید:\n /cancel",
                    KEYBOARD, backToMainMenuKeyboard()
                );
            } else resetAction($user_id);
        } else $answer = 'کپشن موردنظرتو وارد کن:';
    } else if($user[DB_USER_ACTION] == ACTION_SET_SAMPLE_TITLE) {
        if(!$raw_data) {
            $params = explode(RELATED_DATA_SEPARATOR, $user[DB_USER_CACHE]);
            if(isset($params[1]))
                $answer = backupSample($params[1]);
            else $answer = 'مشکلی حین ارسال نمونه سوال به کانال بک آپ پیش آمد!';
            if(!$answer) {
                $answer = 'کپشن فایل به جای عنوان نمونه سوال ثبت شد!';
                $keyboard = array(
                    INLINE_KEYBOARD => array(array(array(TEXT_TAG => 'آپلود نمونه سوال بعدی درس',
                        CALLBACK_DATA => 'ns' . RELATED_DATA_SEPARATOR . $params[0])))
                );
                callMethod(
                    METH_SEND_MESSAGE,
                    CHAT_ID, $chat_id,
                    TEXT_TAG, 'خب چی بکنیم؟',
                    KEYBOARD, getMainMenu($user[DB_USER_MODE])
                );
            }
        } else $answer = 'دستور موردنظر اشتباه است!';
        resetAction($user_id);
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
                $answer = 'مشکلی حین ثبت اطلاعات پیش آمده. لطفا از اول تلاش کن :|';
                resetAction($user_id);
                break;

            case ACTION_DOWNLOAD_BOOKLET:
            case ACTION_EDIT_BOOKLET_CAPTION:
            case ACTION_EDIT_BOOKLET_FILE:
                $categories = extractCategories($data);
                if($categories['options'] == '0' || $categories['options'] == '1') {
                    if(isset($categories['err']))
                        $answer = $categories['err'];
                    else {
                        $booklets = getBooklets(
                            selectBookletByCategoriesCondition($categories[DB_ITEM_TEACHER_ID], $categories[DB_ITEM_COURSE_ID])
                        );
                        if(isset($booklets[0])) {
                            // if there is some booklets
                            $answer = 'استاد ' . $booklets[0]['teacher'] . ' - ' . $booklets[0]['course'] . "\n\n جزوه ی موردنظرتو از لیست زیر انتخاب کن:";
                            if($user[DB_USER_ACTION] == ACTION_DOWNLOAD_BOOKLET) {
                                if(updateAction($user_id, ACTION_SELECT_BOOKLET_TO_GET)) {
                                    $keyboard = createSessionsMenu($booklets, $categories['options']);
                                    if($user[DB_USER_MODE] == ADMIN_USER || $user[DB_USER_MODE] == GOD_USER) {
                                        $downloads = 0;
                                        foreach($booklets as &$booklet)
                                            $downloads += $booklet[DB_ITEM_DOWNLOADS];
                                        $answer = appendStatsToMessage($answer, $downloads);
                                    }
                                    /*array_unshift($keyboard[INLINE_KEYBOARD], array(
                                        array(
                                            TEXT_TAG => 'Linked List',
                                            CALLBACK_DATA => $data
                                        )
                                    ));*/
                                } else {
                                    $answer = 'مشکلی حین دریافت اطلاعات پیش آمد! لطفا لحظاتی بعد دوباره تلاش کنید.';
                                    resetAction($user_id);
                                }
                            } else {
                                if(setActionAndCache($user_id, ACTION_SELECT_BOOKLET_TO_EDIT, $user[DB_USER_ACTION])) {
                                    $keyboard = createSessionsMenu($booklets, $categories['options'], false);
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
                    $keyboard = createClassifyByMenu($user_id, $categories);
                }
                break;
        }
    } else if($user[DB_USER_ACTION] == ACTION_SELECT_BOOKLET_TO_EDIT) {
        $edit_type = $user[DB_USER_CACHE];
        $booklets = Database::getInstance()->query('SELECT * FROM ' . DB_TABLE_BOOKLETS . ' WHERE ' . $data . ' LIMIT 1');
        if($booklets && count($booklets)) {
            if($edit_type == ACTION_EDIT_BOOKLET_CAPTION) {
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
        if(count($params) === 2) {
            // second menus: courses/teachers or yes/no menu for removing admins;
            // or no second menu (upload sample)
            switch($params[0]) {
                case DB_TABLE_COURSES:
                    switch($user[DB_USER_ACTION]) {
                        case ACTION_UPLOAD_SAMPLE:
                            $answer = 'نمونه سوال مورد نظر خود را همراه با کپشن بفرست:';
                            if(!setActionAndCache($user_id, ACTION_SENDING_SAMPLE_FILE, $params[1]))
                                $answer = 'مشکلی حین ورود به حالت آپلود نمونه سوال پیش آمد! لحظاتی دیگر دوباره تلاش کنید...';
                            break;
                        case ACTION_DOWNLOAD_SAMPLE:
                            $samples = getSamples($params[1]);
                            if(isset($samples[0])) {
                                // if there is some booklets
                                $answer = 'نمونه سوال موردنظر خود را از لیست زیر انتخاب کن:';
                                //if($user[DB_USER_ACTION] == ACTION_DOWNLOAD_SAMPLE) {
                                    if(updateAction($user_id, ACTION_SELECT_SAMPLE_TO_GET)) {
                                        $keyboard = createSamplesMenu($samples);
                                        if($user[DB_USER_MODE] == ADMIN_USER || $user[DB_USER_MODE] == GOD_USER) {
                                            $downloads = 0;
                                            foreach($samples as &$sample)
                                                $downloads += $sample[DB_SAMPLES_DOWNLOADS];
                                            $answer = appendStatsToMessage($answer, $downloads);
                                        }
                                    } else {
                                        $answer = 'مشکلی حین دریافت اطلاعات پیش آمد! لطفا لحظاتی بعد دوباره تلاش کنید.';
                                        resetAction($user_id);
                                    }
                                /*} else {
                                    if(setActionAndCache($user_id, ACTION_SELECT_SAMPLE_TO_EDIT, $user[DB_USER_ACTION])) {
                                        $keyboard = createSamplesMenu($samples, false);
                                    } else {
                                        $answer = 'مشکلی حین دریافت اطلاعات پیش آمد! لطفا لحظاتی بعد دوباره تلاش کنید.';
                                        resetAction($user_id);
                                    }
                                }*/
                            } else {
                                $answer = 'هنوز نمونه سوالی آپلود نشده است!';
                                resetAction($user_id);
                            }
                            break;
                        default:
                            $answer = 'از بین اساتید ارائه کننده این درس استاد مورد نظر خود را انتخاب کنید:';
                            if($user[DB_USER_ACTION] == ACTION_DOWNLOAD_BOOKLET) {
                                $keyboard = createCategoricalMenu(DB_TABLE_TEACHERS, $data, DB_ITEM_COURSE_ID . "=$params[1]",
                                    DB_ITEM_TEACHER_ID, $user[DB_USER_CACHE] ? ORDER_BY_MOST_DOWNLOADED_BOTH : ORDER_BY_NAME);
                                if(($user[DB_USER_MODE] == ADMIN_USER || $user[DB_USER_MODE] == GOD_USER) && $keyboard)
                                    $answer = appendStatsToMessage($answer, getDownloadStatistics(null, $params[1]));
                            } else
                                $keyboard = createCategoricalMenu(DB_TABLE_TEACHERS, $data);

                            if(!$keyboard) //means there is no option to select because of filtering
                                $answer = 'موردی یافت نشد!';
                            break;
                    }
                    break;
                case DB_TABLE_TEACHERS:
                    switch($user[DB_USER_ACTION]) {
                        case ACTION_LINK_TEACHER:
                            $answer = 'یوزرنیم استاد مورد نظر را وارد کنید یا یک پیام از او داخل ربات فوروارد کنید:';
                            if(!updateActionCache($user_id, $params[1]))
                                $answer = 'مشکلی حین ورود به حالت لینک اکانت استاد پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
                            break;
                        case ACTION_INTRODUCE_TEACHER:
                            $answer = "حالا متن معرفی استاد رو تایپ کنید. همچنین میتونی داخل متن لینک ویدیو هم قرار بدی. \n درصورتی که میخواهید معرفی نامه استاد را حذف کنید کافی ست کاراکتر خط تیره `-` را ارسال کنید.";
                            if(!updateActionCache($user_id, $params[1])) {
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
                            if($user[DB_USER_ACTION] == ACTION_DOWNLOAD_BOOKLET) {
                                $keyboard = createCategoricalMenu(DB_TABLE_COURSES, $data, DB_ITEM_TEACHER_ID . "=$params[1]",
                                    DB_ITEM_COURSE_ID, $user[DB_USER_CACHE] ? ORDER_BY_MOST_DOWNLOADED_BOTH : ORDER_BY_NAME
                                );
                                if(($user[DB_USER_MODE] == ADMIN_USER || $user[DB_USER_MODE] == GOD_USER) && $keyboard)
                                    $answer = appendStatsToMessage($answer, getDownloadStatistics($params[1]));
                            } else
                                $keyboard = createCategoricalMenu(DB_TABLE_COURSES, $data);

                            if(!$keyboard) //means there is no option to select because of filtering
                                $answer = 'موردی یافت نشد!';
                            break;
                    }
                    break;

                case DB_TABLE_USERS:
                // NOTE: like remove admin
                    switch($user[DB_USER_ACTION]) {
                        case ACTION_DOWNGRADE_USER:
                            if(downgradeUser($params[1])) 
                                $answer = 'کاربر موردنظر به دسترسی عادی بازگشت!';
                            else 
                                $answer = 'مشکلی حین تغییر کاربری پیش آمد. لطفا دوباره تلاش کن!';
                            
                            resetAction($user_id);
                            break;
                        case ACTION_SELECT_TEACHER_TO_CONTACT:
                            if(isset($params[1]) && setActionAndCache($user_id, ACTION_WRITE_MESSAGE, $params[1])) {
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
                            $answer = 'گزینه انتخاب شده اشتباه است!';
                            resetAction($user_id);
                            break;
                    }
                    break;
                case 'ns':
                    $answer = 'نمونه سوال بعدی را همراه با کپشن بفرست:';
                    if(!setActionAndCache($user_id, ACTION_SENDING_SAMPLE_FILE, $params[1]))
                        $answer = 'مشکلی حین ورود به حالت آپلود نمونه سوال بعدی پیش آمد! لحظاتی دیگر دوباره تلاش کنید...';
                    break;
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
