<?php
/**
 * cards.php - 后台管理主界面 (iOS 26Liquid Glass Concept Edition)
 * Fixed: Chrome Tabs container border removed
 */
ini_set('display_errors', 0);
error_reporting(0);

require_once 'config.php';
require_once 'database.php';
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['last_ip']) && $_SESSION['last_ip'] !== $_SERVER['REMOTE_ADDR']) {
    session_unset(); session_destroy();
    header('Location: login.php'); 
    exit;
}

if (isset($_GET['logout'])) { 
    session_destroy(); 
    setcookie('admin_trust', '', time() - 3600, '/'); 
    header('Location: login.php'); 
    exit; 
}

try { $db = new Database(); } catch (Throwable $e) {
    die("系统维护中，无法连接数据库。");
}

if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
    }
}
$csrf_token = $_SESSION['csrf_token'];

function verifyCSRF() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        header('HTTP/1.1 403 Forbidden'); die('Security Alert: CSRF 校验失败，请刷新重试。');
    }
}

$current_ip = $_SERVER['REMOTE_ADDR'];
$current_time = date('Y-m-d H:i');
setcookie('admin_last_ip', $current_ip, time() + 7776000, "/"); 
setcookie('admin_last_time', $current_time, time() + 7776000, "/");

$appList = [];
try { $appList = $db->getApps(); } catch (Throwable $e) { $appList = []; $errorMsg = "应用列表加载失败"; }

$sysConf = $db->getSystemSettings();
$currentAdminUser = $db->getAdminUsername();

$conf_site_title = $sysConf['site_title'] ?? 'GuYi Access';
$conf_favicon = $sysConf['favicon'] ?? base64_decode('aHR0cDovL3EucWxvZ28uY24vaGVhZGltZ19kbD9kc3RfdWluPTE1NjQ0MDAwMCZzcGVjPTY0MCZpbWdfdHlwZT1qcGc=');
$conf_avatar = $sysConf['admin_avatar'] ?? base64_decode('aHR0cDovL3EucWxvZ28uY24vaGVhZGltZ19kbD9kc3RfdWluPTE1NjQ0MDAwMCZzcGVjPTY0MCZpbWdfdHlwZT1qcGc=');
$conf_bg_pc = $sysConf['bg_pc'] ?? 'backend/PC.png';
$conf_bg_mobile = $sysConf['bg_mobile'] ?? 'backend/mod.png';
$conf_bg_blur = $sysConf['bg_blur'] ?? '0';

$mockFile = __DIR__ . '/mock_data.json';
$defaultAppStats = '[{"app_name":"演示应用A","count":5000},{"app_name":"演示应用B","count":3500},{"app_name":"测试项目","count":388}]';
$defaultTypeStats = '{"1":4000,"2":3000,"3":1888}';

$mockSettings = [
    'counts_enabled' => 0,
    'apps_enabled' => 0,
    'types_enabled' => 0,
    'total' => 8888,
    'active' => 666, 
    'apps' => 12, 
    'unused' => 8222,
    'app_stats_json' => $defaultAppStats,
    'type_stats_json' => $defaultTypeStats
];

if (file_exists($mockFile)) {
    $loadedMock = json_decode(file_get_contents($mockFile), true);
    if (is_array($loadedMock)) {
        $mockSettings = array_merge($mockSettings, $loadedMock);
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_export'])) {
    verifyCSRF();
    $ids = $_POST['ids'] ?? [];
    if (empty($ids)) { echo "<script>alert('请先勾选需要导出的卡密'); history.back();</script>"; exit; }
    $data = $db->getCardsByIds($ids);
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="cards_export_'.date('YmdHis').'.txt"');
    foreach ($data as $row) { echo "{$row['card_code']}\r\n"; }
    exit;
}

$tab = $_GET['tab'] ?? 'dashboard';
$pageTitles = ['dashboard'=>'首页','apps'=>'应用管理','list'=>'单码管理','create'=>'批量制卡','logs'=>'审计日志','settings'=>'系统配置'];
$currentTitle = $pageTitles[$tab] ?? '控制台';
$msg = ''; if(!isset($errorMsg)) $errorMsg = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRF();
    if (isset($_POST['create_app'])) {
        try {
            $appName = trim($_POST['app_name']);
            if (empty($appName)) throw new Exception("应用名称不能为空");
            $db->createApp(htmlspecialchars($appName), htmlspecialchars($_POST['app_version'] ?? ''), htmlspecialchars($_POST['app_notes']));
            $msg = "应用「".htmlspecialchars($appName)."」创建成功！"; $appList = $db->getApps();
        } catch (Exception $e) { $errorMsg = htmlspecialchars($e->getMessage()); }
    } elseif (isset($_POST['toggle_app'])) {
        $db->toggleAppStatus($_POST['app_id']); $msg = "应用状态已更新"; $appList = $db->getApps();
    } elseif (isset($_POST['delete_app'])) {
        try { $db->deleteApp($_POST['app_id']); $msg = "应用已删除"; $appList = $db->getApps(); } catch (Exception $e) { $errorMsg = htmlspecialchars($e->getMessage()); }
    } elseif (isset($_POST['edit_app'])) { 
        try {
            $appId = intval($_POST['app_id']); $appName = trim($_POST['app_name']);
            if (empty($appName)) throw new Exception("应用名称不能为空");
            $db->updateApp($appId, htmlspecialchars($appName), htmlspecialchars($_POST['app_version'] ?? ''), htmlspecialchars($_POST['app_notes']));
            $msg = "应用信息已更新"; $appList = $db->getApps();
        } catch (Exception $e) { $errorMsg = htmlspecialchars($e->getMessage()); }
    } elseif (isset($_POST['add_var'])) {
        try {
            $varAppId = intval($_POST['var_app_id']); $varKey = trim($_POST['var_key']); $varVal = trim($_POST['var_value']); $varPub = isset($_POST['var_public']) ? 1 : 0;
            if (empty($varKey)) throw new Exception("变量名不能为空");
            $db->addAppVariable($varAppId, htmlspecialchars($varKey), htmlspecialchars($varVal), $varPub);
            $msg = "变量「".htmlspecialchars($varKey)."」添加成功";
        } catch (Exception $e) { $errorMsg = htmlspecialchars($e->getMessage()); }
    } elseif (isset($_POST['edit_var'])) {
        try {
            $varId = intval($_POST['var_id']); $varKey = trim($_POST['var_key']); $varVal = trim($_POST['var_value']); $varPub = isset($_POST['var_public']) ? 1 : 0;
            if (empty($varKey)) throw new Exception("变量名不能为空");
            $db->updateAppVariable($varId, htmlspecialchars($varKey), htmlspecialchars($varVal), $varPub);
            $msg = "变量更新成功";
        } catch (Exception $e) { $errorMsg = htmlspecialchars($e->getMessage()); }
    } elseif (isset($_POST['del_var'])) {
        $db->deleteAppVariable($_POST['var_id']); $msg = "变量已删除";
    } elseif (isset($_POST['batch_delete'])) {
        $count = $db->batchDeleteCards($_POST['ids'] ?? []); $msg = "已批量删除 {$count} 张卡密";
    } elseif (isset($_POST['batch_unbind'])) {
        $count = $db->batchUnbindCards($_POST['ids'] ?? []); $msg = "已批量解绑 {$count} 个设备";
    } elseif (isset($_POST['batch_add_time'])) {
        $hours = floatval($_POST['add_hours']); $count = $db->batchAddTime($_POST['ids'] ?? [], $hours);
        $msg = "已为 {$count} 张卡密增加 {$hours} 小时";
    } elseif (isset($_POST['gen_cards'])) {
        try {
            $targetAppId = intval($_POST['app_id']);
            $newCodes = $db->generateCards($_POST['num'], $_POST['type'], $_POST['pre'], '',16, htmlspecialchars($_POST['note']), $targetAppId);
            if (isset($_POST['auto_export']) && $_POST['auto_export'] == '1' && !empty($newCodes)) {
                if (ob_get_level()) ob_end_clean();
                header('Content-Description: File Transfer');
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="new_cards_'.date('YmdHis').'.txt"');
                foreach ($newCodes as $code) { echo $code . "\r\n"; }
                exit;
            }
            $msg = "成功生成 {$_POST['num']} 张卡密";
        } catch (Exception $e) { $errorMsg = "生成失败: " . htmlspecialchars($e->getMessage()); }
    } elseif (isset($_POST['del_card'])) {
        $db->deleteCard($_POST['id']); $msg = "卡密已删除";
    } elseif (isset($_POST['unbind_card'])) {
        $res = $db->resetDeviceBindingByCardId($_POST['id']); $msg = $res ? "设备解绑成功" : "解绑失败";
    } elseif (isset($_POST['update_pwd'])) {
        $pwd1 = $_POST['new_pwd'] ?? ''; $pwd2 = $_POST['confirm_pwd'] ?? '';
        if (empty($pwd1)) { $errorMsg = "密码不能为空"; } elseif ($pwd1 !== $pwd2) { $errorMsg = "两次输入的密码不一致"; } else {
            $db->updateAdminPassword($pwd1); setcookie('admin_trust', '', time() - 3600, '/');
            session_destroy(); header('Location: login.php'); exit;
        }
    } elseif (isset($_POST['update_settings'])) {
        try {
            $uploadDir = 'uploads/'; 
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $processUpload = function($inputName, $existingValue) use ($uploadDir) {
                if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES[$inputName]['tmp_name'];
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_file($finfo, $tmpName); finfo_close($finfo);
                    } else { $check = getimagesize($tmpName); $mime = $check ? $check['mime'] : ''; }
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon'];
                    if (!in_array($mime, $allowedMimes)) throw new Exception("文件类型不安全或无法识别，仅支持图片。");
                    $ext = pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION);
                    $newFilename = $inputName . '_' . md5(uniqid(mt_rand(), true)) . '.' . $ext;
                    if (move_uploaded_file($tmpName, $uploadDir . $newFilename)) return $uploadDir . $newFilename;
                }
                return $existingValue; 
            };
            
            $settingsData = [
                'site_title' => trim($_POST['site_title']),
                'favicon' => $processUpload('favicon_file', trim($_POST['favicon'])),
                'admin_avatar' => $processUpload('admin_avatar_file', trim($_POST['admin_avatar'])),
                'bg_pc' => $processUpload('bg_pc_file', trim($_POST['bg_pc'])),
                'bg_mobile' => $processUpload('bg_mobile_file', trim($_POST['bg_mobile'])),
                'bg_blur' => isset($_POST['bg_blur']) ? '1' : '0'
            ];
            $db->saveSystemSettings($settingsData);
            $newUsername = trim($_POST['admin_username']); if(!empty($newUsername)) $db->updateAdminUsername($newUsername);
            $msg = "系统配置已保存"; echo "<script>alert('$msg');location.href='cards.php?tab=settings';</script>"; exit;
        } catch(Exception $e) { $errorMsg = "保存失败: " . htmlspecialchars($e->getMessage()); }
    } elseif (isset($_POST['update_mock_data'])) {
        try {
            $newMock = [
                'counts_enabled' => isset($_POST['mock_counts_enabled']) ? 1 : 0,
                'apps_enabled' => isset($_POST['mock_apps_enabled']) ? 1 : 0,
                'types_enabled' => isset($_POST['mock_types_enabled']) ? 1 : 0,
                'total' => intval($_POST['mock_total']),
                'active' => intval($_POST['mock_active']),
                'apps' => intval($_POST['mock_apps']),
                'unused' => intval($_POST['mock_unused']),
                'app_stats_json' => trim($_POST['mock_app_stats_json']),
                'type_stats_json' => trim($_POST['mock_type_stats_json'])
            ];
            if ($newMock['apps_enabled'] == 1&& json_decode($newMock['app_stats_json']) === null) throw new Exception("应用占比数据的JSON 格式错误");
            if ($newMock['types_enabled'] == 1 && json_decode($newMock['type_stats_json']) === null) throw new Exception("类型分布数据的 JSON 格式错误");
            file_put_contents($mockFile, json_encode($newMock)); $mockSettings = $newMock; $msg = "仪表盘自定义数据已保存";
        } catch (Exception $e) { $errorMsg = "保存失败: " . htmlspecialchars($e->getMessage()); }
    } elseif (isset($_POST['ban_card'])) {
        $db->updateCardStatus($_POST['id'], 2); $msg = "卡密已封禁";
    } elseif (isset($_POST['unban_card'])) {
        $db->updateCardStatus($_POST['id'], 1); $msg = "卡密已解除封禁";
    } elseif (isset($_POST['clean_expired'])) {
        $count = $db->cleanupExpiredCards(); $msg = "已清理 {$count} 张过期卡密";
    }
}

