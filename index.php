<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require 'db.php';

$uid = (int)$_SESSION['user_id'];
$errors = [];

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// ---------- Handle form submissions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $id      = (int)($_POST['id'] ?? 0);
    $post_id = (int)($_POST['post_id'] ?? 0);

    if ($action === 'add_post') {
        if ($title === '' || mb_strlen($title) > 255) {
            $errors[] = 'Title is required (max 255 characters).';
        }
        if ($content === '' || mb_strlen($content) > 5000) {
            $errors[] = 'Content is required (max 5000 characters).';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO posts (user_id, title, content) VALUES (?, ?, ?)');
                $stmt->execute([$uid, $title, $content]);
                $pdo->commit();
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Failed to create post. Please try again.';
            }
        }
    } elseif ($action === 'edit_post') {
        if ($id <= 0) {
            $errors[] = 'Invalid post reference.';
        }
        if ($title === '' || mb_strlen($title) > 255) {
            $errors[] = 'Title is required (max 255 characters).';
        }
        if ($content === '' || mb_strlen($content) > 5000) {
            $errors[] = 'Content is required (max 5000 characters).';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                // user_id in WHERE clause ensures only post owner can edit
                $stmt = $pdo->prepare('UPDATE posts SET title = ?, content = ?, updated_at = NOW(), created_at = created_at WHERE id = ? AND user_id = ?');
                $stmt->execute([$title, $content, $id, $uid]);
                $pdo->commit();
                header('Location: index.php#post-' . $id);
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Failed to update post. Please try again.';
            }
        }
    } elseif ($action === 'delete_post') {
        if ($id > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ? AND user_id = ?');
                $stmt->execute([$id, $uid]);
                $pdo->commit();
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Failed to delete post.';
            }
        }
    } elseif ($action === 'add_comment') {
        if ($post_id <= 0) {
            $errors[] = 'Invalid post reference.';
        }
        if ($content === '' || mb_strlen($content) > 1000) {
            $errors[] = 'Comment is required (max 1000 characters).';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $chk = $pdo->prepare('SELECT id FROM posts WHERE id = ?');
                $chk->execute([$post_id]);
                if (!$chk->fetch()) {
                    $pdo->rollBack();
                    $errors[] = 'Target post does not exist.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)');
                    $stmt->execute([$post_id, $uid, $content]);
                    $pdo->commit();
                    header('Location: index.php#post-' . $post_id);
                    exit;
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Failed to add comment. Please try again.';
            }
        }
    } elseif ($action === 'edit_comment') {
        if ($id <= 0) {
            $errors[] = 'Invalid comment reference.';
        }
        if ($content === '' || mb_strlen($content) > 1000) {
            $errors[] = 'Comment content is required (max 1000 characters).';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                // user_id in WHERE clause ensures only comment owner can edit
                $stmt = $pdo->prepare('UPDATE comments SET content = ?, updated_at = NOW(), created_at = created_at WHERE id = ? AND user_id = ?');
                $stmt->execute([$content, $id, $uid]);
                $pdo->commit();
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Failed to update comment. Please try again.';
            }
        }
    } elseif ($action === 'delete_comment') {
        if ($id > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('DELETE FROM comments WHERE id = ? AND user_id = ?');
                $stmt->execute([$id, $uid]);
                $pdo->commit();
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Failed to delete comment.';
            }
        }
    }
}

// ---------- Fetch data ----------
$editPost = (int)($_GET['edit_post'] ?? 0);
$editComment = (int)($_GET['edit_comment'] ?? 0);

$posts = $pdo->query(
    'SELECT posts.*, users.first_name, users.last_name
     FROM posts JOIN users ON users.id = posts.user_id
     ORDER BY posts.created_at DESC, posts.id DESC'
)->fetchAll();

$rows = $pdo->query(
    'SELECT comments.*, users.first_name, users.last_name
     FROM comments JOIN users ON users.id = comments.user_id
     ORDER BY comments.created_at ASC, comments.id ASC'
)->fetchAll();

