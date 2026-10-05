<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
require 'db.php';

$errors = [];
$first = $last = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first   = trim($_POST['first_name'] ?? '');
    $last    = trim($_POST['last_name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    // Server-side validation
    if ($first === '' || $last === '') {
        $errors[] = 'First and last name are required.';
    } elseif (mb_strlen($first) > 100 || mb_strlen($last) > 100) {
        $errors[] = 'Names must not exceed 100 characters.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
        $errors[] = 'A valid email address is required.';
    }

    if (strlen($pass) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif ($pass !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? FOR UPDATE');
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $pdo->rollBack();
                $errors[] = 'Email is already registered.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)');
                $stmt->execute([$first, $last, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                $pdo->commit();

                header('Location: login.php?registered=1');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Blog Site</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h2>Register</h2>

  <?php foreach ($errors as $e): ?>
    <p class="error"><?= htmlspecialchars($e) ?></p>
  <?php endforeach; ?>
  <p class="error" id="msg" style="display:none;"></p>

  <form method="POST" onsubmit="return check(this)">
    <label>First name
      <input type="text" name="first_name" maxlength="100" value="<?= htmlspecialchars($first) ?>" required>
    </label>
    <label>Last name
      <input type="text" name="last_name" maxlength="100" value="<?= htmlspecialchars($last) ?>" required>
    </label>
    <label>Email
      <input type="email" name="email" maxlength="255" value="<?= htmlspecialchars($email) ?>" required>
    </label>
    <label>Password
      <input type="password" name="password" minlength="8" required>
    </label>
    <label>Confirm password
      <input type="password" name="confirm" minlength="8" required>
    </label>
    <button type="submit">Register</button>
  </form>

  <p class="small">Have an account? <a href="login.php">Login</a></p>

  <script>
    function check(f) {
      const msg = document.getElementById('msg');
      if (f.password.value.length < 8) {
        msg.textContent = 'Password must be at least 8 characters.';
        msg.style.display = 'block';
        return false;
      }
      if (f.password.value !== f.confirm.value) {
        msg.textContent = 'Passwords do not match.';
        msg.style.display = 'block';
        return false;
      }
      msg.style.display = 'none';
      return true;
    }
  </script>
</body>
</html>