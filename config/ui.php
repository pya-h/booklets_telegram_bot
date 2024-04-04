<?php

// UI config
defined('MAX_COLUMN_LENGTH') or define('MAX_COLUMN_LENGTH', 40);
defined('LINKED_LIST_PAGE_LENGTH') or define('LINKED_LIST_PAGE_LENGTH', 10);
defined('CMD_GOD_ACCESS') or define('CMD_GOD_ACCESS', '/godAccess');

// USER MODE SPECIFIC MENUS
    // god mode options
    defined('CMD_ADD_ADMIN') or define('CMD_ADD_ADMIN', 'افزودن ادمین 💂‍♂️');
    defined('CMD_REMOVE_ADMIN') or define('CMD_REMOVE_ADMIN', 'حذف ادمین ❌');

    // god & admin options
    defined('CMD_ADD_COURSE') or define('CMD_ADD_COURSE', 'افزودن درس 📚');
    defined('CMD_ADD_TEACHER') or define('CMD_ADD_TEACHER', 'افزودن استاد 👨‍🏫');
    defined('CMD_UPLOAD') or define('CMD_UPLOAD', 'آپلود 📤');
    defined('CMD_UPLOAD_BOOKLET') or define('CMD_UPLOAD_BOOKLET', '📤 جزوه 📚');
    defined('CMD_UPLOAD_SAMPLE') or define('CMD_UPLOAD_SAMPLE', '📤 نمونه سوال 📑');

    defined('CMD_EDIT_BOOKLET') or define('CMD_EDIT_BOOKLET', 'ویرایش ✏️');
    defined('CMD_EDIT_BOOKLET_CAPTION') or define('CMD_EDIT_BOOKLET_CAPTION', 'ویرایش کپشن 🪶');
    defined('CMD_EDIT_BOOKLET_FILE') or define('CMD_EDIT_BOOKLET_FILE', 'ویرایش فایل 📝');
    defined('CMD_TEACHER_INTRODUCTION') or define('CMD_TEACHER_INTRODUCTION', 'معرفی استاد 📝');
    defined('CMD_STATISTICS') or define('CMD_STATISTICS', 'آمار 🧮');
    defined('CMD_SEND_POST_TO_CHANNEL') or define('CMD_SEND_POST_TO_CHANNEL', 'پست 📯');
    defined('CMD_NOTIFICATION') or define('CMD_NOTIFICATION', 'خبررسانی 📯');
    defined('CMD_LINK_TEACHER') or define('CMD_LINK_TEACHER', 'ارتقا به استاد 🔗');

    // teacher mode options
    defined('CMD_INTRODUCE_TA') or define('CMD_INTRODUCE_TA', 'معرفی TA 👩‍🎓');
    defined('CMD_REMOVE_TA') or define('CMD_REMOVE_TA', 'حذف TA ❌');

// COMMON MENU
    defined('CMD_DOWNLOAD_BY_COURSE') or define('CMD_DOWNLOAD_BY_COURSE', 'نام درس 📖');
    defined('CMD_DOWNLOAD_BY_TEACHER') or define('CMD_DOWNLOAD_BY_TEACHER', 'نام استاد 👨‍🏫');
    defined('CMD_DOWNLOAD_BY_MOST_DOWNLOADED_TEACHER') or define('CMD_DOWNLOAD_BY_MOST_DOWNLOADED_TEACHER', 'پردانلودترین استاد 👨‍🏫');
    defined('CMD_DOWNLOAD_BY_MOST_DOWNLOADED_COURSE') or define('CMD_DOWNLOAD_BY_MOST_DOWNLOADED_COURSE', 'پردانلودترین درس 📖');

    defined('CMD_DOWNLOAD_BOOKLET') or define('CMD_DOWNLOAD_BOOKLET', 'جزوه ها 📖');
    defined('CMD_DOWNLOAD_SAMPLE') or define('CMD_DOWNLOAD_SAMPLE', 'نمونه سوالات 📑');
    defined('CMD_MESSAGE_TO_ADMIN') or define('CMD_MESSAGE_TO_ADMIN', 'پشتیبانی 💬');
    defined('CMD_MESSAGE_TO_TEACHER') or define('CMD_MESSAGE_TO_TEACHER', 'ارتباط با استاد 💭👨‍🏫');
    defined('CMD_TEACHER_BIOS') or define('CMD_TEACHER_BIOS', 'معارفه 💭👨‍🏫');
    defined('CMD_MAIN_MENU') or define('CMD_MAIN_MENU', 'بازگشت به منو ↪️');
    defined('CMD_FAVORITES') or define('CMD_FAVORITES', 'علاقه مندی ها ❤️');
    defined('CMD_GET_BOOKLET_PREFIX') or define('CMD_GET_BOOKLET_PREFIX', '/bk');
defined('CMD_COMMAND_PARAM_SEPARATOR') or define('CMD_COMMAND_PARAM_SEPARATOR', '_');

# ORDERS
    defined('ORDER_BY_NAME') or define('ORDER_BY_NAME', 0);
    defined('ORDER_BY_MOST_DOWNLOADED_TEACHER') or define('ORDER_BY_MOST_DOWNLOADED_TEACHER', 1);
    defined('ORDER_BY_MOST_DOWNLOADED_COURSE') or define('ORDER_BY_MOST_DOWNLOADED_COURSE', 2);
    defined('ORDER_BY_MOST_DOWNLOADED_BOTH') or define('ORDER_BY_MOST_DOWNLOADED_BOTH', 3);