# Booklets Telegram Bot

# Language: PHP
# Database: MySQL

Telegram bot designed for downloading and uploading booklets categorized by teacher and course name.

Running at: https://t.me/Persian_projectbot

# Common options that every user has access to:
1. Download booklets ordered by name and categorized by teacher and courses
2. Download booklets ordered by download counts and categorized by teacher and courses
3. Support: Contact admins. Message sent by the user will be sent for all admins/gods.
4. Contact Teachers that are signed up in this bot, and also their TAs.
5. Read teachers biography

# Special Options: Teachers & TAs
1. Define/Remove his/her Teaching assistants(TAs).
    * TAs doesnt have any special option compared to normal users. TAs just can answer the messages sent for their teacher.
2. Obtain a full statistics for The course & booklets presented by this teacher. So the teacher can see the download counts on his/her each presented course, and the total downloads of them.

# Special Options: Admin & Superusers(=GOD)
1. Upload Booklets
2. Edit booklet captions or change their file.
3. Obtain the statistic of the bot, containg number of users, courses, teachers, booklets, etc.
4. Send Posts to main channel containing glass buttons.
5. Send posts to every bot user; It can be used to: 1- Notify users about an issue or update, 2. advertisement.
    * Note: while sending post to all users, The bot will simultaneously show a progress state.
6. Write biography for teachers.
7. Upgrade a user to Teacher mode. This is used to link Teacher names in the database to an actual Telegram user.
    * After this is done for a user, All users can contact that user as a teacher and his/her Teaching Assistants(TAs).
8. See the number of downloads for every teacher, course or booklets, while using Download booklet option.
    * When an admin clicks on Download Booklet, and then Download by teacherName/courseName, in next menu he/she will see the number of all downloads related to this teacher
    meaning that he/she will see the sum of download counts on this teacher/course booklets.
    * When admin specifies both teacher and course names in the menu, he/she will see the download count of the course that is presented by the selected teacher.
    * This precedure continues hierarchically.
9. Answer user messages sent with Support option. If the message is answered in the past, the bot will notify the admin trying to answer again.

# God Users:
1. All above features (except for support section, obviously!)
2. Add/Remove Other admins

# .env file pattern:
    DB_USER = "DatabaseUsername"
    DB_PASSWORD = "DatabaseUsernamesPassword"
    DB_NAME = "DatabaseName"
    TOKEN = "TelegramBotToken"
    GOD_NAME = "superuser name"
    GOD_SECRET = "superuser pass"
