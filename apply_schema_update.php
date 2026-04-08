<?php
require 'db-config.php';

// Add items column if missing
$check = $db->query("SHOW COLUMNS FROM documents LIKE 'items'");
if ($check && $check->num_rows > 0) {
    echo "Column 'items' already exists\n";
    exit(0);
}

$sql = "ALTER TABLE documents ADD COLUMN items LONGTEXT NULL AFTER html_content";
if ($db->query($sql) === TRUE) {
    echo "Added column 'items' successfully\n";
    exit(0);
} else {
    echo "Failed to add column: " . $db->error . "\n";
    exit(1);
}
