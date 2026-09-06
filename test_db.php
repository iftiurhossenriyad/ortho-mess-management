<?php
require_once 'db.php';

echo json_encode([
    "status" => "success",
    "message" => "Ortho Backend connected to MySQL Database successfully!"
]);
?>