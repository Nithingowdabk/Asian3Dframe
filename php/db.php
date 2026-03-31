<?php
declare(strict_types=1);

/**
 * Reusable MySQL connection for Asian3DFrames.
 * Keep $conn for backward compatibility with existing include-based files.
 */
function asian3dframes_db_connect(): mysqli
{
    $host = 'mysql.hostinger.com';
    $user = 'u470752772_Asian3Dframe';
    $pass = 'Asian3Dframe';
    $name ='u470752772_Asian3Dframe';

    $conn = new mysqli($host, $user, $pass, $name);
    if ($conn->connect_errno) {
        http_response_code(500);
        die('Database connection failed.');
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

$conn = asian3dframes_db_connect();

