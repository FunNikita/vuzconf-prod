<?php
require 'rb-mysql.php';
define("DB_HOST", "localhost"); // Сервер базы данных
define("DB_USERNAME", "vuzconf_user"); // Пользователь базы данных
define("DB_PASSWORD", "*****************"); // Пароль от пользователя базы данных
define("DB_NAME", "vuzconf"); // Имя базы данных
R::setup('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USERNAME, DB_PASSWORD);