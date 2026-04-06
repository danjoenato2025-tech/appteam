<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

include('../dbconnection/config.php');
$error      = '';
$registered = false;
$regInfo    = [];
$old        = [];

$colors = ['#a8b9f8','#ffb347','#f4a2a2','#6ecf8b','#7bc8f6','#d4a5f5','#f9c784','#c2c2c2'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'username'     => trim($_POST['username']     ?? ''),
        'full_name'    => trim($_POST['full_name']    ?? ''),
        'email'        => trim($_POST['email']        ?? ''),
        'associate_id' => trim($_POST['associate_id'] ?? ''),
        'section'      => trim($_POST['section']      ?? ''),
        'team'         => trim($_POST['team']         ?? ''),
        'role'         => in_array($_POST['role'] ?? '', ['admin','user']) ? $_POST['role'] : 'user',
    ];
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';
    $agree     = isset($_POST['agree']);

    // ── Validate ────────────────────────────────────────────
    if (!$old['username'])
        $error = 'Username is required.';
    elseif (!$old['full_name'])
        $error = 'Full name is required.';
    elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))
        $error = 'Please enter a valid email address.';
    elseif (!$old['associate_id'])
        $error = 'Associate ID is required.';
    elseif (!preg_match('/^\d{7}$/', $old['associate_id']))
        $error = 'Associate ID must be exactly 7 digits (numbers only).';
    elseif (!$old['section'])
        $error = 'Section is required.';
    elseif (!$old['team'])
        $error = 'Team is required.';
    elseif (strlen($password) < 6)
        $error = 'Password must be at least 6 characters.';
    elseif ($password !== $password2)
        $error = 'Passwords do not match.';
    elseif (!$agree)
        $error = 'You must agree to the privacy policy & terms.';
    else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email=? OR username=? OR associate_id=? LIMIT 1');
        $stmt->execute([$old['email'], $old['username'], $old['associate_id']]);
        if ($stmt->fetch()) {
            $error = 'An account with this email, username, or Associate ID already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $color  = $colors[array_rand($colors)];

            $stmt = $pdo->prepare('
                INSERT INTO users
                    (username, full_name, email, password, role, plan, billing, status, avatar_color, associate_id, section, team)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $old['username'], $old['full_name'], $old['email'], $hashed,
                $old['role'], 'Basic', 'Manual – Cash', 'pending',
                $color, $old['associate_id'], $old['section'], $old['team'],
            ]);

            $regInfo    = $old;
            $registered = true;
            $old        = [];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – Sneat</title>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/register.css">
    <style>
        /* ══ Success Modal ══ */
        .success-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(30,30,60,.50); backdrop-filter:blur(4px);
            z-index:9999; align-items:center; justify-content:center;
        }
        .success-overlay.open { display:flex; }
        .success-box {
            background:#fff; border-radius:18px; padding:44px 40px 38px;
            max-width:430px; width:93%; text-align:center;
            box-shadow:0 24px 64px rgba(0,0,0,.20);
            animation:popIn .38s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes popIn { from{transform:scale(.72);opacity:0} to{transform:scale(1);opacity:1} }
        .success-icon-wrap {
            width:84px; height:84px; border-radius:50%;
            background:linear-gradient(135deg,#ff9f4322,#ff9f4340);
            display:flex; align-items:center; justify-content:center; margin:0 auto 22px;
        }
        .success-icon-wrap i { font-size:38px; color:#ff9f43; }
        .success-title { font-size:22px; font-weight:700; color:#2d3a4a; margin-bottom:10px; }
        .success-msg   { font-size:14px; color:#6e7a8a; line-height:1.65; margin-bottom:24px; }
        .success-detail {
            background:#f6f7f9; border-radius:11px; padding:14px 18px;
            text-align:left; margin-bottom:28px; font-size:13.5px;
            color:#4a5568; line-height:2;
        }
        .success-detail .sd-row   { display:flex; gap:6px; }
        .success-detail .sd-label { font-weight:600; color:#2d3a4a; min-width:110px; }
        .role-badge {
            display:inline-flex; align-items:center; gap:5px;
            font-size:12.5px; font-weight:600; padding:2px 10px; border-radius:20px;
        }
        .role-badge.admin { background:#ff3e1d18; color:#ff3e1d; }
        .role-badge.user  { background:#696cff18; color:#696cff; }
        .btn-go-login {
            display:inline-flex; align-items:center; gap:8px;
            background:linear-gradient(135deg,#ff9f43,#e08020);
            color:#fff; border:none; border-radius:9px;
            padding:13px 34px; font-size:15px; font-weight:600;
            cursor:pointer; text-decoration:none; transition:opacity .2s;
        }
        .btn-go-login:hover { opacity:.88; }

        /* ══ Role Selector ══ */
        .role-selector { display:flex; gap:12px; margin-top:4px; }
        .role-option { flex:1; position:relative; }
        .role-option input[type="radio"] { position:absolute; opacity:0; width:0; }
        .role-option label {
            display:flex; align-items:center; gap:9px; border:2px solid #e0e2e8;
            border-radius:9px; padding:11px 14px; cursor:pointer; font-size:14px;
            font-weight:500; color:#5a6070; background:#fafafa; transition:all .2s; user-select:none;
        }
        .role-option input[type="radio"]:checked + label { border-color:#696cff; background:#696cff12; color:#696cff; }
        .role-option.admin input[type="radio"]:checked + label { border-color:#ff3e1d; background:#ff3e1d10; color:#ff3e1d; }

        /* ══ Associate ID hint ══ */
        .field-hint { font-size:11.5px; color:#a0a8b5; margin-top:4px; }
        .field-hint.error { color:#ff3e1d; }
    </style>
</head>
<body>

<!-- ════ SUCCESS MODAL ════ -->
<div class="success-overlay <?= $registered ? 'open' : '' ?>" id="success-overlay">
    <div class="success-box">
        <div class="success-icon-wrap"><i class="fa-solid fa-clock"></i></div>
        <div class="success-title">Registration Submitted! ⏳</div>
        <div class="success-msg">
            Your account has been created and is <strong>awaiting admin approval</strong>.<br>
            You will be able to log in once an administrator approves your account.
        </div>
        <div style="background:#fff8ec;border-left:4px solid #ff9f43;border-radius:9px;padding:12px 16px;margin-bottom:20px;text-align:left;font-size:13px;color:#5a6070;">
            <i class="fa-solid fa-bell" style="color:#ff9f43;margin-right:6px;"></i>
            An admin has been notified of your registration and will review your account shortly.
        </div>
        <div class="success-detail">
            <div class="sd-row"><span class="sd-label">👤 Name</span><span><?= htmlspecialchars($regInfo['full_name']    ?? '') ?></span></div>
            <div class="sd-row"><span class="sd-label">🪪 Associate ID</span><span><?= htmlspecialchars($regInfo['associate_id'] ?? '') ?></span></div>
            <div class="sd-row"><span class="sd-label">🏢 Section</span><span><?= htmlspecialchars($regInfo['section']      ?? '') ?></span></div>
            <div class="sd-row"><span class="sd-label">👥 Team</span><span><?= htmlspecialchars($regInfo['team']         ?? '') ?></span></div>
            <div class="sd-row">
                <span class="sd-label">🔐 Role</span>
                <span>
                    <?php $r = $regInfo['role'] ?? 'user'; ?>
                    <span class="role-badge <?= $r ?>">
                        <i class="fa-solid <?= $r === 'admin' ? 'fa-shield-halved' : 'fa-user' ?>"></i>
                        <?= ucfirst($r) ?>
                    </span>
                </span>
            </div>
            <div class="sd-row"><span class="sd-label">📧 Email</span><span><?= htmlspecialchars($regInfo['email'] ?? '') ?></span></div>
        </div>
        <a class="btn-go-login" href="../login.php">
            <i class="fa-solid fa-right-to-bracket"></i> Go to Login
        </a>
    </div>
</div>

<!-- ════ REGISTER FORM ════ -->
<div class="auth-wrap">
    <div class="auth-card">

        <div class="brand">
            <div class="brand-icon">AM</div>
            <span class="brand-name">TEAM</span>
        </div>

        <h1 class="auth-title">Adventure starts here 🚀</h1>
        <p class="auth-sub">Make your app management easy and fun!</p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" autocomplete="on">

            <!-- Username & Full Name -->
            <div class="field-grid">
                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" placeholder="Input your Username"
                        value="<?= htmlspecialchars($old['username'] ?? '') ?>" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="full_name">Full Name</label>
                    <input id="full_name" name="full_name" type="text" placeholder="First Name, Last Name"
                        value="<?= htmlspecialchars($old['full_name'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Email -->
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" placeholder="Input your Email Address"
                    value="<?= htmlspecialchars($old['email'] ?? '') ?>" autocomplete="email" required>
            </div>

            <!-- Associate ID – 7 digits only -->
            <div class="field">
                <label for="associate_id">Associate ID</label>
                <input id="associate_id" name="associate_id" type="text"
                    placeholder="7-digit number e.g. 1234567"
                    value="<?= htmlspecialchars($old['associate_id'] ?? '') ?>"
                    maxlength="7"
                    pattern="\d{7}"
                    inputmode="numeric"
                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,7); validateAssocId(this);"
                    required>
                <div class="field-hint" id="assoc-hint">Numbers only · exactly 7 digits</div>
            </div>

            <!-- Section & Team -->
            <div class="field-grid">
                <div class="field">
                    <label for="section">Section</label>
                    <input id="section" name="section" type="text" placeholder="e.g. IT Operations"
                        value="<?= htmlspecialchars($old['section'] ?? '') ?>" required>
                </div>
                <div class="field">
                    <label for="team">Team</label>
                    <input id="team" name="team" type="text" placeholder="e.g. Alpha"
                        value="<?= htmlspecialchars($old['team'] ?? '') ?>" required>
                </div>
            </div>

            <!-- Role -->
            <div class="field">
                <label>Role</label>
                <div class="role-selector">
                    <div class="role-option">
                        <input type="radio" name="role" id="role-user" value="user"
                            <?= (($old['role'] ?? 'user') === 'user') ? 'checked' : '' ?>>
                        <label for="role-user"><i class="fa-solid fa-user"></i> User</label>
                    </div>
                    <div class="role-option admin">
                        <input type="radio" name="role" id="role-admin" value="admin"
                            <?= (($old['role'] ?? '') === 'admin') ? 'checked' : '' ?>>
                        <label for="role-admin"><i class="fa-solid fa-shield-halved"></i> Admin</label>
                    </div>
                </div>
            </div>

            <!-- Password -->
            <div class="field">
                <label for="password">Password</label>
                <div class="pass-field">
                    <input id="password" name="password" type="password" placeholder="Input your Password"
                        oninput="checkStrength(this.value)" autocomplete="new-password" required>
                    <button type="button" class="eye-toggle" onclick="togglePass('password','eye1')">
                        <i class="fa-regular fa-eye-slash" id="eye1"></i>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
            </div>

            <!-- Confirm Password -->
            <div class="field">
                <label for="password2">Confirm Password</label>
                <div class="pass-field">
                    <input id="password2" name="password2" type="password" placeholder="Input your Password"
                        autocomplete="new-password" required>
                    <button type="button" class="eye-toggle" onclick="togglePass('password2','eye2')">
                        <i class="fa-regular fa-eye-slash" id="eye2"></i>
                    </button>
                </div>
            </div>

            <!-- Agree -->
            <label class="agree-row">
                <input type="checkbox" name="agree" id="agree" <?= isset($_POST['agree']) ? 'checked' : '' ?>>
                <span>I agree to the <a href="#">privacy policy &amp; terms</a></span>
            </label>

            <button type="submit" class="btn-register">Create Account</button>
        </form>

        <p class="switch-row">Already have an account? <a href="../login.php">Sign in instead</a></p>

        <div class="divider"><span>or</span></div>

        <div class="socials">
            <button class="social-btn" type="button" title="Facebook"><i class="fa-brands fa-facebook-f" style="color:#1877f2;"></i></button>
            <button class="social-btn" type="button" title="Twitter"><i class="fa-brands fa-twitter"    style="color:#1da1f2;"></i></button>
            <button class="social-btn" type="button" title="GitHub"><i class="fa-brands fa-github"      style="color:#333;"></i></button>
            <button class="social-btn" type="button" title="Google"><i class="fa-brands fa-google"      style="color:#ea4335;"></i></button>
        </div>

    </div>
</div>

<script>
    /* Associate ID live validation hint */
    function validateAssocId(el) {
        const hint = document.getElementById('assoc-hint');
        const val  = el.value;
        if (val.length === 0) {
            hint.textContent = 'Numbers only · exactly 7 digits';
            hint.className   = 'field-hint';
        } else if (val.length < 7) {
            hint.textContent = `${val.length}/7 digits entered`;
            hint.className   = 'field-hint error';
        } else {
            hint.textContent = '✓ Valid Associate ID';
            hint.className   = 'field-hint';
            hint.style.color = '#28c76f';
        }
    }

    function togglePass(inputId, iconId) {
        const inp  = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        const show = inp.type === 'password';
        inp.type       = show ? 'text' : 'password';
        icon.className = show ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
    }

    function checkStrength(val) {
        const fill = document.getElementById('strength-fill');
        let score  = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        const map = [
            { w:'0%',   bg:'transparent' },
            { w:'25%',  bg:'#ff3e1d'     },
            { w:'50%',  bg:'#ff9f43'     },
            { w:'75%',  bg:'#00cfe8'     },
            { w:'100%', bg:'#28c76f'     },
        ];
        fill.style.width      = map[score].w;
        fill.style.background = map[score].bg;
    }
</script>
</body>
</html>