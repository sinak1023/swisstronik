<?php



class Database
{
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    private $options;
    private $pdo;

    public function __construct($host = null, $dbname = null, $username = null, $password = null, $charset = 'utf8mb4')
    {
        $this->host     = $host;
        $this->dbname   = $dbname;
        $this->username = $username;
        $this->password = $password;
        $this->charset  = $charset;

        $this->options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE utf8mb4_unicode_ci",
            PDO::ATTR_PERSISTENT         => false
        ];

        $this->connect();
    }

    private function connect()
    {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $this->options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            throw $e;
        }
    }

    
    public function prepare($sql)
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt;
        } catch (PDOException $e) {
            throw $e;
        }
    }


    public function fetch($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function execute($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt;
    }


    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->commit();
    }

    public function rollBack()
    {
        return $this->pdo->rollBack();
    }

    public function isConnected()
    {
        return $this->pdo !== null;
    }

    public function close()
    {
        $this->pdo = null;
    }

    private function logError($exception, $sql, $params)
    {
        $error = [
            'message' => $exception->getMessage(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
            'sql'     => $sql,
            'params'  => $params,
            'trace'   => $exception->getTraceAsString()
        ];

        error_log(json_encode($error, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }


    public function exec($sql)
    {
        try {
            return $this->pdo->exec($sql);
        } catch (PDOException $e) {
            $this->logError($e, $sql, []);
            throw $e;
        }
    }



}
