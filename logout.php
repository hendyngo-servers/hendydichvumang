<?php
require_once 'includes/db.php';
session_unset();
session_destroy();
header("Location: index.php");
exit;
?>
