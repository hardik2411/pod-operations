<?php

/**
 * This class will perform insert, update, delete and select sql statment with PDO.
 *
 */
class PdoOpt {

  public $dbh;
  private $database;

  public function __construct($db) {
    $this->database = $db;
  }

  public function initiateConnection() {
    $db=$this->database;
    try {
      if (!isset($db['port'])) {
        $this->dbh = new PDO("mysql:host={$db['server']};dbname={$db['database']}", $db['user'], $db['pass']);
      } else {
        $this->dbh = new PDO("mysql:host={$db['server']};port={$db['port']};dbname={$db['database']}", $db['user'], $db['pass']);
      }
      $this->displayError();
    } catch (Exception $e) {
      $tmp_arr = array();
      $tmp_arr = $e->getTrace()[3];
      $tmp_arr['err_message'] = $e->getMessage();
      $this->errorLog(json_encode($tmp_arr));
    }
  }

  public function closeConnection() {
    try {
      $this->dbh = null;
    } catch (Exception $e) {
      
    }
  }

  /**
   * Enable display error parameter of PDO.
   */
  public function displayError() {
    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /**
   * Execute insert query.
   * 
   * $params = array( 
   * 'id' => 1,
   * 'field1' => 'abc',
   * 'created_at' => 'msqlfunc_NOW()' Put "msqlfunc_" prefix before use mysql functions
   * )
   * $pdo->insert('demo_table', $params)
   * 
   * @param string $table
   * @param array $params
   * @return integer
   */
  public function insert($table, $params) {
   
    try {
      $this->initiateConnection();
      $fields = "";
      foreach ($params as $field => $value) {
        if (strpos($value, 'msqlfunc_') === 0) {
          $value = str_replace('msqlfunc_', '', $value);
          $fields .= $field . ' = ' . $value . ', ';
          unset($params[$field]);
        } else {
          $fields .= $field . ' = :' . $field . ', ';
        }
      }
      $fields = trim($fields, ', ');

      $sql = "INSERT INTO $table SET $fields";

      $stmt = $this->dbh->prepare($sql);
      $stmt->execute($params);
      $last_id = $this->dbh->lastInsertId();
      $stmt = null;
      $this->closeConnection();
      return $last_id;
    } catch (Exception $e) {
      $tmp_arr = $e->getTrace();
      $tmp_arr['err_message'] = $e->getMessage();
      $this->closeConnection();
      $this->errorLog(json_encode($tmp_arr));
    }
  }

  /**
   * Execute update query
   * 
   * $params = array(
   * 'name' => 'new_test',
   * 'email' => 'new_test@test.com',
   * 'created_at' => 'msqlfunc_NOW()' Put "msqlfunc_" prefix before use mysql functions
   * );
   * 
   * $where = array(
   * 'clause' => 'id=:id',
   * 'params' => array(
   * ':id' => 24
   * )
   *  )
   * $pdo->update("demo_table", $params, $where);
   * 
   * @param string $table
   * @param array $params
   * @param array $warr  
   */
  public function update($table, $params, $warr) {
    
    try {
      $this->initiateConnection();
      $fields = $where = "";
      foreach ($params as $field => $value) {
        if (strpos($value, 'msqlfunc_') === 0) {
          $value = str_replace('msqlfunc_', '', $value);
          $fields .= $field . ' = ' . $value . ', ';
          unset($params[$field]);
        } else {
          $fields .= $field . ' = :' . $field . ', ';
        }
      }
      $fields = trim($fields, ', ');
      $where = $warr['clause'];
      $params = array_merge($params, $warr['params']);
      $sql = "UPDATE $table SET $fields WHERE $where";

      $stmt = $this->dbh->prepare($sql);
      $stmt->execute($params);
      $stmt = null;
      $this->closeConnection();
    } catch (Exception $e) {
      $tmp_arr = $e->getTrace();
      $tmp_arr['err_message'] = $e->getMessage();
      $this->closeConnection();
      $this->errorLog(json_encode($tmp_arr));
    }
  }

  /**
   * Execute delete query.
   * 
   * $query = "DELETE FROM demo_table WHERE id = :id"
   * $params = array( 
   * ':id' => 1
   * )
   * $pdo->delete($query, $params)
   * 
   * @param string query
   * @param array $params
   * @return integer
   */
  public function delete($query, $params = array()) {
    try {
      $this->initiateConnection();
      $stmt = $this->dbh->prepare($query);
      if (!$stmt && $this->error_display) {
        echo "Error = <pre>";
        print_R($this->dbh->errorInfo());
        exit;
      }
      $stmt->execute($params);
      $row_count = $stmt->rowCount();
      $stmt = null;
      $this->closeConnection();
      return $row_count;
    } catch (Exception $e) {
      $tmp_arr = $e->getTrace();
      $tmp_arr['err_message'] = $e->getMessage();
      $this->closeConnection();
      $this->errorLog(json_encode($tmp_arr));
    }
  }

  /**
   * Fetch one record
   * 
   * $query = "SELECT * FROM demo_tables WHERE id=:id"
   * $params = array(
   * ':id' => '1',
   *  );
   * 
   * $pdo->selectOne($query, $params);
   * 
   * @param string $query
   * @param array $params
   * @param string $fetch_method
   * @return array
   */
  public function selectOne($query, $params = array(), $fetch_method = PDO::FETCH_ASSOC) {
    $data = $this->select($query, $params, $fetch_method);
    return count($data) > 0 ? $data[0] : $data;
  }

  /**
   * Fetch record set 
   * 
   * $query = "SELECT * FROM demo_tables WHERE id=:id"
   * $params = array(
   * ':id' => '1'
   *  );
   * 
   * $pdo->select($query, $params);
   * 
   * @param string $query
   * @param array $params
   * @param string $fetch_method
   * @return array
   */
  public function select($query, $params = array(), $fetch_method = PDO::FETCH_ASSOC) {
     $data = array();
     
    try {
      $this->initiateConnection();
      $stmt = $this->dbh->prepare($query);
      $stmt->setFetchMode($fetch_method);

      if (count($params) > 0) {
        $stmt->execute($params);
      } else {
        $stmt->execute();
      }
      $rows = $stmt->rowCount();
      if ($rows > 0) {
        while ($row = $stmt->fetch($fetch_method)) {
          $data[] = $row;
        }
      }
      $stmt = null;
      $this->closeConnection();
      return $data;
    } catch (Exception $e) {
      $tmp_arr = $e->getTrace();
      $tmp_arr['err_message'] = $e->getMessage();
      $this->closeConnection();
      $this->errorLog(json_encode($tmp_arr));
    }
  }

  public function prepare($query){
    try{
      $this->initiateConnection();
      $stmt = $this->dbh->prepare($query);
      $stmt->execute();
      $stmt = null;
      $this->closeConnection();
    } catch(Exception $e){
      $tmp_arr = $e->getTrace();
      $tmp_arr['err_message'] = $e->getMessage();
      $this->closeConnection();
      $this->errorLog(json_encode($tmp_arr));
    }
  }

  protected function errorLog($message) {

    $messageArr=json_decode($message, true);
    $messageArr['Website'] = $ADMIN_HOST;
    $messageArr['IP_Address']=$_SERVER['REMOTE_ADDR'];
    $messageArr['HTTP_CF_CONNECTING_IP']=$_SERVER['HTTP_CF_CONNECTING_IP'];
    $messageArr['HTTP_CF_IPCOUNTRY']=$_SERVER['HTTP_CF_IPCOUNTRY'];
    $messageArr['Page_name']=$_SERVER['REQUEST_URI'];
    if(isset($_SESSION)){
      $messageArr['session_info']=$_SESSION;
    }

    
    echo "<pre>";
    print_R($messageArr);
    echo "</pre>";
    exit('Something went wrong.');    
  }
  
  public function checkNumErrors() {
    $this->removeOldLog();
    $file = dirname(__DIR__) . "/sqlError.csv";
    $exceed = false;
    if (file_exists($file)) {
      $f = fopen($file, "r");
      $count = 0;
      while ($record = fgetcsv($f)) {
        if (strtotime($record[0]) >= strtotime("-1 minute")) {
          $count++;
          //echo"<br>" . $count;
          if ($count > 20) {
            $exceed = true;
            break;
          }
        }
      }
      fclose($f);
    }
    return $exceed;
  }

  public function appendErrorLog() {
    $file = dirname(__DIR__) . "/sqlError.csv";
    $f = fopen($file, "a");
    $fields = array(date('Y-m-d H:i:s'));
    fputcsv($f, $fields);
    fclose($f);
  }

  public function removeOldLog() {
    $file = dirname(__DIR__) . "/sqlError.csv";
    if (file_exists($file)) {
      $f = fopen($file, "r");
      $count = 0;
      $exceed = false;
      $Latestdata = array();
      while ($record = fgetcsv($f)) {
        if (strtotime($record[0]) > strtotime("-1 minute")) {
          $Latestdata[] = array($record[0]);
        }
      }
      fclose($f);
      if ($Latestdata) {
        $file = dirname(__DIR__) . "/sqlError.csv";
        $f = fopen($file, "w");
        foreach ($Latestdata as $data) {
          fputcsv($f, $data);
        }
        fclose($f);
      }
    }
  }
}

?>