$dashboardData = ['stats'=>['total'=>0,'unused'=>0,'active'=>0], 'app_stats'=>[], 'chart_types'=>[]];
$logs = []; $activeDevices = []; $cardList = []; $totalCards = 0; $totalPages = 0;

try { $dashboardData = $db->getDashboardData(); } catch (Throwable $e) { }
try { $logs = $db->getUsageLogs(20, 0); } catch (Throwable $e) { }
try { $activeDevices = $db->getActiveDevices(); } catch (Throwable $e) { }

$display_stats = [
    'total' => $dashboardData['stats']['total'],
    'active' => $dashboardData['stats']['active'],
    'apps' => count($appList),
    'unused' => $dashboardData['stats']['unused']
];
if ($mockSettings['counts_enabled'] == 1) {
    $display_stats['total'] = $mockSettings['total'];
    $display_stats['active'] = $mockSettings['active'];
    $display_stats['apps'] = $mockSettings['apps'];
    $display_stats['unused'] = $mockSettings['unused'];
}
if ($mockSettings['apps_enabled'] == 1) {
    $mockAppStats = json_decode($mockSettings['app_stats_json'], true);
    if ($mockAppStats) $dashboardData['app_stats'] = $mockAppStats;
}
if ($mockSettings['types_enabled'] == 1) {
    $mockTypeStats = json_decode($mockSettings['type_stats_json'], true);
    if ($mockTypeStats) $dashboardData['chart_types'] = $mockTypeStats;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
if ($perPage < 5) $perPage = 20; if ($perPage > 500) $perPage = 500;

$statusFilter = null; $filterStr = $_GET['filter'] ?? 'all';
if ($filterStr === 'unused') $statusFilter = 0; elseif ($filterStr === 'active') $statusFilter = 1; elseif ($filterStr === 'banned') $statusFilter = 2;

$appFilter = isset($_GET['app_id']) && $_GET['app_id'] !== '' ? intval($_GET['app_id']) : null;
$typeFilter = ($appFilter !== null && isset($_GET['type']) && $_GET['type'] !== '') ? $_GET['type'] : null;

$isSearching = isset($_GET['q']) && !empty($_GET['q']); $offset = ($page - 1) * $perPage;

try {
    if ($isSearching) {
        $allResults = $db->searchCards($_GET['q']); 
        $totalCards = count($allResults); 
        $cardList = array_slice($allResults, $offset, $perPage); 
    } elseif ($appFilter !== null || $typeFilter !== null) { 
        $totalCards = $db->getTotalCardCount($statusFilter, $appFilter, $typeFilter); 
        $cardList = $db->getCardsPaginated($perPage, $offset, $statusFilter, $appFilter, $typeFilter); 
    } else { 
        $totalCards = $db->getTotalCardCount($statusFilter, null, $typeFilter); 
        $cardList = $db->getCardsPaginated($perPage, $offset, $statusFilter, null, $typeFilter); 
    }
} catch (Throwable $e) { 
    try {
        if ($appFilter !== null) {
            $totalCards = $db->getTotalCardCount($statusFilter, $appFilter); 
            $cardList = $db->getCardsPaginated($perPage, $offset, $statusFilter, $appFilter);
        } else {
            $totalCards = $db->getTotalCardCount($statusFilter); 
            $cardList = $db->getCardsPaginated($perPage, $offset, $statusFilter);
        }
    } catch (Throwable $ex) {}
}
$totalPages = ceil($totalCards / $perPage); if ($totalPages > 0&& $page > $totalPages) { $page = $totalPages; }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
<title><?php echo htmlspecialchars($conf_site_title); ?></title>

<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($conf_site_title); ?>">
<meta name="format-detection" content="telephone=no">

<link rel="icon" href="<?php echo htmlspecialchars($conf_favicon); ?>" type="image/png">
<link rel="apple-touch-icon" href="<?php echo htmlspecialchars($conf_avatar); ?>">

<link href="assets/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="assets/css/cards.css?v=<?php echo time(); ?>" rel="stylesheet">
<script src="assets/js/chart.js"></script>
<style>
body {
    background-image: url('<?php echo htmlspecialchars($conf_bg_pc); ?>');
}
body::before {
    backdrop-filter: blur(<?php echo $conf_bg_blur==='1'?'4px':'0px';?>);
    -webkit-backdrop-filter: blur(<?php echo $conf_bg_blur==='1'?'4px':'0px';?>);
    background: rgba(255, 255, 255, 0.02);
}

@media(max-width:768px){
    body {
        background-image: url('<?php echo htmlspecialchars($conf_bg_mobile); ?>') !important; 
    }
}

/*========== 极致透明 UI 覆盖层 ========== */
#sidebar, .mobile-bottom-nav, .panel, .stat-card, .chrome-tab, .modal-content, .announcement-box, .poem-box, .tip-card, .setting-card, .nav-segment {
    background: rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(2px) !important;
    -webkit-backdrop-filter: blur(2px) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02) !important;
}