$comments = [];
foreach ($rows as $c) {
    $comments[$c['post_id']][] = $c;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>News Feed - Blog Site</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="wide">

  <nav class="nav">
    <span>Welcome, <strong><?= h($_SESSION['name'] ?? 'User') ?></strong></span>
    <a href="logout.php">Logout</a>
  </nav>

  <?php foreach ($errors as $e): ?>
    <p class="error"><?= h($e) ?></p>
  <?php endforeach; ?>

  <h2>Create Post</h2>
  <form method="POST">
    <input type="hidden" name="action" value="add_post">
    <label>Title
      <input type="text" name="title" maxlength="255" placeholder="Post title..." required>
    </label>
    <label>Content
      <textarea name="content" rows="3" maxlength="5000" placeholder="What's on your mind?" required></textarea>
    </label>
    <button type="submit">Publish</button>
  </form>

  <?php if (empty($posts)): ?>
    <p class="empty-feed">No posts yet. Be the first to share something!</p>
  <?php endif; ?>

  <?php foreach ($posts as $p): ?>
    <article class="post" id="post-<?= (int)$p['id'] ?>">

      <?php if ($editPost === (int)$p['id'] && (int)$p['user_id'] === $uid): ?>
        <form method="POST">
          <input type="hidden" name="action" value="edit_post">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <label>Title
            <input type="text" name="title" value="<?= h($p['title']) ?>" maxlength="255" required>
          </label>
          <label>Content
            <textarea name="content" rows="4" maxlength="5000" required><?= h($p['content']) ?></textarea>
          </label>
          <div class="btn-row">
            <button type="submit">Save Changes</button>
            <a href="index.php" class="btn-cancel">Cancel</a>
          </div>
        </form>
      <?php else: ?>
        <h3><?= h($p['title']) ?></h3>
        <p class="meta">
          <?= h($p['first_name'] . ' ' . $p['last_name']) ?> &middot;
          <?= date('M j, Y g:i A', strtotime($p['created_at'])) ?>
          <?php if (!empty($p['updated_at'])): ?>
            <span class="tag">edited</span>
          <?php endif; ?>
          <?php if ((int)$p['user_id'] === $uid): ?>
            &middot; <a href="?edit_post=<?= (int)$p['id'] ?>">Edit</a>
            &middot;
            <form method="POST" class="inline-form" onsubmit="return confirm('Delete this post?');">
              <input type="hidden" name="action" value="delete_post">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="btn-link">Delete</button>
            </form>
          <?php endif; ?>
        </p>
        <p class="post-content"><?= nl2br(h($p['content'])) ?></p>
      <?php endif; ?>

      <!-- Comments Section -->
      <section class="comments-section">
        <?php foreach ($comments[$p['id']] ?? [] as $c): ?>
          <div class="comment">
            <?php if ($editComment === (int)$c['id'] && (int)$c['user_id'] === $uid): ?>
              <form method="POST">
                <input type="hidden" name="action" value="edit_comment">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <input type="text" name="content" value="<?= h($c['content']) ?>" maxlength="1000" required>
                <div class="btn-row" style="margin-top: 8px;">
                  <button type="submit" style="width: auto; padding: 6px 14px;">Save</button>
                  <a href="index.php" class="btn-cancel">Cancel</a>
                </div>
              </form>
            <?php else: ?>
              <p class="meta">
                <?= h($c['first_name'] . ' ' . $c['last_name']) ?> &middot;
                <?= date('M j, Y g:i A', strtotime($c['created_at'])) ?>
                <?php if (!empty($c['updated_at'])): ?>
                  <span class="tag">edited</span>
                <?php endif; ?>
                <?php if ((int)$c['user_id'] === $uid): ?>
                  &middot; <a href="?edit_comment=<?= (int)$c['id'] ?>">Edit</a>
                  &middot;
                  <form method="POST" class="inline-form" onsubmit="return confirm('Delete this comment?');">
                    <input type="hidden" name="action" value="delete_comment">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn-link">Delete</button>
                  </form>
                <?php endif; ?>
              </p>
              <p><?= nl2br(h($c['content'])) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <form method="POST" class="comment-form">
          <input type="hidden" name="action" value="add_comment">
          <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
          <input type="text" name="content" placeholder="Write a comment..." maxlength="1000" required>
          <button type="submit">Comment</button>
        </form>
      </section>

    </article>
  <?php endforeach; ?>

</body>
</html>