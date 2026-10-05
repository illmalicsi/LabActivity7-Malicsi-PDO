<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // Server-side validation
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($pass === '') {
        $error = 'Password is required.';
    } else {
        $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, password FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Blog Site</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h2>Login</h2>

  <?php if (isset($_GET['registered'])): ?>
    <p class="success">Registration successful! Please log in.</p>
  <?php endif; ?>

  <?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <form method="POST">
    <label>Email
      <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus>
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <button type="submit">Login</button>
  </form>

  <p class="small">No account? <a href="register.php">Register</a></p>
</body>
</html>