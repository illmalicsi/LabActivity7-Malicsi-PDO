<?php
try {
  $pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=blog_site;charset=utf8mb4',
    'root', '',
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (PDOException $e) {
  die("Connection failed: " . $e->getMessage());
}
?>
