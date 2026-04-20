<?php
/**
 * Выход из админки
 */

session_start();
session_destroy();

header('Location: index.php');
exit;
