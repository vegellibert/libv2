<?php
session_start();
session_unset();
session_destroy();
header('Location: /libv2/login.php');
exit();
?>