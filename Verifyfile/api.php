<?php
// Verifyfile/api.php - 安全增强版 + AES-256-GCM 加密 + 并发控制
require_once '../config.php';
require_once '../database.php';

header('Content-Type: application/json; charset=utf-8');

// --- 并发安全的频率限制 ---
$rate_ip = $_SERVER['REMOTE_ADDR'];
$rate_file = sys_get_temp_dir() . '/rate_' . md5($rate_ip);
$current_minute = date('Hi'); // 格式 1425 (小时分钟)

$fp = fopen($rate_file, 'c+');
if ($fp && flock($fp, LOCK_EX)) { // 获取独占锁
    $content = stream_get_contents($fp);
    $rate_data = json_decode($content, true);
    
    if ($rate_data && $rate_data['time'] == $current_minute) {
        if ($rate_data['count'] > 60) {
            flock($fp, LOCK_UN);
            fclose($fp);
            http_response_code(429);
            die(json_encode(['code' => 429, 'msg' => 'Too Many Requests - 请稍后重试']));
        }
        $rate_data['count']++;
    } else {
        $rate_data = ['time' => $current_minute, 'count' => 1];
    }
    
    ftruncate($fp, 0); // 清空文件
    rewind($fp);
    fwrite($fp, json_encode($rate_data));
    flock($fp, LOCK_UN); // 释放锁
    fclose($fp);
}
// ------------------------------------------------

// --- [AES-256-GCM 输出封装函数] ---
function output_json($code, $msg, $data = null, $encryptKey = null) {
    $response = ['code' => $code, 'msg' => $msg, 'data' => $data];
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);

    if ($encryptKey && strlen($encryptKey) === 64 && function_exists('openssl_encrypt')) {
        try {
            $key = hex2bin($encryptKey); 
            $iv = openssl_random_pseudo_bytes(12); 
            $tag = ""; 
            $ciphertext = openssl_encrypt($json, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            $encryptedPayload = base64_encode($iv . $tag . $ciphertext);
            echo json_encode(['encrypted_data' => $encryptedPayload]);
            exit;
        } catch (Exception $e) { }
    }
    echo $json;
    exit;
}
// ------------------------------------------------

$json_input = file_get_contents('php://input');
$data = [];
if (!empty($json_input)) $data = json_decode($json_input, true) ?? [];
// 兼容 POST 表单和 JSON
if (is_array($data)) {
    $data = array_merge($_GET, $_POST, $data);
} else {
    $data = array_merge($_GET, $_POST);
}

$card_code = !empty($data['card_code']) ? trim($data['card_code']) : (isset($data['card']) ? trim($data['card']) : '');
$app_key   = isset($data['app_key']) ? trim($data['app_key']) : '';
$device    = !empty($data['device_hash']) ? trim($data['device_hash']) : (isset($data['device']) ? trim($data['device']) : '');

try {
    $db = new Database();

    if (empty($card_code) && !empty($app_key)) {
        $appInfo = $db->getAppIdByKey($app_key);
        if (!$appInfo) output_json(403, 'AppKey 错误或不存在');

        $raw_vars = $db->getAppVariables($appInfo['id'], true); 
        $variables = [];
        foreach ($raw_vars as $v) $variables[$v['key_name']] = $v['value'];

        output_json(200, 'OK', ['variables' => $variables ?: null], $app_key);
    }

    if (empty($card_code)) output_json(400, '请输入卡密');
    if (empty($device)) $device = md5($_SERVER['REMOTE_ADDR']);

    $result = $db->verifyCard($card_code, $device, $app_key);
    
    if ($result['success']) {
        $variables = [];
        if (isset($result['app_id']) && $result['app_id'] > 0) {
            $raw_vars = $db->getAppVariables($result['app_id'], false);
            foreach ($raw_vars as $v) $variables[$v['key_name']] = $v['value'];
        }
        output_json(200, 'OK', ['expire_time' => $result['expire_time'], 'variables' => $variables], $app_key);
    } else {
        output_json(403, $result['message'], null);
    }

} catch (Exception $e) {
    output_json(500, 'Server Error: ' . $e->getMessage());
}
?>
