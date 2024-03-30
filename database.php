<?php
require_once __DIR__ . '/config/credentials.php';
require_once __DIR__ . '/config/db.php';



// database engine
class Database {
  private $connection;
  private static $database;

  public static function getInstance($option = null): Database
  {
    if (self::$database == null){
      self::$database = new Database($option);
    }

    return self::$database;
  }

  private function __construct(){
    $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($this->connection->connect_error) {
      echo "Connection failed: " . $this->connection->connect_error;
      exit;
    }
    $this->connection->query("SET NAMES 'utf8'");
  }

  public function update(string $sql_query, array $query_data = array()) {
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }
    return $result;
  }

  public function insert(string $sql_query, array $query_data = array()){
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }
    return mysqli_insert_id($this->connection);;
  }

  private function safeQuery(string $sql_query, ?array $query_data) {
    if($query_data)
      foreach ($query_data as $key=>$value){
        if($value) {
          $value = $this->connection->real_escape_string($value);
          $value = is_string($value) ? "'$value'" : "$value";
        } else $value = "NULL";
        $sql_query = str_replace(":$key", $value, $sql_query);
      }
    return $this->connection->query($sql_query);
  }

  public function query(string $sql_query, ?array $query_data = null, ?string $specific_column = null): ?array {
    $result = $this->safeQuery($sql_query, $query_data);
    if (!$result) {
      echo "Query: " . $sql_query . " failed due to " . mysqli_error($this->connection);
      return null;
    }

    $records = array();
    if ($result->num_rows == 0) {
      return $records;
    }
    while($row = $result->fetch_assoc()) {
      $records[] = !$specific_column ? $row : $row[$specific_column];
    }
    return $records;
  }

  public function getConnection(): mysqli
  {
    return $this->connection;
  }

  public function fuckoff(){
    $this->connection->close();
  }

}

// project specific functions:
function extractCategories(string &$data): array
{
  $categories = explode(DATA_JOIN_SIGN, $data);

  if(count($categories) < 2)
    return array('err' => 'یکی از فیلدهای درس یا استاد به درستی انتخاب نشدن!');
  $course = null;
  $teacher = null;
  if(strpos($categories[0], DB_TABLE_COURSES) !== false && strpos($categories[1], DB_TABLE_TEACHERS) !== false) {
    $course = explode(RELATED_DATA_SEPARATOR, $categories[0]);
    $teacher = explode(RELATED_DATA_SEPARATOR, $categories[1]);
  } else if(strpos($categories[1], DB_TABLE_COURSES) !== false && strpos($categories[0], DB_TABLE_TEACHERS) !== false) {
    $course = explode(RELATED_DATA_SEPARATOR, $categories[1]);
    $teacher = explode(RELATED_DATA_SEPARATOR, $categories[0]);
  }
  if(!$course || !$teacher || count($course) !== 2 || count($teacher) !== 2)
    return array('err' => 'یکی از فیلدهای درس یا استاد به درستی انتخاب نشدن!');

  return array(DB_ITEM_TEACHER_ID => $teacher[1], DB_ITEM_COURSE_ID => $course[1], 'options' => $categories[2] ?? -1);
}

function getStatistics(): array
{
    $db = Database::getInstance();
    $users = $db->query('SELECT * FROM ' . DB_TABLE_USERS);
    $other_tables = array(DB_TABLE_COURSES => 'تعداد درس ها', DB_TABLE_TEACHERS => 'تعداد اساتید', DB_TABLE_BOOKLETS => 'تعداد جزوات', DB_TABLE_MESSAGES => "تعداد پیام های کاربران");
    $admins = array_filter($users, function($item) {
        return $item[DB_USER_MODE] == ADMIN_USER;
    });
    $stats = array(DB_TABLE_USERS => array('total' => count($users), 'fa' => 'تعداد کل کاربران ربات'), 'admins' => array('total' => count($admins), 'fa' => 'تعداد ادمین ها'));
    foreach($other_tables as $table=>$table_fa) {
        $result = $db->query("SELECT COUNT(*) AS TOTAL FROM $table");
        $stats[$table] = array('total' => $result[0]['TOTAL'] ?? 0, 'fa' => $table_fa);
    }
    return $stats;
}

function selectBookletByCategoriesCondition($teacher_id, $course_id): string {
  return DB_TABLE_BOOKLETS . '.' . DB_ITEM_TEACHER_ID . "=$teacher_id AND "
      . DB_TABLE_BOOKLETS . '.' . DB_ITEM_COURSE_ID . "=$course_id";
}

function entityIsReferencedInAnotherTableQuery($table, $another_table, $foreign_key): string {
  return "(select count(" . DB_ITEM_ID . ") from $another_table where $table." . DB_ITEM_ID . " = $another_table.$foreign_key) > 0";
}