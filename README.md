# Booklets Telegram Bot

# Language: PHP
# Database: MySQL

Telegram bot designed for downloading and uploading booklets categorized by teacher and course name.

Running at: https://t.me/Persian_collegebot

# Common options that every user has access to:
1. Download booklets ordered by name and categorized by teacher and courses
2. Download booklets ordered by download counts and categorized by teacher and courses
3. Support: Contact admins. Message sent by the user will be sent for all admins/gods.
4. Contact Teachers that are signed up in this bot, and also their TAs.
5. Read teachers biography

# Special Options: Teachers & TAs
1. Define/Remove his/her Teaching assistants(TAs).
    * TAs don't have any special option compared to normal users. TAs just can answer the messages sent for their teacher.
2. Obtain a full statistics for The course & booklets presented by this teacher. So the teacher can see the download counts on his/her each presented course, and the total downloads of them.

# Special Options: Admin & Superusers(=GOD)
1. Upload Booklets and Add Course/Teacher names.
2. Edit booklet captions or change their file.
3. Obtain the statistic of the bot, including number of users, courses, teachers, booklets, etc.
4. Send Posts to main channel including glass buttons.
5. Send posts to every bot user; It can be used to:
    * 1- Notify users about an issue or update
    * 2- advertisement.
    * Note: while sending post to all users, The bot will simultaneously show progress state.
6. Write biography for teachers.
7. Upgrade a user to Teacher mode. This is used to link Teacher names in the database to an actual Telegram user.
    * After this is done for a user, All users can contact that user as a teacher and his/her Teaching Assistants(TAs).
8. See the number of downloads for every teacher, course or booklets, while using Download booklet option.
    * When an admin clicks on Download Booklet, and then Download by teacherName/courseName, in next menu he/she will see the number of all downloads related to this teacher/course
    meaning that he/she will see the sum of download counts on this teacher/course booklets.
    * When admin specifies both teacher and course names in the menu, he/she will see the download count of the course that is presented by the selected teacher.
    * This precedure continues hierarchically.
9. Answer user messages sent with Support option. If the message is answered in the past, then bot will notify the admin trying to answer again.

# God Users:
1. All above features (except for support section, obviously!)
2. Add/Remove Other admins

# Special mode users Authorizations:
* God users can log in through /godAccess command and entering the username and password of the god.
    * Maximum number of gods allowed is 3 (can be changed in database.php, by changing MAX_GODS constant); when number of gods logged in becomes 3, /godAccess command will be disabled.
* Admin: Gods can promote a user to Admin User.
    * God will select 'Add admin' option, then he/she can promote a bot user to admin, by forwarding a message from him'her, or by entering username(must start with @)
    * After that bot will ask for a name; this name will be the name of that specific user (not the telegram name, because some users enter nonsense as the name)
* Teacher: Gods and admins can promote a user to teacher mode.
    * After selecting 'Link Teacher Account' Option, bot will ask which teacher(From the teachers list) is going to be linked to a telegram user. Then admion can forward a message,
        or enter teacher's telegram username starting with @.
* TA: TA user can only be added/removed by the teacher. Each teacher can add/remove his/her own TAs.

# Note:
* Each user can have just one mode: God, Admin, Teacher, TA or normal user. Trying to set two modes for one user, will simply override his/her current mode.

# .env file pattern:
    DB_USER = "DatabaseUsername"
    DB_PASSWORD = "DatabaseUsernamesPassword"
    DB_NAME = "DatabaseName"
    TOKEN = "TelegramBotToken"
    GOD_NAME = "superuser name"
    GOD_SECRET = "superuser pass"
