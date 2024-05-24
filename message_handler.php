<?php

function handleCasualMessage(&$update)
{
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
    $username = $update['message']['from']['username'] ?? null;
    $user = getUser($user_id, $username);
    $message = $update['message'];
    $message_id = $update['message'][MESSAGE_ID_TAG];
    $data = $message[TEXT_TAG] ?? null;
    $response = $keyboard = null;

    if ($data) {
        // most common options
        switch ($data) {
            case '/start':
            case '/cancel':
            case CMD_MAIN_MENU:
                $response = 'خب! چه کاری میتونم برات انجام بدم؟';
                resetAction($user_id);
                break;
            default:
                if ($user[DB_USER_ACTION] == ACTION_WRITE_MESSAGE) {
                    $target_group_id = $user[DB_USER_CACHE] ?? null; // null => admins, int => teachers and their TAs
                    saveMessage($user_id, $message_id, $target_group_id);
                    if ($target_group_id) {
                        $targets = getTeacherGroup($target_group_id);
                        $group_name = 'این استاد یا دستیار حل تمرین وی';
                    } else {
                        $targets = getSuperiors();
                        $group_name = 'تیم پشتیبانی';
                    }

                    foreach ($targets as &$target) {
                        callMethod(
                            METH_FORWARD_MESSAGE,
                            CHAT_ID,
                            $target[DB_ITEM_ID],
                            'from_chat_id',
                            $chat_id,
                            MESSAGE_ID_TAG,
                            $message_id
                        );
                        callMethod(
                            METH_SEND_MESSAGE,
                            CHAT_ID,
                            $target[DB_ITEM_ID],
                            TEXT_TAG,
                            'برای پاسخ به پیام بالا میتونی از گزینه زیر استفاده کنید',
                            KEYBOARD,
                            [
                                INLINE_KEYBOARD => [
                                    [[TEXT_TAG => 'پاسخ', CALLBACK_DATA => jsonifyCallbackData(IA_REPLY_MESSAGE, ['m' => $message_id])]],
                                ],
                            ]
                        );
                    }
                    $response = "پیام شما با موفقیت ارسال شد✅ \n در صورت لزوم، $group_name پاسخ را از طریق همین بات به شما اعلام خواهد کرد.";
                    resetAction($user_id);
                } else if ($user[DB_USER_ACTION] == ACTION_WRITE_REPLY_TO_USER && $user[DB_USER_MODE] != NORMAL_USER) {
                    if(!($msg = getMessage($user[DB_USER_CACHE]))) {
                        $response = 'چنین پیامی در دیتابیس وجود ندارد و امکان پاسخ دهی به آن نیست!';
                        break;
                    }

                    $answer_made_by = 'ادمین';
                    if ($user[DB_USER_MODE] == TEACHER_USER) {
                        $answer_made_by = 'استاد';
                    } else if ($user[DB_USER_MODE] == TA_USER) {
                        $answer_made_by = 'حل تمرین استاد';
                    }

                    callMethod(
                        METH_SEND_MESSAGE,
                        CHAT_ID,
                        $msg[DB_MESSAGES_SENDER_ID],
                        TEXT_TAG,
                        "$answer_made_by پیام شما را پاسخ داد.",
                        'reply_to_message_id',
                        $msg[DB_ITEM_ID],
                        KEYBOARD,
                        [
                            INLINE_KEYBOARD => [
                                [
                                    [
                                        TEXT_TAG => 'مشاهده',
                                        CALLBACK_DATA => jsonifyCallbackData(IA_SHOW_MESSAGE, ['m' => $message_id, 'fc' => $chat_id, 'r2' => $msg[DB_ITEM_ID]]),
                                    ],
                                ],
                            ],
                        ]
                    );
                    markMessageAsAnswered($user[DB_USER_CACHE]);
                    $response = 'پاسخ شما با موفقیت ارسال شد.';

                    resetAction($user_id);
                } else if (strpos($data, CMD_GET_BOOKLET_PREFIX) !== false) {
                    $params = explode(CMD_COMMAND_PARAM_SEPARATOR, $data);

                    if (!isset($params[2])) {
                        $response = 'دستور مورد نظر شناسایی نشد!';
                        break;
                    }

                    $response = 'طبقه بندی جزوه ها بر اساس:';
                    $categories = [DB_ITEM_TEACHER_ID => $params[1], DB_ITEM_COURSE_ID => $params[2]];
                    $keyboard = createClassifyByMenu(
                        $user_id,
                        $categories,
                        createCallbackData(IA_LIST_BOOKLETS, ['t' => $params[1], 'c' => $params[2]])
                    );

                } else
                    $response = handleGospel($user, $data);

                break;
        }
    }

    if (!$response) {
        switch ($data) {
            case CMD_DOWNLOAD_BOOKLET:
                $response = 'جست و جو بر اساس:';
                $keyboard = getDownloadOptions();
                break;
            case CMD_DOWNLOAD_SAMPLE:
                $keyboard = createCategoricalMenu(
                    IA_LIST_SAMPLES,
                    DB_TABLE_COURSES,
                    null,
                    false,
                    ORDER_BY_NAME,
                    null,
                    entityIsReferencedInAnotherTableQuery(DB_TABLE_COURSES, DB_TABLE_SAMPLES, DB_ITEM_COURSE_ID),
                );
                $response = $keyboard ? 'درس مورد نظر خود را از لیست زیر انتخاب کنید:' : 'هنوز نمونه سوالی آپلود نشده است!';
                break;
            case CMD_GOD_ACCESS:
                if (!isGodEnough()) {
                    $response = 'God\'s Name:';
                    if (!updateAction($user_id, ACTION_WHISPER_GODS_NAME)) {
                        $response = 'خطای غیرمنتظره پیش آمد! دوباره تلاش کنید!';
                        resetAction($user_id);
                    }
                }
                break;
            case CMD_DOWNLOAD_BY_COURSE:
            case CMD_DOWNLOAD_BY_MOST_DOWNLOADED_COURSE:
                $response = "درس مورد نظر خود را از لیست زیر انتخاب کنید:";
                $keyboard = createCategoricalMenu(IA_LIST_BOOKLETS, DB_TABLE_COURSES, null, false, 
                    $data == CMD_DOWNLOAD_BY_COURSE ? ORDER_BY_NAME : ORDER_BY_MOST_DOWNLOADED_COURSE);
                if(!$keyboard) {
                    $response = 'در حال حاضر هیچ عنوان درسی ای در ربات ثبت نشده است.';
                }
                break;
            case CMD_DOWNLOAD_BY_TEACHER:
            case CMD_DOWNLOAD_BY_MOST_DOWNLOADED_TEACHER:
                $response = "استاد مورد نظر خود را از لیست زیر انتخاب کنید:";
                $keyboard = createCategoricalMenu(IA_LIST_BOOKLETS, DB_TABLE_TEACHERS, null, false, 
                    $data == CMD_DOWNLOAD_BY_TEACHER ? ORDER_BY_NAME : ORDER_BY_MOST_DOWNLOADED_TEACHER);
                if(!$keyboard) {
                    $response = 'در حال حاضر نام هیچ استادی در ربات ثبت نشده است.';
                }
                break;
            case CMD_MESSAGE_TO_ADMIN:
                if (updateAction($user_id, ACTION_WRITE_MESSAGE)) {
                    $response = 'متن خود را در قالب یک پیام ارسال کنید.📝';
                    $keyboard = backToMainMenuKeyboard();
                } else {
                    $response = 'حین ورود به حالت ارسال پیام مشکلی پیش آمد. لطفا دوباره تلاش کنید!';
                    resetAction($user_id);
                }
                break;
            case CMD_MESSAGE_TO_TEACHER:
                $keyboard = createUsersMenu(IA_CONTACT_TEACHER, DB_USER_MODE . '=' . TEACHER_USER . ' AND ' . DB_ITEM_TEACHER_ID . ' IS NOT NULL', DB_ITEM_TEACHER_ID);
                logText(json_encode($keyboard));
                if ($keyboard) {
                    $response = 'استادهای زیر در بات فعال هستند و می توانید به آن ها پیام دهید:';
                } else {
                    $response = 'در حال حاضر هیچ استادی در بات فعالیت ندارد!';
                }
                break;
            case CMD_TEACHER_BIOS:
                $response = "شما می توانید معرفی نامه هر یک از اساتید زیر را با کلیک روی اسم وی مشاهده کنید.";
                $keyboard = createCategoricalMenu(IA_SELECT_TEACHER_OPTIONS, DB_TABLE_TEACHERS, null, false, ORDER_BY_NAME, fn ($id) => [
                    'a' => IA_SELECT_TEACHER_OPTIONS,
                    'p' => [
                        'op' => 'bio',
                        'id' => $id,
                    ],
                ]);
                if(!$keyboard) {
                    $response = 'در حال حاضر بیوگرافی هیچ استادی در ربات ثبت نشده است.!';
                }
                break;
            case CMD_FAVORITES:
                $favs = getFavoritesList($user_id);
                $response = createLinkedList($favs);
                if (count($favs) > LINKED_LIST_PAGE_LENGTH) {
                    $keyboard = [
                        INLINE_KEYBOARD => [
                            [
                                [TEXT_TAG => "بعدی", CALLBACK_DATA => jsonifyCallbackData(IA_LIST_FAVORITES, ['pg' => 1])],
                            ],
                        ],
                    ];
                }
                break;
            default:
                $response = null;
                break;
        }
    }

    if (!$response) {
        switch ($user[DB_USER_MODE]) {
            case GOD_USER:
                if ($data === CMD_ADD_ADMIN) {
                    if (updateAction($user_id, ACTION_ADD_ADMIN)) {
                        $response = 'یوزرنیم کاربر مورد نظر را وارد کنید یا یک پیام از او داخل ربات فوروارد کنید:';
                        $keyboard = backToMainMenuKeyboard();
                    } else {
                        $response = 'مشکلی حین ورود به حالت اضافه کردن ادمین پیش آمد. لطفا دوباره تلاش کنید!';
                        resetAction($user_id);
                    }
                    break;
                } else if ($user[DB_USER_ACTION] == ACTION_ADD_ADMIN) {
                    $response = startUpgradingUser($user_id, $message, ADMIN_USER, 'ادمین');
                    break;
                } else if ($data === CMD_REMOVE_ADMIN) {
                    $keyboard = createUsersMenu(IA_DOWNGRADE_ADMIN, DB_USER_MODE . '=' . ADMIN_USER);
                    if ($keyboard) {
                        $response = 'روی شخص موردنظرت کلیک کن تا از حالت ادمین خارج شود:';
                    } else {
                        $response = 'هیج ادمینی یافت نشد!';
                    }
                    break;
                }
            case ADMIN_USER:
                // get admin's action value
                if (!$user[DB_USER_ACTION]) {
                    // if action value is none
                    switch ($data) {
                        case CMD_UPLOAD:
                            $keyboard = backToMainMenuKeyboard([CMD_UPLOAD_SAMPLE, CMD_UPLOAD_BOOKLET]);
                            $response = 'چه چیزی میخواهید آپلود کنید؟';
                            break;
                        case CMD_UPLOAD_BOOKLET:
                        case CMD_EDIT_BOOKLET_FILE:
                        case CMD_EDIT_BOOKLET_CAPTION:
                            $response = 'از لیست زیر درس موردنظر خود را انتخاب کنید:';
                            $keyboard = createCategoricalMenu([
                                CMD_UPLOAD_BOOKLET => IA_UPLOAD_BOOKLET,
                                CMD_EDIT_BOOKLET_FILE => IA_EDIT_BOOKLET_FILE, CMD_EDIT_BOOKLET_CAPTION => IA_EDIT_BOOKLET_CAPTION,
                            ][$data], DB_TABLE_COURSES);
                            break;
                        case CMD_UPLOAD_SAMPLE:
                            $response = 'از لیست زیر درس موردنظر خود را انتخاب کنید:';
                            $keyboard = createCategoricalMenu(IA_UPLOAD_SAMPLE, DB_TABLE_COURSES);
                            break;
                        case CMD_STATISTICS:
                            $response = "آمار ربات: \n";
                            foreach (getStatistics() as $field => $stat) {
                                $response .= "{$stat['fa']}: {$stat['total']} \n";
                            }
                            break;
                        case CMD_SEND_POST_TO_CHANNEL:
                            if (updateAction($user_id, ACTION_SEND_POST_TO_CHANNEL)) {
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
                        case CMD_ADD_ENTITY:
                            $keyboard = getAddEntityOptions();
                            $response = 'چه چیزی میخواهید اضافه کنید؟';
                            break;
                        case CMD_ADD_COURSE:
                        case CMD_ADD_TEACHER:
                        case CMD_ADD_CATEGORY:
                        case CMD_ADD_AUTHOR:
                            $entity = [
                                CMD_ADD_COURSE => [
                                    'a' => ACTION_ADD_COURSE,
                                    't' => 'درس'
                                ],
                                CMD_ADD_TEACHER => [
                                    'a' => ACTION_ADD_TEACHER,
                                    't' => 'استاد'
                                ],
                                CMD_ADD_CATEGORY => [
                                    'a' => ACTION_ADD_CATEGORY,
                                    't' => 'کتگوری'
                                ],
                                CMD_ADD_AUTHOR => [
                                    'a' => ACTION_ADD_AUTHOR,
                                    't' => 'نویسنده'
                                ]
                            ][$data];
                            if (updateAction($user_id, $entity['a'])) {
                                $response = 'نام ' . $entity['t'] . ' جدید رو وارد کنید:';
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                $response = 'خطایی غیرمنتظره اتفاق افتاد. لطفا دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_LINK_TEACHER:
                        case CMD_TEACHER_INTRODUCTION:
                            $response = "استاد مورد نظر خود را از لیست زیر انتخاب کنید:";
                            $keyboard = createCategoricalMenu(IA_SELECT_TEACHER_OPTIONS, DB_TABLE_TEACHERS, null, false, ORDER_BY_NAME, fn ($id) => [
                                'a' => IA_SELECT_TEACHER_OPTIONS,
                                'd' => [
                                    'op' => $data !== CMD_TEACHER_INTRODUCTION ? 'lnk' : 'int',
                                    'id' => $id,
                                ],
                            ]);
                            break;
                        case CMD_NOTIFICATION:
                            if (updateAction($user_id, ACTION_SEND_NOTIFICATION, true)) {
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
                    switch ($user[DB_USER_ACTION]) {
                        case ACTION_SENDING_BOOKLET_FILE: // TODO: combine case with ACTION_SENDING_SAMPLE_FILE
                            $file = getFileFrom($message);
                            $keyboard = backToMainMenuKeyboard();
                            if (!$file) {
                                $response = 'هیچ فایلی ارسال نشده. دوباره ارسال کنید!';
                                break;
                            }

                            $result = addBooklet($user, $file);
                            if (isset($result['err'])) {
                                $response = $result['err'];
                                break;
                            }
                            if (setActionAndCache($user_id, ACTION_SET_BOOKLET_CAPTION, json_encode($result))) {
                                // TOCHECK:
                                $response = 'جزوه مورد نظر با موفقیت ارسال شد. حالا کپشن جزوه را مشخص کنید:';
                                $keyboard = [
                                    INLINE_KEYBOARD => [
                                        [
                                            //columns:
                                            [TEXT_TAG => 'کپشن فایل', CALLBACK_DATA => jsonifyCallbackData(IA_SET_CAPTION, ['e' => 'b', 'def' => true])],
                                            [TEXT_TAG => 'وارد کردن کپشن', CALLBACK_DATA => jsonifyCallbackData(IA_SET_CAPTION, ['e' => 'b', 'def' => false])],
                                        ],
                                    ],
                                ];
                            } else {
                                $response = 'جزوه ثبت شد ولی مشکلی حین ورود به حالت تعیین کپشن پیش آمد!';
                            }

                            break;
                        case ACTION_SENDING_SAMPLE_FILE: // TODO: combine case with ACTION_SENDING_BOOKLET_FILE
                            $file = getFileFrom($message);
                            $keyboard = backToMainMenuKeyboard();
                            if (!$file) {
                                $response = 'هیچ فایلی ارسال نشده. دوباره ارسال کنید!';
                                break;
                            }

                            $result = addSample($user, $file);
                            if (isset($result['err'])) {
                                $response = $result['err'];
                                break;
                            }

                            if (setActionAndCache($user_id, ACTION_SET_SAMPLE_TITLE, json_encode($result))) {
                                $response = 'نمونه سوال مورد نظر با موفقیت ارسال شد. حالا عنوان آن را تایپ کنید:';
                                $keyboard = [
                                    INLINE_KEYBOARD => [
                                        [
                                            //columns:
                                            [TEXT_TAG => 'کپشن فایل', CALLBACK_DATA => jsonifyCallbackData(IA_SET_CAPTION, ['e' => 'sm', 'def' => true])],
                                            [TEXT_TAG => 'وارد کردن کپشن', CALLBACK_DATA => jsonifyCallbackData(IA_SET_CAPTION, ['e' => 'sm', 'def' => false])],
                                        ],
                                    ],
                                ];
                            } else {
                                $response = 'نمونه سوال ثبت شد ولی مشکلی حین ورود به حالت تعیین عنوان پیش آمد!';
                            }

                            break;
                        case ACTION_SET_BOOKLET_CAPTION:
                            $response = backupBooklet($user, $data);
                            if (!$response) {
                                $response = "کپشن موردنظر با موفقیت ثبت شد! حالا جزوه بعدی رو بفرست: \nنکته: برای اتمام فرایند آپلود جزوات این درس از گزینه بازگشت به منو استفاده کنید یا روی دستور زیر کلیک کنید:\n /cancel";
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                resetAction($user_id);
                            }
                            break;

                        case ACTION_SET_SAMPLE_TITLE:
                            $response = backupSample($user, $data);
                            if (!$response) {
                                $response = "کپشن موردنظر با موفقیت ثبت شد! حالا نمونه سوال بعدی رو بفرست: \nنکته: برای اتمام فرایند آپلود نمونه سوالات این درس از گزینه بازگشت به منو استفاده کنید یا روی دستور زیر کلیک کنید:\n /cancel";
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                resetAction($user_id);
                            }
                            break;

                        case ACTION_EDIT_BOOKLET_FILE:
                            $file = getFileFrom($message);
                            if (!$file) {
                                $response = 'هیچ فایلی ارسال نشده. دوباره ارسال کنید!';
                                $keyboard = backToMainMenuKeyboard();
                            } else if (changeBookletFile($user[DB_USER_CACHE], $file)) {
                                $response = backupBooklet($user);
                                if (!$response) {
                                    $response = "ویرایش فایل این جزوه با موفقیت انجام شد! حالا جزوه بعدی رو بفرست: \nنکته: برای اتمام فرایند آپلود جزوات این درس از گزینه بازگشت به منو استفاده کنید یا روی دستور زیر کلیک کنید:\n /cancel";
                                    $keyboard = backToMainMenuKeyboard();
                                } else {
                                    resetAction($user_id);
                                }
                            }
                            break;
                        case ACTION_SEND_POST_TO_CHANNEL:
                            if ($data) {
                                callMethod(
                                    METH_SEND_MESSAGE,
                                    CHAT_ID,
                                    FIRST_2_JOIN_CHANNEL_ID,
                                    TEXT_TAG,
                                    $data,
                                    KEYBOARD,
                                    [
                                        INLINE_KEYBOARD => [
                                            [
                                                [TEXT_TAG => 'برای دانلود جزوات کلیک کنید', INLINE_URL_TAG => PERSIAN_COLLEGE_BOT_LINK],
                                            ],
                                            [
                                                [TEXT_TAG => 'کانال یوتیوب ما', INLINE_URL_TAG => PERSIAN_COLLEGE_YOUTRUBE_LINK],
                                            ],
                                        ],
                                    ]
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
                            if ($user[DB_USER_CACHE]) {
                                $response = startUpgradingUser($user_id, $message, TEACHER_USER, 'استاد', $user[DB_USER_CACHE]);
                            } else {
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
                            if ($user[DB_USER_CACHE]) {
                                $response = 'معرفی نامه استاد با موفقیت به روزرسانی شد.';
                                if (!introduceTeacher($user[DB_USER_CACHE], $data != '-' ? $data : null)) {
                                    $response = 'حین ذخیره متن معرفی نامه خطای نامعلوم اتفاق افتاد! لطفا لحظاتی بعد دوباره تلاش کنید.';
                                }

                                resetAction($user_id);
                            } else {
                                $response = "ابتدا باید استاد موردنظر را انتخاب کنید و سپس اقدام به نوشتن معرفی نامه کنید. درصورتی که از این عملیات منصرف شده اید روی دستور لغو کلیک کنید:
                                \n/cancel";
                            }
                            break;
                        case ACTION_SEND_NOTIFICATION:
                            if ($data) {
                                $users = getAllUsers();
                                $count = count($users);
                                $progress_trigger = (int) ($count / 20);
                                if (!$progress_trigger) {
                                    $progress_trigger = 1;
                                }

                                $progress_text = "در حال ارسال پیام ... ";
                                $telegram_response = callMethod(
                                    METH_SEND_MESSAGE,
                                    CHAT_ID,
                                    $chat_id,
                                    TEXT_TAG,
                                    $progress_text,
                                    'reply_to_message_id',
                                    $message_id
                                );
                                $progress_msg_id = extractFromSentMessage($telegram_response);
                                for ($i = 0; $i < $count; $i++) {
                                    $telegram_response = callMethod(
                                        METH_SEND_MESSAGE,
                                        TEXT_TAG,
                                        $data,
                                        CHAT_ID,
                                        $users[$i]
                                    );
                                    // update username of the user
                                    $dest = extractFromSentMessage($telegram_response, 'chat');
                                    if (isset($dest['username'])) {
                                        updateUserField($users[$i], '@' . $dest['username'], DB_USER_USERNAME);
                                    }
                                    if ($i % $progress_trigger == 0) {
                                        $progress = sprintf("%.2f %%", 100 * $i / $count);
                                        callMethod(
                                            METH_EDIT_MESSAGE,
                                            CHAT_ID,
                                            $chat_id,
                                            MESSAGE_ID_TAG,
                                            $progress_msg_id,
                                            TEXT_TAG,
                                            "$progress_text $progress"
                                        );
                                    }
                                }
                                callMethod(
                                    METH_EDIT_MESSAGE,
                                    CHAT_ID,
                                    $chat_id,
                                    MESSAGE_ID_TAG,
                                    $progress_msg_id,
                                    TEXT_TAG,
                                    $progress_text . "100 % \nپیام با موفقیت برای کاربران ($count نفر) ارسال شد.",
                                    CHAT_ID,
                                    $chat_id
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
                if ($user[DB_USER_ACTION] == ACTION_ADD_TA) {
                    $response = startUpgradingUser($user_id, $message, TA_USER, 'حل تمرین شما', $user[DB_USER_CACHE]);
                } else if ($user[DB_USER_ACTION] == ACTION_ASSIGN_USER_NAME) {
                    $response = updateUserField($user[DB_USER_CACHE], $data) ? "$data به عنوان حل تمرین شما ثبت شد. "
                        : 'مشکلی در ثبت اسم کاربر پیش آمد!';
                    resetAction($user_id);
                } else {
                    switch ($data) {
                        case CMD_STATISTICS:
                            if(!($response = getTeachersFullDownloadStats($user[DB_ITEM_TEACHER_ID]))) {
                                $response = 'در حال حاضر هیچ ارائه ای از شما در این ربات وجود ندارد.';
                            }
                            break;
                        case CMD_INTRODUCE_TA:
                            if (setActionAndCache($user_id, ACTION_ADD_TA, $user[DB_ITEM_TEACHER_ID])) {
                                $response = 'یوزرنیم کاربر مورد نظر را وارد کنید یا یک پیام از او داخل ربات فوروارد کنید:';
                                $keyboard = backToMainMenuKeyboard();
                            } else {
                                $response = 'مشکلی حین ورود به حالت معرفی TA پیش آمد. لطفا لحظاتی بعد دوباره تلاش کنید!';
                                resetAction($user_id);
                            }
                            break;
                        case CMD_REMOVE_TA:
                            $keyboard = createUsersMenu(IA_REMOVE_TA, DB_ITEM_TEACHER_ID . '=' . $user[DB_ITEM_TEACHER_ID] . ' AND ' . DB_USER_MODE . '=' . TA_USER);
                            if ($keyboard) {
                                $response = 'روی شخص موردنظرت کلیک کن تا از لیست TA های شما خارج شود:';
                            } else {
                                $response = 'شما هنوز هیچ TA ای معرفی نکرده اید!';
                            }

                            break;
                    }
                }
                break;
        }
    }

    if (!$response) {
        $response = "متوجه نشدم! لطفا دوباره تلاش کنید...";
        resetAction($user_id);
    }
    callMethod(
        METH_SEND_MESSAGE,
        CHAT_ID,
        $chat_id,
        TEXT_TAG,
        $response,
        'reply_to_message_id',
        $message_id,
        KEYBOARD,
        $keyboard ?? getMainMenu($user[DB_USER_MODE] ?? 0)
    );
}
