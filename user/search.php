<?php
session_start();
// Reuse main search page (it enforces login and handles queries)
header('Location: ../search.php');
exit;
?>
