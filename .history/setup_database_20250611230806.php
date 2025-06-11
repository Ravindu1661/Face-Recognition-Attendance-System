<?php
require_once 'includes/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Setting