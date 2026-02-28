<?php
/**
 * ╔══════════════════════════════════════════════════════════╗
 * ║  GuYi Network Verification System — V4Remastered      ║
 * ║  Admin Login Page                                        ║
 * ╚══════════════════════════════════════════════════════════╝
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'database.php';

session_start();

//━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §1. 数据库连接
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

try {
    $db = new Database();
} catch (Throwable $e) {
    http_response_code(503);
    die('<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>System Offline</title><style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0a0a0f;font-family:system-ui,-apple-system,sans-serif;color:#e2e8f0}
        .err-box{max-width:520px;padding:48px;text-align:center}
        .err-icon{width:80px;height:80px;margin:0 auto 24px;border-radius:20px;background:rgba(239,68,68,.15);display:flex;align-items:center;justify-content:center}
        .err-icon svg{width:40px;height:40px;color:#ef4444}
        h1{font-size:24px;font-weight:700;margin-bottom:12px;background:linear-gradient(135deg,#f87171,#ef4444);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        p{color:#94a3b8;font-size:14px;line-height:1.6;margin-bottom:16px}
        code{display:block;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:16px;font-size:12px;color:#fca5a5;word-break:break-all;margin:20px 0;text-align:left;font-family:"JetBrains Mono",monospace}
        .retry{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:linear-gradient(135deg,#6366f1,#4f46e5);color:white;border-radius:12px;text-decoration:none;font-weight:600;font-size:14px;transition:transform .2s,box-shadow .2s}
        .retry:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(99,102,241,.4)}
    </style></head><body>
    <div class="err-box">
        <div class="err-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
        <h1>系统连接失败</h1>
        <p>无法建立数据库连接，请检查配置文件或数据库服务状态。</p>
        <code>' . htmlspecialchars($e->getMessage()) . '</code><a href="javascript:location.reload()" class="retry"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6"/><path d="M3.51 15a99 0 1 0 2.13-9.36L1 10"/></svg>重新连接</a>
    </div></body></html>');
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §2. 安全检查
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

if (defined('SYS_SECRET') && strpos(SYS_SECRET, 'ENT_SECure_K3y') !== false) {
    die('<div style="color:#ef4444;font-weight:700;padding:40px;text-align:center;font-family:system-ui">⚠️ 安全警告：请立即修改 config.php 中的 SYS_SECRET 常量！
    </div>');
}

try {
    if ($db->getAdminUsername() ==='admin') {
        $db->updateAdminUsername('GuYi');}
} catch (Exception $e) {}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §3. CSRF Token
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §4. 信任设备Cookie验证
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

$rawHash= $db->getAdminHash();
$adminHashFingerprint = md5((string)$rawHash);
$is_trusted         = false;

if (isset($_COOKIE['admin_trust'])) {
    $parts = explode('|', $_COOKIE['admin_trust']);
    if (count($parts) === 2) {
        [$payload, $sign] = $parts;
        if (hash_equals(hash_hmac('sha256', $payload, SYS_SECRET), $sign)) {
            $data = json_decode(base64_decode($payload), true);
            if (
                $data &&
                isset($data['exp'], $data['ua'], $data['ph']) &&
                $data['exp'] > time() &&
                $data['ua'] === md5($_SERVER['HTTP_USER_AGENT'] ?? '') &&
                hash_equals($data['ph'], $adminHashFingerprint)
            ) {
                $is_trusted = true;
            }
        }
    }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §5. 已登录则直接跳转后台
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

// 信任设备自动登录
if (!isset($_SESSION['admin_logged_in']) && $is_trusted) {
    $_SESSION['admin_logged_in'] = true;
    session_regenerate_id(true);
    $_SESSION['last_ip'] = $_SERVER['REMOTE_ADDR'];
}

// IP锁定校验
if (
    isset($_SESSION['admin_logged_in'], $_SESSION['last_ip']) &&
    $_SESSION['last_ip'] !== $_SERVER['REMOTE_ADDR']
) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// 已登录直接跳后台
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: cards.php');
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §6. 登录认证逻辑
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

$login_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    // CSRF 校验
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Security Alert: CSRF Token Mismatch.');
    }

    // 验证码校验（非信任设备）
    if (!$is_trusted) {
        $input_captcha = strtoupper(trim($_POST['captcha'] ?? ''));
        $sess_captcha  = $_SESSION['captcha_code'] ?? 'INVALID';
        unset($_SESSION['captcha_code']);
        if (empty($input_captcha) || $input_captcha !== $sess_captcha) {
            $login_error = '验证码错误或已过期';
        }
    }

    // 密码校验
    if (!$login_error) {
        $hash = $db->getAdminHash();
        if (!empty($hash) && password_verify($_POST['password'], $hash)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['last_ip']= $_SERVER['REMOTE_ADDR'];

            // 写入信任Cookie
            $cookieData = [
                'exp' => time() + 86400 * 3,
                'ua'  => md5($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'ph'  => md5($hash),
            ];
            $payload = base64_encode(json_encode($cookieData));
            $sign    = hash_hmac('sha256', $payload, SYS_SECRET);
            setcookie('admin_trust', "$payload|$sign", time() + 86400 * 3, '/', '', false, true);

            header('Location: cards.php');
            exit;
        } else {
            usleep(500000);
            $login_error = '访问被拒绝：密钥无效';
        }
    }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §7. 读取系统配置（用于页面渲染）
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

$sysConf         = $db->getSystemSettings();
$conf_site_title = $sysConf['site_title']?? 'GuYi Access';
$conf_favicon    = $sysConf['favicon']       ?? 'backend/logo.png';
$conf_avatar     = $sysConf['admin_avatar']  ?? 'backend/logo.png';
$conf_bg_pc      = $sysConf['bg_pc']         ??'backend/pcpjt.png';
$conf_bg_mobile  = $sysConf['bg_mobile']     ?? 'backend/pjt.png';
$conf_bg_blur    = $sysConf['bg_blur']       ?? '1';
$blur_val        = ($conf_bg_blur === '1') ? '24px' : '0px';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>登录 — <?= htmlspecialchars($conf_site_title) ?></title>
<link rel="icon" href="<?= htmlspecialchars($conf_favicon) ?>" type="image/png">
<style>
:root {
    --login-accent:#6366f1;
    --login-surface:rgba(15,15,25,.6);
    --login-border:rgba(255,255,255,.1);
    --login-border-focus:rgba(255,255,255,.25);
    --login-text:#f1f5f9;
    --login-muted:#94a3b8;
    --login-input-bg:rgba(255,255,255,.06);
    --login-radius:20px;
    --login-input-h:52px;
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0 }
html { height:100%; -webkit-font-smoothing:antialiased }
body {
    min-height:100vh; display:flex; align-items:center; justify-content:center;
    font-family:system-ui,-apple-system,'Segoe UI',Roboto,'PingFang SC','Microsoft YaHei',sans-serif;
    color:var(--login-text);
    background:url('<?= htmlspecialchars($conf_bg_pc) ?>') center/cover fixed no-repeat #0a0a0f;
    overflow:hidden;
}
@media(max-width:768px){
    body { background-image:url('<?= htmlspecialchars($conf_bg_mobile) ?>')!important }
}
.bg-overlay {
    position:fixed; inset:0;
    background:rgba(0,0,0,.45);
    backdrop-filter:blur(<?= $blur_val ?>);
    -webkit-backdrop-filter:blur(<?= $blur_val ?>);
    z-index:0;
}
.particles { position:fixed; inset:0; z-index:1; pointer-events:none; overflow:hidden }
.particles span {
    position:absolute; width:4px; height:4px;
    background:rgba(99,102,241,.6); border-radius:50%;
    animation:float linear infinite; opacity:0;
}
@keyframes float {
    0%{ opacity:0; transform:translateY(100vh) scale(0) }
    10%  { opacity:1 }
    90%  { opacity:1 }
    100% { opacity:0; transform:translateY(-20vh) scale(1) }
}
.login-wrap { position:relative; z-index:10; width:min(440px,90vw) }
.login-card {
    background:var(--login-surface);
    backdrop-filter:blur(40px) saturate(150%);
    -webkit-backdrop-filter:blur(40px) saturate(150%);
    border:1px solid var(--login-border);
    border-radius:28px; overflow:hidden; position:relative;
    box-shadow:0 32px 64px -12px rgba(0,0,0,.5),0 0 0 1px rgba(255,255,255,.05) inset;
    animation:cardEntry .8s cubic-bezier(.16,1,.3,1) both;
}
@keyframes cardEntry {
    from { opacity:0; transform:translateY(40px) scale(.96) }
    to   { opacity:1; transform:translateY(0) scale(1) }
}
.login-card::before {
    content:''; position:absolute; inset:-1px; border-radius:inherit; padding:1px;
    background:conic-gradient(from 230deg,var(--login-accent),transparent 40%,transparent 60%,var(--login-accent));
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor; mask-composite:exclude;
    opacity:.5; pointer-events:none; animation:borderSpin 8s linear infinite;
}
@keyframes borderSpin { to { filter:hue-rotate(360deg) } }
.login-card::after {
    content:''; position:absolute; inset:0; border-radius:inherit; pointer-events:none; opacity:0;
    background:radial-gradient(400px circle at var(--mx,50%) var(--my,50%),rgba(99,102,241,.08),transparent 50%);
    transition:opacity .4s; z-index:20;
}
.login-card:hover::after { opacity:1 }
.login-header { padding:40px 36px 20px; text-align:center }
.login-avatar {
    width:72px; height:72px; border-radius:20px; margin:0 auto 16px;
    background:url('<?= htmlspecialchars($conf_avatar) ?>') center/cover;
    box-shadow:0 8px 32px rgba(99,102,241,.3);
    border:2px solid rgba(255,255,255,.15);
    position:relative; animation:avatarPulse 3s ease-in-out infinite;
}
@keyframes avatarPulse {
    0%,100% { box-shadow:0 8px 32px rgba(99,102,241,.3) }
    50%{ box-shadow:0 8px 32px rgba(99,102,241,.5) }
}
.login-avatar::after {
    content:''; position:absolute; inset:-4px; border-radius:22px;
    border:2px solid transparent; border-top-color:var(--login-accent);
    animation:spin 3s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg) } }
.login-title {
    font-size:22px; font-weight:800; letter-spacing:-.5px; margin-bottom:6px;
    background:linear-gradient(135deg,#fff,#c7d2fe);
    -webkit-background-clip:text; -webkit-text-fill-color:transparent;
}
.login-subtitle { font-size:12px; color:var(--login-muted); letter-spacing:.5px }
.login-body { padding:8px 36px 36px }
.field { position:relative; margin-bottom:20px }
.field-input {
    width:100%; height:var(--login-input-h); padding:16px 18px; padding-right:50px;
    background:var(--login-input-bg); border:1.5px solid var(--login-border);
    border-radius:16px; color:var(--login-text); font-size:14px; outline:none;
    transition:all .3s ease;
}
.field-input::placeholder { color:transparent }
.field-input:focus {
    border-color:var(--login-border-focus);
    background:rgba(255,255,255,.08);
    box-shadow:0 0 0 4px rgba(99,102,241,.1);
}
.field-input:-webkit-autofill {
    -webkit-text-fill-color:var(--login-text)!important;
    box-shadow:inset 0 0 0 1000px rgba(255,255,255,.06)!important;
    transition:background-color 5000s ease-in-out 0s;
}
.field-label {
    position:absolute; left:18px; top:50%; transform:translateY(-50%);
    font-size:13px; color:var(--login-muted); pointer-events:none;
    transition:all .25s cubic-bezier(.4,0,.2,1);
}
.field-input:focus + .field-label,
.field-input:not(:placeholder-shown) + .field-label {
    top:-10px; font-size:11px; transform:translateY(0);
    background:rgba(15,15,25,.9); padding:2px 10px; border-radius:99px;
    color:#c7d2fe; border:1px solid rgba(255,255,255,.1); font-weight:600;
}
.field-eye {
    position:absolute; right:10px; top:50%; transform:translateY(-50%);
    width:36px; height:36px; border-radius:10px; border:none; background:transparent;
    display:grid; place-items:center; cursor:pointer; transition:all .2s; color:var(--login-muted);
}
.field-eye:hover { background:rgba(255,255,255,.08); color:#c7d2fe }
.captcha-img {
    position:absolute; right:8px; top:50%; transform:translateY(-50%);
    height:38px; border-radius:10px; cursor:pointer;
    border:1px solid rgba(255,255,255,.1); opacity:.8; transition:all .2s;
}
.captcha-img:hover { opacity:1; border-color:var(--login-accent) }
.login-btn {
    width:100%; height:52px; border:none; border-radius:16px; cursor:pointer;
    font-size:15px; font-weight:700; letter-spacing:.5px; color:#fff;
    background:linear-gradient(135deg,#6366f1 0%,#4f46e5 50%,#6366f1 100%);
    background-size:200% 200%;
    box-shadow:0 8px 32px rgba(99,102,241,.4),inset 0 1px 0 rgba(255,255,255,.2);
    position:relative; overflow:hidden; transition:all .3s ease;
}
.login-btn:hover {
    background-position:100% 0; transform:translateY(-2px);
    box-shadow:0 12px 40px rgba(99,102,241,.5);
}
.login-btn:active { transform:translateY(0) scale(.98) }
.login-btn::before {
    content:''; position:absolute; inset:0;
    background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);
    transform:translateX(-100%) skewX(-15deg); transition:transform .6s;
}
.login-btn:hover::before { transform:translateX(200%) skewX(-15deg) }
.login-error {
    background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.2);
    color:#fca5a5; font-size:13px; padding:12px 16px; border-radius:14px;
    margin-bottom:18px; display:flex; align-items:center; gap:8px;
    animation:shake .5s ease;
}
@keyframes shake {
    10%,90% { transform:translateX(-1px) }
    20%,80%{ transform:translateX(2px) }
    30%,50%,70% { transform:translateX(-3px) }
    40%,60%  { transform:translateX(3px) }
}
.login-footer {
    text-align:center; padding:12px 4px;
    font-size:11px; color:rgba(255,255,255,.3); letter-spacing:.5px;
}
</style>
</head>
<body>
<div class="bg-overlay"></div>
<div class="particles" id="particles"></div>

<div class="login-wrap">
    <div class="login-card" id="loginCard">
        <header class="login-header">
            <div class="login-avatar"></div>
            <h1 class="login-title">欢迎回来，指挥官</h1>
            <p class="login-subtitle">IDENTITY VERIFICATION REQUIRED</p>
        </header>
        <div class="login-body">
            <?php if (isset($login_error)): ?>
            <div class="login-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= htmlspecialchars($login_error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <div class="field">
                    <input type="password" id="pwd" name="password"
                           class="field-input" placeholder=" "
                           autocomplete="current-password" required>
                    <label class="field-label">管理员密钥</label>
                    <button type="button" class="field-eye" id="eyeBtn" aria-label="切换密码可见">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                             stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>

                <?php if (!$is_trusted): ?>
                <div class="field">
                    <input type="text" name="captcha" class="field-input"
                           placeholder=" " autocomplete="off"
                           required maxlength="4" style="padding-right:130px">
                    <label class="field-label">验证码</label>
                    <img src="Verifyfile/captcha.php"
                         class="captcha-img"
                         onclick="this.src='Verifyfile/captcha.php?t='+Math.random()"
                         title="点击刷新" alt="captcha">
                </div>
                <?php endif; ?>

                <button type="submit" class="login-btn">进入控制台</button>
            </form>

            <div class="login-footer">
                © <?= date('Y') ?> <?= htmlspecialchars($conf_site_title) ?> — Secured
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('eyeBtn')?.addEventListener('click', function () {
    const p = document.getElementById('pwd');
    const v = p.type === 'password';
    p.type = v ? 'text' : 'password';
    this.style.color = v ? '#818cf8' : '';
});

//粒子效果
(function () {
    const c = document.getElementById('particles');
    for (let i = 0; i < 30; i++) {
        const s = document.createElement('span');
        s.style.cssText =
            'left:' + Math.random() * 100 + '%;' +
            'animation-duration:' + (6 + Math.random() * 8) + 's;' +
            'animation-delay:' + Math.random() * 6 + 's;' +
            'width:' + (2 + Math.random() * 4) + 'px;' +
            'height:' + (2 + Math.random() * 4) + 'px';
        c.appendChild(s);
    }
})();

// 鼠标光晕
document.getElementById('loginCard').addEventListener('mousemove', function (e) {
    const r = this.getBoundingClientRect();
    this.style.setProperty('--mx', e.clientX - r.left + 'px');
    this.style.setProperty('--my', e.clientY - r.top + 'px');
});
</script>
</body>
</html>
