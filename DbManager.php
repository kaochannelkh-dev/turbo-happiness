<?php
/**
 * Database Manager - Provides full SQL control from PHP code
 */
class DbManager {
    /** @var mysqli|null Database connection */
    private $conn = null;
    
    /** @var string Last error message */
    private $lastError = '';
    
    /** @var string Last executed query */
    private $lastQuery = '';
    
    /** @var array Connection configuration */
    private $config = [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'database' => 'lottery_db',
        'port' => 3306,
        'charset' => 'utf8mb4'
    ];
    
    /**
     * Constructor
     * 
     * @param array $config Optional DB configuration
     * @param bool $autoConnect Connect automatically on instantiation
     */
    public function __construct($config = [], $autoConnect = true) {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
        
        if ($autoConnect) {
            $this->connect();
        }
    }
    
    /**
     * Connect to the database
     * 
     * @param int $retries Number of connection retry attempts
     * @param int $retryDelay Delay between retries in milliseconds
     * @return bool Success
     */
    public function connect($retries = 3, $retryDelay = 500) {
        if ($this->conn) {
            return true; // Already connected
        }
        
        $attempts = 0;
        
        while ($attempts <= $retries) {
            try {
                $this->conn = new mysqli(
                    $this->config['host'],
                    $this->config['user'],
                    $this->config['password'],
                    $this->config['database'],
                    $this->config['port']
                );
                
                if ($this->conn->connect_error) {
                    $this->lastError = "Connection failed: " . $this->conn->connect_error;
                    $attempts++;
                    
                    if ($attempts <= $retries) {
                        // Wait before retrying
                        usleep($retryDelay * 1000);
                        continue;
                    }
                    
                    return false;
                }
                
                // Set charset
                $this->conn->set_charset($this->config['charset']);
                
                // Set timeout if configured
                if (isset($this->config['timeout']) && is_int($this->config['timeout'])) {
                    $this->conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, $this->config['timeout']);
                }
                
                return true;
                
            } catch (Exception $e) {
                $this->lastError = "Connection exception: " . $e->getMessage();
                $attempts++;
                
                if ($attempts <= $retries) {
                    // Wait before retrying
                    usleep($retryDelay * 1000);
                    continue;
                }
                
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * Reconnect to the database
     * 
     * @return bool Success
     */
    public function reconnect() {
        $this->close();
        return $this->connect();
    }
    
    /**
     * Check if connection is alive and reconnect if needed
     * 
     * @return bool Success
     */
    public function checkConnection() {
        if (!$this->conn) {
            return $this->connect();
        }
        
        if (!$this->ping()) {
            return $this->reconnect();
        }
        
        return true;
    }
    
    /**
     * Ping the database server to check connection status
     * 
     * @return bool Success
     */
    public function ping() {
        if (!$this->conn) {
            return false;
        }
        
        return $this->conn->ping();
    }
    
    /**
     * Get the connection status
     * 
     * @return bool True if connected
     */
    public function isConnected() {
        return $this->conn !== null && $this->ping();
    }
    
    /**
     * Get the underlying mysqli connection object
     * 
     * @return mysqli|null Connection object or null if not connected
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Get connection information
     * 
     * @return array Connection info
     */
    public function getConnectionInfo() {
        if (!$this->conn) {
            return [
                'connected' => false,
                'host' => $this->config['host'],
                'database' => $this->config['database'],
                'error' => $this->lastError
            ];
        }
        
        return [
            'connected' => true,
            'host' => $this->config['host'],
            'database' => $this->config['database'],
            'server_info' => $this->conn->server_info,
            'client_info' => $this->conn->client_info,
            'protocol_version' => $this->conn->protocol_version,
            'thread_id' => $this->conn->thread_id
        ];
    }
    
    /**
     * Set connection charset
     * 
     * @param string $charset Character set
     * @return bool Success
     */
    public function setCharset($charset) {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        $result = $this->conn->set_charset($charset);
        
        if ($result) {
            $this->config['charset'] = $charset;
        }
        
        return $result;
    }
    
    /**
     * Select a database
     * 
     * @param string $database Database name
     * @return bool Success
     */
    public function selectDatabase($database) {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        $result = $this->conn->select_db($database);
        
        if ($result) {
            $this->config['database'] = $database;
        }
        
        return $result;
    }
    
    /**
     * Execute a raw SQL query
     * 
     * @param string $sql SQL query to execute
     * @return mysqli_result|bool Query result or boolean
     */
    public function query($sql) {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        $this->lastQuery = $sql;
        $result = $this->conn->query($sql);
        
        if ($result === false) {
            $this->lastError = "Query error: " . $this->conn->error;
        }
        
        return $result;
    }
    
    /**
     * Execute a prepared statement
     * 
     * @param string $sql SQL with placeholders
     * @param string $types Parameter types (i: integer, d: double, s: string, b: blob)
     * @param array $params Parameters to bind
     * @return mysqli_result|bool Result or boolean
     */
    public function execute($sql, $types = null, $params = []) {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        $this->lastQuery = $sql;
        
        // If no parameters, use regular query
        if (empty($params) || $types === null) {
            return $this->query($sql);
        }
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $this->lastError = "Prepare failed: " . $this->conn->error;
            return false;
        }
        
        // Bind parameters
        if (!empty($params)) {
            $bindParams = array($types);
            foreach ($params as $key => $value) {
                $bindParams[] = &$params[$key];
            }
            
            call_user_func_array(array($stmt, 'bind_param'), $bindParams);
        }
        
        // Execute statement
        if (!$stmt->execute()) {
            $this->lastError = "Execute failed: " . $stmt->error;
            $stmt->close();
            return false;
        }
        
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result !== false ? $result : true;
    }
    
    /**
     * Fetch all rows as associative array
     * 
     * @param string $sql SQL query
     * @param string|null $types Parameter types
     * @param array $params Parameters
     * @return array|false Result rows or false on failure
     */
    public function fetchAll($sql, $types = null, $params = []) {
        $result = $this->execute($sql, $types, $params);
        
        if ($result === false || $result === true) {
            return false;
        }
        
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        
        return $rows;
    }
    
    /**
     * Fetch a single row
     * 
     * @param string $sql SQL query
     * @param string|null $types Parameter types
     * @param array $params Parameters
     * @return array|false Row or false on failure
     */
    public function fetchRow($sql, $types = null, $params = []) {
        $result = $this->execute($sql, $types, $params);
        
        if ($result === false || $result === true) {
            return false;
        }
        
        $row = $result->fetch_assoc();
        $result->free();
        
        return $row;
    }
    
    /**
     * Fetch a single value
     * 
     * @param string $sql SQL query
     * @param string|null $types Parameter types
     * @param array $params Parameters
     * @return mixed|null Value or null
     */
    public function fetchValue($sql, $types = null, $params = []) {
        $row = $this->fetchRow($sql, $types, $params);
        
        if ($row === false) {
            return null;
        }
        
        return reset($row); // First column value
    }
    
    /**
     * Insert data into a table
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return int|false Last insert ID or false on failure
     */
    public function insert($table, $data) {
        if (empty($data)) {
            $this->lastError = "No data provided for insert";
            return false;
        }
        
        $columns = [];
        $placeholders = [];
        $values = [];
        $types = '';
        
        foreach ($data as $column => $value) {
            $columns[] = "`$column`";
            $placeholders[] = "?";
            $values[] = $value;
            
            // Determine parameter type
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 'b'; // For everything else
            }
        }
        
        $sql = "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $result = $this->execute($sql, $types, $values);
        
        if ($result === false) {
            return false;
        }
        
        return $this->conn->insert_id;
    }
    
