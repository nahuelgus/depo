<?php
require_once __DIR__ . '/../config/bootstrap.php';
logout();
header('Location: /app/deposito/public/login.php');
