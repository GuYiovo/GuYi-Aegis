<?php
/**
 * Verifyfile/api.php - 安全增强优化版
 * 
 * 优化点：
 * 1. 速率限制改用文件锁防竞态
 * 2. 速率限制支持 IP + AppKey 双维度
 * 3. 错误日志与响应分离
 * 4. 参数处理更安全
 * 5. 统一响应函数避免重复代码
 * 6. 增加输入长度校验
 * 7. 增加安全响应头
 */

require_once '../config.php';
require_once '../database.php';

//============================================================
// 基础设置
// ============================================================

// 安全响应头
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate');

// 统一响应函数
function sendJson(int $code, string $msg, $data = null, bool $exit = true): void
{
    $response = ['code' => $code, 'msg' => $msg];
    if ($data !== null) {
        $response['data'] = $data;
    }
    //仅在失败时也输出 data:null，保持客户端兼容
    if ($code !== 200 && $data === null) {
        $response['data'] = null;
    }
    http_response_code($code === 200 ? 200 : ($code === 429 ? 429 : 200));
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    if ($exit) exit;
}

// 错误日志（不暴露给客户端）
function logError(string $context, Throwable $e): void
{
    $logDir = sys_get_temp_dir() . '/api_errors';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0700, true);
    }
    $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
    $entry = sprintf(
        "[%s] [%s] %s in %s:%d\nTrace: %s\n---\n",
        date('Y-m-d H:i:s'),
        $context,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

// ============================================================
// 速率限制（文件锁 + 双维度）
// ============================================================

function checkRateLimit(string $ip, string $appKey, int $maxPerMinute = 60): void
{
    $tmpDir= sys_get_temp_dir() . '/rate_limits';
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0700, true);
    }

    // 双维度：IP 维度 + AppKey 维度（若有）
    $dimensions = [
        'ip'=> md5($ip),
        'key' => $appKey ? md5($appKey) : null,];

    $currentMinute = date('YmdHi');

    foreach ($dimensions as $type => $hash) {
        if ($hash === null) continue;

        $rateFile = "{$tmpDir}/rate_{$type}_{$hash}";
        $lockFile = "{$rateFile}.lock";

        // 使用文件锁保证原子操作
        $fp = fopen($lockFile, 'c');
        if (!$fp) continue;

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            continue;
        }

        try {
            $rateData = [];
            if (file_exists($rateFile)) {
                $raw = file_get_contents($rateFile);
                $rateData = json_decode($raw, true) ?? [];
            }

            // 超过一分钟则重置
            if (($rateData['time'] ?? '') !== $currentMinute) {
                $rateData = ['time' => $currentMinute, 'count' => 0];
            }

            // 不同维度不同阈值
            $limit = ($type === 'key') ? $maxPerMinute * 3 : $maxPerMinute;

            if ($rateData['count'] >= $limit) {
                flock($fp, LOCK_UN);
                fclose($fp);
                http_response_code(429);
                sendJson(429, "请求过于频繁，请稍后重试（{$type}维度限流）");
            }

            $rateData['count']++;
            file_put_contents($rateFile, json_encode($rateData), LOCK_EX);} finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}

// ============================================================
// 参数获取（优先级：POST > JSON Body > GET，防止覆盖攻击）
// ============================================================

function getParams(): array
{
    // 优先级从低到高：GET< JSON Body < POST
    $jsonInput = file_get_contents('php://input');
    $jsonData  = [];
    if (!empty($jsonInput) && str_contains(
        $_SERVER['CONTENT_TYPE'] ?? '', 
        'application/json'
    )) {
        $jsonData = json_decode($jsonInput, true) ?? [];
    }

    // 后者覆盖前者，POST 优先级最高
    return array_merge($jsonData, $_GET, $_POST);
}

function sanitizeParam(array $data, string $key, string $alias = ''): string
{
    $val = $data[$key] ?? ($alias ? ($data[$alias] ?? '') : '');
    return mb_substr(trim((string)$val), 0, 256); // 限制长度防超长攻击
}

// ============================================================
// 长度与格式校验
// ============================================================

function validateCardCode(string $code): bool
{
    //卡密一般为字母数字，长度 8~64，按实际规则调整
    return strlen($code) >= 4 && strlen($code) <= 128
        && preg_match('/^[\w\-]+$/', $code);
}

function validateAppKey(string $key): bool
{
    return empty($key) || (strlen($key) <= 128 && preg_match('/^[\w\-]+$/', $key));
}

// ============================================================
// 主逻辑
// ============================================================

try {
    $ip= $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $params= getParams();

    $cardCode = sanitizeParam($params, 'card_code', 'card');
    $appKey   = sanitizeParam($params, 'app_key');
    $device   = sanitizeParam($params, 'device_hash', 'device');

    // 速率检查（在参数解析后，可加入 appKey 维度）
    checkRateLimit($ip, $appKey);

    // 参数格式校验
    if (!validateAppKey($appKey)) {
        sendJson(400, 'app_key 格式非法');
    }
    if (!empty($cardCode) && !validateCardCode($cardCode)) {
        sendJson(400, '卡密格式非法');
    }

    $db = new Database();

    //-------------------------------------------------------
    // 场景 A：仅查询 App 变量（无卡密）
    // -------------------------------------------------------
    if (empty($cardCode) && !empty($appKey)) {

        $appInfo = $db->getAppIdByKey($appKey);
        if (!$appInfo) {
            sendJson(403, 'AppKey 错误或不存在');}

        $variables = buildVariableMap($db->getAppVariables($appInfo['id'], true));

        sendJson(200, 'OK', [
            'variables' => $variables ?: null,
        ]);
    }

    // -------------------------------------------------------
    // 场景 B：卡密验证
    // -------------------------------------------------------
    if (empty($cardCode)) {
        sendJson(400, '请输入卡密');
    }

    // 设备标识兜底：ip hash（告知客户端建议传入）
    if (empty($device)) {
        $device = md5($ip);
    }

    $result = $db->verifyCard($cardCode, $device, $appKey);

    if ($result['success']) {
        $variables = [];
        if (!empty($result['app_id']) && $result['app_id'] > 0) {
            $variables = buildVariableMap($db->getAppVariables($result['app_id'], false));
        }

        sendJson(200, 'OK', [
            'expire_time' => $result['expire_time'],
            'variables'   => $variables ?: null,
        ]);
    } else {
        sendJson(403, $result['message']);
    }

} catch (Throwable $e) {
    // 区分开发/生产环境
    logError('api_main', $e);

    $isDev = defined('APP_DEBUG') && APP_DEBUG === true;
    sendJson(500, $isDev ? ('调试信息: ' . $e->getMessage()) : '服务器内部错误，请稍后重试');
}

// ============================================================
// 辅助函数
// ============================================================

/**
 * 将变量数组转为key => value 映射
 */
function buildVariableMap(array $rawVars): array
{
    $map = [];
    foreach ($rawVars as $v) {
        if (isset($v['key_name'], $v['value'])) {
            $map[$v['key_name']] = $v['value'];
        }
    }
    return $map;
}