/*修复：清除多标签页容器的外框 */
.chrome-tabs {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

header {
    background: transparent !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    border-bottom: none !important;
}

.breadcrumb-bar {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}

.form-control, select, textarea {
    background: rgba(255, 255, 255, 0.2) !important;
    backdrop-filter: blur(2px) !important;
    -webkit-backdrop-filter: blur(2px) !important;
    border: 1px solid rgba(255, 255, 255, 0.4) !important;
}

table th {
    background: rgba(0, 0, 0, 0.03) !important;
}
table tr:hover {
    background: rgba(255, 255, 255, 0.2) !important;
}

.user-panel {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
}

.panel-head {
    border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
}

.pagination .page-btn {
    background: rgba(255, 255, 255, 0.2) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
}
</style>
</head>
<body>
<!-- Desktop Sidebar -->
<aside id="sidebar">
    <div class="brand">
        <img src="<?php echo htmlspecialchars($conf_avatar); ?>" alt="Logo" class="brand-logo"> 
        <div style="display:flex; flex-direction:column; justify-content:center;">
            <span style="line-height:1; font-size:15px;"><?php echo htmlspecialchars(mb_strimwidth($conf_site_title, 0, 16, '..')); ?></span>
            <span style="font-size:11px; color:rgba(0,0,0,0.5); font-weight:600; margin-top:4px;">Pro Enterprise</span>
        </div>
    </div>
    <div class="nav">
        <div class="nav-label">概览</div>
        <a href="?tab=dashboard" class="<?=$tab=='dashboard'?'active':''?>"><i class="fas fa-chart-pie"></i> 仪表盘</a>
        <div class="nav-label">核心业务</div>
        <a href="?tab=apps" class="<?=$tab=='apps'?'active':''?>"><i class="fas fa-cubes"></i> 应用列表</a>
        <a href="?tab=list" class="<?=$tab=='list'?'active':''?>"><i class="fas fa-database"></i> 卡密库存</a>
        <a href="?tab=create" class="<?=$tab=='create'?'active':''?>"><i class="fas fa-plus-circle"></i> 批量制卡</a>
        <div class="nav-label">系统监控</div>
        <a href="?tab=logs" class="<?=$tab=='logs'?'active':''?>"><i class="fas fa-history"></i> 审计日志</a>
        <a href="?tab=settings" class="<?=$tab=='settings'?'active':''?>"><i class="fas fa-cog"></i> 全局配置</a>
    </div>
    <div class="user-panel">
        <img src="<?php echo htmlspecialchars($conf_avatar); ?>" alt="Admin" class="avatar-img">
        <div class="user-info"><div><?php echo htmlspecialchars($currentAdminUser); ?></div><span>超级管理员</span></div>
        <a href="?logout=1" class="logout" title="退出登录"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</aside>

<!-- iOS26 Floating Navigation -->
<nav class="mobile-bottom-nav" id="floatingNav">
    <a href="?tab=dashboard" class="mobile-nav-item <?=$tab=='dashboard'?'active':''?>">
        <i class="fas fa-chart-pie"></i><span>首页</span>
    </a>
    <a href="?tab=apps" class="mobile-nav-item <?=$tab=='apps'?'active':''?>">
        <i class="fas fa-cubes"></i><span>应用</span>
    </a>
    <a href="?tab=list" class="mobile-nav-item <?=$tab=='list'?'active':''?>">
        <i class="fas fa-database"></i><span>库存</span>
    </a>
    <a href="?tab=create" class="mobile-nav-item <?=$tab=='create'?'active':''?>">
        <i class="fas fa-plus-circle"></i><span>制卡</span>
    </a>
    <a href="?tab=settings" class="mobile-nav-item <?=$tab=='settings'?'active':''?>">
        <i class="fas fa-cog"></i><span>设置</span>
    </a>
</nav>

<main>
    <header>
        <div style="display:flex; align-items:center;">
            <div class="title"><?=$currentTitle?></div>
        </div>
        <?php if($msg): ?><div style="font-size:13px; color:#059669; background:rgba(16, 185, 129, 0.15); padding:8px 16px; border-radius:12px; font-weight:600; display:flex; align-items:center; gap:6px; backdrop-filter:blur(10px);"><i class="fas fa-check-circle"></i> <?=$msg?></div><?php endif; ?><?php if($errorMsg): ?><div style="font-size:13px; color:#dc2626; background:rgba(239, 68, 68, 0.15); padding:8px 16px; border-radius:12px; font-weight:600; display:flex; align-items:center; gap:6px; backdrop-filter:blur(10px);"><i class="fas fa-exclamation-circle"></i> <?=$errorMsg?></div><?php endif; ?>
    </header><div class="breadcrumb-bar">GuYi System<i class="fas fa-chevron-right" style="font-size:8px; opacity:0.5;"></i> <?=$currentTitle?></div>
    <div class="chrome-tabs" id="tabs-container"></div>
    <div class="content">
        <!-- Dashboard Content -->
        <?php if($tab == 'dashboard'): ?>
            <div class="dashboard-header-grid">
                <div class="panel announcement-box" style="margin:0;">
                    <div style="padding: 24px; display: flex; gap: 20px; align-items: flex-start;">
                        <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); display:flex; align-items:center; justify-content:center; color:#3b82f6; font-size: 20px; flex-shrink: 0;"><i class="fas fa-bullhorn"></i></div>
                        <div style="flex: 1; width: 100%;">
                            <div style="font-weight: 700; font-size: 17px; margin-bottom: 8px; color: #1e293b; display: flex; justify-content: space-between; align-items:center;">
                                <span>欢迎，<?php echo htmlspecialchars($currentAdminUser); ?></span>
                                <span style="font-size: 11px; background: #6366f1; color: white; padding: 4px 10px; border-radius: 20px; font-weight: 600; letter-spacing:0.5px;">V26PRO</span>
                            </div>
                            
                            <!-- 官方公告栏 (防二改混淆处理) -->
                            <div style="font-size: 13px; color: #475569; margin-bottom: 12px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                <span style="display: flex; align-items: center; gap: 4px; background: rgba(59, 130, 246, 0.1); padding: 4px 10px; border-radius: 6px;"><i class="fab fa-qq" style="color:#3b82f6;"></i><?=base64_decode('5a6Y5pa5576k77ya')?><strong style="color:#3b82f6;"><?=base64_decode('MTA3NzY0MzE4NA==')?></strong></span>
                                <span style="display: flex; align-items: center; gap: 4px; background: rgba(16, 185, 129, 0.1); padding: 4px 10px; border-radius: 6px;"><i class="fas fa-globe" style="color:#10b981;"></i><?=base64_decode('5a6Y572R77ya')?><a href="<?=base64_decode('aHR0cHM6Ly/lj6/niLEudG9wLw==')?>" target="_blank" style="color:#10b981; font-weight:600; text-decoration:none;"><?=base64_decode('aHR0cHM6Ly/lj6/niLEudG9wLw==')?></a></span>
                            </div>

                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.2); display: flex; gap: 16px;">
                                <div style="display:flex; flex-direction:column; gap:4px;">
                                    <span style="font-size:11px; opacity:0.6; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">Current IP</span>
                                    <div style="font-family:'JetBrains Mono'; font-size:13px; font-weight:500;"><i class="fas fa-circle" style="color:#10b981; font-size:8px; margin-right:4px;"></i> <?php echo $current_ip; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="panel poem-box" style="margin:0;">
                    <div style="z-index: 2; width: 100%; display: flex; flex-direction: column; justify-content: center; height: 100%;">
                         <div style="font-size: 11px; color: var(--primary); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;"><i class="fas fa-cloud-sun"></i> 每日一诗</div>
                        <div id="poem_content" style="font-family: 'Kaiti', 'STKaiti', 'SimSun', serif; font-size: 22px; font-weight: 700; color: #1e293b; margin-bottom: 12px; line-height: 1.4; text-shadow: 0 2px 10px rgba(0,0,0,0.05); min-height: 30px;"><i class="fas fa-spinner fa-spin" style="font-size: 16px; opacity: 0.5;"></i> 正在以此为题赋诗一首...
                        </div>
                <div id="poem_info" style="font-size: 13px; color: #64748b; font-weight: 500; display: flex; align-items: center; gap: 10px;"></div>
                    </div>
                    <i class="fas fa-feather-alt" style="position: absolute; right: -10px; bottom: -20px; font-size: 100px; opacity: 0.05; transform: rotate(-20deg); pointer-events: none;"></i>
                </div>
            </div>

            <div class="grid-4">
                <div class="stat-card"><div class="stat-label">总库存量</div><div class="stat-value"><?php echo number_format($display_stats['total']); ?></div><div class="stat-icon"><i class="fas fa-layer-group"></i></div></div>
                <div class="stat-card"><div class="stat-label">活跃设备</div><div class="stat-value"><?php echo number_format($display_stats['active']); ?></div><div class="stat-icon"><i class="fas fa-wifi"></i></div></div>
                <div class="stat-card"><div class="stat-label">接入应用</div><div class="stat-value"><?php echo number_format($display_stats['apps']); ?></div><div class="stat-icon"><i class="fas fa-cubes"></i></div></div>
                <div class="stat-card"><div class="stat-label">待售库存</div><div class="stat-value"><?php echo number_format($display_stats['unused']); ?></div><div class="stat-icon"><i class="fas fa-tag"></i></div></div></div>
            <div class="dashboard-split-grid">
                 <div class="panel">
                    <div class="panel-head"><span class="panel-title"><i class="fas fa-chart-bar" style="color:#6366f1;"></i> 应用库存占比</span></div>
                    <div class="table-responsive">
                        <table><thead><tr><th>应用名称</th><th>卡密数</th><th>占比</th></tr></thead><tbody><?php 
                                $totalCards = $display_stats['total'] > 0 ? $display_stats['total'] : 1; 
                                foreach($dashboardData['app_stats'] as $stat): 
                                    if(empty($stat['app_name'])) continue; 
                                    $percent = round(($stat['count'] / $totalCards) * 100, 1); 
                                ?>
                                <tr><td data-label="应用名称" style="font-weight:600;"><?php echo htmlspecialchars($stat['app_name']); ?></td><td data-label="卡密数"><?php echo number_format($stat['count']); ?></td><td data-label="占比"><div style="display:flex;align-items:center;gap:12px;justify-content: flex-end;"><div style="flex:1;height:8px;background:rgba(0,0,0,0.05);border-radius:4px;overflow:hidden;min-width:60px;max-width:100px;"><div style="width:<?=$percent?>%;height:100%;background:linear-gradient(90deg, #6366f1, #818cf8); border-radius:4px;"></div></div><span style="font-size:12px;color:#64748b;font-weight:600;width:36px;"><?=$percent?>%</span></div></td></tr>
                                <?php endforeach; ?>
                        </tbody></table>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head"><span class="panel-title"><i class="fas fa-chart-pie" style="color:#10b981;"></i> 类型分布</span></div>
                    <div style="height:200px;padding:20px;"><canvas id="typeChart"></canvas></div>
                </div>
            </div><div class="panel">
                <div class="panel-head"><span class="panel-title"><i class="fas fa-satellite-dish" style="color:#f59e0b;"></i> 实时活跃设备</span><a href="?tab=list" class="btn btn-primary" style="font-size:12px; padding:6px 14px;">全部列表</a></div>
                <div class="table-responsive">
                    <table><thead><tr><th>所属应用</th><th>卡密</th><th>设备指纹</th><th>激活时间</th><th>到期时间</th></tr></thead><tbody>
                            <?php foreach(array_slice($activeDevices, 0, 5) as $dev): ?>
                            <tr><td data-label="所属应用"><?php if($dev['app_id']>0): ?><span class="app-tag"><?=htmlspecialchars($dev['app_name'])?></span><?php else: ?><span style="color:#94a3b8;font-size:12px;">未分类</span><?php endif; ?></td><td data-label="卡密"><span class="code"><?php echo $dev['card_code']; ?></span></td><td data-label="设备指纹" style="font-family:'JetBrains Mono'; font-size:12px; color:#64748b;"><?php echo htmlspecialchars(substr($dev['device_hash'],0,12)).'...'; ?></td><td data-label="激活时间"><?php echo date('H:i', strtotime($dev['activate_time'])); ?></td><td data-label="到期时间"><span class="badge badge-success"><?php echo date('m-d H:i', strtotime($dev['expire_time'])); ?></span></td></tr>
                            <?php endforeach; ?>
                    </tbody></table>
                </div>
            </div>
        <?php endif; ?>

        <!-- App Management Content -->
        <?php if($tab == 'apps'): ?>
            <?php 
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $currentScriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
            $apiUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . $currentScriptDir . "/Verifyfile/api.php";
            ?>
            <div class="nav-segment" style="margin-top:20px;">
                <button onclick="switchAppView('apps')" id="btn_apps" class="nav-pill active"><i class="fas fa-list-ul" style="margin-right:6px;"></i>应用列表</button>
                <button onclick="switchAppView('vars')" id="btn_vars" class="nav-pill"><i class="fas fa-sliders-h" style="margin-right:6px;"></i>变量管理</button>
            </div>
             <div id="view_apps">
                <div class="panel">
                    <div class="panel-head"><span class="panel-title">已接入应用列表</span><span style="font-size:12px;color:#94a3b8; font-weight:500;">共<?=count($appList)?> 个应用</span></div>
                    <div class="table-responsive">
                        <table><thead><tr><th>应用信息</th><th>App Key</th><th>数据统计</th><th>状态</th><th>操作</th></tr></thead><tbody>
                                <?php foreach($appList as $app): ?>
                                <tr><td data-label="应用信息"><div style="font-weight:600; color:var(--text-main); font-size:14px;"><?=htmlspecialchars($app['app_name'])?></div><div style="font-size:11px;color:#94a3b8; margin-top:4px; display:flex; align-items:center; flex-wrap:wrap;"><?php if(!empty($app['app_version'])): ?><span class="badge badge-neutral" style="padding:2px 6px; margin-right:6px; font-weight:600; font-size:10px;"><?=htmlspecialchars($app['app_version'])?></span><?php endif; ?><?=htmlspecialchars($app['notes']?: '暂无备注')?></div></td><td data-label="App Key"><div class="app-key-box" onclick="copy('<?=$app['app_key']?>')" style="cursor:pointer;" title="点击复制"><i class="fas fa-key" style="font-size:10px; color:#94a3b8;"></i><span><?=$app['app_key']?></span></div></td><td data-label="数据统计"><span class="badge badge-primary"><?=number_format($app['card_count'])?> 张</span></td><td data-label="状态"><?=$app['status']==1 ? '<span class="badge badge-success">正常</span>' : '<span class="badge badge-danger">禁用</span>'?></td><td data-label="操作"><button type="button" onclick="openEditApp(<?=$app['id']?>, '<?=addslashes($app['app_name'])?>', '<?=addslashes($app['app_version'])?>', '<?=addslashes($app['notes'])?>')" class="btn btn-primary btn-icon" title="编辑"><i class="fas fa-edit"></i></button> <button type="button" onclick="singleAction('toggle_app', <?=$app['id']?>)" class="btn <?=$app['status']==1?'btn-warning':'btn-secondary'?> btn-icon" title="<?=$app['status']==1?'禁用':'启用'?>"><i class="fas <?=$app['status']==1?'fa-ban':'fa-check'?>"></i></button> <?php if($app['card_count'] > 0): ?><button type="button" onclick="alert('无法删除：该应用下仍有 <?=number_format($app['card_count'])?> 张卡密。\n\n请先进入「卡密库存」，筛选该应用并删除所有卡密后，方可删除应用。')" class="btn btn-secondary btn-icon" style="cursor:pointer; opacity: 0.6;" title="请先清空卡密"><i class="fas fa-trash"></i></button><?php else: ?><button type="button" onclick="singleAction('delete_app', <?=$app['id']?>)" class="btn btn-danger btn-icon" title="删除"><i class="fas fa-trash"></i></button><?php endif; ?></td></tr>
                                <?php endforeach; ?>
                                <?php if(count($appList) == 0): ?><tr><td colspan="5" style="text-align:center;padding:40px;color:#94a3b8;">暂无应用，请点击下方创建</td></tr><?php endif; ?>
                        </tbody></table>
                    </div>
                </div>
                <div class="grid-4" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                    <details class="panel" open>
                        <summary class="panel-head"><span class="panel-title"><i class="fas fa-plus-circle" style="margin-right:8px;color:var(--primary);"></i>创建新应用</span></summary>
                        <div style="padding:28px;">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?=$csrf_token?>"><input type="hidden" name="create_app" value="1">
                                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;color:#475569;">应用名称</label><input type="text" name="app_name" class="form-control" required placeholder="例如: Android客户端">
                                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;color:#475569;">应用版本号</label><input type="text" name="app_version" class="form-control" placeholder="例如: v1.0">
                                <label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;color:#475569;">备注说明</label><textarea name="app_notes" class="form-control" style="height:80px;resize:none;" placeholder="可选：填写应用用途描述"></textarea>
                                <button type="submit" class="btn btn-primary" style="width:100%; padding:12px;">立即创建</button>
                            </form>
                        </div>
                    </details><details class="panel">
                        <summary class="panel-head"><span class="panel-title"><i class="fas fa-code" style="margin-right:8px;color:#8b5cf6;"></i>API 接口信息</span></summary>
                        <div style="padding:28px;">
                            <label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;color:#475569;">接口地址</label>
                            <div class="app-key-box" style="margin-bottom:16px; display:flex; justify-content:space-between; width:100%; padding:12px;"><span style="font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo $apiUrl; ?></span><i class="fas fa-copy" style="cursor:pointer;color:#6366f1;" onclick="copy('<?php echo $apiUrl; ?>')"></i></div>
                            <div style="font-size:12px;color:#64748b; line-height:1.5;">通过 AppKey 验证卡密或获取公开变量。请妥善保管您的 AppKey。</div>
                        </div>
                    </details>
                </div>
                <div id="editAppModal" class="modal">
                    <div class="modal-bg" onclick="closeEditApp()"></div>
                    <div class="modal-content">
                        <div style="font-size:18px; font-weight:700; margin-bottom:20px; color:#1e293b;">编辑应用信息</div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>"><input type="hidden" name="edit_app" value="1"><input type="hidden" id="edit_app_id" name="app_id">
                            <label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;">应用名称</label><input type="text" id="edit_app_name" name="app_name" class="form-control" required>
                            <label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;">应用版本号</label><input type="text" id="edit_app_version" name="app_version" class="form-control">
                            <label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;">备注说明</label><textarea id="edit_app_notes" name="app_notes" class="form-control" style="height:80px;resize:none;" placeholder="输入内容..."></textarea>
                            <div style="display:flex; gap:12px; margin-top:8px;"><button type="button" class="btn btn-secondary" onclick="closeEditApp()" style="flex:1;">取消</button><button type="submit" class="btn btn-primary" style="flex:1;">保存修改</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div id="view_vars" style="display:none;">
                <div class="panel">
                    <div class="panel-head"><span class="panel-title">云端变量管理</span></div>
                    <div class="table-responsive">
                        <table><thead><tr><th>所属应用</th><th>键名 (Key)</th><th>值 (Value)</th><th>权限</th><th>操作</th></tr></thead><tbody>
                                <?php $hasVars = false; foreach($appList as $app) { $vars = $db->getAppVariables($app['id']); foreach($vars as $v) { $hasVars = true; echo "<tr><td data-label='所属应用'><span class='app-tag'>".htmlspecialchars($app['app_name'])."</span></td><td data-label='键名'><span class='code' style='color:#ec4899;background:rgba(236, 72, 153, 0.1);border-color:rgba(236, 72, 153, 0.2);'>".htmlspecialchars($v['key_name'])."</span></td><td data-label='变量值'><div class='app-key-box' style='max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;'>".htmlspecialchars($v['value'])."</div></td><td data-label='权限'>".($v['is_public'] ? '<span class="badge badge-success">公开</span>' : '<span class="badge badge-warn">私有</span>')."</td><td data-label='操作'><button type='button' onclick=\"openEditVar({$v['id']}, '".addslashes($v['key_name'])."', '".str_replace(array("\r\n", "\r", "\n"), '\n', addslashes($v['value']))."', {$v['is_public']})\" class='btn btn-primary btn-icon' title='编辑'><i class='fas fa-edit'></i></button> <button type='button' onclick=\"singleAction('del_var', {$v['id']},'var_id')\" class='btn btn-danger btn-icon' title='删除'><i class='fas fa-trash'></i></button></td></tr>"; } } if(!$hasVars) echo "<tr><td colspan='5' style='text-align:center;padding:40px;color:#94a3b8;'>暂无变量数据</td></tr>"; ?>
                        </tbody></table>
                    </div>
                </div>
                <details class="panel" open>
                    <summary class="panel-head"><span class="panel-title">添加变量</span></summary>
                    <div style="padding:28px;">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>"><input type="hidden" name="add_var" value="1">
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:24px;"><div><label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;">所属应用</label><select name="var_app_id" class="form-control" required><option value="">-- 请选择 --</option><?php foreach($appList as $app): ?><option value="<?=$app['id']?>"><?=htmlspecialchars($app['app_name'])?></option><?php endforeach; ?></select></div><div><label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;">键名 (Key)</label><input type="text" name="var_key" class="form-control" placeholder="例如: update_url" required></div></div>
                            <label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;">变量值</label><textarea name="var_value" class="form-control" style="height:80px;resize:none;" placeholder="输入内容..."></textarea>
                            <div style="margin-bottom:24px; display:flex; align-items:center;"><input type="checkbox" id="var_public" name="var_public" value="1" style="width:18px;height:18px;margin-right:10px;accent-color:var(--primary);"><label for="var_public" style="font-size:13px; font-weight:600;">设为公开变量 (Public)</label></div>
                            <button type="submit" class="btn btn-success" style="width:100%; padding:12px;">保存变量</button>
                        </form>
                    </div>
                </details>
                <div id="editVarModal" class="modal">
                    <div class="modal-bg" onclick="closeEditVar()"></div>
                    <div class="modal-content">
                        <div style="font-size:18px; font-weight:700; margin-bottom:20px;">编辑变量</div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>"><input type="hidden" name="edit_var" value="1"><input type="hidden" id="edit_var_id" name="var_id">
                            <label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;">键名 (Key)</label><input type="text" id="edit_var_key" name="var_key" class="form-control" required>
                            <label style="display:block;margin-bottom:8px;font-weight:600;font-size:13px;">变量值</label><textarea id="edit_var_value" name="var_value" class="form-control" style="height:80px;resize:none;" placeholder="输入内容..."></textarea>
                            <div style="margin-bottom:24px; display:flex; align-items:center;"><input type="checkbox" id="edit_var_public" name="var_public" value="1" style="width:18px;height:18px;margin-right:10px;accent-color:var(--primary);"><label for="edit_var_public" style="font-size:13px; font-weight:600;">设为公开变量 (Public)</label></div>
                            <div style="display:flex; gap:12px;"><button type="button" class="btn btn-secondary" onclick="closeEditVar()" style="flex:1;">取消</button><button type="submit" class="btn btn-primary" style="flex:1;">保存修改</button></div>
                        </form>
                    </div>
                </div>
            </div><script>
            function switchAppView(v){document.getElementById('btn_apps').classList.toggle('active',v==='apps');document.getElementById('btn_vars').classList.toggle('active',v==='vars');document.getElementById('view_apps').style.display=v==='apps'?'block':'none';document.getElementById('view_vars').style.display=v==='vars'?'block':'none'}
            function openEditVar(id,k,v,pub){document.getElementById('edit_var_id').value=id;document.getElementById('edit_var_key').value=k;document.getElementById('edit_var_value').value=v;document.getElementById('edit_var_public').checked=(pub==1);document.getElementById('editVarModal').classList.add('show')}
            function closeEditVar(){document.getElementById('editVarModal').classList.remove('show')}
            function openEditApp(id,n,v,no){document.getElementById('edit_app_id').value=id;document.getElementById('edit_app_name').value=n;document.getElementById('edit_app_version').value=v;document.getElementById('edit_app_notes').value=no;document.getElementById('editAppModal').classList.add('show')}
            function closeEditApp(){document.getElementById('editAppModal').classList.remove('show')}
            </script>
        <?php endif; ?>

        <!-- List Content -->
        <?php if($tab == 'list'): ?>
             <div class="panel" style="margin-bottom: 24px; margin-top:20px;">
                <div class="panel-head"><span class="panel-title"><i class="fas fa-filter" style="color:var(--primary);margin-right:8px;"></i>库存筛选</span></div>
                <div style="padding: 24px;">
                    <form method="GET" style="display:flex; gap:20px; flex-wrap:wrap; align-items:flex-end; margin:0;">
                        <input type="hidden" name="tab" value="list">
                        <?php if(isset($_GET['filter'])): ?><input type="hidden" name="filter" value="<?=htmlspecialchars($_GET['filter'])?>"><?php endif; ?>
                        
                        <div style="flex:1; min-width:200px;">
                            <label style="font-size:12px; font-weight:600; color:#64748b; margin-bottom:8px; display:block;">归属应用</label>
                            <select name="app_id" class="form-control" style="margin: 0;" onchange="this.form.submit()">
                                <option value="">-- 全部应用 --</option>
                                <?php foreach($appList as $app): ?>
                                    <option value="<?=$app['id']?>" <?=($appFilter === $app['id']) ? 'selected' : ''?>><?=htmlspecialchars($app['app_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <?php if ($appFilter !== null): ?>
                        <div style="flex:1; min-width:200px; animation: fadeIn 0.3s ease;">
                            <label style="font-size:12px; font-weight:600; color:#64748b; margin-bottom:8px; display:block;">卡密分类</label>
                            <select name="type" class="form-control" style="margin: 0;" onchange="this.form.submit()">
                                <option value="">-- 全部类型 --</option>
                                <?php foreach(CARD_TYPES as $typeId => $typeConfig): ?>
                                    <option value="<?=$typeId?>" <?=((string)$typeFilter === (string)$typeId) ? 'selected' : ''?>><?=$typeConfig['name']?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php if ($appFilter !== null || $typeFilter !== null || !empty($_GET['q'])): ?>
                <div class="nav-segment" style="margin-bottom: 24px;">
                    <?php $buildFilterUrl = function($fVal) use ($appFilter, $typeFilter) {
                            $p = ['tab'=>'list', 'filter'=>$fVal];
                            if($appFilter !== null) $p['app_id'] = $appFilter;
                            if($typeFilter !== null) $p['type'] = $typeFilter;
                            return '?' . http_build_query($p);
                        };
                    ?>
                    <a href="<?=$buildFilterUrl('all')?>" class="nav-pill <?=$filterStr=='all'?'active':''?>">全部</a>
                    <a href="<?=$buildFilterUrl('unused')?>" class="nav-pill <?=$filterStr=='unused'?'active':''?>">未激活</a>
                    <a href="<?=$buildFilterUrl('active')?>" class="nav-pill <?=$filterStr=='active'?'active':''?>">已激活</a>
                    <a href="<?=$buildFilterUrl('banned')?>" class="nav-pill <?=$filterStr=='banned'?'active':''?>">已封禁</a>
                </div>
                <div class="panel">
                    <form id="batchForm" method="POST">
                        <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
                        <div class="panel-head">
                            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;width:100%;">
                                <input type="text" placeholder="模糊搜索：卡密、备注、类型、设备指纹或应用..." value="<?=$_GET['q']??''?>" class="form-control" style="margin:0;min-width:200px;flex:1;" onkeydown="if(event.key==='Enter'){event.preventDefault();window.location='?tab=list&q='+this.value;}">
                                <a href="?tab=list" class="btn btn-icon" style="background:rgba(255,255,255,0.4);color:#64748b;"><i class="fas fa-sync"></i></a><a href="?tab=create" class="btn btn-primary btn-icon"><i class="fas fa-plus"></i></a>
                            </div>
                            <div style="width:100%; display:flex; gap:8px; margin-top:16px; overflow-x:auto; padding-bottom:4px; -webkit-overflow-scrolling: touch;"><button type="submit" name="batch_export" value="1" class="btn" style="background:#6366f1;color:white;flex-shrink:0;">导出</button><button type="button" onclick="submitBatch('batch_unbind')" class="btn" style="background:#f59e0b;color:white;flex-shrink:0;">解绑</button><button type="button" onclick="batchAddTime()" class="btn" style="background:#10b981;color:white;flex-shrink:0;">加时</button><button type="button" onclick="if(confirm('确定要清理所有已过期的卡密吗？')) { var f=document.createElement('form'); f.method='POST'; var i=document.createElement('input'); i.name='clean_expired'; i.value='1'; var c=document.createElement('input'); c.name='csrf_token'; c.value='<?=$csrf_token?>'; f.appendChild(i); f.appendChild(c); document.body.appendChild(f); f.submit(); }" class="btn btn-warning" style="flex-shrink:0;">清理过期</button><button type="button" onclick="submitBatch('batch_delete')" class="btn btn-danger" style="flex-shrink:0;">删除</button></div>
                        </div>
                        <input type="hidden" name="add_hours" id="addHoursInput">
                        <div class="table-responsive">
                            <table><thead><tr><th style="width:40px;text-align:center;"><input type="checkbox" onclick="toggleAll(this)" style="accent-color:var(--primary);"></th><th>应用</th><th>卡密代码</th><th>类型</th><th>状态</th><th>绑定设备</th><th>备注</th><th>操作</th></tr></thead><tbody>
                                    <?php foreach($cardList as $card): ?>
                <tr><td data-label="选择" style="text-align:center;"><input type="checkbox" name="ids[]" value="<?=$card['id']?>" class="row-check" style="accent-color:var(--primary);"></td><td data-label="所属应用"><?php if($card['app_id']>0 && !empty($card['app_name'])): ?><span class="app-tag"><?=htmlspecialchars($card['app_name'])?></span><?php else: ?><span style="color:#94a3b8;font-size:12px;">未分类</span><?php endif; ?></td><td data-label="卡密代码"><span class="code" onclick="copy('<?=$card['card_code']?>')"><?=$card['card_code']?></span></td><td data-label="类型"><span style="font-weight:600;font-size:12px;"><?=CARD_TYPES[$card['card_type']]['name']??$card['card_type']?></span></td><td data-label="状态"><?php if($card['status']==2): echo '<span class="badge badge-danger">已封禁</span>'; elseif($card['status']==1): echo (strtotime($card['expire_time'])>time()) ? (empty($card['device_hash'])?'<span class="badge badge-warn">待绑定</span>':'<span class="badge badge-success">使用中</span>') : '<span class="badge badge-danger">已过期</span>'; else: echo '<span class="badge badge-neutral">闲置</span>'; endif; ?></td><td data-label="绑定设备"><?php if($card['status']==1&& !empty($card['device_hash'])): ?><div style="font-family:'JetBrains Mono';font-size:11px;color:#64748b;" title="<?=$card['device_hash']?>"><i class="fas fa-mobile-alt" style="margin-right:4px;"></i> <?=substr($card['device_hash'], 0, 8).'...'?></div><?php else: ?><span style="color:#cbd5e1;">-</span><?php endif; ?></td><td data-label="备注" style="color:#94a3b8;font-size:12px;max-width:100px;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($card['notes']?:'-')?></td><td data-label="操作" style="display:flex;gap:6px;justify-content: flex-end;"><?php if($card['status']==1 && !empty($card['device_hash'])): ?><button type="button" onclick="singleAction('unbind_card', <?=$card['id']?>)" class="btn btn-warning btn-icon" title="解绑"><i class="fas fa-unlink"></i></button><?php endif; ?> <?php if($card['status']!=2): ?><button type="button" onclick="singleAction('ban_card', <?=$card['id']?>)" class="btn btn-secondary btn-icon" style="color:#ef4444;"><i class="fas fa-ban"></i></button><?php else: ?><button type="button" onclick="singleAction('unban_card', <?=$card['id']?>)" class="btn btn-secondary btn-icon" style="color:#10b981;"><i class="fas fa-unlock"></i></button><?php endif; ?><button type="button" onclick="singleAction('del_card', <?=$card['id']?>)" class="btn btn-danger btn-icon"><i class="fas fa-trash"></i></button></td></tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($cardList)): ?><tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">暂无符合条件的卡密</td></tr><?php endif; ?></tbody></table>
                        </div><div class="pagination">
                            <?php $queryParams = ['tab' => 'list', 'filter' => $filterStr];
                            if (!empty($_GET['q'])) { $queryParams['q'] = $_GET['q']; }
                            if ($appFilter !== null) { $queryParams['app_id'] = $appFilter; }
                            if ($typeFilter !== null) { $queryParams['type'] = $typeFilter; }
                            
                            $pageLimitUrl = $queryParams; $pageLimitUrl['page'] = 1;
                            ?>
                            <select class="form-control" style="width:auto; margin:0 12px 0 0;" onchange="window.location.href='?<?=http_build_query($pageLimitUrl)?>&limit='+this.value"><option value="10" <?=$perPage==10?'selected':''?>>10 条/页</option><option value="20" <?=$perPage==20?'selected':''?>>20 条/页</option><option value="50" <?=$perPage==50?'selected':''?>>50 条/页</option><option value="100" <?=$perPage==100?'selected':''?>>100 条/页</option></select>
                            
                            <?php 
                            $queryParams['limit'] = $perPage;
                            $getUrl = function($p) use ($queryParams) { $queryParams['page'] = $p; return '?' . http_build_query($queryParams); };
                            if($page > 1) { echo '<a href="'.$getUrl($page-1).'" class="page-btn"><i class="fas fa-chevron-left"></i></a>'; }
                            $start = max(1, $page - 2); $end = min($totalPages, $page + 2);
                            if ($start > 1) { echo '<a href="'.$getUrl(1).'" class="page-btn">1</a>'; if ($start > 2) echo '<span class="page-btn" style="border:none;background:transparent;cursor:default;">...</span>'; }
                            for ($i = $start; $i <= $end; $i++) { if ($i == $page) { echo '<span class="page-btn active">'.$i.'</span>'; } else { echo '<a href="'.$getUrl($i).'" class="page-btn">'.$i.'</a>'; } }
                            if ($end < $totalPages) { if ($end < $totalPages - 1) echo '<span class="page-btn" style="border:none;background:transparent;cursor:default;">...</span>'; echo '<a href="'.$getUrl($totalPages).'" class="page-btn">'.$totalPages.'</a>'; }
                            if($page < $totalPages) { echo '<a href="'.$getUrl($page+1).'" class="page-btn"><i class="fas fa-chevron-right"></i></a>'; }
                            ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Create Content -->
        <?php if($tab == 'create'): ?><div class="create-wrapper">
                <div class="panel create-panel">
                    <div class="panel-head"><span class="panel-title"><i class="fas fa-magic" style="color:var(--primary); margin-right:8px;"></i>批量制卡中心</span><span style="font-size:12px; color:#64748b;">快速生成大批量验证卡密</span></div>
                    <div class="create-body">
                        <form method="POST" class="create-form-grid">
                            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>"><input type="hidden" name="gen_cards" value="1">
                            <div class="form-section full-width"><label><i class="fas fa-layer-group"></i> 归属应用 (必选)</label><select name="app_id" class="form-control big-select" required><option value="">-- 请选择目标应用 --</option><?php foreach($appList as $app): if($app['status']==0) continue; ?><option value="<?=$app['id']?>"><?=htmlspecialchars($app['app_name'])?></option><?php endforeach; ?></select></div>
                            <div class="form-section"><label><i class="fas fa-sort-numeric-up-alt"></i> 生成数量</label><input type="number" name="num" class="form-control" value="10" min="1" max="500" placeholder="最大500"></div>
                            <div class="form-section"><label><i class="fas fa-clock"></i> 套餐类型</label><select name="type" class="form-control"><?php foreach(CARD_TYPES as $k=>$v): ?><option value="<?=$k?>"><?=$v['name']?> (<?=$v['duration']>=86400?($v['duration']/86400).'天':($v['duration']/3600).'小时'?>)</option><?php endforeach; ?></select></div>
                            <div class="form-section"><label><i class="fas fa-font"></i> 自定义前缀 (选填)</label><input type="text" name="pre" class="form-control" placeholder="例如: VIP-"></div>
                            <div class="form-section"><label><i class="fas fa-tag"></i> 备注 (选填)</label><input type="text" name="note" class="form-control" placeholder="例如: 代理商批次A"></div>
                            
                            <div class="full-width" style="display:flex; gap:16px; margin-top:10px;">
                                <button type="submit" class="btn btn-primary big-btn" style="flex:1;"><i class="fas fa-bolt"></i> 立即生成</button>
                                <button type="submit" name="auto_export" value="1" class="btn btn-success big-btn" style="flex:1;"><i class="fas fa-file-download"></i> 生成并导出 (TXT)</button>
                            </div>
                        </form><div class="create-decoration"><div class="tip-card"><div class="tip-title">💡 制卡小贴士</div><div class="tip-content">1. 单次生成建议不超过 500 张以保证系统响应速度。<br><br>2. 卡密格式默认为 16 位随机字符，如需区分批次，建议使用"前缀"功能。<br><br>3. <b>新增功能：</b> 点击"生成并导出"可直接下载包含新卡密的 TXT 文件，无需去列表页筛选。</div></div><div style="flex:1; display:flex; align-items:center; justify-content:center; opacity:0.1;"><i class="fas fa-cogs" style="font-size:80px;"></i></div></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Settings Content -->
        <?php if($tab == 'settings'): ?>
            <div class="settings-wrapper">
                <div class="panel settings-panel" style="padding: 24px;">
                    <div class="panel-head" style="margin: -24px -24px 24px; padding: 24px;"><span class="panel-title"><i class="fas fa-sliders-h" style="color:var(--primary); margin-right:8px;"></i>全站与个人设置</span></div>
                    <div class="settings-grid">
                        <div style="display: flex; flex-direction: column; gap: 24px;">
                            <div class="setting-card">
                                <div class="setting-card-title"><i class="fas fa-globe"></i> 基础配置</div>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?=$csrf_token?>"><input type="hidden" name="update_settings" value="1">
                                    <?php if($conf_bg_blur === '1'): ?><input type="hidden" name="bg_blur_default" value="1"><?php endif; ?>
                                    <div class="form-section"><label>网站标题</label><input type="text" name="site_title" class="form-control" value="<?php echo htmlspecialchars($conf_site_title); ?>" placeholder="默认为 GuYi Access"></div>
                                    <div class="form-section" style="margin-top: 12px;"><label>管理员用户名 (显示用)</label><input type="text" name="admin_username" class="form-control" value="<?php echo htmlspecialchars($currentAdminUser); ?>" placeholder="默认为 GuYi"></div>
                                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:16px;">保存基础信息</button>
                                </form>
                            </div>
                            <div class="setting-card" style="border: 1px solid var(--primary-glow); background: rgba(99,102,241,0.02);">
                                <div class="setting-card-title"><i class="fas fa-tachometer-alt"></i> 仪表盘数据自定义</div>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?=$csrf_token?>"><input type="hidden" name="update_mock_data" value="1">
                                    <div style="margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.3); padding-bottom:16px;">
                                        <div style="display:flex; align-items:center; margin-bottom:12px;">
                                            <input type="checkbox" name="mock_counts_enabled" value="1" id="mock_counts_check" <?php echo $mockSettings['counts_enabled']==1?'checked':''; ?> style="width:18px;height:18px;margin-right:10px;cursor:pointer;accent-color:var(--primary);">
                                            <label for="mock_counts_check" style="margin:0;cursor:pointer;font-weight:700;font-size:13px;color:#1e293b;">自定义 [基础统计数值]</label>
                                        </div>
                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                            <div class="form-section"><label>总库存量</label><input type="number" name="mock_total" class="form-control" value="<?php echo intval($mockSettings['total']); ?>"></div>
                                            <div class="form-section"><label>活跃设备</label><input type="number" name="mock_active" class="form-control" value="<?php echo intval($mockSettings['active']); ?>"></div>
                                            <div class="form-section"><label>接入应用</label><input type="number" name="mock_apps" class="form-control" value="<?php echo intval($mockSettings['apps']); ?>"></div>
                                            <div class="form-section"><label>待售库存</label><input type="number" name="mock_unused" class="form-control" value="<?php echo intval($mockSettings['unused']); ?>"></div>
                                        </div>
                                    </div>
                                    <div style="margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.3); padding-bottom:16px;">
                                        <div style="display:flex; align-items:center; margin-bottom:12px;">
                                            <input type="checkbox" name="mock_apps_enabled" value="1" id="mock_apps_check" <?php echo $mockSettings['apps_enabled']==1?'checked':''; ?> style="width:18px;height:18px;margin-right:10px;cursor:pointer;accent-color:var(--primary);">
                                            <label for="mock_apps_check" style="margin:0;cursor:pointer;font-weight:700;font-size:13px;color:#1e293b;">自定义 [应用库存占比列表]</label>
                                        </div>
                                        <div class="form-section">
                                            <label>应用数据(JSON 数组)</label>
                                            <textarea name="mock_app_stats_json" class="form-control" style="height:80px;font-family:'JetBrains Mono';font-size:11px;" placeholder='[{"app_name":"App A","count":100},...]'><?php echo htmlspecialchars($mockSettings['app_stats_json']); ?></textarea>
                                        </div>
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <div style="display:flex; align-items:center; margin-bottom:12px;">
                                            <input type="checkbox" name="mock_types_enabled" value="1" id="mock_types_check" <?php echo $mockSettings['types_enabled']==1?'checked':''; ?> style="width:18px;height:18px;margin-right:10px;cursor:pointer;accent-color:var(--primary);">
                                            <label for="mock_types_check" style="margin:0;cursor:pointer;font-weight:700;font-size:13px;color:#1e293b;">自定义 [类型分布图表]</label>
                                        </div>
                                        <div class="form-section">
                                            <label>分布数据 (JSON 对象 ID:数量)</label>
                                            <textarea name="mock_type_stats_json" class="form-control" style="height:60px;font-family:'JetBrains Mono';font-size:11px;" placeholder='{"1":100, "2":50}'><?php echo htmlspecialchars($mockSettings['type_stats_json']); ?></textarea>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success" style="width:100%; margin-top:12px;">保存数据设置</button>
                                </form>
                            </div>

                            <div class="setting-card">
                                <div class="setting-card-title"><i class="fas fa-shield-alt"></i> 安全设置</div>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?=$csrf_token?>"><input type="hidden" name="update_pwd" value="1">
                                    <div class="password-wrapper"><input type="password" id="pwd1" name="new_pwd" class="form-control" placeholder="设置新密码" required><i class="fas fa-eye toggle-pwd" onclick="togglePwd()" title="显示/隐藏密码"></i></div>
                                    <div class="password-wrapper"><input type="password" id="pwd2" name="confirm_pwd" class="form-control" placeholder="确认新密码" required></div>
                                    <button type="submit" class="btn btn-danger" style="width:100%;">更新管理员密码</button>
                                </form>
                            </div>
                        </div>
                        <div class="setting-card">
                            <div class="setting-card-title"><i class="fas fa-paint-brush"></i> 视觉素材管理</div>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?=$csrf_token?>"><input type="hidden" name="update_settings" value="1">
                                <input type="hidden" name="site_title" value="<?php echo htmlspecialchars($conf_site_title); ?>"><input type="hidden" name="admin_username" value="<?php echo htmlspecialchars($currentAdminUser); ?>">
                                <div class="form-section"><label>Favicon 图标</label><div class="file-input-group"><input type="text" id="fav_input" name="favicon" class="form-control" value="<?php echo htmlspecialchars($conf_favicon); ?>"><input type="file" id="fav_file" name="favicon_file" class="hidden-file" accept="image/*" onchange="updateFileName(this, 'fav_input', 'fav_preview')"><label for="fav_file" class="upload-btn-overlay"><i class="fas fa-cloud-upload-alt"></i></label></div><div id="fav_preview" class="file-preview"></div></div>
                                <div class="form-section"><label>后台头像</label><div class="file-input-group"><input type="text" id="avatar_input" name="admin_avatar" class="form-control" value="<?php echo htmlspecialchars($conf_avatar); ?>"><input type="file" id="avatar_file" name="admin_avatar_file" class="hidden-file" accept="image/*" onchange="updateFileName(this, 'avatar_input', 'avatar_preview')"><label for="avatar_file" class="upload-btn-overlay"><i class="fas fa-cloud-upload-alt"></i></label></div><div id="avatar_preview" class="file-preview"></div></div>
                                <div class="form-section"><label>PC端背景壁纸</label><div class="file-input-group"><input type="text" id="pc_input" name="bg_pc" class="form-control" value="<?php echo htmlspecialchars($conf_bg_pc); ?>"><input type="file" id="pc_file" name="bg_pc_file" class="hidden-file" accept="image/*" onchange="updateFileName(this, 'pc_input', 'pc_preview')"><label for="pc_file" class="upload-btn-overlay"><i class="fas fa-cloud-upload-alt"></i></label></div><div id="pc_preview" class="file-preview"></div></div>
                                <div class="form-section"><label>移动端 背景壁纸</label><div class="file-input-group"><input type="text" id="mob_input" name="bg_mobile" class="form-control" value="<?php echo htmlspecialchars($conf_bg_mobile); ?>"><input type="file" id="mob_file" name="bg_mobile_file" class="hidden-file" accept="image/*" onchange="updateFileName(this, 'mob_input', 'mob_preview')"><label for="mob_file" class="upload-btn-overlay"><i class="fas fa-cloud-upload-alt"></i></label></div><div id="mob_preview" class="file-preview"></div></div>
                                <div class="form-section" style="flex-direction:row; align-items:center; gap:10px; margin-top:15px; background:rgba(255,255,255,0.2); padding:10px; border-radius:10px;"><input type="checkbox" name="bg_blur" value="1" id="bg_blur_check" <?php echo $conf_bg_blur=='1'?'checked':''; ?> style="width:16px;height:16px;cursor:pointer;"><label for="bg_blur_check" style="margin:0;cursor:pointer;">开启背景全局模糊 (Glass Effect)</label></div>
                                <button type="submit" class="btn btn-primary" style="width:100%; margin-top:20px;">保存视觉配置</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>
<div id="toast" class="toast"><i class="fas fa-check-circle"></i> 已复制到剪贴板</div>
<script>
(function(a,b,c){if(c in b&&b[c]){var d,e=a.location,f=/^(a|html)$/i;a.addEventListener("click",function(a){d=a.target;while(!f.test(d.nodeName))d=d.parentNode;"href"in d&&(d.href.indexOf("http")||~d.href.indexOf(e.host))&&(a.preventDefault(),e.href=d.href)},!1)}})(document,window.navigator,"standalone");

function togglePwd(){const p1=document.getElementById('pwd1'),p2=document.getElementById('pwd2'),i=document.querySelector('.toggle-pwd');if(p1.type==='password'){p1.type='text';p2.type='text';i.classList.replace('fa-eye','fa-eye-slash')}else{p1.type='password';p2.type='password';i.classList.replace('fa-eye-slash','fa-eye')}}
function updateFileName(inp,tid,pid){if(inp.files&&inp.files.length>0)document.getElementById(pid).innerHTML='<i class="fas fa-check-circle"></i> 已选择文件: '+inp.files[0].name}
function copy(t){navigator.clipboard.writeText(t).then(()=>{const o=document.getElementById('toast');o.classList.add('show');setTimeout(()=>o.classList.remove('show'),2000)})}
function toggleAll(s){document.querySelectorAll('.row-check').forEach(c=>c.checked=s.checked)}
function submitBatch(a){if(document.querySelectorAll('.row-check:checked').length===0){alert('请先勾选需要操作的卡密');return}if(!confirm('确定要执行此批量操作吗？'))return;const f=document.getElementById('batchForm'),h=document.createElement('input');h.type='hidden';h.name=a;h.value='1';f.appendChild(h);f.submit()}
function batchAddTime(){if(document.querySelectorAll('.row-check:checked').length===0){alert('请先勾选卡密');return}const h=prompt("请输入增加小时数","24");if(h&&!isNaN(h)){document.getElementById('addHoursInput').value=h;submitBatch('batch_add_time')}}
function singleAction(a,id,k='id'){if(!confirm('确定操作？'))return;const f=document.createElement('form');f.method='POST';f.style.display='none';const i1=document.createElement('input');i1.name=a;i1.value='1';const i2=document.createElement('input');if(a==='del_var')i2.name='var_id';else if(a.includes('app'))i2.name='app_id';else i2.name='id';i2.value=id;const i3=document.createElement('input');i3.name='csrf_token';i3.value='<?=$csrf_token?>';f.appendChild(i1);f.appendChild(i2);f.appendChild(i3);document.body.appendChild(f);f.submit()}

document.addEventListener("DOMContentLoaded", function() {
    let lastScrollTop = 0;
    const navBar = document.getElementById('floatingNav');

    window.addEventListener('scroll', function() {
        if(window.innerWidth > 768) return;
        
        let st = window.pageYOffset || document.documentElement.scrollTop;
        if (st > lastScrollTop && st > 100) {
            navBar.classList.add('nav-hidden');
        } else if (st < lastScrollTop) {
            navBar.classList.remove('nav-hidden');
        }
        lastScrollTop = st <= 0 ? 0 : st;
    }, false);

    <?php if($tab == 'dashboard'): ?>
    const tData = <?php echo json_encode($dashboardData['chart_types']); ?>, cTypes = <?php echo json_encode(CARD_TYPES); ?>;
    new Chart(document.getElementById('typeChart'), {type:'doughnut',data:{labels:Object.keys(tData).map(k=>(cTypes[k]?.name||k)),datasets:[{data:Object.values(tData),backgroundColor:['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'70%',plugins:{legend:{position:'right',labels:{usePointStyle:true,boxWidth:8,font:{size:11,family:"'Outfit', sans-serif"}}}}}});
    
    fetch('https://v1.jinrishici.com/all.json')
        .then(response => response.json())
        .then(data => {
            document.getElementById('poem_content').innerText = data.content;
            document.getElementById('poem_info').innerHTML = `<span class="badge badge-primary">${data.author}</span> <span style="font-size:12px;color:#64748b;">《${data.origin}》</span>`;
        })
        .catch(err => {
            document.getElementById('poem_content').innerText = "欲穷千里目，更上一层楼。";
            document.getElementById('poem_info').innerHTML = `<span class="badge badge-primary">王之涣</span>`;
        });
    <?php endif; ?>
});

(function(){
    const cid='<?=$tab?>',ct='<?=$currentTitle?>',c=document.getElementById('tabs-container');
    let tabs=JSON.parse(localStorage.getItem('admin_tabs')||'[]');
    if(tabs.length===0||tabs[0].id!=='dashboard'){tabs=tabs.filter(t=>t.id!=='dashboard');tabs.unshift({id:'dashboard',title:'首页'});}
    if(!tabs.find(t=>t.id===cid))tabs.push({id:cid,title:ct});
    localStorage.setItem('admin_tabs',JSON.stringify(tabs));
    let h='';tabs.forEach(t=>{const a=t.id===cid?'active':'',b=t.id==='dashboard'?'':`<i class="fas fa-times" onclick="closeTab(event,'${t.id}')" style="font-size:10px;margin-left:4px;opacity:0.6;"></i>`;h+=`<a href="?tab=${t.id}" class="chrome-tab ${a}">${t.title} ${b}</a>`});c.innerHTML=h;window.closeTab=function(e,id){e.preventDefault();e.stopPropagation();tabs=tabs.filter(t=>t.id!==id);localStorage.setItem('admin_tabs',JSON.stringify(tabs));if(id===cid)window.location.href='?tab=dashboard';else e.target.closest('.chrome-tab').remove()}
})();
</script>
</body>
</html>
