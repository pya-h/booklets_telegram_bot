<?php

defined('RELATED_DATA_SEPARATOR') or define('RELATED_DATA_SEPARATOR', '/');
defined('DATA_JOIN_SIGN') or define('DATA_JOIN_SIGN', '<>');
defined('INDEX_SEPARATOR') or define('INDEX_SEPARATOR', ':');

// database tables
defined('DB_TABLE_USERS') or define('DB_TABLE_USERS','users');
defined('DB_TABLE_COURSES') or define('DB_TABLE_COURSES','courses');
defined('DB_TABLE_TEACHERS') or define('DB_TABLE_TEACHERS','teachers');
defined('DB_TABLE_BOOKLETS') or define('DB_TABLE_BOOKLETS','booklets');
defined('DB_TABLE_MESSAGES') or define('DB_TABLE_MESSAGES','messages');
defined('DB_TABLE_FAVORITES') or define('DB_TABLE_FAVORITES','favorites');
defined('DB_TABLE_SAMPLES') or define('DB_TABLE_SAMPLES','samples');

// database table:COMMON fields
defined('DB_ITEM_ID') or define('DB_ITEM_ID','id');
defined('DB_ITEM_NAME') or define('DB_ITEM_NAME','name'); // for both course and teacher tables
defined('DB_ITEM_TEACHER_ID') or define('DB_ITEM_TEACHER_ID','teacher_id');
defined('DB_ITEM_COURSE_ID') or define('DB_ITEM_COURSE_ID','course_id');
defined('DB_ITEM_USER_ID') or define('DB_ITEM_USER_ID','user_id');
defined('DB_ITEM_FILE_ID') or define('DB_ITEM_FILE_ID','file_id');
defined('DB_ITEM_FILE_TYPE') or define('DB_ITEM_FILE_TYPE','type');
defined('DB_ITEM_DOWNLOADS') or define('DB_ITEM_DOWNLOADS','downloads');

// database table user fields:
defined('DB_USER_USERNAME') or define('DB_USER_USERNAME','username');
defined('DB_USER_ACTION') or define('DB_USER_ACTION','action');
defined('DB_USER_MODE') or define('DB_USER_MODE','mode');
defined('DB_USER_CACHE') or define('DB_USER_CACHE','action_cache');

// database table: teacher fields
defined('DB_TEACHER_BIO') or define('DB_TEACHER_BIO', 'bio');

//database table:booklets fields
defined('DB_BOOKLETS_CAPTION') or define('DB_BOOKLETS_CAPTION','caption');
defined('DB_BOOKLETS_INDEX') or define('DB_BOOKLETS_INDEX','index_name');

//database table:messages fields
defined('DB_MESSAGES_SENDER_ID') or define('DB_MESSAGES_SENDER_ID','sender_id');
defined('DB_MESSAGES_ANSWERED') or define('DB_MESSAGES_ANSWERED','answered');
defined('DB_MESSAGES_TARGET_GROUP') or define('DB_MESSAGES_TARGET_GROUP','target');

defined('MAX_GODS') or define('MAX_GODS', 3);

// user modes:
defined('NORMAL_USER') or define('NORMAL_USER', 0);
defined('ADMIN_USER') or define('ADMIN_USER', 1);
defined('GOD_USER') or define('GOD_USER', 2);
defined('TEACHER_USER') or define('TEACHER_USER', 3);
defined('TA_USER') or define('TA_USER', 4);