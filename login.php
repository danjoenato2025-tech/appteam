<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
include('dbconnection/config.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (!$identifier || !$password) {
        $error = 'Please enter your email / username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email OR username = :username LIMIT 1');
        $stmt->execute([':email' => $identifier, ':username' => $identifier]);
        $account = $stmt->fetch();

        if ($account && password_verify($password, $account['password'])) {
            $_SESSION['user_id']   = $account['id'];
            $_SESSION['username']  = $account['username'];
            $_SESSION['full_name'] = $account['full_name'];
            $_SESSION['role']      = $account['role'];
            $_SESSION['email']     = $account['email'];
            $_SESSION['color']     = $account['avatar_color'];

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid credentials. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – AM Team</title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Public Sans', sans-serif;
    background: #f4f5fb;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .auth-wrap {
    width: 100%;
    max-width: 420px;
    padding: 20px;
  }

  .auth-card {
    background: #fff;
    border-radius: 18px;
    padding: 36px 32px 28px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.10);
  }

  /* ── YETI ── */
  .yeti-wrap {
    display: flex;
    justify-content: center;
    margin-bottom: 18px;
  }

  .yeti-svg {
    width: 110px;
    height: 110px;
    filter: drop-shadow(0 4px 14px rgba(100,140,255,0.18));
  }

  /* Arms that cover eyes */
  .yeti-arms {
    transition: opacity 0.25s ease, transform 0.3s cubic-bezier(.34,1.56,.64,1);
    transform-origin: center;
  }
  .yeti-arms.hidden {
    opacity: 0;
    transform: translateY(30px) scale(0.7);
  }
  .yeti-arms.visible {
    opacity: 1;
    transform: translateY(0) scale(1);
  }

  /* Eyes: open vs peeking */
  .eyes-open   { display: block; }
  .eyes-peeking { display: none; }

  .yeti-svg.peeking .eyes-open   { display: none; }
  .yeti-svg.peeking .eyes-peeking { display: block; }

  /* Brand */
  .brand {
    display: flex;
    align-items: center;
    gap: 10px;
    justify-content: center;
    margin-bottom: 6px;
  }
  .brand-icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg,#696cff,#9155fd);
    border-radius: 9px;
    color: #fff;
    font-weight: 700;
    font-size: 14px;
    display: flex; align-items: center; justify-content: center;
  }
  .brand-name {
    font-size: 22px;
    font-weight: 700;
    color: #2c2c4a;
    letter-spacing: 2px;
  }

  .auth-sub {
    text-align: center;
    color: #8a8da9;
    font-size: 13.5px;
    margin-bottom: 22px;
    line-height: 1.5;
  }

  /* Alerts */
  .alert {
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 13.5px;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
  }
  .alert-error   { background:#fff1f0; color:#d93025; border:1px solid #ffd7d5; }
  .alert-success { background:#f0fff4; color:#1a7f37; border:1px solid #b7f0c8; }

  /* Fields */
  .field { margin-bottom: 18px; }
  .field label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #44455a;
    margin-bottom: 6px;
  }
  .field input {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #dde0ef;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    color: #2c2c4a;
    background: #fafbff;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
  }
  .field input:focus {
    border-color: #696cff;
    box-shadow: 0 0 0 3px rgba(105,108,255,0.12);
    background: #fff;
  }
  .field input.is-error { border-color: #d93025; }

  .pass-field { position: relative; }
  .pass-field input { padding-right: 42px; }
  .eye-toggle {
    position: absolute; right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: #8a8da9; font-size: 15px;
    padding: 2px 4px;
    transition: color .2s;
  }
  .eye-toggle:hover { color: #696cff; }

  /* Extras */
  .form-extras {
    display: flex; align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    font-size: 13px;
  }
  .remember { display: flex; align-items: center; gap: 6px; color: #44455a; cursor: pointer; }
  .remember input { accent-color: #696cff; }
  .forgot { color: #696cff; text-decoration: none; font-weight: 500; }
  .forgot:hover { text-decoration: underline; }

  /* Login btn */
  .btn-login {
    width: 100%;
    padding: 11px;
    background: linear-gradient(135deg,#696cff,#9155fd);
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    border: none;
    border-radius: 9px;
    cursor: pointer;
    letter-spacing: .3px;
    transition: opacity .2s, transform .15s;
    font-family: inherit;
  }
  .btn-login:hover { opacity: .9; transform: translateY(-1px); }
  .btn-login:active { transform: translateY(0); }

  /* Switch */
  .switch-row {
    text-align: center;
    margin-top: 18px;
    font-size: 13.5px;
    color: #8a8da9;
  }
  .switch-row a { color: #696cff; font-weight: 600; text-decoration: none; }
  .switch-row a:hover { text-decoration: underline; }

  /* Divider */
  .divider {
    display: flex; align-items: center;
    gap: 10px; margin: 18px 0 14px;
    color: #b8bbd4; font-size: 13px;
  }
  .divider::before, .divider::after {
    content: ''; flex: 1;
    height: 1px; background: #e8eaf6;
  }

  /* Socials */
  .socials { display: flex; justify-content: center; gap: 12px; }
  .social-btn {
    width: 38px; height: 38px;
    border: 1.5px solid #dde0ef;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    font-size: 16px;
    display: flex; align-items: center; justify-content: center;
    transition: border-color .2s, box-shadow .2s, transform .15s;
  }
  .social-btn:hover {
    border-color: #696cff;
    box-shadow: 0 2px 8px rgba(105,108,255,0.15);
    transform: translateY(-2px);
  }
</style>
</head>
<body>

<div class="auth-wrap">
  <div class="auth-card">

    <!-- YETI SVG -->
    <div class="yeti-wrap">
      <svg id="yetiSvg" class="yeti-svg" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
        <!-- Body/fur base -->
        <circle cx="100" cy="100" r="90" fill="#dce8f5"/>

        <!-- Inner face -->
        <ellipse cx="100" cy="108" rx="62" ry="58" fill="#eef4fb"/>

        <!-- Fur spikes top -->
        <path d="M38 75 Q50 30 65 55 Q72 20 83 50 Q90 10 100 45 Q110 10 117 50 Q128 20 135 55 Q150 30 162 75" fill="#dce8f5" stroke="#c6d9ef" stroke-width="1"/>

        <!-- Ears -->
        <ellipse cx="22" cy="100" rx="16" ry="20" fill="#dce8f5"/>
        <ellipse cx="178" cy="100" rx="16" ry="20" fill="#dce8f5"/>
        <ellipse cx="22" cy="100" rx="9" ry="13" fill="#f4b8c1"/>
        <ellipse cx="178" cy="100" rx="9" ry="13" fill="#f4b8c1"/>

        <!-- OPEN EYES GROUP -->
        <g class="eyes-open">
          <!-- Eye whites -->
          <ellipse cx="76" cy="95" rx="16" ry="17" fill="white"/>
          <ellipse cx="124" cy="95" rx="16" ry="17" fill="white"/>
          <!-- Pupils -->
          <circle cx="79" cy="97" r="9" fill="#2c3e6b"/>
          <circle cx="121" cy="97" r="9" fill="#2c3e6b"/>
          <!-- Highlights -->
          <circle cx="83" cy="93" r="3" fill="white"/>
          <circle cx="125" cy="93" r="3" fill="white"/>
          <!-- Lashes -->
          <path d="M60 82 Q64 76 70 80" stroke="#2c3e6b" stroke-width="2" fill="none" stroke-linecap="round"/>
          <path d="M140 82 Q136 76 130 80" stroke="#2c3e6b" stroke-width="2" fill="none" stroke-linecap="round"/>
        </g>

        <!-- PEEKING EYES GROUP (small, peering over arms) -->
        <g class="eyes-peeking">
          <ellipse cx="76" cy="108" rx="14" ry="8" fill="white"/>
          <ellipse cx="124" cy="108" rx="14" ry="8" fill="white"/>
          <circle cx="78" cy="109" r="5" fill="#2c3e6b"/>
          <circle cx="122" cy="109" r="5" fill="#2c3e6b"/>
          <circle cx="80" cy="107" r="2" fill="white"/>
          <circle cx="124" cy="107" r="2" fill="white"/>
        </g>

        <!-- Nose -->
        <ellipse cx="100" cy="118" rx="7" ry="5" fill="#f4b8c1"/>

        <!-- Mouth (happy) -->
        <path d="M85 133 Q100 146 115 133" stroke="#2c3e6b" stroke-width="2.5" fill="none" stroke-linecap="round"/>

        <!-- ARMS (cover eyes when password focused) -->
        <g id="yetiArms" class="yeti-arms visible">
          <!-- Left arm -->
          <path d="M10 130 Q30 100 55 95 Q70 92 78 96" 
                fill="#dce8f5" stroke="#b8cfe8" stroke-width="2" stroke-linecap="round"/>
          <!-- Left hand fingers -->
          <ellipse cx="78" cy="96" rx="14" ry="8" fill="#dce8f5" stroke="#b8cfe8" stroke-width="1.5"/>
          <path d="M66 90 Q71 84 76 89" stroke="#b8cfe8" stroke-width="1.5" fill="none" stroke-linecap="round"/>
          <path d="M72 87 Q77 81 82 87" stroke="#b8cfe8" stroke-width="1.5" fill="none" stroke-linecap="round"/>
          <path d="M79 86 Q84 81 88 87" stroke="#b8cfe8" stroke-width="1.5" fill="none" stroke-linecap="round"/>

          <!-- Right arm -->
          <path d="M190 130 Q170 100 145 95 Q130 92 122 96" 
                fill="#dce8f5" stroke="#b8cfe8" stroke-width="2" stroke-linecap="round"/>
          <!-- Right hand fingers -->
          <ellipse cx="122" cy="96" rx="14" ry="8" fill="#dce8f5" stroke="#b8cfe8" stroke-width="1.5"/>
          <path d="M134 90 Q129 84 124 89" stroke="#b8cfe8" stroke-width="1.5" fill="none" stroke-linecap="round"/>
          <path d="M128 87 Q123 81 118 87" stroke="#b8cfe8" stroke-width="1.5" fill="none" stroke-linecap="round"/>
          <path d="M121 86 Q116 81 112 87" stroke="#b8cfe8" stroke-width="1.5" fill="none" stroke-linecap="round"/>
        </g>

      </svg>
    </div>

    <!-- Brand -->
    <div class="brand">
      <div class="brand-icon">AM</div>
      <span class="brand-name">TEAM</span>
    </div>

    <p class="auth-sub">Please sign-in to your account and start the monitoring of system</p>

    <!-- Alert -->
    <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($_GET['registered'])): ?>
      <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i>
        Registration successful! You can now log in.
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="login.php" autocomplete="on">

      <div class="field">
        <label for="identifier">Email or Username</label>
        <input
          id="identifier"
          name="identifier"
          type="text"
          placeholder="Enter your email or username"
          value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
          autocomplete="username"
          <?= $error ? 'class="is-error"' : '' ?>
          required>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="pass-field">
          <input
            id="password"
            name="password"
            type="password"
            placeholder="············"
            autocomplete="current-password"
            <?= $error ? 'class="is-error"' : '' ?>
            required>
          <button type="button" class="eye-toggle" onclick="togglePassword()" id="eyeBtn">
            <i class="fa-regular fa-eye-slash" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <div class="form-extras">
        <label class="remember">
          <input type="checkbox" name="remember" id="remember"> Remember Me
        </label>
        <a href="#" class="forgot">Forgot Password?</a>
      </div>

      <button type="submit" class="btn-login">Login</button>
    </form>

    <p class="switch-row">
      New on our platform? <a href="Account/register.php">Create an account</a>
    </p>

    <div class="divider"><span>or</span></div>

    <div class="socials">
      <button class="social-btn" title="Facebook" type="button">
        <i class="fa-brands fa-facebook-f" style="color:#1877f2;"></i>
      </button>
      <button class="social-btn" title="Twitter" type="button">
        <i class="fa-brands fa-twitter" style="color:#1da1f2;"></i>
      </button>
      <button class="social-btn" title="GitHub" type="button">
        <i class="fa-brands fa-github" style="color:#333;"></i>
      </button>
      <button class="social-btn" title="Google" type="button">
        <i class="fa-brands fa-google" style="color:#ea4335;"></i>
      </button>
    </div>

  </div>
</div>

<script>
  const yetiSvg  = document.getElementById('yetiSvg');
  const yetiArms = document.getElementById('yetiArms');
  const passInput = document.getElementById('password');
  let passwordVisible = false;

  function setYetiCovering(covering) {
    if (covering) {
      // Arms UP – covering eyes
      yetiArms.classList.remove('hidden');
      yetiArms.classList.add('visible');
      yetiSvg.classList.remove('peeking');
    } else {
      // Arms DOWN – eyes open
      yetiArms.classList.remove('visible');
      yetiArms.classList.add('hidden');
      yetiSvg.classList.remove('peeking');
    }
  }

  function setYetiPeeking() {
    // Arms still up but peeking eyes visible
    yetiArms.classList.remove('hidden');
    yetiArms.classList.add('visible');
    yetiSvg.classList.add('peeking');
  }

  // When password field is focused → arms cover eyes
  passInput.addEventListener('focus', () => {
    if (!passwordVisible) setYetiCovering(true);
  });

  // When password field loses focus → arms down
  passInput.addEventListener('blur', () => {
    setYetiCovering(false);
  });

  // Toggle password visibility
  function togglePassword() {
    const inp  = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    passwordVisible = !passwordVisible;

    if (passwordVisible) {
      inp.type = 'text';
      icon.className = 'fa-regular fa-eye';
      // Yeti peeks between fingers
      setYetiPeeking();
    } else {
      inp.type = 'password';
      icon.className = 'fa-regular fa-eye-slash';
      // Back to covering
      if (document.activeElement === inp) {
        setYetiCovering(true);
      }
    }
  }

  // Identifier field focus → eyes open, arms down
  document.getElementById('identifier').addEventListener('focus', () => {
    setYetiCovering(false);
  });
</script>

</body>
</html>