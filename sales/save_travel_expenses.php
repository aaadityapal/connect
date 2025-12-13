<?php
// Proxy to the root save_travel_expenses.php
// We change the directory to root so that all relative includes inside the target file work correctly
chdir('..');
require 'save_travel_expenses.php';
?>