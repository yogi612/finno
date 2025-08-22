<?php
// Database connection configuration
// Use environment variables or default to development values
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: '';
$db_user = getenv('DB_USER') ?: '';
$db_pass = getenv('DB_PASS') ?: '';

// Database connection with better error handling
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set UTC time zone for consistent timestamps
    $pdo->exec("SET time_zone = '+00:00'");
    
    // Uncomment for development to debug queries
    // $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); 
} catch (PDOException $error) {
    // Log error details
    error_log("Database connection error: " . $error->getMessage());
    
    // For production, show a friendlier message
    if (getenv('APP_ENV') === 'production') {
        die("Database connection error. Please try again later or contact support.");
    } else {
        die("Database connection failed: " . $error->getMessage());
    }
}

// Helper functions for database operations

/**
 * Execute a query with parameters and return a single result
 */
function db_query_single($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage() . " - SQL: " . $sql);
        return false;
    }
}

/**
 * Execute a query with parameters and return all results
 */
function db_query($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage() . " - SQL: " . $sql);
        return false;
    }
}

/**
 * Insert a record and return the ID
 */
function db_insert($table, $data) {
    global $pdo;
    try {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $i = 1;
        foreach ($data as $value) {
            if (is_null($value)) {
                $stmt->bindValue($i, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($i, $value);
            }
            $i++;
        }
        $stmt->execute();
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Insert error: " . $e->getMessage() . " - Table: " . $table);
        return false;
    }
}

/**
 * Update a record
 */
function db_update($table, $data, $where, $whereParams = []) {
    global $pdo;
    try {
        $setParts = [];
        $params = [];
        foreach ($data as $column => $value) {
            $setParts[] = "$column = ?";
            $params[] = $value;
        }
        $setClause = implode(', ', $setParts);
        $params = array_merge($params, $whereParams);
        $sql = "UPDATE $table SET $setClause WHERE $where";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $i => $value) {
            if (is_null($value)) {
                $stmt->bindValue($i+1, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($i+1, $value);
            }
        }
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Update error: " . $e->getMessage() . " - Table: " . $table);
        return false;
    }
}

/**
 * Generate a UUID v4 
 */
function generate_uuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
