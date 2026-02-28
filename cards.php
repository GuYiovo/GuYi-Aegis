<?php
/**
 * ╔══════════════════════════════════════════════════════════╗
 * ║  GuYi Network Verification System — V4Remastered      ║
 * ║Award-Grade Admin Control Panel                ║
 * ╚══════════════════════════════════════════════════════════╝
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'database.php';

session_start();

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §1. 数据库连接
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

try {
    $db = new Database();
} catch (Throwable $e) {
    http_response_code(503);
    die('数据库连接失败：' . htmlspecialchars($e->getMessage()));
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §2. 登录鉴权守卫
//未登录 →跳转 login.php
//     IP变更 → 强制重新登录
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

// IP 锁定校验
if (
    isset($_SESSION['admin_logged_in'], $_SESSION['last_ip']) &&
    $_SESSION['last_ip'] !== $_SERVER['REMOTE_ADDR']
) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// 未登录跳转
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §3. CSRF Token
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function verifyCSRF(): void {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Security Alert: CSRF Token Mismatch.');
    }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §4. 全局数据预加载
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

$appList = [];
try { $appList = $db->getApps(); } catch (Throwable $e) { $appList = []; }

$sysConf          = $db->getSystemSettings();
$currentAdminUser = $db->getAdminUsername();

$conf_site_title = $sysConf['site_title']   ?? 'GuYi Access';
$conf_favicon    = $sysConf['favicon']       ?? 'backend/logo.png';
$conf_avatar     = $sysConf['admin_avatar']  ?? 'backend/logo.png';
$conf_bg_pc      = $sysConf['bg_pc']         ?? 'backend/pcpjt.png';
$conf_bg_mobile  = $sysConf['bg_mobile']     ?? 'backend/pjt.png';
$conf_bg_blur    = $sysConf['bg_blur']       ?? '1';

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §5. 批量导出处理（在HTML 输出前处理）
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_export'])) {
    verifyCSRF();
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) {
        echo "<script>alert('请先勾选需要导出的卡密');history.back();</script>";
        exit;
    }
    $data = $db->getCardsByIds($ids);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="cards_export_' . date('YmdHis') . '.txt"');
    foreach ($data as $row) {
        echo $row['card_code'] . "\r\n";
    }
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §6. 登出处理
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

if (isset($_GET['logout'])) {
    session_destroy();
    setcookie('admin_trust', '', time() - 3600, '/');
    header('Location: login.php');
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §7. 路由 & 页面标题
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

$tab = $_GET['tab'] ?? 'dashboard';
$pageTitles = [
    'dashboard' => '仪表盘',
    'apps'=> '应用管理',
    'list'      => '卡密库存',
    'create'    => '批量制卡',
    'logs'      => '审计日志',
    'settings'  => '全局配置',
];
$currentTitle = $pageTitles[$tab] ?? '控制台';
$pageIcons = [
    'dashboard' => 'fa-chart-pie',
    'apps'      => 'fa-cubes',
    'list'      => 'fa-database',
    'create'    => 'fa-wand-magic-sparkles',
    'logs'      => 'fa-shield-halved',
    'settings'  => 'fa-gear',
];
$currentIcon = $pageIcons[$tab] ?? 'fa-circle';

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §8. POST 业务逻辑
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

$msg= '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();

    if (isset($_POST['create_app'])) {
        try {
            $appName = trim($_POST['app_name']);
            if (empty($appName)) throw new Exception("应用名称不能为空");
            $db->createApp(
                htmlspecialchars($appName),
                htmlspecialchars($_POST['app_version'] ?? ''),
                htmlspecialchars($_POST['app_notes'])
            );
            $msg= "应用「" . htmlspecialchars($appName) . "」创建成功";
            $appList = $db->getApps();
        } catch (Exception $e) { $errorMsg = $e->getMessage(); }
    }
    elseif (isset($_POST['toggle_app'])) {
        $db->toggleAppStatus($_POST['app_id']);
        $msg     = "应用状态已切换";$appList = $db->getApps();
    }
    elseif (isset($_POST['delete_app'])) {
        try {
            $db->deleteApp($_POST['app_id']);
            $msg     = "应用已删除";
            $appList = $db->getApps();
        } catch (Exception $e) { $errorMsg = $e->getMessage(); }
    }
    elseif (isset($_POST['edit_app'])) {
        try {
            $appId   = intval($_POST['app_id']);
            $appName = trim($_POST['app_name']);
            if (empty($appName)) throw new Exception("应用名称不能为空");
            $db->updateApp(
                $appId,
                htmlspecialchars($appName),
                htmlspecialchars($_POST['app_version'] ?? ''),
                htmlspecialchars($_POST['app_notes'])
            );
            $msg     = "应用信息已更新";
            $appList = $db->getApps();
        } catch (Exception $e) { $errorMsg = $e->getMessage(); }
    }
    elseif (isset($_POST['add_var'])) {
        try {
            $varAppId = intval($_POST['var_app_id']);
            $varKey   = trim($_POST['var_key']);
            $varVal= trim($_POST['var_value']);
            $varPub   = isset($_POST['var_public']) ? 1 : 0;
            if (empty($varKey)) throw new Exception("变量名不能为空");
            $db->addAppVariable($varAppId, htmlspecialchars($varKey), htmlspecialchars($varVal), $varPub);
            $msg = "变量「" . htmlspecialchars($varKey) . "」添加成功";
        } catch (Exception $e) { $errorMsg = $e->getMessage(); }
    }
    elseif (isset($_POST['edit_var'])) {
        try {
            $varId= intval($_POST['var_id']);
            $varKey = trim($_POST['var_key']);
            $varVal = trim($_POST['var_value']);
            $varPub = isset($_POST['var_public']) ? 1 : 0;
            if (empty($varKey)) throw new Exception("变量名不能为空");
            $db->updateAppVariable($varId, htmlspecialchars($varKey), htmlspecialchars($varVal), $varPub);
            $msg = "变量更新成功";
        } catch (Exception $e) { $errorMsg = $e->getMessage(); }
    }
    elseif (isset($_POST['del_var'])) {
        $db->deleteAppVariable($_POST['var_id']);
        $msg = "变量已删除";
    }
    elseif (isset($_POST['batch_delete'])) {
        $count = $db->batchDeleteCards($_POST['ids'] ?? []);
        $msg= "已批量删除 {$count} 张卡密";
    }
    elseif (isset($_POST['batch_unbind'])) {
        $count = $db->batchUnbindCards($_POST['ids'] ?? []);
        $msg   = "已批量解绑 {$count} 个设备";
    }
    elseif (isset($_POST['batch_add_time'])) {
        $hours = floatval($_POST['add_hours']);
        $count = $db->batchAddTime($_POST['ids'] ?? [], $hours);
        $msg   = "已为 {$count} 张卡密增加 {$hours} 小时";
    }
    elseif (isset($_POST['gen_cards'])) {
        try {
            $targetAppId = intval($_POST['app_id']);
            $db->generateCards(
                $_POST['num'], $_POST['type'], $_POST['pre'],
                '',16, htmlspecialchars($_POST['note']), $targetAppId
            );
            $msg = "成功生成 {$_POST['num']} 张卡密";
        } catch (Exception $e) { $errorMsg = "生成失败: " . $e->getMessage(); }
    }
    elseif (isset($_POST['del_card'])) {
        $db->deleteCard($_POST['id']);
        $msg = "卡密已删除";
    }
    elseif (isset($_POST['unbind_card'])) {
        $res = $db->resetDeviceBindingByCardId($_POST['id']);
        $msg = $res ? "设备解绑成功" : "解绑失败";
    }
    elseif (isset($_POST['ban_card'])) {
        $db->updateCardStatus($_POST['id'], 2);
        $msg = "卡密已封禁";
    }
    elseif (isset($_POST['unban_card'])) {
        $db->updateCardStatus($_POST['id'], 1);
        $msg = "卡密已解除封禁";
    }
    elseif (isset($_POST['update_pwd'])) {
        $pwd1 = $_POST['new_pwd']?? '';
        $pwd2 = $_POST['confirm_pwd'] ?? '';
        if (empty($pwd1))$errorMsg = "密码不能为空";
        elseif ($pwd1 !== $pwd2) $errorMsg = "两次输入的密码不一致";
        else {
            $db->updateAdminPassword($pwd1);
            setcookie('admin_trust', '', time() - 3600, '/');
            $msg = "管理员密码已更新，信任设备需重新登录";
        }
    }
    elseif (isset($_POST['update_settings'])) {
        try {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $processUpload = function ($inputName, $existingValue) use ($uploadDir) {
                if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','gif','webp','ico','svg'])) {
                        $filename = $inputName . '_' . time() . '.' . $ext;
                        move_uploaded_file($_FILES[$inputName]['tmp_name'], $uploadDir . $filename);return $uploadDir . $filename;
                    }
                }
                return $existingValue;
            };

            $settingsData = [
                'site_title'   => trim($_POST['site_title']),
                'favicon'      => $processUpload('favicon_file',trim($_POST['favicon'])),
                'admin_avatar' => $processUpload('admin_avatar_file', trim($_POST['admin_avatar'])),
                'bg_pc'        => $processUpload('bg_pc_file',        trim($_POST['bg_pc'])),
                'bg_mobile'    => $processUpload('bg_mobile_file',    trim($_POST['bg_mobile'])),
                'bg_blur'      => isset($_POST['bg_blur']) ? '1' : '0',];
            $db->saveSystemSettings($settingsData);

            $newUsername = trim($_POST['admin_username']);
            if (!empty($newUsername)) $db->updateAdminUsername($newUsername);

            $msg = "系统配置已保存";
            echo "<script>alert('{$msg}');location.href='cards.php?tab=settings';</script>";exit;
        } catch (Exception $e) { $errorMsg = "保存失败: " . $e->getMessage(); }
    }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §9. 数据查询
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

$dashboardData = ['stats' => ['total' => 0, 'unused' => 0, 'active' => 0], 'app_stats' => [], 'chart_types' => []];
$logs= [];
$activeDevices = [];
$cardList      = [];
$totalCards    = 0;
$totalPages    = 0;

try { $dashboardData = $db->getDashboardData(); } catch (Throwable $e) { if (empty($errorMsg)) $errorMsg = "仪表盘数据加载失败"; }
try { $logs          = $db->getUsageLogs(20, 0); } catch (Throwable $e) {}
try { $activeDevices = $db->getActiveDevices();} catch (Throwable $e) {}

$page= max(1, intval($_GET['page']?? 1));
$perPage = max(5, min(500, intval($_GET['limit'] ?? 20)));

$statusFilter = null;
$filterStr    = $_GET['filter'] ?? 'all';
if ($filterStr === 'unused') $statusFilter = 0;
elseif ($filterStr === 'active') $statusFilter = 1;
elseif ($filterStr === 'banned') $statusFilter = 2;

$appFilter= isset($_GET['app_id']) && $_GET['app_id'] !== '' ? intval($_GET['app_id']) : null;
$isSearching = isset($_GET['q']) && !empty(trim($_GET['q']));
$offset= ($page - 1) * $perPage;

try {
    if ($isSearching) {
        $allResults = $db->searchCards($_GET['q']);$totalCards = count($allResults);$cardList   = array_slice($allResults, $offset, $perPage);
    } elseif ($appFilter !== null) {
        $totalCards = $db->getTotalCardCount($statusFilter, $appFilter);
        $cardList   = $db->getCardsPaginated($perPage, $offset, $statusFilter, $appFilter);
    }
} catch (Throwable $e) { if (empty($errorMsg)) $errorMsg = "卡密列表加载失败"; }

$totalPages = ceil($totalCards / max(1, $perPage));
if ($totalPages > 0&& $page > $totalPages) $page = $totalPages;

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// §10. 辅助变量
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

$protocol= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$currentScriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$apiUrl          = $protocol . '://' . $_SERVER['HTTP_HOST'] . $currentScriptDir . '/Verifyfile/api.php';
$blur_val_admin  = ($conf_bg_blur === '1') ? '20px' : '0px';
$statTotal       = $dashboardData['stats']['total']?? 0;
$statActive      = $dashboardData['stats']['active'] ?? 0;
$statUnused      = $dashboardData['stats']['unused'] ?? 0;
$statApps        = count($appList);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?= htmlspecialchars($conf_site_title) ?> — <?= $currentTitle ?></title>
<link rel="icon" href="<?= htmlspecialchars($conf_favicon) ?>" type="image/png">
<link href="assets/css/all.min.css" rel="stylesheet">
<script src="assets/js/chart.js"></script>
<style>
/* ═══════════════════════════════════════════════════════════V4 DESIGN SYSTEM
   ═══════════════════════════════════════════════════════════ */