    /**
     * Update data in a table
     * 
     * @param string $table Table name
     * @param array $data Data to update (column => value)
     * @param string $where WHERE clause (without "WHERE")
     * @param string|null $whereTypes Types for WHERE parameters
     * @param array $whereParams Parameters for WHERE clause
     * @return int|false Affected rows or false on failure
     */
    public function update($table, $data, $where = '', $whereTypes = null, $whereParams = []) {
        if (empty($data)) {
            $this->lastError = "No data provided for update";
            return false;
        }
        
        $setClauses = [];
        $values = [];
        $types = '';
        
        foreach ($data as $column => $value) {
            $setClauses[] = "`$column` = ?";
            $values[] = $value;
            
            // Determine parameter type
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_string($value)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $setClauses);
        
        // Add WHERE clause if provided
        if (!empty($where)) {
            $sql .= " WHERE $where";
            
            if (!empty($whereParams) && $whereTypes !== null) {
                $types .= $whereTypes;
                $values = array_merge($values, $whereParams);
            }
        }
        
        $result = $this->execute($sql, $types, $values);
        
        if ($result === false) {
            return false;
        }
        
        return $this->conn->affected_rows;
    }
    
    /**
     * Delete data from a table
     * 
     * @param string $table Table name
     * @param string $where WHERE clause (without "WHERE")
     * @param string|null $types Parameter types
     * @param array $params Parameters
     * @return int|false Affected rows or false on failure
     */
    public function delete($table, $where = '', $types = null, $params = []) {
        $sql = "DELETE FROM `$table`";
        
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        
        $result = $this->execute($sql, $types, $params);
        
        if ($result === false) {
            return false;
        }
        
        return $this->conn->affected_rows;
    }
    
