<?php
require_once __DIR__ . '/config/credentials.php';
require_once __DIR__ . '/config/db.php';



// database engine
class Database
{
  private $connection;
  private static $database;

  public static function getInstance(): Database
  {
    if (self::$database == null) {
      self::$database = new Database();
    }

    return self::$database;
  }

  private function __construct()
  {
    $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($this->connection->connect_error) {
      echo "Connection failed: " . $this->connection->connect_error;
      exit;
    }
    $this->connection->query("SET NAMES 'utf8'");
  }

  public function update(string $sql_query, array $query_data = array())
  {
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }
    return $result;
  }

  public function insert(string $sql_query, array $query_data = array())
  {
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }
    return mysqli_insert_id($this->connection);
    ;
  }

  private function safeQuery(string $sql_query, ?array $query_data)
  {
    if ($query_data)
      foreach ($query_data as $key => $value) {
        if ($value) {
          $value = $this->connection->real_escape_string($value);
          $value = is_string($value) ? "'$value'" : "$value";
        } else
          $value = "NULL";
        $sql_query = str_replace(":$key", $value, $sql_query);
      }
    return $this->connection->query($sql_query);
  }

  public function query(string $sql_query, ?array $query_data = null, ?string $specific_column = null): ?array
  {
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }

    $records = array();
    if ($result->num_rows == 0) {
      return $records;
    }
    // FIXME: If there is a specific column specified thyen apply that while doing the query to optimiz sql query
    while ($row = $result->fetch_assoc()) {
      $records[] = !$specific_column ? $row : $row[$specific_column];
    }
    return $records;
  }

  public function getConnection(): mysqli
  {
    return $this->connection;
  }

  public function fuckoff()
  {
    $this->connection->close();
  }

}

// project specific functions:
function extractCategories(array $categories, $options = null): array
{
    if (!isset($categories[1]))
        return ['err' => 'یکی از فیلدهای درس یا استاد به درستی انتخاب نشده است!'];

    $result = [];

    foreach ($categories as $category) {
        switch($category['t']) {
            case 'cr':
                $result[DB_ITEM_COURSE_ID] = $category['id'];
                break;
            case 'tc':
                $result[DB_ITEM_TEACHER_ID] = $category['id'];
                break;

            default:
                return ['err' => 'یکی از فیلدهای درس یا استاد به درستی انتخاب نشده است!'];
        }
    }

    if(!$result[DB_ITEM_TEACHER_ID])
        return ['err' => 'به نظر می رسد استاد موردنظر به درستی انتخاب نشده است!'];

    if(!$result[DB_ITEM_COURSE_ID])
        return ['err' => 'به نظر می رسد درس  موردنظر درستی انتخاب نشده است!'];

    $result['options'] = $options !== null ? $options : -1;

    return $result;
}

function getStatistics(): array
{
  $db = Database::getInstance();
  $users = $db->query('SELECT * FROM ' . DB_TABLE_USERS);
  $other_tables = array(DB_TABLE_COURSES => 'تعداد درس ها', DB_TABLE_TEACHERS => 'تعداد اساتید', DB_TABLE_BOOKLETS => 'تعداد جزوات', DB_TABLE_MESSAGES => "تعداد پیام های کاربران");
  $admins = array_filter($users, function ($item) {
    return $item[DB_USER_MODE] == ADMIN_USER;
  });
  $stats = array(DB_TABLE_USERS => array('total' => count($users), 'fa' => 'تعداد کل کاربران ربات'), 'admins' => array('total' => count($admins), 'fa' => 'تعداد ادمین ها'));
  foreach ($other_tables as $table => $table_fa) {
    $result = $db->query("SELECT COUNT(*) AS TOTAL FROM $table");
    $stats[$table] = array('total' => $result[0]['TOTAL'] ?? 0, 'fa' => $table_fa);
  }
  return $stats;
}

function categoricalWhereClause($teacher_id, $course_id): string
{
  return DB_TABLE_BOOKLETS . '.' . DB_ITEM_TEACHER_ID . "=$teacher_id AND "
    . DB_TABLE_BOOKLETS . '.' . DB_ITEM_COURSE_ID . "=$course_id";
}

function entityIsReferencedInAnotherTableQuery($table, $another_table, $foreign_key): string
{
  return "(select count(" . DB_ITEM_ID . ") from $another_table where $table." . DB_ITEM_ID . " = $another_table.$foreign_key) > 0";
}