:root {
    --c-bg:#f0f2f7;
    --c-surface:rgba(255,255,255,.72);
    --c-surface-solid:#ffffff;
    --c-surface-hover:rgba(255,255,255,.88);
    --c-border:rgba(0,0,0,.06);
    --c-border-active:rgba(99,102,241,.3);
    --c-shadow:rgba(0,0,0,.04);
    --c-shadow-lg:rgba(0,0,0,.08);
    --c-text:#1a1a2e;
    --c-text-secondary:#64748b;
    --c-text-muted:#94a3b8;
    --c-primary:#6366f1;
    --c-primary-hover:#4f46e5;
    --c-primary-light:rgba(99,102,241,.08);
    --c-primary-glow:rgba(99,102,241,.25);
    --c-success:#10b981;
    --c-success-light:rgba(16,185,129,.1);
    --c-danger:#f43f5e;
    --c-danger-light:rgba(244,63,94,.08);
    --c-warning:#f59e0b;
    --c-warning-light:rgba(245,158,11,.1);
    --c-info:#3b82f6;
    --c-info-light:rgba(59,130,246,.1);
    --radius-sm:10px;
    --radius-md:16px;
    --radius-lg:24px;
    --radius-xl:28px;
    --header-h:72px;
    --ease:cubic-bezier(.4,0,.2,1);
    --ease-spring:cubic-bezier(.34,1.56,.64,1);
    --ease-out:cubic-bezier(.16,1,.3,1);
}

/* ─── Sidebar Variables ─── */
:root {
    --sb-w:272px;
    --sb-w-collapsed:78px;
    --sb-bg:rgba(10,10,20,.88);
    --sb-glass:blur(50px) saturate(200%);
    --sb-text:rgba(255,255,255,.5);
    --sb-text-hover:rgba(255,255,255,.85);
    --sb-text-active:#fff;
    --sb-accent:#818cf8;
    --sb-accent-glow:rgba(129,140,248,.4);
    --sb-divider:rgba(255,255,255,.04);
    --sb-item-radius:14px;
    --sb-item-h:44px;
    --sb-transition:cubic-bezier(.4,0,.2,1);
}

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0 }
html { -webkit-font-smoothing:antialiased }
body {
    font-family:'Inter',system-ui,-apple-system,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;
    color:var(--c-text); display:flex; height:100vh; overflow:hidden;
    background:url('<?= htmlspecialchars($conf_bg_pc) ?>') center/cover fixed no-repeat var(--c-bg);
}
body::before {
    content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
    background:rgba(240,242,247,.75);backdrop-filter:blur(<?= $blur_val_admin ?>) saturate(120%);
    -webkit-backdrop-filter:blur(<?= $blur_val_admin ?>) saturate(120%);
}
@media(max-width:768px){
    body { background-image:url('<?= htmlspecialchars($conf_bg_mobile) ?>')!important }
}

::-webkit-scrollbar { width:6px; height:6px }
::-webkit-scrollbar-track { background:transparent }
::-webkit-scrollbar-thumb { background:rgba(0,0,0,.1); border-radius:99px }
::-webkit-scrollbar-thumb:hover { background:rgba(0,0,0,.2) }

/* ─── Sidebar─── */
.sidebar {
    width:var(--sb-w); flex-shrink:0; position:relative; z-index:100;
    display:flex; flex-direction:column; background:var(--sb-bg);
    backdrop-filter:var(--sb-glass); -webkit-backdrop-filter:var(--sb-glass);
    border-right:1px solid rgba(255,255,255,.04);
    transition:width .35s var(--sb-transition),transform .35s var(--sb-transition);
    overflow:hidden;
}
.sidebar.collapsed { width:var(--sb-w-collapsed) }
.sidebar.collapsed .sb-label,
.sidebar.collapsed .sb-link-text,
.sidebar.collapsed .sb-brand-text,
.sidebar.collapsed .sb-user-info,
.sidebar.collapsed .sb-collapse-text,
.sidebar.collapsed .sb-badge { opacity:0; width:0; overflow:hidden }
.sidebar.collapsed .sb-brand { padding:0 16px; justify-content:center }
.sidebar.collapsed .sb-brand-icon { margin:0 }
.sidebar.collapsed .sb-nav { padding:16px 12px }
.sidebar.collapsed .sb-link {
    padding:0; width:var(--sb-item-h); height:var(--sb-item-h);
    justify-content:center; margin:0 auto4px;
}
.sidebar.collapsed .sb-link-icon { margin:0; font-size:18px }
.sidebar.collapsed .sb-user { padding:12px; justify-content:center }
.sidebar.collapsed .sb-avatar { margin:0 }
.sidebar.collapsed .sb-logout { margin:4px auto 0 }
.sidebar.collapsed .sb-collapse-btn { justify-content:center; padding:0 16px }
.sidebar.collapsed .sb-collapse-btn i { transform:rotate(180deg) }

/* Collapsed tooltips */
.sidebar.collapsed .sb-link { position:relative }
.sidebar.collapsed .sb-link::after {
    content:attr(data-tooltip); position:absolute;
    left:calc(100% + 12px); top:50%;
    transform:translateY(-50%) scale(.9);
    background:rgba(15,15,30,.95); color:#fff;
    padding:6px 14px; border-radius:10px; font-size:12px; font-weight:600;
    white-space:nowrap; opacity:0; pointer-events:none;
    transition:all .2s var(--sb-transition);
    box-shadow:0 8px 24px rgba(0,0,0,.3);
    border:1px solid rgba(255,255,255,.08); z-index:1001;
}
.sidebar.collapsed .sb-link:hover::after {
    opacity:1; transform:translateY(-50%) scale(1);
}

/* Brand */
.sb-brand {
    height:72px; padding:0 22px; display:flex; align-items:center;
    gap:14px; flex-shrink:0; position:relative;
    transition:all .3s var(--sb-transition);
}
.sb-brand::after {
    content:''; position:absolute; bottom:0; left:20px; right:20px;
    height:1px; background:linear-gradient(90deg,transparent,rgba(255,255,255,.06),transparent);
}
.sb-brand-icon {
    width:38px; height:38px; border-radius:12px; flex-shrink:0;
    position:relative; overflow:hidden;
    box-shadow:0 0 20px rgba(99,102,241,.2);
    transition:all .3s var(--sb-transition);
}
.sb-brand-icon img { width:100%; height:100%; object-fit:cover; border-radius:inherit }
.sb-brand-icon::before {
    content:''; position:absolute; inset:-1px; border-radius:inherit; padding:1.5px;
    background:conic-gradient(from 180deg,var(--sb-accent),transparent40%,transparent 60%,var(--sb-accent));
    -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
    -webkit-mask-composite:xor; mask-composite:exclude;
    animation:brandSpin 6s linear infinite;
}
@keyframes brandSpin { to { transform:rotate(360deg) } }
.sb-brand-text { display:flex; flex-direction:column; transition:opacity .2s }
.sb-brand-name { color:#fff; font-size:15px; font-weight:700; line-height:1.2; letter-spacing:-.3px }
.sb-brand-sub{ color:rgba(255,255,255,.25); font-size:10px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; margin-top:3px }

/* Navigation */
.sb-nav {
    flex:1; overflow-y:auto; overflow-x:hidden; padding:20px 16px;
    transition:padding .3s var(--sb-transition);
    scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.08) transparent;
}
.sb-nav::-webkit-scrollbar { width:3px }
.sb-nav::-webkit-scrollbar-thumb { background:rgba(255,255,255,.1); border-radius:99px }
.sb-label {
    font-size:10px; text-transform:uppercase; color:rgba(255,255,255,.2);
    font-weight:700; letter-spacing:1.5px; padding:20px 14px 10px;
    transition:opacity .2s; user-select:none;
}
.sb-label:first-child { padding-top:4px }
.sb-link {
    display:flex; align-items:center; height:var(--sb-item-h); padding:0 14px;
    color:var(--sb-text); text-decoration:none; border-radius:var(--sb-item-radius);
    font-size:13.5px; font-weight:500; margin-bottom:2px;
    transition:all .25s var(--sb-transition); position:relative; overflow:hidden; cursor:pointer;
}
.sb-link::before {
    content:''; position:absolute; inset:0; border-radius:inherit; opacity:0;
    transition:opacity .25s;background:linear-gradient(135deg,rgba(255,255,255,.06),rgba(255,255,255,.02));
}
.sb-link:hover::before { opacity:1 }
.sb-link:hover { color:var(--sb-text-hover); transform:translateX(2px) }
.sb-link.active {
    color:var(--sb-text-active); font-weight:600;
    background:linear-gradient(135deg,rgba(129,140,248,.12),rgba(129,140,248,.04));
}
.sb-link.active::before { opacity:0 }
.sb-link.active::after {
    content:''; position:absolute; left:0; top:8px; bottom:8px; width:3px;
    background:var(--sb-accent); border-radius:0 6px 6px 0;
    box-shadow:0 0 12px var(--sb-accent-glow),0 0 4px var(--sb-accent);animation:indicatorPulse 2s ease-in-out infinite;
}
@keyframes indicatorPulse {
    0%,100% { box-shadow:0 0 12px var(--sb-accent-glow),0 0 4px var(--sb-accent) }
    50%{ box-shadow:0 0 20px var(--sb-accent-glow),0 0 8px var(--sb-accent) }
}
.sb-link-icon {
    width:20px; height:20px; display:flex; align-items:center; justify-content:center;
    margin-right:12px; font-size:15px; flex-shrink:0;
    transition:all .25s var(--sb-transition); position:relative;
}
.sb-link:hover .sb-link-icon { transform:scale(1.1) }
.sb-link.active .sb-link-icon { color:var(--sb-accent); filter:drop-shadow(0 0 6px var(--sb-accent-glow)) }
.sb-link-text { white-space:nowrap; transition:opacity .15s; flex:1 }
.sb-badge {
    margin-left:auto; padding:2px 8px; border-radius:99px; font-size:10px; font-weight:700;
    background:rgba(129,140,248,.15); color:var(--sb-accent);
    transition:opacity .15s; min-width:20px; text-align:center;
}
.sb-divider {
    height:1px; margin:12px 14px;
    background:linear-gradient(90deg,transparent,var(--sb-divider),rgba(255,255,255,.08),var(--sb-divider),transparent);
}
.sb-link-glow {
    position:absolute; inset:0; border-radius:inherit; opacity:0; transition:opacity .3s;
    background:radial-gradient(circle at var(--glow-x,50%) var(--glow-y,50%),rgba(129,140,248,.08),transparent60%);
    pointer-events:none;
}
.sb-link:hover .sb-link-glow { opacity:1 }