    /**
     * Execute custom SQL and return insert ID
     * 
     * @param string $sql SQL query
     * @param string|null $types Parameter types
     * @param array $params Parameters
     * @return int|false Insert ID or false on failure
     */
    public function insertCustom($sql, $types = null, $params = []) {
        $result = $this->execute($sql, $types, $params);
        
        if ($result === false) {
            return false;
        }
        
        return $this->conn->insert_id;
    }
    
    /**
     * Begin a transaction
     * 
     * @return bool Success
     */
    public function beginTransaction() {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        return $this->conn->begin_transaction();
    }
    
    /**
     * Commit a transaction
     * 
     * @return bool Success
     */
    public function commit() {
        if (!$this->conn) {
            $this->lastError = "No active connection";
            return false;
        }
        
        return $this->conn->commit();
    }
    
    /**
     * Rollback a transaction
     * 
     * @return bool Success
     */
    public function rollback() {
        if (!$this->conn) {
            $this->lastError = "No active connection";
            return false;
        }
        
        return $this->conn->rollback();
    }
    
    /**
     * Get the last error
     * 
     * @return string Error message
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Get the last executed query
     * 
     * @return string Last query
     */
    public function getLastQuery() {
        return $this->lastQuery;
    }
    
    /**
     * Safely escape a string for SQL
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    public function escape($value) {
        if (!$this->ensureConnection()) {
            return $value;
        }
        
        return $this->conn->real_escape_string($value);
    }
    
    /**
     * Create a table
     * 
     * @param string $table Table name
     * @param array $columns Column definitions
     * @param array $options Table options
     * @return bool Success
     */
    public function createTable($table, $columns, $options = []) {
        if (empty($columns)) {
            $this->lastError = "No columns provided";
            return false;
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (\n";
        $sql .= implode(",\n", $columns);
        
        // Add primary key if specified
        if (!empty($options['primary_key'])) {
            $sql .= ",\n PRIMARY KEY (" . $options['primary_key'] . ")";
        }
        
        // Add indices if specified
        if (!empty($options['indices'])) {
            foreach ($options['indices'] as $name => $def) {
                $sql .= ",\n $def";
            }
        }
        
        $sql .= "\n) ";
        
        // Add engine
        $sql .= "ENGINE=" . ($options['engine'] ?? 'InnoDB') . " ";
        
        // Add charset
        $sql .= "DEFAULT CHARSET=" . ($options['charset'] ?? 'utf8mb4') . " ";
        
        // Add collation if specified
        if (!empty($options['collation'])) {
            $sql .= "COLLATE=" . $options['collation'] . " ";
        }
        
        return $this->query($sql) !== false;
    }
    
    /**
     * Get the number of rows from a SELECT query
     * 
     * @param string $sql SQL query
     * @param string|null $types Parameter types
     * @param array $params Parameters
     * @return int|false Row count or false on failure
     */
    public function getRowCount($sql, $types = null, $params = []) {
        $result = $this->execute($sql, $types, $params);
        
        if ($result === false || $result === true) {
            return false;
        }
        
        $count = $result->num_rows;
        $result->free();
        
        return $count;
    }
    
    /**
     * Check if a table exists
     * 
     * @param string $table Table name
     * @return bool Whether table exists
     */
    public function tableExists($table) {
        $result = $this->fetchValue(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?", 
            "ss", 
            [$this->config['database'], $table]
        );
        
        return (int)$result > 0;
    }
    
    /**
     * Get all tables in the database
     * 
     * @return array|false Array of table names or false on failure
     */
    public function getTables() {
        $rows = $this->fetchAll(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = ?",
            "s",
            [$this->config['database']]
        );
        
        if ($rows === false) {
            return false;
        }
        
        return array_column($rows, 'table_name');
    }
    
    /**
     * Get detailed server information
     * 
     * @return array Server information
     */
    public function getServerInfo() {
        if (!$this->ensureConnection()) {
            return [
                'connected' => false,
                'error' => $this->lastError
            ];
        }
        
        return [
            'server' => $this->conn->host_info,
            'server_type' => stripos($this->conn->server_info, 'mariadb') !== false ? 'MariaDB' : 'MySQL',
            'server_version' => $this->conn->server_info,
            'protocol_version' => $this->conn->protocol_version,
            'user' => $this->config['user'] . '@' . $this->config['host'],
            'charset' => $this->conn->character_set_name(),
            'ssl_used' => $this->conn->ssl_set ? 'Yes' : 'No'
        ];
    }
    
    /**
     * Display server information in HTML format
     * 
     * @param bool $return Whether to return the HTML or echo it
     * @return string|void HTML output if $return is true
     */
    public function displayServerInfo($return = false) {
        $info = $this->getServerInfo();
        
        if (!$info['server']) {
            $html = '<div class="db-error">Not connected to database server.</div>';
            if ($return) return $html;
            echo $html;
            return;
        }
        
        $html = '<div class="db-info">';
        $html .= '<h3>Database Server Information</h3>';
        $html .= '<table border="0" cellspacing="0" cellpadding="3">';
        $html .= '<tr><td>Server:</td><td>' . htmlspecialchars($info['server']) . '</td></tr>';
        $html .= '<tr><td>Server type:</td><td>' . htmlspecialchars($info['server_type']) . '</td></tr>';
        $html .= '<tr><td>Server connection:</td><td>SSL is ' . ($info['ssl_used'] === 'Yes' ? '' : 'not ') . 'being used</td></tr>';
        $html .= '<tr><td>Server version:</td><td>' . htmlspecialchars($info['server_version']) . '</td></tr>';
        $html .= '<tr><td>Protocol version:</td><td>' . htmlspecialchars($info['protocol_version']) . '</td></tr>';
        $html .= '<tr><td>User:</td><td>' . htmlspecialchars($info['user']) . '</td></tr>';
        $html .= '<tr><td>Server charset:</td><td>' . htmlspecialchars($info['charset']) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        
        if ($return) return $html;
        echo $html;
    }
    
    /**
     * Get database status and statistics
     * 
     * @return array Database status
     */
    public function getDatabaseStatus() {
        if (!$this->ensureConnection()) {
            return [
                'connected' => false,
                'error' => $this->lastError
            ];
        }
        
        $statusData = [];
        
        // Get global status
        $status = $this->fetchAll("SHOW GLOBAL STATUS");
        if ($status) {
            $statusMap = [];
            foreach ($status as $row) {
                $statusMap[$row['Variable_name']] = $row['Value'];
            }
            
            $statusData['uptime'] = isset($statusMap['Uptime']) ? $statusMap['Uptime'] : 'Unknown';
            $statusData['threads'] = isset($statusMap['Threads_connected']) ? $statusMap['Threads_connected'] : 'Unknown';
            $statusData['questions'] = isset($statusMap['Questions']) ? $statusMap['Questions'] : 'Unknown';
            $statusData['slow_queries'] = isset($statusMap['Slow_queries']) ? $statusMap['Slow_queries'] : 'Unknown';
            $statusData['opens'] = isset($statusMap['Opened_tables']) ? $statusMap['Opened_tables'] : 'Unknown';
            $statusData['flush_tables'] = isset($statusMap['Flush_commands']) ? $statusMap['Flush_commands'] : 'Unknown';
            $statusData['open_tables'] = isset($statusMap['Open_tables']) ? $statusMap['Open_tables'] : 'Unknown';
        }
        
        // Get database size
        $dbSize = $this->fetchValue("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) 
            FROM information_schema.TABLES 
            WHERE table_schema = ?", 
            "s", [$this->config['database']]
        );
        
        $statusData['db_size'] = $dbSize ? $dbSize . ' MB' : 'Unknown';
        $statusData['tables_count'] = count($this->getTables() ?: []);
        
        return $statusData;
    }
    
    /**
     * Ensure a connection exists before operations
     * 
     * @return bool Success
     */
    private function ensureConnection() {
        if (!$this->conn) {
            return $this->connect();
        }
        return true;
    }
    
    /**
     * Close the database connection
     */
    public function close() {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
    }
    
    /**
     * Destructor - Close connection when object is destroyed
     */
    public function __destruct() {
        $this->close();
    }
}
?>
