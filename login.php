<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in() && !empty(current_user())) {
    redirect(base_url('dashboard.php'));
}

$error = '';

// "Remember me" auto-login via a cookie token (simple, not a full remember-token
// rotation scheme, but adequate for a shared-hosting ISP back-office tool).
if (!is_logged_in() && !empty($_COOKIE['remember_token'])) {
    [$uid, $token] = array_pad(explode(':', $_COOKIE['remember_token'], 2), 2, '');
    if ($uid && $token) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND remember_token = ? AND status = "active" LIMIT 1');
        $stmt->execute([$uid, $token]);
        $u = $stmt->fetch();
        if ($u) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $u['id'];
            $_SESSION['last_activity'] = time();
            redirect(base_url('dashboard.php'));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $identifier = post('identifier');
    $password = post('password');
    $remember = post('remember') === '1';

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } elseif (attempt_login($identifier, $password, $error)) {
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            db()->prepare('UPDATE users SET remember_token = ? WHERE id = ?')->execute([$token, current_user_id()]);
            setcookie('remember_token', current_user_id() . ':' . $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
        }
        if (!empty(current_user()['must_change_password'])) {
            flash_set('warning', 'For security, please set a new password before continuing.');
            redirect(base_url('account/change_password.php'));
        }
        $redirectTo = $_SESSION['redirect_after_login'] ?? base_url('dashboard.php');
        unset($_SESSION['redirect_after_login']);
        redirect($redirectTo);
    }
}
$softwareName = setting('software_name', 'ISP Management System');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · <?= e($softwareName) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body class="login-page">
  <div class="card login-card shadow-lg border-0">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="logo-badge mx-auto mb-3" style="width:52px;height:52px;border-radius:14px;font-size:1.4rem;">
          <i class="fa-solid fa-tower-broadcast"></i>
        </div>
        <h4 class="fw-bold mb-0"><?= e($softwareName) ?></h4>
        <div class="text-muted small"><?= e(setting('organization_name', '')) ?></div>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><i class="fa-solid fa-circle-exclamation me-1"></i><?= e($error) ?></div>
      <?php endif; ?>
      <?php foreach (flash_get() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div>
      <?php endforeach; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label required">Username or Email</label>
          <input type="text" name="identifier" class="form-control" required autofocus value="<?= e($_POST['identifier'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label required">Password</label>
          <div class="input-group">
            <input type="password" name="password" id="passwordInput" class="form-control" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePw()"><i class="fa-solid fa-eye" id="pwIcon"></i></button>
          </div>
        </div>
        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" name="remember" value="1" id="rememberMe">
          <label class="form-check-label small" for="rememberMe">Remember me on this device</label>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="fa-solid fa-right-to-bracket me-1"></i> Login
        </button>
      </form>
    </div>
  </div>
<script>
function togglePw(){
  var i=document.getElementById('passwordInput'), ic=document.getElementById('pwIcon');
  if(i.type==='password'){i.type='text';ic.classList.replace('fa-eye','fa-eye-slash');}
  else{i.type='password';ic.classList.replace('fa-eye-slash','fa-eye');}
}
</script>
</body>
</html>