/* Collapse Toggle */
.sb-collapse-btn {
    display:flex; align-items:center; gap:10px; padding:0 22px; height:48px;
    color:rgba(255,255,255,.3); font-size:12px; font-weight:600;
    cursor:pointer; transition:all .2s; border:none; background:none;
    width:100%; border-top:1px solid var(--sb-divider);
}
.sb-collapse-btn:hover { color:rgba(255,255,255,.6) }
.sb-collapse-btn i { font-size:14px; transition:transform .3s var(--sb-transition) }

/* User Panel */
.sb-user {
    margin:12px 14px; padding:14px 16px;
    background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.04);
    border-radius:var(--sb-item-radius); display:flex; align-items:center; gap:12px;
    transition:all .3s var(--sb-transition); position:relative; overflow:hidden;
}
.sb-user::before {
    content:''; position:absolute; inset:0; border-radius:inherit; opacity:0;
    background:linear-gradient(135deg,rgba(129,140,248,.05),transparent);
    transition:opacity .3s;
}
.sb-user:hover::before { opacity:1 }
.sb-avatar {
    width:34px; height:34px; border-radius:10px; object-fit:cover;
    border:1.5px solid rgba(255,255,255,.08); flex-shrink:0;
    transition:all .3s var(--sb-transition);
}
.sb-user-info { display:flex; flex-direction:column; transition:opacity .15s; flex:1; min-width:0 }
.sb-uname { font-size:13px; color:#fff; font-weight:600; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
.sb-urole {
    font-size:10px; color:rgba(255,255,255,.3); font-weight:500;
    display:flex; align-items:center; gap:4px; margin-top:2px;
}
.sb-urole::before {
    content:''; width:5px; height:5px; border-radius:50%;
    background:#10b981; box-shadow:0 0 6px rgba(16,185,129,.5);
    animation:onlinePulse 2s ease-in-out infinite;
}
@keyframes onlinePulse { 0%,100% { opacity:1 } 50% { opacity:.5 } }
.sb-logout {
    color:rgba(255,255,255,.3); cursor:pointer; padding:8px; border-radius:10px;
    transition:all .2s; text-decoration:none;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.sb-logout:hover { color:#f43f5e; background:rgba(244,63,94,.1); transform:scale(1.05) }

.sb-overlay {
    display:none; position:fixed; inset:0; z-index:99;
    background:rgba(0,0,0,.45);
    backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px);
}
.sb-overlay.show { display:block }

/*─── Main Layout ─── */
.main { flex:1; display:flex; flex-direction:column; overflow:hidden; position:relative; z-index:1}
.top-bar {
    height:var(--header-h); padding:0 32px;
    display:flex; align-items:center; justify-content:space-between;
    flex-shrink:0; gap:16px;
}
.top-left{ display:flex; align-items:center; gap:12px }
.menu-btn{ display:none; background:none; border:none; font-size:20px; color:var(--c-text); cursor:pointer; padding:8px }
.top-title { font-size:22px; font-weight:800; color:var(--c-text); letter-spacing:-.5px; display:flex; align-items:center; gap:10px }
.top-title i { font-size:18px; color:var(--c-primary); opacity:.8 }
.top-right { display:flex; align-items:center; gap:10px }
.breadcrumb { padding:0 32px 8px; font-size:12px; color:var(--c-text-muted); display:flex; align-items:center; gap:6px; font-weight:500 }
.breadcrumb i { font-size:8px; opacity:.4 }
.content { flex:1; overflow-y:auto; padding:0 32px 40px; scroll-behavior:smooth }

/* ─── Cards ─── */
.card {
    background:var(--c-surface);
    backdrop-filter:blur(20px) saturate(120%); -webkit-backdrop-filter:blur(20px) saturate(120%);
    border:1px solid var(--c-border); border-radius:var(--radius-lg);
    box-shadow:0 4px 24px var(--c-shadow); transition:all .3s var(--ease);
    overflow:hidden; margin-bottom:24px;
}
.card:hover { background:var(--c-surface-hover); box-shadow:0 12px 40px var(--c-shadow-lg); border-color:rgba(0,0,0,.08) }
.card-head {
    padding:20px 28px; display:flex; justify-content:space-between;
    align-items:center; border-bottom:1px solid var(--c-border);
    flex-wrap:wrap; gap:12px;
}
.card-title { font-size:15px; font-weight:700; color:var(--c-text); display:flex; align-items:center; gap:8px }
.card-title i { color:var(--c-primary); font-size:14px }
.card-body { padding:24px 28px }

/* ─── Stats ─── */
.stats { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:28px }
.stat {
    background:var(--c-surface); backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
    border:1px solid var(--c-border); border-radius:var(--radius-lg);
    padding:24px; position:relative; overflow:hidden;
    transition:all .3s var(--ease); cursor:default;
}
.stat:hover { transform:translateY(-4px); box-shadow:0 16px 40px var(--c-shadow-lg) }
.stat-label { font-size:12px; font-weight:700; color:var(--c-text-muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px }
.stat-value { font-size:36px; font-weight:800; color:var(--c-text); letter-spacing:-2px; line-height:1 }
.stat-icon {
    position:absolute; right:20px; top:50%; transform:translateY(-50%);
    width:52px; height:52px; border-radius:14px;
    display:flex; align-items:center; justify-content:center; font-size:22px;
}
.stat:nth-child(1) .stat-icon { background:linear-gradient(135deg,#dbeafe,#bfdbfe); color:#2563eb }
.stat:nth-child(2) .stat-icon { background:linear-gradient(135deg,#d1fae5,#a7f3d0); color:#059669 }
.stat:nth-child(3) .stat-icon { background:linear-gradient(135deg,#ede9fe,#ddd6fe); color:#7c3aed }
.stat:nth-child(4) .stat-icon { background:linear-gradient(135deg,#ffedd5,#fed7aa); color:#ea580c }
.stat::after { content:''; position:absolute; bottom:0; left:0; right:0; height:3px; border-radius:0 0 var(--radius-lg) var(--radius-lg) }
.stat:nth-child(1)::after { background:linear-gradient(90deg,#3b82f6,#60a5fa) }
.stat:nth-child(2)::after { background:linear-gradient(90deg,#10b981,#34d399) }
.stat:nth-child(3)::after { background:linear-gradient(90deg,#8b5cf6,#a78bfa) }
.stat:nth-child(4)::after { background:linear-gradient(90deg,#f59e0b,#fbbf24) }

/* ─── Tables ─── */
.table-wrap { width:100%; overflow-x:auto }
table { width:100%; border-collapse:collapse; font-size:13px; white-space:nowrap }
th {
    text-align:left; padding:14px 24px;
    background:rgba(248,250,252,.5); color:var(--c-text-muted);
    font-weight:700; text-transform:uppercase; font-size:11px; letter-spacing:.8px;
    border-bottom:1px solid var(--c-border);
}
td { padding:16px 24px; border-bottom:1px solid var(--c-border); color:var(--c-text); vertical-align:middle; transition:background .2s }
tr:last-child td { border-bottom:none }
tr:hover td { background:rgba(99,102,241,.02) }

/* ─── Badges ─── */
.badge { display:inline-flex; align-items:center; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:700; line-height:1.4; letter-spacing:.3px; gap:4px }
.badge-success { background:var(--c-success-light); color:#047857 }
.badge-danger{ background:var(--c-danger-light);  color:#be123c }
.badge-warn    { background:var(--c-warning-light); color:#b45309 }
.badge-neutral { background:rgba(148,163,184,.12);  color:#475569 }
.badge-primary { background:var(--c-primary-light); color:#4338ca }
.badge-info    { background:var(--c-info-light);    color:#1d4ed8 }
.badge::before { content:''; width:6px; height:6px; border-radius:50% }
.badge-success::before { background:#10b981 }
.badge-danger::before  { background:#f43f5e }
.badge-warn::before    { background:#f59e0b }
.badge-neutral::before { background:#94a3b8 }
.badge-primary::before { background:#6366f1 }

/* ─── Buttons ─── */
.btn {
    display:inline-flex; align-items:center; justify-content:center; gap:6px;
    padding:10px 20px; border-radius:var(--radius-md); font-size:13px; font-weight:600;
    cursor:pointer; transition:all .2s var(--ease); border:1px solid transparent;
    text-decoration:none; line-height:1.4;
}
.btn:hover  { transform:translateY(-1px) }
.btn:active { transform:translateY(0) scale(.98) }
.btn-primary{ background:linear-gradient(135deg,#6366f1,#4f46e5); color:#fff; box-shadow:0 4px 16px var(--c-primary-glow) }
.btn-primary:hover { box-shadow:0 8px 24px var(--c-primary-glow) }
.btn-secondary { background:var(--c-surface-solid); color:var(--c-text-secondary); border-color:var(--c-border) }
.btn-secondary:hover { background:#f8fafc; border-color:rgba(0,0,0,.1) }
.btn-danger    { background:var(--c-danger-light);  color:#be123c; border-color:rgba(244,63,94,.15) }
.btn-danger:hover { background:rgba(244,63,94,.15); box-shadow:0 4px 12px rgba(244,63,94,.15) }
.btn-warning   { background:var(--c-warning-light); color:#b45309; border-color:rgba(245,158,11,.15) }
.btn-success   { background:var(--c-success-light); color:#047857; border-color:rgba(16,185,129,.15) }
.btn-ghost     { background:transparent; color:var(--c-text-muted); border:none; padding:8px }
.btn-ghost:hover { background:var(--c-primary-light); color:var(--c-primary) }
.btn-icon{ padding:8px; min-width:36px; height:36px }
.btn-group     { display:flex; gap:6px; flex-wrap:wrap }

/* ─── Forms ─── */
.form-group{ margin-bottom:18px }
.form-label  { display:block; font-size:13px; font-weight:600; color:var(--c-text-secondary); margin-bottom:8px }
.form-label i { color:var(--c-primary); margin-right:4px }
.form-input {
    width:100%; padding:12px16px; border:1.5px solid var(--c-border);
    border-radius:var(--radius-md); font-size:14px; color:var(--c-text);
    background:var(--c-surface); transition:all .25s var(--ease); -webkit-appearance:none;
}
.form-input:focus { outline:none; border-color:var(--c-primary); box-shadow:0 0 0 4px var(--c-primary-light); background:var(--c-surface-solid) }
.form-input::placeholder { color:var(--c-text-muted) }
select.form-input { cursor:pointer }
textarea.form-input { resize:vertical; min-height:80px }
.code {
    font-family:'JetBrains Mono',monospace; background:var(--c-surface-solid);
    padding:6px 12px; border-radius:8px; font-size:12px; color:var(--c-text);
    border:1px solid var(--c-border); cursor:pointer; transition:all .2s;
    font-weight:500; display:inline-flex; align-items:center; gap:6px;
}
.code:hover { border-color:var(--c-primary); color:var(--c-primary); box-shadow:0 2px 8px var(--c-primary-light) }
.code i { font-size:10px; opacity:.5 }
.key-box {
    display:inline-flex; align-items:center; gap:8px;
    font-family:'JetBrains Mono',monospace; font-size:11px;
    background:rgba(248,250,252,.8); padding:6px 12px; border-radius:8px;
    border:1px solid var(--c-border); color:var(--c-text-secondary);
    cursor:pointer; transition:all .2s;
}
.key-box:hover { border-color:var(--c-primary); color:var(--c-primary) }
.key-box i { font-size:10px; opacity:.5 }

/* ─── Toast─── */
.toast {
    position:fixed; bottom:30px; right:30px; z-index:9999;
    display:flex; align-items:center; gap:10px;
    background:var(--c-surface-solid); border:1px solid var(--c-border);
    padding:14px 24px; border-radius:var(--radius-md);
    box-shadow:0 12px 40px rgba(0,0,0,.12); font-size:14px; font-weight:500;
    opacity:0; transform:translateY(20px); transition:all .35s var(--ease-spring);
}
.toast.show { opacity:1; transform:translateY(0) }
.toast i { color:var(--c-success); font-size:18px }

/* ─── Message Pills ─── */
.msg-pill {
    font-size:13px; padding:8px 16px; border-radius:var(--radius-sm);
    font-weight:600; display:flex; align-items:center; gap:6px;
    animation:fadeSlide .4s var(--ease-out);
}
@keyframes fadeSlide { from { opacity:0; transform:translateX(10px) } to { opacity:1; transform:translateX(0) } }
.msg-success { color:#059669; background:var(--c-success-light) }
.msg-error   { color:#dc2626; background:var(--c-danger-light) }

/* ─── Modal ─── */
.modal { display:none; position:fixed; inset:0; z-index:2000; align-items:center; justify-content:center }
.modal.show { display:flex }
.modal-bg { position:absolute; inset:0; background:rgba(15,23,42,.25); backdrop-filter:blur(8px) }
.modal-box {
    position:relative; width:90%; max-width:440px; padding:32px;
    background:var(--c-surface-solid); backdrop-filter:blur(25px);
    border:1px solid var(--c-border); border-radius:var(--radius-xl);
    box-shadow:0 25px 60px rgba(0,0,0,.2); animation:modalPop .35s var(--ease-spring);
}
@keyframes modalPop { from { opacity:0; transform:scale(.92) translateY(10px) } to { opacity:1; transform:scale(1) translateY(0) } }
.modal-title { font-size:18px; font-weight:800; margin-bottom:24px; color:var(--c-text) }

/* ─── Tab Pills ─── */
.pill-tabs {
    display:inline-flex; background:var(--c-surface); border:1px solid var(--c-border);
    border-radius:var(--radius-md); padding:4px; gap:2px;
    margin-bottom:24px; overflow-x:auto; width:100%;
}
.pill {
    flex:1; padding:10px 20px; border-radius:var(--radius-sm); font-size:13px; font-weight:600;
    color:var(--c-text-muted); background:transparent; border:none; cursor:pointer;
    transition:all .2s; white-space:nowrap; text-decoration:none; text-align:center; min-width:max-content;
}
.pill:hover  { color:var(--c-text); background:rgba(99,102,241,.04) }
.pill.active { background:var(--c-surface-solid); color:var(--c-primary); box-shadow:0 2px 8px var(--c-shadow) }

/* ─── Dashboard ─── */
.welcome-card { background:linear-gradient(135deg,rgba(99,102,241,.06),rgba(59,130,246,.04)); border:1px solid rgba(99,102,241,.1); position:relative; overflow:hidden }
.welcome-card::before {
    content:''; position:absolute; top:-50%; right:-20%; width:300px; height:300px;
    background:radial-gradient(circle,rgba(99,102,241,.08),transparent 70%);
    border-radius:50%; pointer-events:none;
}
.welcome-inner { padding:28px; display:flex; gap:20px; align-items:flex-start; position:relative; z-index:1 }
.welcome-icon {
    width:52px; height:52px; border-radius:16px; flex-shrink:0;
    background:linear-gradient(135deg,#6366f1,#8b5cf6);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:22px; box-shadow:0 8px 24px rgba(99,102,241,.3);
}
.welcome-title { font-size:18px; font-weight:800; color:var(--c-text); margin-bottom:6px }
.welcome-desc{ font-size:13px; color:var(--c-text-secondary); line-height:1.6 }
.version-badge { font-size:10px; background:var(--c-primary); color:#fff; padding:4px 10px; border-radius:20px; font-weight:700; letter-spacing:.5px }
.split-grid{ display:grid; grid-template-columns:2fr 1fr; gap:24px; margin-bottom:24px }
.progress{ height:6px; background:rgba(0,0,0,.04); border-radius:3px; overflow:hidden; flex:1; min-width:60px }
.progress-bar{ height:100%; border-radius:3px; background:linear-gradient(90deg,var(--c-primary),#818cf8); transition:width .6s var(--ease) }

/* ─── Create─── */
.create-wrap { max-width:960px; margin:0 auto }
.create-grid { display:grid; grid-template-columns:3fr 2fr; gap:32px; padding:28px }
.create-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px }
.full-span { grid-column:span 2 }
.big-btn { grid-column:span 2; padding:16px; font-size:15px; margin-top:8px; box-shadow:0 8px 24px var(--c-primary-glow) }
.tip-box { background:linear-gradient(145deg,#f8fafc,#fff); border-radius:var(--radius-lg); padding:24px; border:1px solid var(--c-border); display:flex; flex-direction:column; gap:16px }
.tip-item { background:var(--c-primary-light); padding:16px; border-radius:12px; border-left:3px solid var(--c-primary) }
.tip-item h4 { font-size:14px; font-weight:700; margin-bottom:6px; color:var(--c-text) }
.tip-item p  { font-size:12px; color:var(--c-text-secondary); line-height:1.5 }

/* ─── Settings ─── */
.settings-wrap { max-width:960px; margin:0 auto }
.settings-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px }
.setting-block { background:rgba(255,255,255,.5); border:1px solid var(--c-border); border-radius:var(--radius-lg); padding:24px }
.setting-block-title { font-size:15px; font-weight:700; color:var(--c-text); margin-bottom:20px; display:flex; align-items:center; gap:8px; padding-bottom:14px; border-bottom:1px solid var(--c-border) }
.setting-block-title i { color:var(--c-primary) }
.file-group { position:relative; display:flex; align-items:center }
.file-group .form-input { padding-right:44px }
.file-upload-btn {
    position:absolute; right:6px; top:50%; transform:translateY(-50%);
    width:32px; height:32px; border-radius:8px; background:var(--c-primary-light);
    color:var(--c-primary); display:flex; align-items:center; justify-content:center;
    cursor:pointer; transition:all .2s; font-size:14px;
}
.file-upload-btn:hover { background:var(--c-primary); color:#fff }
.file-hidden { display:none!important }
.file-status { margin-top:4px; font-size:11px; color:var(--c-text-muted); display:flex; align-items:center; gap:4px }
.file-status i { color:var(--c-success) }
.toggle-row {
    display:flex; align-items:center; gap:10px; margin-top:16px;
    background:var(--c-surface); padding:14px 16px;
    border-radius:var(--radius-sm); border:1px solid var(--c-border);
}
.toggle-row input[type="checkbox"] { width:18px; height:18px; accent-color:var(--c-primary); cursor:pointer }
.toggle-row label { cursor:pointer; font-size:13px; font-weight:600; color:var(--c-text) }
.pwd-wrap { position:relative }
.pwd-wrap .form-input { padding-right:40px }
.pwd-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); cursor:pointer; color:var(--c-text-muted); padding:4px; transition:color .2s }
.pwd-toggle:hover { color:var(--c-primary) }

/* ─── Pagination ─── */
.pagination { padding:20px 24px; display:flex; align-items:center; justify-content:flex-end; gap:6px; flex-wrap:wrap; border-top:1px solid var(--c-border) }
.page-btn {
    min-width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center;
    border-radius:var(--radius-sm); font-size:13px; font-weight:600;
    color:var(--c-text-secondary); background:var(--c-surface); border:1px solid var(--c-border);
    cursor:pointer; transition:all .2s; text-decoration:none;
}
.page-btn:hover { background:var(--c-surface-solid); border-color:var(--c-primary); color:var(--c-primary) }
.page-btn.active { background:var(--c-primary); color:#fff; border-color:var(--c-primary); box-shadow:0 4px 12px var(--c-primary-glow) }

/* ─── Animations ─── */
@keyframes fadeIn { from { opacity:0; transform:translateY(12px) } to { opacity:1; transform:translateY(0) } }
.animate-in { animation:fadeIn .5s var(--ease-out) both }
.delay-1 { animation-delay:.05s }
.delay-2 { animation-delay:.1s }
.delay-3 { animation-delay:.15s }
.delay-4 { animation-delay:.2s }

details.card > summary { list-style:none; cursor:pointer; user-select:none }
details.card > summary::-webkit-details-marker { display:none }
details.card > summary .card-head { transition:background .2s }
details.card > summary:hover .card-head { background:rgba(99,102,241,.02) }

/* ─── Responsive ─── */
@media(max-width:1024px) { .stats { grid-template-columns:repeat(2,1fr) } }
@media(max-width:768px) {
    .sidebar {
        position:fixed; top:0; left:0; height:100%;
        width:280px!important; transform:translateX(-100%);
        z-index:1000; box-shadow:10px 0 40px rgba(0,0,0,.2);
    }
    .sidebar.open { transform:translateX(0) }
    .sidebar.collapsed { width:280px!important }
    .sb-collapse-btn { display:none }
    .sidebar.collapsed .sb-label,
    .sidebar.collapsed .sb-link-text,
    .sidebar.collapsed .sb-brand-text,
    .sidebar.collapsed .sb-user-info,
    .sidebar.collapsed .sb-badge { opacity:1; width:auto; overflow:visible }
    .sidebar.collapsed .sb-brand { padding:0 22px; justify-content:flex-start }
    .sidebar.collapsed .sb-nav { padding:20px 16px }
    .sidebar.collapsed .sb-link { padding:0 14px; width:auto; height:var(--sb-item-h); justify-content:flex-start; margin:0 0 2px }
    .sidebar.collapsed .sb-link-icon { margin-right:12px; font-size:15px }
    .sidebar.collapsed .sb-user { padding:14px 16px; justify-content:flex-start }
    .menu-btn { display:block }
    .top-bar { padding:0 20px }
    .breadcrumb { padding:0 20px 8px }
    .content { padding:0 16px 80px }
    .stats { grid-template-columns:1fr; gap:12px }
    .split-grid { grid-template-columns:1fr!important }
    .create-grid { grid-template-columns:1fr; padding:20px }
    .create-form-grid { grid-template-columns:1fr }
    .full-span,.big-btn { grid-column:span 1 }
    .settings-grid { grid-template-columns:1fr }
    .card-head { flex-direction:column; align-items:stretch }
    .stat-value { font-size:28px }
    .stat-icon { width:44px; height:44px; font-size:18px }
    th,td { padding:12px 16px }
}
</style>
</head>
<body>

<!-- Sidebar Overlay -->
<div class="sb-overlay" onclick="toggleSidebar()"></div>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <div class="sb-brand-icon">
            <img src="<?= htmlspecialchars($conf_avatar) ?>" alt="Logo">
        </div>
        <div class="sb-brand-text">
            <span class="sb-brand-name"><?= htmlspecialchars(mb_strimwidth($conf_site_title, 0, 16, '..')) ?></span>
            <span class="sb-brand-sub">Control Panel</span>
        </div>
    </div>

    <nav class="sb-nav">
        <div class="sb-label">概览</div>
        <a href="?tab=dashboard" class="sb-link <?= $tab === 'dashboard' ? 'active' : '' ?>" data-tooltip="仪表盘">
            <div class="sb-link-glow"></div>
            <span class="sb-link-icon"><i class="fas fa-chart-pie"></i></span>
            <span class="sb-link-text">仪表盘</span>
        </a>

        <div class="sb-divider"></div><div class="sb-label">核心管理</div>

        <a href="?tab=apps" class="sb-link <?= $tab === 'apps' ? 'active' : '' ?>" data-tooltip="应用列表">
            <div class="sb-link-glow"></div>
            <span class="sb-link-icon"><i class="fas fa-cubes"></i></span>
            <span class="sb-link-text">应用列表</span>
            <span class="sb-badge"><?= count($appList) ?></span>
        </a>

        <a href="?tab=list" class="sb-link <?= $tab === 'list' ? 'active' : '' ?>" data-tooltip="卡密库存">
            <div class="sb-link-glow"></div>
            <span class="sb-link-icon"><i class="fas fa-database"></i></span>
            <span class="sb-link-text">卡密库存</span>
        </a>

        <a href="?tab=create" class="sb-link <?= $tab === 'create' ? 'active' : '' ?>" data-tooltip="批量制卡">
            <div class="sb-link-glow"></div>
            <span class="sb-link-icon"><i class="fas fa-plus-circle"></i></span>
            <span class="sb-link-text">批量制卡</span>
        </a>

        <div class="sb-divider"></div>
        <div class="sb-label">系统</div>

        <a href="?tab=logs" class="sb-link <?= $tab === 'logs' ? 'active' : '' ?>" data-tooltip="审计日志">
            <div class="sb-link-glow"></div>
            <span class="sb-link-icon"><i class="fas fa-shield-halved"></i></span>
            <span class="sb-link-text">审计日志</span>
        </a>

        <a href="?tab=settings" class="sb-link <?= $tab === 'settings' ? 'active' : '' ?>" data-tooltip="全局配置">
            <div class="sb-link-glow"></div>
            <span class="sb-link-icon"><i class="fas fa-gear"></i></span>
            <span class="sb-link-text">全局配置</span>
        </a>
    </nav>

    <button class="sb-collapse-btn" onclick="toggleCollapse()" title="折叠侧边栏">
        <i class="fas fa-chevron-left"></i>
        <span class="sb-collapse-text">收起菜单</span>
    </button>

    <div class="sb-user">
        <img src="<?= htmlspecialchars($conf_avatar) ?>" alt="" class="sb-avatar">
        <div class="sb-user-info">
            <div class="sb-uname"><?= htmlspecialchars($currentAdminUser) ?></div>
            <div class="sb-urole">在线· Super Admin</div>
        </div>
        <a href="?logout=1" class="sb-logout" title="退出登录">
            <i class="fas fa-right-from-bracket"></i></a>
    </div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main">
    <header class="top-bar">
        <div class="top-left">
            <button class="menu-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div class="top-title">
                <i class="fas <?= $currentIcon ?>"></i> <?= $currentTitle ?>
            </div>
        </div>
        <div class="top-right">
            <?php if ($msg): ?>
            <div class="msg-pill msg-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($errorMsg): ?>
            <div class="msg-pill msg-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>
        </div>
    </header>

    <div class="breadcrumb">
        <span>System</span>
        <i class="fas fa-chevron-right"></i>
        <span><?= $currentTitle ?></span>
    </div>

    <div class="content">

<?php
//═══════════════════════════════════════════════════════
// TAB: DASHBOARD
// ═══════════════════════════════════════════════════════
if ($tab === 'dashboard'): ?>

    <div class="card welcome-card animate-in">
        <div class="welcome-inner">
            <div class="welcome-icon"><i class="fas fa-rocket"></i></div>
            <div style="flex:1">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                    <span class="welcome-title">Welcome back, <?= htmlspecialchars($currentAdminUser) ?></span>
                    <span class="version-badge">V4REMASTERED</span>
                </div>
                <div class="welcome-desc">
                    系统运行状态良好，所有服务节点连接正常。
                    <div style="margin-top:8px;opacity:.7;font-size:12px">
                        <i class="fas fa-circle-info"></i> 交流群:1077643184· <?= date('Y-m-d H:i') ?>
                    </div>
                </div>
            </div>
        </div>
    </div><div class="stats">
        <div class="stat animate-in delay-1">
            <div class="stat-label">总库存量</div>
            <div class="stat-value"><?= number_format($statTotal) ?></div>
            <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
        </div>
        <div class="stat animate-in delay-2">
            <div class="stat-label">活跃设备</div>
            <div class="stat-value"><?= number_format($statActive) ?></div>
            <div class="stat-icon"><i class="fas fa-wifi"></i></div>
        </div>
        <div class="stat animate-in delay-3">
            <div class="stat-label">接入应用</div>
            <div class="stat-value"><?= $statApps ?></div>
            <div class="stat-icon"><i class="fas fa-cubes"></i></div>
        </div>
        <div class="stat animate-in delay-4">
            <div class="stat-label">待售库存</div>
            <div class="stat-value"><?= number_format($statUnused) ?></div>
            <div class="stat-icon"><i class="fas fa-tag"></i></div>
        </div>
    </div>

    <div class="split-grid animate-in delay-3">
        <div class="card">
            <div class="card-head">
                <span class="card-title"><i class="fas fa-chart-bar"></i> 应用库存占比</span>
            </div>
            <div class="table-wrap"><table>
                <thead><tr><th>应用名称</th><th>卡密数</th><th>占比</th></tr></thead>
                <tbody>
                <?php
                $dTotal = max(1, $statTotal);
                foreach ($dashboardData['app_stats'] as $stat):
                    if (empty($stat['app_name'])) continue;
                    $pct = round(($stat['count'] / $dTotal) * 100, 1);
                ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($stat['app_name']) ?></td>
                    <td><?= number_format($stat['count']) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px">
                            <div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div>
                            <span style="font-size:12px;color:var(--c-text-muted);font-weight:600;width:40px"><?= $pct ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
        <div class="card">
            <div class="card-head">
                <span class="card-title"><i class="fas fa-chart-pie"></i> 类型分布</span>
            </div>
            <div style="height:220px;padding:20px"><canvas id="typeChart"></canvas></div>
        </div>
    </div>

    <div class="card animate-in delay-4">
        <div class="card-head">
            <span class="card-title"><i class="fas fa-satellite-dish"></i> 实时活跃设备</span>
            <a href="?tab=list" class="btn btn-primary btn-icon" style="font-size:11px;padding:6px 14px">
                <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>应用</th><th>卡密</th><th>设备指纹</th><th>激活</th><th>到期</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($activeDevices, 0, 5) as $dev): ?>
            <tr>
                <td>
                    <?php if ($dev['app_id'] > 0): ?>
                    <span class="badge badge-primary"><?= htmlspecialchars($dev['app_name']) ?></span>
                    <?php else: ?>
                    <span style="color:var(--c-text-muted);font-size:12px">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="code" onclick="copy('<?= $dev['card_code'] ?>')">
                        <i class="fas fa-copy"></i> <?= $dev['card_code'] ?>
                    </span>
                </td>
                <td style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--c-text-muted)">
                    <?= htmlspecialchars(substr($dev['device_hash'], 0, 12)) ?>…
                </td>
                <td><?= date('H:i', strtotime($dev['activate_time'])) ?></td>
                <td><span class="badge badge-success"><?= date('m-d H:i', strtotime($dev['expire_time'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($activeDevices)): ?>
            <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--c-text-muted)">暂无在线设备</td></tr>
            <?php endif; ?>
            </tbody>
        </table></div>
    </div>

<?php
// ═══════════════════════════════════════════════════════
// TAB: APPS
// ═══════════════════════════════════════════════════════
elseif ($tab === 'apps'): ?>

    <div class="pill-tabs animate-in" style="margin-top:8px">
        <button onclick="switchAppView('apps')" id="btn_apps" class="pill active">
            <i class="fas fa-list-ul"></i> 应用列表
        </button>
        <button onclick="switchAppView('vars')" id="btn_vars" class="pill">
            <i class="fas fa-sliders-h"></i> 变量管理
        </button>
    </div>

    <!--应用列表视图 -->
    <div id="view_apps">
        <div class="card animate-in delay-1">
            <div class="card-head">
                <span class="card-title">已接入应用</span><span style="font-size:12px;color:var(--c-text-muted);font-weight:500"><?= count($appList) ?> 个应用</span>
            </div>
            <div class="table-wrap"><table>
                <thead><tr><th>应用信息</th><th>App Key</th><th>数据</th><th>状态</th><th>操作</th></tr></thead>
                <tbody>
                <?php foreach ($appList as $app): ?>
                <tr>
                    <td>
                        <div style="font-weight:700;font-size:14px"><?= htmlspecialchars($app['app_name']) ?></div>
                        <div style="font-size:11px;color:var(--c-text-muted);margin-top:4px;display:flex;align-items:center;gap:6px">
                            <?php if (!empty($app['app_version'])): ?>
                            <span class="badge badge-neutral" style="padding:2px 6px;font-size:10px"><?= htmlspecialchars($app['app_version']) ?></span>
                            <?php endif; ?>
                            <?= htmlspecialchars($app['notes']?: '暂无备注') ?>
                        </div>
                    </td>
                    <td>
                        <span class="key-box" onclick="copy('<?= $app['app_key'] ?>')">
                            <i class="fas fa-key"></i> <?= $app['app_key'] ?>
                        </span>
                    </td>
                    <td><span class="badge badge-primary"><?= number_format($app['card_count']) ?> 张</span></td>
                    <td>
                        <?= $app['status'] == 1
                            ? '<span class="badge badge-success">正常</span>'
                            : '<span class="badge badge-danger">禁用</span>' ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <button onclick="openEditApp(<?= $app['id'] ?>,'<?= addslashes($app['app_name']) ?>','<?= addslashes($app['app_version']) ?>','<?= addslashes($app['notes']) ?>')"
                                    class="btn btn-ghost btn-icon" title="编辑">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="singleAction('toggle_app',<?= $app['id'] ?>)"
                                    class="btn btn-ghost btn-icon" title="<?= $app['status'] == 1 ? '禁用' : '启用' ?>">
                                <i class="fas <?= $app['status'] == 1 ? 'fa-ban' : 'fa-check' ?>"></i>
                            </button>
                            <?php if ($app['card_count'] > 0): ?>
                            <button onclick="alert('请先清空该应用下的 <?= $app['card_count'] ?> 张卡密')"
                                    class="btn btn-ghost btn-icon" style="opacity:.4" title="需先清空卡密">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php else: ?>
                            <button onclick="singleAction('delete_app',<?= $app['id'] ?>)"
                                    class="btn btn-ghost btn-icon" style="color:var(--c-danger)" title="删除">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($appList)): ?>
                <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--c-text-muted)">暂无应用</td></tr>
                <?php endif; ?></tbody>
            </table></div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:24px">
            <details class="card animate-in delay-2" open>
                <summary>
                    <div class="card-head">
                        <span class="card-title"><i class="fas fa-plus-circle"></i> 创建新应用</span>
                    </div>
                </summary>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="create_app" value="1">
                        <div class="form-group">
                            <label class="form-label">应用名称</label>
                            <input type="text" name="app_name" class="form-input" required placeholder="例如: Android客户端">
                        </div>
                        <div class="form-group">
                            <label class="form-label">版本号</label>
                            <input type="text" name="app_version" class="form-input" placeholder="例如: v1.0">
                        </div>
                        <div class="form-group">
                            <label class="form-label">备注</label>
                            <textarea name="app_notes" class="form-input" placeholder="可选"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%">
                            <i class="fas fa-plus"></i> 立即创建
                        </button>
                    </form>
                </div>
            </details>

            <details class="card animate-in delay-3">
                <summary>
                    <div class="card-head">
                        <span class="card-title"><i class="fas fa-code"></i> API接口信息</span>
                    </div>
                </summary>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">接口地址</label>
                        <div class="key-box" style="width:100%;padding:12px;justify-content:space-between"
                             onclick="copy('<?= $apiUrl ?>')">
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $apiUrl ?></span>
                            <i class="fas fa-copy" style="color:var(--c-primary);flex-shrink:0"></i>
                        </div>
                    </div>
                    <p style="font-size:12px;color:var(--c-text-muted);line-height:1.6;margin-top:12px">
                        通过 AppKey 验证卡密或获取公开变量。请妥善保管您的 AppKey。
                    </p>
                </div>
            </details>
        </div>

        <!-- 编辑应用 Modal -->
        <div id="editAppModal" class="modal">
            <div class="modal-bg" onclick="closeEditApp()"></div>
            <div class="modal-box">
                <div class="modal-title">编辑应用信息</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="edit_app" value="1">
                    <input type="hidden" id="edit_app_id" name="app_id">
                    <div class="form-group">
                        <label class="form-label">应用名称</label>
                        <input type="text" id="edit_app_name" name="app_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">版本号</label>
                        <input type="text" id="edit_app_version" name="app_version" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">备注</label>
                        <textarea id="edit_app_notes" name="app_notes" class="form-input"></textarea>
                    </div>
                    <div style="display:flex;gap:12px">
                        <button type="button" class="btn btn-secondary" onclick="closeEditApp()" style="flex:1">取消</button>
                        <button type="submit" class="btn btn-primary" style="flex:1">保存修改</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 变量管理视图 -->
    <div id="view_vars" style="display:none">
        <div class="card animate-in">
            <div class="card-head"><span class="card-title">云端变量管理</span></div>
            <div class="table-wrap"><table>
                <thead><tr><th>应用</th><th>键名</th><th>值</th><th>权限</th><th>操作</th></tr></thead>
                <tbody>
                <?php
                $hasVars = false;
                foreach ($appList as $app) {
                    $vars = $db->getAppVariables($app['id']);
                    foreach ($vars as $v) {
                        $hasVars = true;
                        echo '<tr>';
                        echo '<td><span class="badge badge-primary">' . htmlspecialchars($app['app_name']) . '</span></td>';
                        echo '<td><span class="code" style="color:#ec4899;background:rgba(236,72,153,.06);border-color:rgba(236,72,153,.15)">' . htmlspecialchars($v['key_name']) . '</span></td>';
                        echo '<td><span class="key-box" style="max-width:200px;overflow:hidden;text-overflow:ellipsis">' . htmlspecialchars($v['value']) . '</span></td>';
                        echo '<td>' . ($v['is_public'] ? '<span class="badge badge-success">公开</span>' : '<span class="badge badge-warn">私有</span>') . '</td>';
                        echo '<td><div class="btn-group">';
                        echo '<button onclick="openEditVar(' . $v['id'] . ',\'' . addslashes($v['key_name']) . '\',\'' . str_replace(["\r\n","\r","\n"], '\n', addslashes($v['value'])) . '\',' . $v['is_public'] . ')" class="btn btn-ghost btn-icon"><i class="fas fa-edit"></i></button>';
                        echo '<button onclick="singleAction(\'del_var\',' . $v['id'] . ',\'var_id\')" class="btn btn-ghost btn-icon" style="color:var(--c-danger)"><i class="fas fa-trash"></i></button>';
                        echo '</div></td></tr>';
                    }
                }
                if (!$hasVars) {
                    echo '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--c-text-muted)">暂无变量数据</td></tr>';
                }
                ?>
                </tbody>
            </table></div>
        </div>

        <details class="card" open>
            <summary>
                <div class="card-head">
                    <span class="card-title"><i class="fas fa-plus"></i> 添加变量</span>
                </div>
            </summary>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="add_var" value="1">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:18px">
                        <div class="form-group">
                            <label class="form-label">所属应用</label>
                            <select name="var_app_id" class="form-input" required>
                                <option value="">-- 选择 --</option>
                                <?php foreach ($appList as $app): ?>
                                <option value="<?= $app['id'] ?>"><?= htmlspecialchars($app['app_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">键名</label>
                            <input type="text" name="var_key" class="form-input" placeholder="例如: update_url" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">变量值</label>
                        <textarea name="var_value" class="form-input"></textarea>
                    </div>
                    <div class="toggle-row" style="margin-bottom:18px">
                        <input type="checkbox" name="var_public" value="1" id="var_pub">
                        <label for="var_pub">设为公开变量 (Public)</label>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">
                        <i class="fas fa-check"></i> 保存变量
                    </button>
                </form>
            </div>
        </details>

        <!-- 编辑变量 Modal -->
        <div id="editVarModal" class="modal">
            <div class="modal-bg" onclick="closeEditVar()"></div>
            <div class="modal-box">
                <div class="modal-title">编辑变量</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="edit_var" value="1">
                    <input type="hidden" id="edit_var_id" name="var_id">
                    <div class="form-group">
                        <label class="form-label">键名</label>
                        <input type="text" id="edit_var_key" name="var_key" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">变量值</label>
                        <textarea id="edit_var_value" name="var_value" class="form-input"></textarea>
                    </div>
                    <div class="toggle-row" style="margin-bottom:18px">
                        <input type="checkbox" id="edit_var_public" name="var_public" value="1">
                        <label for="edit_var_public">公开变量</label>
                    </div>
                    <div style="display:flex;gap:12px">
                        <button type="button" class="btn btn-secondary" onclick="closeEditVar()" style="flex:1">取消</button>
                        <button type="submit" class="btn btn-primary" style="flex:1">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php
// ═══════════════════════════════════════════════════════
// TAB: LIST
// ═══════════════════════════════════════════════════════
elseif ($tab === 'list'): ?>

    <div class="card animate-in" style="margin-top:8px">
        <div class="card-body" style="padding:20px 28px">
            <select class="form-input" style="margin:0"
                    onchange="location.href='?tab=list&app_id='+this.value">
                <option value="">— 请先选择应用 —</option>
                <?php foreach ($appList as $app): ?>
                <option value="<?= $app['id'] ?>" <?= ($appFilter === $app['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($app['app_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if ($appFilter !== null || !empty($_GET['q'])): ?>
    <div class="pill-tabs animate-in delay-1"><a href="?tab=list&filter=all<?= $appFilter !== null ? '&app_id=' . $appFilter : '' ?>"
           class="pill <?= $filterStr === 'all' ? 'active' : '' ?>">全部</a>
        <a href="?tab=list&filter=unused<?= $appFilter !== null ? '&app_id=' . $appFilter : '' ?>"
           class="pill <?= $filterStr === 'unused' ? 'active' : '' ?>">未激活</a>
        <a href="?tab=list&filter=active<?= $appFilter !== null ? '&app_id=' . $appFilter : '' ?>"
           class="pill <?= $filterStr === 'active' ? 'active' : '' ?>">已激活</a>
        <a href="?tab=list&filter=banned<?= $appFilter !== null ? '&app_id=' . $appFilter : '' ?>"
           class="pill <?= $filterStr === 'banned' ? 'active' : '' ?>">已封禁</a>
    </div>

    <div class="card animate-in delay-2">
        <form id="batchForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="card-head">
                <div style="display:flex;gap:10px;align-items:center;flex:1;min-width:0">
                    <input type="text"
                           placeholder="搜索卡密、备注或设备指纹…"
                           value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                           class="form-input" style="margin:0;flex:1;min-width:160px"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();location='?tab=list&q='+this.value}">
                    <a href="?tab=list<?= $appFilter !== null ? '&app_id=' . $appFilter : '' ?>"
                       class="btn btn-secondary btn-icon" title="刷新">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                    <a href="?tab=create" class="btn btn-primary btn-icon" title="制卡">
                        <i class="fas fa-plus"></i>
                    </a>
                </div><div class="btn-group" style="margin-top:8px">
                    <button type="submit" name="batch_export" value="1" class="btn btn-primary" style="font-size:12px">
                        <i class="fas fa-file-export"></i> 导出
                    </button>
                    <button type="button" onclick="submitBatch('batch_unbind')" class="btn btn-warning" style="font-size:12px">
                        <i class="fas fa-unlink"></i> 解绑
                    </button><button type="button" onclick="batchAddTime()" class="btn btn-success" style="font-size:12px">
                        <i class="fas fa-clock"></i> 加时
                    </button>
                <button type="button" onclick="submitBatch('batch_delete')" class="btn btn-danger" style="font-size:12px">
                        <i class="fas fa-trash"></i> 删除
                    </button>
                </div>
            </div>
            <input type="hidden" name="add_hours" id="addHoursInput">
            <div class="table-wrap"><table>
                <thead><tr>
                    <th style="width:40px;text-align:center">
                        <input type="checkbox" onclick="toggleAll(this)" style="accent-color:var(--c-primary)">
                    </th>
                    <th>应用</th><th>卡密代码</th><th>类型</th><th>状态</th>
                    <th>绑定设备</th><th>备注</th><th>操作</th>
                </tr></thead>
                <tbody>
                <?php foreach ($cardList as $card): ?>
                <tr>
                    <td style="text-align:center">
                        <input type="checkbox" name="ids[]" value="<?= $card['id'] ?>"
                               class="row-check" style="accent-color:var(--c-primary)">
                    </td>
                    <td>
                        <?php if ($card['app_id'] > 0 && !empty($card['app_name'])): ?>
                        <span class="badge badge-primary"><?= htmlspecialchars($card['app_name']) ?></span>
                        <?php else: ?>
                        <span style="color:var(--c-text-muted);font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="code" onclick="copy('<?= $card['card_code'] ?>')">
                            <i class="fas fa-copy"></i> <?= $card['card_code'] ?>
                        </span>
                    </td>
                    <td><span style="font-weight:600;font-size:12px"><?= CARD_TYPES[$card['card_type']]['name'] ?? $card['card_type'] ?></span></td>
                    <td>
                        <?php
                        if ($card['status'] == 2) {
                            echo '<span class="badge badge-danger">封禁</span>';
                        } elseif ($card['status'] == 1) {
                            if (strtotime($card['expire_time']) > time()) {
                                echo empty($card['device_hash'])
                                    ? '<span class="badge badge-warn">待绑定</span>'
                                    : '<span class="badge badge-success">使用中</span>';
                            } else {
                                echo '<span class="badge badge-danger">已过期</span>';
                            }
                        } else {
                            echo '<span class="badge badge-neutral">闲置</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($card['status'] == 1 && !empty($card['device_hash'])): ?>
                        <span style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--c-text-muted)"
                              title="<?= htmlspecialchars($card['device_hash']) ?>">
                            <i class="fas fa-mobile-alt" style="margin-right:4px;opacity:.5"></i>
                            <?= substr($card['device_hash'], 0, 8) ?>…
                        </span>
                        <?php else: ?>
                        <span style="color:var(--c-text-muted)">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--c-text-muted);font-size:12px;max-width:100px;overflow:hidden;text-overflow:ellipsis">
                        <?= htmlspecialchars($card['notes'] ?: '—') ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <?php if ($card['status'] == 1 && !empty($card['device_hash'])): ?>
                            <button type="button" onclick="singleAction('unbind_card',<?= $card['id'] ?>)"
                                    class="btn btn-ghost btn-icon" title="解绑">
                                <i class="fas fa-unlink"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($card['status'] != 2): ?>
                            <button type="button" onclick="singleAction('ban_card',<?= $card['id'] ?>)"
                                    class="btn btn-ghost btn-icon" style="color:var(--c-danger)" title="封禁">
                                <i class="fas fa-ban"></i>
                            </button>
                            <?php else: ?>
                            <button type="button" onclick="singleAction('unban_card',<?= $card['id'] ?>)"
                                    class="btn btn-ghost btn-icon" style="color:var(--c-success)" title="解封">
                                <i class="fas fa-unlock"></i>
                            </button>
                            <?php endif; ?>
                            <button type="button" onclick="singleAction('del_card',<?= $card['id'] ?>)"
                                    class="btn btn-ghost btn-icon" style="color:var(--c-danger)" title="删除">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($cardList)): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--c-text-muted)">暂无符合条件的卡密</td></tr>
                <?php endif; ?>
                </tbody>
            </table></div>

            <!-- 分页 -->
            <div class="pagination">
                <select class="form-input"
                        style="width:auto;margin:0 12px 0 0;padding:8px 12px;font-size:12px"
                        onchange="location.href='?tab=list&filter=<?= $filterStr ?>&page=1&limit='+this.value+'<?= isset($_GET['q']) ? '&q=' . htmlspecialchars($_GET['q']) : '' ?><?= $appFilter !== null ? '&app_id=' . $appFilter : '' ?>'">
                    <?php foreach ([10, 20, 50, 100] as $lim): ?>
                    <option value="<?= $lim ?>" <?= $perPage == $lim ? 'selected' : '' ?>><?= $lim ?> 条/页</option>
                    <?php endforeach; ?>
                </select>
                <?php
                $qp = ['tab' => 'list', 'limit' => $perPage, 'filter' => $filterStr];
                if (!empty($_GET['q'])) $qp['q'] = $_GET['q'];
                if ($appFilter !== null) $qp['app_id'] = $appFilter;
                $mkUrl = function ($p) use ($qp) { $qp['page'] = $p; return '?' . http_build_query($qp); };

                if ($page > 1) echo '<a href="' . $mkUrl($page - 1) . '" class="page-btn"><i class="fas fa-chevron-left"></i></a>';
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                if ($start > 1) {
                    echo '<a href="' . $mkUrl(1) . '" class="page-btn">1</a>';
                    if ($start > 2) echo '<span class="page-btn" style="border:none;background:transparent;cursor:default">…</span>';
                }
                for ($i = $start; $i <= $end; $i++) {
                    echo $i == $page
                        ? '<span class="page-btn active">' . $i . '</span>'
                        : '<a href="' . $mkUrl($i) . '" class="page-btn">' . $i . '</a>';
                }
                if ($end < $totalPages) {
                    if ($end < $totalPages - 1) echo '<span class="page-btn" style="border:none;background:transparent;cursor:default">…</span>';
                    echo '<a href="' . $mkUrl($totalPages) . '" class="page-btn">' . $totalPages . '</a>';
                }
                if ($page < $totalPages) echo '<a href="' . $mkUrl($page + 1) . '" class="page-btn"><i class="fas fa-chevron-right"></i></a>';
                ?>
            </div>
        </form>
    </div><?php endif; ?>

<?php
// ═══════════════════════════════════════════════════════
// TAB: CREATE
// ═══════════════════════════════════════════════════════
elseif ($tab === 'create'): ?>

    <div class="create-wrap animate-in">
        <div class="card">
            <div class="card-head">
                <span class="card-title"><i class="fas fa-wand-magic-sparkles"></i> 批量制卡中心</span>
                <span style="font-size:12px;color:var(--c-text-muted)">快速生成验证卡密</span>
            </div>
            <div class="create-grid">
                <form method="POST" class="create-form-grid">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="gen_cards" value="1">
                    <div class="form-group full-span">
                        <label class="form-label"><i class="fas fa-layer-group"></i> 归属应用</label>
                        <select name="app_id" class="form-input" required style="font-weight:600;color:var(--c-primary)">
                            <option value="">— 请选择目标应用 —</option>
                            <?php foreach ($appList as $app): if ($app['status'] == 0) continue; ?>
                            <option value="<?= $app['id'] ?>"><?= htmlspecialchars($app['app_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-sort-numeric-up-alt"></i> 生成数量</label>
                        <input type="number" name="num" class="form-input" value="10" min="1" max="500">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-clock"></i> 套餐类型</label>
                        <select name="type" class="form-input">
                            <?php foreach (CARD_TYPES as $k => $v): ?>
                            <option value="<?= $k ?>">
                                <?= $v['name'] ?> (<?= $v['duration'] >= 86400 ? ($v['duration'] / 86400) . '天' : ($v['duration'] / 3600) . '小时' ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-font"></i> 自定义前缀</label>
                        <input type="text" name="pre" class="form-input" placeholder="例如: VIP-">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-tag"></i> 备注</label>
                        <input type="text" name="note" class="form-input" placeholder="可选">
                    </div>
                    <button type="submit" class="btn btn-primary big-btn">
                        <i class="fas fa-bolt"></i> 立即生成
                    </button>
                </form><div class="tip-box">
                    <div class="tip-item">
                        <h4>💡 制卡小贴士</h4>
                        <p>单次建议不超过500张。卡密为16位随机字符，可使用前缀区分批次。</p>
                    </div>
                    <div class="tip-item">
                        <h4>📦 批量导出</h4>
                        <p>在「卡密库存」中勾选导出为TXT文件，方便分发。</p>
                    </div>
                    <div style="flex:1;display:flex;align-items:center;justify-content:center;opacity:.06">
                        <i class="fas fa-cogs" style="font-size:80px"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
// ═══════════════════════════════════════════════════════
// TAB: LOGS
// ═══════════════════════════════════════════════════════
elseif ($tab === 'logs'): ?>

    <div class="card animate-in" style="margin-top:8px">
        <div class="card-head">
            <span class="card-title"><i class="fas fa-shield-halved"></i> 鉴权审计日志</span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>时间</th><th>来源</th><th>卡密</th><th>IP / 设备</th><th>结果</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td style="color:var(--c-text-muted);font-size:12px">
                    <?= date('m-d H:i', strtotime($log['access_time'])) ?>
                </td>
                <td><span class="badge badge-info" style="font-size:10px">
                        <?= htmlspecialchars($log['app_name']?: '—') ?>
                    </span>
                </td>
                <td>
                    <span class="code" style="font-size:11px">
                        <?= substr($log['card_code'], 0, 10) ?>…
                    </span>
                </td>
                <td style="font-size:11px">
                    <?= htmlspecialchars(substr($log['ip_address'], 0, 15)) ?><br>
                    <span style="color:var(--c-text-muted)"><?= htmlspecialchars(substr($log['device_hash'], 0, 6)) ?></span>
                </td>
                <td>
                    <?php
                    $r = $log['result'];
                    if (strpos($r, '成功') !== false || strpos($r, '活跃') !== false)
                        echo '<span class="badge badge-success" style="font-size:10px">成功</span>';
                    elseif (strpos($r, '失败') !== false)
                        echo '<span class="badge badge-danger" style="font-size:10px">失败</span>';
                    else
                        echo '<span class="badge badge-neutral" style="font-size:10px">' . htmlspecialchars($r) . '</span>';
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--c-text-muted)">暂无日志</td></tr>
            <?php endif; ?>
            </tbody>
        </table></div>
    </div>

<?php
// ═══════════════════════════════════════════════════════
// TAB: SETTINGS
// ═══════════════════════════════════════════════════════
elseif ($tab === 'settings'): ?>

    <div class="settings-wrap animate-in">
        <div class="settings-grid">
            <div style="display:flex;flex-direction:column;gap:24px">
                <!-- 基础配置 -->
                <div class="setting-block">
                    <div class="setting-block-title"><i class="fas fa-globe"></i> 基础配置</div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="update_settings" value="1">
                        <input type="hidden" name="favicon"value="<?= htmlspecialchars($conf_favicon) ?>">
                        <input type="hidden" name="admin_avatar" value="<?= htmlspecialchars($conf_avatar) ?>">
                        <input type="hidden" name="bg_pc"        value="<?= htmlspecialchars($conf_bg_pc) ?>">
                        <input type="hidden" name="bg_mobile"    value="<?= htmlspecialchars($conf_bg_mobile) ?>">
                        <div class="form-group">
                            <label class="form-label">网站标题</label>
                            <input type="text" name="site_title" class="form-input" value="<?= htmlspecialchars($conf_site_title) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">管理员用户名</label>
                            <input type="text" name="admin_username" class="form-input" value="<?= htmlspecialchars($currentAdminUser) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%">
                            <i class="fas fa-check"></i> 保存基础信息
                        </button>
                    </form>
                </div>

                <!-- 安全设置 -->
                <div class="setting-block">
                    <div class="setting-block-title"><i class="fas fa-shield-alt"></i> 安全设置</div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="update_pwd" value="1">
                        <div class="form-group">
                            <div class="pwd-wrap">
                                <input type="password" id="pwd1" name="new_pwd"
                                       class="form-input" placeholder="设置新密码" required>
                                <i class="fas fa-eye pwd-toggle" onclick="togglePwd()"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="pwd-wrap">
                                <input type="password" id="pwd2" name="confirm_pwd"
                                       class="form-input" placeholder="确认新密码" required>
                </div>
                        </div>
                        <button type="submit" class="btn btn-danger" style="width:100%">
                            <i class="fas fa-key"></i> 更新密码
                        </button>
                    </form>
                </div></div>

            <!-- 视觉素材管理 -->
            <div class="setting-block">
                <div class="setting-block-title"><i class="fas fa-paint-brush"></i> 视觉素材管理</div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="update_settings" value="1">
                    <input type="hidden" name="site_title"value="<?= htmlspecialchars($conf_site_title) ?>">
                    <input type="hidden" name="admin_username"  value="<?= htmlspecialchars($currentAdminUser) ?>">
                    <?php
                    $assets = [
                        ['Favicon 图标', 'favicon',      $conf_favicon,'favicon_file','fav'],
                        ['后台头像','admin_avatar', $conf_avatar,     'admin_avatar_file', 'avatar'],
                        ['PC端壁纸',     'bg_pc',        $conf_bg_pc,'bg_pc_file','pc'],
                        ['移动端壁纸',   'bg_mobile',    $conf_bg_mobile,'bg_mobile_file','mob'],
                    ];
                    foreach ($assets as [$label, $name, $val, $fname, $prefix]):?>
                    <div class="form-group">
                        <label class="form-label"><?= $label ?></label>
                        <div class="file-group">
                            <input type="text" id="<?= $prefix ?>_input" name="<?= $name ?>"
                                   class="form-input" value="<?= htmlspecialchars($val) ?>"
                                   style="margin-bottom:0" placeholder="输入链接或上传">
                            <input type="file" id="<?= $prefix ?>_file" name="<?= $fname ?>"
                                   class="file-hidden" accept="image/*"
                                   onchange="showFile(this,'<?= $prefix ?>')"><label for="<?= $prefix ?>_file" class="file-upload-btn" title="上传">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </label>
                        </div>
                <div id="<?= $prefix ?>_status" class="file-status"></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="toggle-row">
                        <input type="checkbox" name="bg_blur" value="1" id="bg_blur_chk"
                               <?= $conf_bg_blur === '1' ? 'checked' : '' ?>>
                        <label for="bg_blur_chk">开启背景高斯模糊 (Glass Effect)</label>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:20px">
                        <i class="fas fa-palette"></i> 保存视觉配置
                    </button></form>
            </div>
        </div>
    </div>

<?php endif; ?></div><!-- /.content -->
</main>

<!-- Toast 通知 -->
<div id="toast" class="toast">
    <i class="fas fa-check-circle"></i>
    <span id="toast-text">已复制到剪贴板</span>
</div>

<script>
//═══════════════════════════════════════════════════════════
// SIDEBAR
// ═══════════════════════════════════════════════════════════

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.querySelector('.sb-overlay').classList.toggle('show');
}

function toggleCollapse() {
    const sb = document.getElementById('sidebar');
    sb.classList.toggle('collapsed');
    localStorage.setItem('sb_collapsed', sb.classList.contains('collapsed') ? '1' : '0');
}

document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');

    // 恢复折叠状态
    if (window.innerWidth > 768 && localStorage.getItem('sb_collapsed') === '1') {
        sidebar.classList.add('collapsed');
    }

    // 鼠标光晕跟踪
    document.querySelectorAll('.sb-link').forEach(link => {
        link.addEventListener('mousemove', e => {
            const rect = link.getBoundingClientRect();
            const glow = link.querySelector('.sb-link-glow');
            if (glow) {
                glow.style.setProperty('--glow-x', (e.clientX - rect.left) + 'px');
                glow.style.setProperty('--glow-y', (e.clientY - rect.top) + 'px');
            }
        });
    });

    // 快捷键 Ctrl+B
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            window.innerWidth > 768 ? toggleCollapse() : toggleSidebar();
        }
        if (e.key === 'Escape' && sidebar.classList.contains('open')) toggleSidebar();
    });

    // 窗口缩放自动关闭
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('open');
            document.querySelector('.sb-overlay')?.classList.remove('show');
        }
    });
});

// ═══════════════════════════════════════════════════════════
// 工具函数
// ═══════════════════════════════════════════════════════════

function showToast(text = '已复制到剪贴板') {
    const t = document.getElementById('toast');
    document.getElementById('toast-text').textContent = text;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}

function copy(text) {
    navigator.clipboard.writeText(text).then(() => showToast()).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        showToast();
    });
}

function togglePwd() {
    const p1= document.getElementById('pwd1');
    const p2   = document.getElementById('pwd2');
    const icon = document.querySelector('.pwd-toggle');
    const show = p1.type === 'password';
    p1.type = p2.type = show ? 'text' : 'password';
    icon.classList.toggle('fa-eye',!show);
    icon.classList.toggle('fa-eye-slash', show);
}

function showFile(input, prefix) {
    if (input.files && input.files.length > 0) {
        document.getElementById(prefix + '_status').innerHTML =
            '<i class="fas fa-check-circle"></i> ' + input.files[0].name;
    }
}

function toggleAll(src) {
    document.querySelectorAll('.row-check').forEach(c => c.checked = src.checked);
}

function submitBatch(action) {
    if (!document.querySelectorAll('.row-check:checked').length) {
        alert('请先勾选卡密'); return;
    }
    if (!confirm('确定执行此操作？')) return;
    const f = document.getElementById('batchForm');
    const h = document.createElement('input');
    h.type = 'hidden'; h.name = action; h.value = '1';
    f.appendChild(h);
    f.submit();
}

function batchAddTime() {
    if (!document.querySelectorAll('.row-check:checked').length) {
        alert('请先勾选卡密'); return;
    }
    const h = prompt('请输入增加小时数', '24');
    if (h && !isNaN(h)) {
        document.getElementById('addHoursInput').value = h;
        submitBatch('batch_add_time');
    }
}

function singleAction(action, id) {
    if (!confirm('确定操作？')) return;
    const f = document.createElement('form');
    f.method = 'POST'; f.style.display = 'none';
    const add = (n, v) => {
        const i = document.createElement('input');
        i.name = n; i.value = v; f.appendChild(i);
    };
    add(action, '1');
    add(action === 'del_var' ? 'var_id' : (action.includes('app') ? 'app_id' : 'id'), id);
    add('csrf_token', '<?= $csrf_token ?>');
    document.body.appendChild(f);
    f.submit();
}

//应用视图切换
function switchAppView(v) {
    document.getElementById('btn_apps').classList.toggle('active', v === 'apps');
    document.getElementById('btn_vars').classList.toggle('active', v === 'vars');
    document.getElementById('view_apps').style.display = v === 'apps' ? 'block' : 'none';
    document.getElementById('view_vars').style.display = v === 'vars' ? 'block' : 'none';
}

// Modal 控制
function openEditApp(id, name, ver, notes) {
    document.getElementById('edit_app_id').value      = id;
    document.getElementById('edit_app_name').value    = name;
    document.getElementById('edit_app_version').value = ver;
    document.getElementById('edit_app_notes').value   = notes;
    document.getElementById('editAppModal').classList.add('show');
}
function closeEditApp() { document.getElementById('editAppModal').classList.remove('show') }

function openEditVar(id, key, val, pub) {
    document.getElementById('edit_var_id').value      = id;
    document.getElementById('edit_var_key').value     = key;
    document.getElementById('edit_var_value').value   = val;
    document.getElementById('edit_var_public').checked = (pub == 1);
    document.getElementById('editVarModal').classList.add('show');
}
function closeEditVar() { document.getElementById('editVarModal').classList.remove('show') }

//═══ Dashboard图表 ═══
<?php if ($tab === 'dashboard'): ?>
document.addEventListener('DOMContentLoaded', function () {
    const typeData= <?= json_encode($dashboardData['chart_types']) ?>;
    const cardTypes = <?= json_encode(CARD_TYPES) ?>;
    const colors    = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316'];
    new Chart(document.getElementById('typeChart'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(typeData).map(k => (cardTypes[k]?.name || k)),
            datasets: [{
                data: Object.values(typeData),
                backgroundColor: colors.slice(0, Object.keys(typeData).length),
                borderWidth: 0,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        padding: 16,
                        font: { size: 11, family: "'Inter',system-ui,sans-serif", weight: '500' }
                    }
                }
            }
        }
    });
});
<?php endif; ?>

//成功消息自动消失
<?php if ($msg): ?>
setTimeout(() => { document.querySelector('.msg-success')?.remove() }, 5000);
<?php endif; ?>
</script>
</body>
</html>
