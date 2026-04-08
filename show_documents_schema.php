<?php
require 'db-config.php';

$res = $db->query("SHOW COLUMNS FROM documents");
if (!$res) {
    echo "ERROR: " . $db->error . "\n";
    exit(1);
}

$cols = [];
while ($row = $res->fetch_assoc()) {
    $cols[] = $row;
}

header('Content-Type: application/json');
echo json_encode($cols, JSON_PRETTY_PRINT);
