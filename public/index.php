<?php
require_once __DIR__ . '/../config/bootstrap.php';
if (current_user()) { header('Location: /app/deposito/public/dashboard.php'); }
else { header('Location: /app/deposito/public/login.php'); }
