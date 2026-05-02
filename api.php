<?php
define('DEE_LOADED', true);
require_once 'config.php';

// Allow large source media uploads; videos are compressed server-side.
ini_set('upload_max_filesize', '450M');
ini_set('post_max_size', '460M');
ini_set('max_execution_time', 900);
ini_set('memory_limit', '512M');

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDb();

// Hide direct browser access to /api.php without a valid API action/type.
if ($method === 'GET' && trim((string)input('action', '')) === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not Found');
}

function isAdmin() {
    return isAdminSessionValid();
}

function requestCsrfTokenFromRequest($data = []) {
    if (is_array($data) && !empty($data['_csrf'])) {
        return trim((string)$data['_csrf']);
    }
    if (!empty($_POST['_csrf'])) {
        return trim((string)$_POST['_csrf']);
    }
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return trim((string)$_SERVER['HTTP_X_CSRF_TOKEN']);
    }
    if (!empty($_GET['_csrf'])) {
        return trim((string)$_GET['_csrf']);
    }
    return '';
}

function requireAdminAccess($data = []) {
    if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
    $csrf = requestCsrfTokenFromRequest($data);
    if (!isValidAdminCsrf($csrf)) {
        jsonOut(['error' => 'Session verification failed. Please log in again.'], 403);
    }
}

function changeTokenPath() {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir . '/change_token.txt';
}

function getChangeToken() {
    $f = changeTokenPath();
    if (!file_exists($f)) return '0';
    $v = trim((string)@file_get_contents($f));
    return $v !== '' ? $v : '0';
}

function bumpChangeToken() {
    @file_put_contents(changeTokenPath(), (string)microtime(true), LOCK_EX);
}

function tableHasColumn($db, $table, $column) {
    $tbl = $db->real_escape_string($table);
    $col = $db->real_escape_string($column);
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl' AND COLUMN_NAME = '$col'
            LIMIT 1";
    $res = $db->query($sql);
    if (!$res) return false;
    return (bool)$res->fetch_row();
}

function ensureSchema($db) {
    static $done = false;
    if ($done) return;
    $done = true;

    // Product defects support.
    if (!tableHasColumn($db, 'products', 'defects')) {
        @ $db->query("ALTER TABLE products ADD COLUMN defects VARCHAR(1024) NOT NULL DEFAULT 'No known defects'");
    }

    // Optional product description.
    if (!tableHasColumn($db, 'products', 'description')) {
        @ $db->query("ALTER TABLE products ADD COLUMN description TEXT NULL");
    }

    // Optional product working video URL.
    if (!tableHasColumn($db, 'products', 'video_url')) {
        @ $db->query("ALTER TABLE products ADD COLUMN video_url VARCHAR(1024) NULL");
    }
    if (!tableHasColumn($db, 'products', 'video_urls')) {
        @ $db->query("ALTER TABLE products ADD COLUMN video_urls TEXT NULL");
    }
    if (!tableHasColumn($db, 'products', 'base_price')) {
        @ $db->query("ALTER TABLE products ADD COLUMN base_price INT NOT NULL DEFAULT 0");
    }
    if (!tableHasColumn($db, 'products', 'sale_percent')) {
        @ $db->query("ALTER TABLE products ADD COLUMN sale_percent INT NOT NULL DEFAULT 0");
    }
    if (!tableHasColumn($db, 'products', 'sale_active')) {
        @ $db->query("ALTER TABLE products ADD COLUMN sale_active TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Category cover image mapping by product id.
    @ $db->query("CREATE TABLE IF NOT EXISTS category_covers (
        category_key VARCHAR(64) NOT NULL PRIMARY KEY,
        product_id VARCHAR(128) NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Short-lived payment reservations (3 min lock before proof upload).
    @ $db->query("CREATE TABLE IF NOT EXISTS payment_reservations (
        id VARCHAR(128) NOT NULL PRIMARY KEY,
        access_token VARCHAR(128) NOT NULL,
        payment_mode VARCHAR(32) NOT NULL DEFAULT 'prepaid',
        product_ids TEXT NOT NULL,
        payload LONGTEXT NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'active',
        order_id VARCHAR(128) NULL,
        screenshot VARCHAR(255) NULL,
        expires_at DATETIME NOT NULL,
        converted_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_status_expires (status, expires_at),
        KEY idx_order_id (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Delivery portal booking attempts (one row per order).
    @ $db->query("CREATE TABLE IF NOT EXISTS delivery_bookings (
        order_id VARCHAR(128) NOT NULL PRIMARY KEY,
        status VARCHAR(32) NOT NULL DEFAULT 'pending',
        portal_booking_id VARCHAR(128) NULL,
        attempts INT NOT NULL DEFAULT 0,
        response_text TEXT NULL,
        last_error TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Shipping/fee settings store
    @ $db->query("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL DEFAULT '',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed defaults only if missing
    $defaultSettings = [
        'cod_shipping_fee'       => '300',
        'cod_partial_threshold'  => '5000',
        'cod_partial_amount'     => '1000',
        'cod_tax_rate'           => '0.08',
    ];
    foreach ($defaultSettings as $k => $v) {
        $ks = $db->real_escape_string($k);
        $vs = $db->real_escape_string($v);
        @ $db->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('$ks','$vs')");
    }

    // Saved tracking details (synced from delivery portal worker/extension).
    if (!tableHasColumn($db, 'orders', 'tracking_number')) {
        @ $db->query("ALTER TABLE orders ADD COLUMN tracking_number VARCHAR(64) NULL");
    }
    if (!tableHasColumn($db, 'orders', 'tracking_carrier')) {
        @ $db->query("ALTER TABLE orders ADD COLUMN tracking_carrier VARCHAR(32) NULL");
    }
    if (!tableHasColumn($db, 'orders', 'tracking_status_text')) {
        @ $db->query("ALTER TABLE orders ADD COLUMN tracking_status_text VARCHAR(512) NULL");
    }
    if (!tableHasColumn($db, 'orders', 'tracking_updated_at')) {
        @ $db->query("ALTER TABLE orders ADD COLUMN tracking_updated_at DATETIME NULL");
    }
}

function normalizeCondition($value) {
    $raw = trim((string)$value);
    $legacy = strtolower($raw);
    if ($legacy === 'great') return '9/10';
    if ($legacy === 'good') return '8/10';
    if (preg_match('/^(10|[1-9])\s*\/\s*10$/', $raw, $m)) {
        return ((int)$m[1]) . '/10';
    }
    return '10/10';
}

function normalizeDefects($value) {
    $d = trim((string)$value);
    return $d !== '' ? $d : 'No known defects';
}

function normalizeDescription($value) {
    return trim((string)$value);
}

function normalizeCategoryValue($value, $default = 'accessories') {
    $raw = strtolower(trim((string)$value));
    $raw = preg_replace('/[^a-z0-9]+/', '_', $raw);
    $raw = trim((string)$raw, '_');
    if ($raw === '') $raw = $default;
    if ($raw === 'shirts') return 'tops';
    if ($raw === 'misc_items') return 'misc';
    return $raw;
}

function pushImageUrl(&$out, $url) {
    $u = trim((string)$url);
    if ($u === '') return;
    if (!in_array($u, $out, true)) $out[] = $u;
}

function imageUrlsFromValue($value) {
    $out = [];
    if (is_array($value)) {
        foreach ($value as $v) pushImageUrl($out, $v);
        return $out;
    }
    if (!is_string($value)) return $out;
    $raw = trim($value);
    if ($raw === '') return $out;

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $v) pushImageUrl($out, $v);
        return $out;
    }

    $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
    foreach ($parts as $p) pushImageUrl($out, $p);
    return $out;
}

function normalizeProductImageUrls($product) {
    $p = is_array($product) ? $product : [];
    $out = [];

    foreach (['imageUrls', 'images', 'image_urls'] as $k) {
        if (!array_key_exists($k, $p)) continue;
        foreach (imageUrlsFromValue($p[$k]) as $u) pushImageUrl($out, $u);
    }

    foreach (['imageUrl', 'img', 'image_url'] as $k) {
        if (!array_key_exists($k, $p)) continue;
        pushImageUrl($out, $p[$k]);
    }

    return array_slice($out, 0, 5);
}

function normalizeProductVideoUrls($product) {
    $p = is_array($product) ? $product : [];
    $out = [];

    foreach (['videoUrls', 'videos', 'video_urls'] as $k) {
        if (!array_key_exists($k, $p)) continue;
        foreach (imageUrlsFromValue($p[$k]) as $u) pushImageUrl($out, $u);
    }
    foreach (['videoUrl', 'video_url'] as $k) {
        if (!array_key_exists($k, $p)) continue;
        pushImageUrl($out, $p[$k]);
    }

    return array_slice($out, 0, 5);
}

function normalizeProductIdList($ids) {
    $ids = is_array($ids) ? $ids : [];
    $out = [];
    foreach ($ids as $id) {
        $id = trim((string)$id);
        if ($id !== '' && !in_array($id, $out, true)) $out[] = $id;
    }
    return $out;
}

function paymentReservationSeconds() {
    return 180; // 3 minutes
}

function productIdsToInClause($db, $productIds) {
    $ids = normalizeProductIdList($productIds);
    if (!$ids) return '';
    $escapedIds = array_map(function($pid) use ($db) {
        return $db->real_escape_string($pid);
    }, $ids);
    return "'" . implode("','", $escapedIds) . "'";
}

function releaseReservedProducts($db, $productIds) {
    $inIds = productIdsToInClause($db, $productIds);
    if ($inIds === '') return;
    $db->query("UPDATE products SET status='available' WHERE id IN ($inIds) AND status='confirmation_pending'");
}

function sweepExpiredReservations($db) {
    $expiredRows = [];
    $res = $db->query("SELECT id, product_ids FROM payment_reservations WHERE status='active' AND expires_at <= NOW() LIMIT 100");
    while ($res && ($row = $res->fetch_assoc())) {
        $expiredRows[] = $row;
    }
    if (!$expiredRows) return;

    $db->begin_transaction();
    try {
        foreach ($expiredRows as $row) {
            $rid = $db->real_escape_string((string)$row['id']);
            $lockRes = $db->query("SELECT id, status, product_ids FROM payment_reservations WHERE id='$rid' FOR UPDATE");
            $locked = $lockRes ? $lockRes->fetch_assoc() : null;
            if (!$locked) continue;
            if (strtolower((string)($locked['status'] ?? '')) !== 'active') continue;

            $pids = normalizeProductIdList(json_decode((string)($locked['product_ids'] ?? ''), true) ?: []);
            releaseReservedProducts($db, $pids);
            $db->query("UPDATE payment_reservations SET status='expired' WHERE id='$rid'");
        }
        $db->commit();
        bumpChangeToken();
    } catch (Throwable $e) {
        $db->rollback();
    }
}

function deliveryPortalEnabled() {
    $base = trim((string)(defined('DELIVERY_PORTAL_BASE_URL') ? DELIVERY_PORTAL_BASE_URL : ''));
    $user = trim((string)(defined('DELIVERY_PORTAL_USERNAME') ? DELIVERY_PORTAL_USERNAME : ''));
    $pass = trim((string)(defined('DELIVERY_PORTAL_PASSWORD') ? DELIVERY_PORTAL_PASSWORD : ''));
    return $base !== '' && $user !== '' && $pass !== '';
}

function deliveryPortalUrl($path) {
    $base = rtrim((string)(defined('DELIVERY_PORTAL_BASE_URL') ? DELIVERY_PORTAL_BASE_URL : ''), '/');
    return $base . '/' . ltrim((string)$path, '/');
}

function deliveryPortalOriginUrl() {
    $base = trim((string)(defined('DELIVERY_PORTAL_BASE_URL') ? DELIVERY_PORTAL_BASE_URL : ''));
    $parts = parse_url($base);
    $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
    $host = isset($parts['host']) ? $parts['host'] : '';
    $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
    if ($host === '') return '';
    return $scheme . '://' . $host . $port;
}

function deliveryPortalBuildUrl($path, $defaultPath = 'clients/new_booking.php') {
    $raw = trim((string)$path);
    if ($raw === '') return deliveryPortalUrl($defaultPath);
    if (preg_match('/^https?:\/\//i', $raw)) return $raw;

    $origin = deliveryPortalOriginUrl();
    if ($origin !== '' && strpos($raw, '/') === 0) {
        return rtrim($origin, '/') . $raw;
    }
    if ($origin !== '' && stripos($raw, 'portal/') === 0) {
        return rtrim($origin, '/') . '/' . ltrim($raw, '/');
    }
    if (stripos($raw, 'clients/') === 0) {
        return deliveryPortalUrl($raw);
    }
    return deliveryPortalUrl('clients/' . ltrim($raw, '/'));
}

function deliveryAppendQuery($url, $params = []) {
    $raw = trim((string)$url);
    if ($raw === '' || !is_array($params) || !$params) return $raw;

    $pairs = [];
    foreach ($params as $k => $v) {
        $key = trim((string)$k);
        if ($key === '') continue;
        $pairs[] = rawurlencode($key) . '=' . rawurlencode((string)$v);
    }
    if (!$pairs) return $raw;

    $sep = (strpos($raw, '?') === false) ? '?' : '&';
    return $raw . $sep . implode('&', $pairs);
}

function deliveryTextLimit($value, $maxLen = 2000) {
    $v = trim((string)$value);
    if ($maxLen < 1 || strlen($v) <= $maxLen) return $v;
    return substr($v, 0, $maxLen);
}

function normalizeInstagramHandleForDm($value) {
    $raw = trim((string)$value);
    if ($raw === '') return '';
    $raw = preg_replace('/^@+/', '', $raw);
    $raw = trim($raw);
    if ($raw === '') return '';
    if (preg_match('#instagram\.com/([^/?#]+)#i', $raw, $m)) {
        $raw = trim((string)($m[1] ?? ''));
    }
    $raw = preg_replace('/[^a-zA-Z0-9._]/', '', $raw);
    return trim((string)$raw);
}

function metaIgDmConfigured() {
    if (!defined('META_IG_DM_ENABLED') || !META_IG_DM_ENABLED) return false;
    $biz = trim((string)(defined('META_IG_BUSINESS_ID') ? META_IG_BUSINESS_ID : ''));
    $tok = trim((string)(defined('META_IG_PAGE_ACCESS_TOKEN') ? META_IG_PAGE_ACCESS_TOKEN : ''));
    return $biz !== '' && $tok !== '';
}

function sendInstagramConfirmationDm($orderRow) {
    $result = ['ok' => false, 'sent' => false, 'message' => '', 'username' => ''];
    $username = normalizeInstagramHandleForDm($orderRow['instagram'] ?? '');
    $result['username'] = $username;

    if ($username === '') {
        $result['message'] = 'No Instagram username on order.';
        return $result;
    }
    if (!metaIgDmConfigured()) {
        $result['message'] = 'Meta IG DM is not configured.';
        return $result;
    }
    if (!function_exists('curl_init')) {
        $result['message'] = 'cURL extension is not available.';
        return $result;
    }

    $bizId = trim((string)META_IG_BUSINESS_ID);
    $token = trim((string)META_IG_PAGE_ACCESS_TOKEN);
    $apiVer = trim((string)(defined('META_GRAPH_API_VERSION') ? META_GRAPH_API_VERSION : 'v23.0'));
    if ($apiVer === '') $apiVer = 'v23.0';

    $text = trim((string)(defined('META_IG_CONFIRM_TEXT') ? META_IG_CONFIRM_TEXT : ''));
    if ($text === '') $text = 'your order is confirmed! thank you for your purchase <3';

    // Best-effort send by username. If Meta rejects due recipient constraints,
    // we return the API message so setup can be adjusted (app mode/permissions/scoped IDs).
    $endpoint = 'https://graph.instagram.com/' . rawurlencode($apiVer) . '/' . rawurlencode($bizId) . '/messages';
    $payload = [
        'recipient' => ['username' => $username],
        'message' => ['text' => $text],
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 25,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err !== '') {
        $result['message'] = 'Meta request failed: ' . $err;
        return $result;
    }

    $json = json_decode((string)$body, true);
    if ($status >= 200 && $status < 300 && is_array($json) && !empty($json['message_id'])) {
        $result['ok'] = true;
        $result['sent'] = true;
        $result['message'] = 'Instagram confirmation DM sent.';
        $result['messageId'] = (string)$json['message_id'];
        return $result;
    }

    $apiError = '';
    if (is_array($json) && !empty($json['error']) && is_array($json['error'])) {
        $apiError = trim((string)($json['error']['message'] ?? ''));
    }
    $result['message'] = $apiError !== '' ? $apiError : ('Meta API HTTP ' . $status);
    $result['status'] = $status;
    return $result;
}

function deliveryRequest($url, $method = 'GET', $fields = [], $cookieFile = '', $extra = []) {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'cURL extension is not available.'];
    }
    $method = strtoupper(trim((string)$method));
    if (!in_array($method, ['GET', 'POST'], true)) $method = 'GET';
    $extra = is_array($extra) ? $extra : [];

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'DeeThrifts-AutoBooking/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_HEADER => false,
    ];
    if ($cookieFile !== '') {
        $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    $headers = [];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query(is_array($fields) ? $fields : []);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if (!empty($extra['headers']) && is_array($extra['headers'])) {
        foreach ($extra['headers'] as $h) {
            $hs = trim((string)$h);
            if ($hs !== '') $headers[] = $hs;
        }
    }
    if ($headers) $opts[CURLOPT_HTTPHEADER] = $headers;
    if (!empty($extra['referer'])) {
        $opts[CURLOPT_REFERER] = (string)$extra['referer'];
    }

    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = (string)curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $err ?: 'Request failed'];
    }

    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'ok' => ($status >= 200 && $status < 400),
        'status' => $status,
        'body' => (string)$body,
        'error' => '',
    ];
}

function deliveryExtractInputValue($html, $name, $default = '') {
    $h = (string)$html;
    $n = preg_quote((string)$name, '/');
    $patterns = [
        // name before value (quoted value)
        '/<input\b[^>]*\bname\s*=\s*(?:"|\')?' . $n . '(?:"|\')?[^>]*\bvalue\s*=\s*(?:"|\')([^"\']*)(?:"|\')[^>]*>/i',
        // value before name (quoted value)
        '/<input\b[^>]*\bvalue\s*=\s*(?:"|\')([^"\']*)(?:"|\')[^>]*\bname\s*=\s*(?:"|\')?' . $n . '(?:"|\')?[^>]*>/i',
        // name before value (unquoted value)
        '/<input\b[^>]*\bname\s*=\s*(?:"|\')?' . $n . '(?:"|\')?[^>]*\bvalue\s*=\s*([^\s>]+)[^>]*>/i',
        // value before name (unquoted value)
        '/<input\b[^>]*\bvalue\s*=\s*([^\s>]+)[^>]*\bname\s*=\s*(?:"|\')?' . $n . '(?:"|\')?[^>]*>/i',
    ];

    foreach ($patterns as $rx) {
        if (!preg_match($rx, $h, $m)) continue;
        $raw = trim((string)($m[1] ?? ''), " \t\n\r\0\x0B\"'");
        return html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $default;
}

function deliveryExtractHiddenInputs($html) {
    $out = [];
    $h = (string)$html;
    if (!preg_match_all('/<input\b[^>]*>/i', $h, $matches)) return $out;
    foreach ($matches[0] as $tag) {
        $type = '';
        if (preg_match('/type\s*=\s*["\']([^"\']*)["\']/i', $tag, $t)) {
            $type = strtolower(trim((string)$t[1]));
        }
        if ($type !== 'hidden') continue;
        if (!preg_match('/name\s*=\s*["\']([^"\']+)["\']/i', $tag, $n)) continue;
        $name = html_entity_decode((string)$n[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = '';
        if (preg_match('/value\s*=\s*["\']([^"\']*)["\']/i', $tag, $v)) {
            $value = html_entity_decode((string)$v[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if ($name !== '') $out[$name] = $value;
    }
    return $out;
}

function deliveryExtractPortalParcelId($html) {
    $h = (string)$html;

    $nameCandidates = ['Parcel_Id', 'parcel_id', 'ParcelId', 'ParcelID'];
    foreach ($nameCandidates as $nameCandidate) {
        $direct = trim((string)deliveryExtractInputValue($h, $nameCandidate, ''));
        if ($direct !== '') return $direct;
    }

    $hidden = deliveryExtractHiddenInputs($h);
    foreach ($hidden as $k => $v) {
        if (strcasecmp((string)$k, 'Parcel_Id') === 0 || strcasecmp((string)$k, 'ParcelId') === 0) {
            $val = trim((string)$v);
            if ($val !== '') return $val;
        }
    }

    // Fallback for layouts that label the field as "Shipment Id".
    if (preg_match('/Shipment\s*Id.{0,300}?<input\b[^>]*\bvalue\s*=\s*(?:"|\')?([A-Za-z0-9_-]+)(?:"|\')?/is', $h, $m)) {
        $val = trim((string)($m[1] ?? ''));
        if ($val !== '') return $val;
    }

    // JS variable fallback (if set client-side in script).
    if (preg_match('/\b(?:Parcel_Id|parcel_id|ParcelId)\b\s*[:=]\s*(?:"|\')?([A-Za-z0-9_-]+)(?:"|\')?/i', $h, $m)) {
        $val = trim((string)($m[1] ?? ''));
        if ($val !== '') return $val;
    }

    // Generic fallback near Parcel_Id token.
    if (preg_match('/Parcel_Id[^0-9A-Za-z_-]{0,80}([A-Za-z0-9_-]{4,})/i', $h, $m)) {
        $val = trim((string)($m[1] ?? ''));
        if ($val !== '') return $val;
    }

    return '';
}

function deliveryIsBookingFormHtml($html) {
    $h = strtolower((string)$html);
    return (strpos($h, 'name="consigneename"') !== false) ||
           (strpos($h, "name='consigneename'") !== false) ||
           (strpos($h, 'id="userform"') !== false);
}

function deliveryExtractNewBookingPath($html) {
    $h = (string)$html;
    if (preg_match('/href\s*=\s*["\']([^"\']*new_booking\.php[^"\']*)["\']/i', $h, $m)) {
        return html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

function deliveryOrderSummary($orderRow) {
    $names = json_decode((string)($orderRow['product_names'] ?? ''), true);
    if (!is_array($names) || !$names) {
        $items = json_decode((string)($orderRow['items'] ?? ''), true);
        if (is_array($items)) {
            $names = [];
            foreach ($items as $it) {
                $nm = trim((string)($it['name'] ?? ''));
                if ($nm !== '') $names[] = $nm;
            }
        }
    }
    if (!is_array($names)) $names = [];
    $cleanNames = array_values(array_filter(array_map(function($v) {
        return trim((string)$v);
    }, $names), function($v) {
        return $v !== '';
    }));
    $summary = trim(implode(', ', $cleanNames));
    if ($summary !== '') $summary = 'Products: ' . $summary;
    if ($summary === '') $summary = 'Order ' . (string)($orderRow['id'] ?? '');
    return deliveryTextLimit($summary, 180);
}

function codUpfrontPaidForOrder($orderRow) {
    $paymentMode = strtolower(trim((string)($orderRow['payment_mode'] ?? '')));
    if ($paymentMode !== 'cod_deposit') return 0;

    $subtotal = (int)($orderRow['subtotal'] ?? 0);
    if ($subtotal < 0) $subtotal = 0;

    $upfront = codShippingFeeAmount();
    if ($subtotal > codPartialThresholdAmount()) {
        $upfront += codPartialAmount();
    }
    return $upfront;
}

function codCollectibleAmountForOrder($orderRow) {
    $paymentMode = strtolower(trim((string)($orderRow['payment_mode'] ?? '')));
    $total = (int)($orderRow['total'] ?? 0);
    if ($total < 0) $total = 0;

    if ($paymentMode === 'prepaid') return 0;
    if ($paymentMode === 'cod_deposit') {
        $remaining = $total - codUpfrontPaidForOrder($orderRow);
        return $remaining > 0 ? $remaining : 0;
    }
    return $total;
}

function trackingTextNormalize($value) {
    $txt = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = preg_replace('/<\s*br\s*\/?>/i', ' ', $txt);
    $txt = preg_replace('/<[^>]*>/', ' ', $txt);
    $txt = preg_replace('/\s+/', ' ', $txt);
    return trim((string)$txt);
}

function trackingExtractNumberFromText($value) {
    $raw = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($raw === '') return '';

    // Raw-html tolerant pass (handles broken tags/spacing around status text).
    if (preg_match('/Shipment\s*out\s*for\s*delivery[\s\S]{0,180}?(ANN-[A-Za-z0-9-]{3,}|K[IL][A-Za-z0-9-]{4,}|\d{10,20})/i', $raw, $m)) {
        return strtoupper(trim((string)($m[1] ?? '')));
    }

    $txt = trackingTextNormalize($raw);
    if ($txt === '') return '';

    $deliveryPatterns = [
        '/Shipment\s*out\s*for\s*delivery-\d{4}[\/\-]\d{2}[\/\-]\d{2}\s+(ANN-[A-Za-z0-9-]{3,})/i',
        '/Shipment\s*out\s*for\s*delivery-\d{4}[\/\-]\d{2}[\/\-]\d{2}\s+(K[IL][A-Za-z0-9-]{4,})/i',
        '/Shipment\s*out\s*for\s*delivery-\d{4}[\/\-]\d{2}[\/\-]\d{2}\s+(\d{10,20})/i',
        '/Shipment\s*out\s*for\s*delivery[^A-Za-z0-9]{0,24}(?:\d{4}[\/\-]\d{2}[\/\-]\d{2})?[^A-Za-z0-9]{0,24}(ANN-[A-Za-z0-9-]{3,})/i',
        '/Shipment\s*out\s*for\s*delivery[^A-Za-z0-9]{0,24}(?:\d{4}[\/\-]\d{2}[\/\-]\d{2})?[^A-Za-z0-9]{0,24}(K[IL][A-Za-z0-9-]{4,})/i',
        '/Shipment\s*out\s*for\s*delivery[^A-Za-z0-9]{0,24}(?:\d{4}[\/\-]\d{2}[\/\-]\d{2})?[^0-9]{0,24}(\d{10,20})/i',
    ];
    foreach ($deliveryPatterns as $rx) {
        if (preg_match($rx, $txt, $m)) {
            return strtoupper(trim((string)($m[1] ?? '')));
        }
    }

    // Generic token fallbacks.
    if (preg_match('/\b(ANN-[A-Za-z0-9-]{3,})\b/i', $txt, $m)) {
        return strtoupper(trim((string)($m[1] ?? '')));
    }
    if (preg_match('/\b(K[IL][A-Za-z0-9-]{4,})\b/i', $txt, $m)) {
        return strtoupper(trim((string)($m[1] ?? '')));
    }
    if (preg_match('/\b(\d{10,20})\b/', $txt, $m)) {
        return trim((string)($m[1] ?? ''));
    }

    return '';
}

function trackingExtractFromOrderRow($indexHtml, $orderId) {
    $html = (string)$indexHtml;
    $oid = trim((string)$orderId);
    if ($html === '' || $oid === '') return '';

    $oidRx = preg_quote($oid, '/');
    $patterns = [
        '/Order\s*' . $oidRx . '[\s\S]{0,1800}?Shipment\s*out\s*for\s*delivery[\s\S]{0,220}?(ANN-[A-Za-z0-9-]{3,}|K[IL][A-Za-z0-9-]{4,}|\d{10,20})/i',
        '/Order\s*' . $oidRx . '[\s\S]{0,1800}?\b(ANN-[A-Za-z0-9-]{3,}|K[IL][A-Za-z0-9-]{4,}|\d{10,20})\b/i',
    ];
    foreach ($patterns as $rx) {
        if (!preg_match($rx, $html, $m)) continue;
        $candidate = strtoupper(trim((string)($m[1] ?? '')));
        if ($candidate !== '') return $candidate;
    }
    return '';
}

function trackingCarrierMeta($trackingNumber) {
    $tn = strtoupper(trim((string)$trackingNumber));
    if ($tn === '') {
        return [
            'type' => 'pending',
            'label' => '',
            'url' => '',
            'actionLabel' => '',
        ];
    }
    if (strpos($tn, 'ANN-') === 0) {
        return [
            'type' => 'local',
            'label' => 'Local Delivery',
            'url' => 'https://instagram.com/deethrifts.pk',
            'actionLabel' => 'Contact on Instagram',
        ];
    }
    if (preg_match('/^K[IL][A-Z0-9-]{4,}$/', $tn)) {
        return [
            'type' => 'leopards',
            'label' => 'Leopards Courier',
            'url' => 'https://www.leopardscourier.com/',
            'actionLabel' => 'Open Leopards',
        ];
    }
    if (preg_match('/^\d{12,20}$/', $tn)) {
        return [
            'type' => 'mp',
            'label' => 'M&P Courier',
            'url' => 'https://www.mulphilog.com/',
            'actionLabel' => 'Open M&P',
        ];
    }
    return [
        'type' => 'other',
        'label' => 'Courier',
        'url' => '',
        'actionLabel' => '',
    ];
}

function normalizeTrackingNumber($value) {
    $tn = strtoupper(trim((string)$value));
    if ($tn === '') return '';
    if (preg_match('/^(ANN-[A-Z0-9-]{3,}|K[IL][A-Z0-9-]{4,}|\d{10,20})$/', $tn)) return $tn;
    return strtoupper(trim((string)trackingExtractNumberFromText($tn)));
}

function normalizeSyncedOrderStatus($portalStatus, $statusText = '') {
    $raw = strtolower(trim((string)$portalStatus));
    $text = strtolower(trackingTextNormalize((string)$statusText));
    $blob = trim($raw . ' ' . $text);
    if ($blob === '') return '';

    if (strpos($blob, 'delivered') !== false) return 'Delivered';
    if (strpos($blob, 'in transit') !== false) return 'Confirmed';
    if (strpos($blob, 'out for delivery') !== false) return 'Confirmed';
    if (strpos($blob, 'parcel booked') !== false) return 'Confirmed';
    return '';
}

function trackingResponseForNumber($trackingNumber, $statusText = '', $source = '') {
    $tn = trim((string)$trackingNumber);
    $meta = trackingCarrierMeta($tn);
    $message = '';
    if ($tn === '') {
        $message = 'No tracking info yet. Please check back in a day.';
    } elseif ($meta['type'] === 'local') {
        $message = 'This parcel is being delivered locally. Please contact @deethrifts.pk on Instagram for more details.';
    } elseif ($meta['type'] === 'leopards' || $meta['type'] === 'mp') {
        $message = 'Tracking number found. Use the button below to open the courier site.';
    } else {
        $message = 'Tracking number found.';
    }

    return [
        'trackingNumber' => $tn,
        'carrierType' => (string)$meta['type'],
        'carrierLabel' => (string)$meta['label'],
        'carrierUrl' => (string)$meta['url'],
        'carrierActionLabel' => (string)$meta['actionLabel'],
        'statusText' => trackingTextNormalize($statusText),
        'source' => trim((string)$source),
        'message' => $message,
    ];
}

function deliveryIndexLooksReady($html) {
    $h = strtolower((string)$html);
    return (strpos($h, 'new_booking.php') !== false) ||
           (strpos($h, 'all bookings') !== false) ||
           (strpos($h, 'mytable') !== false);
}

function deliveryLoginPayloadCandidates($username, $password) {
    $user = trim((string)$username);
    $pass = trim((string)$password);
    return [
        ['username' => $user, 'password' => $pass],
        ['user_name' => $user, 'password' => $pass],
        ['email' => $user, 'password' => $pass],
        ['UserName' => $user, 'Password' => $pass],
        ['txt_username' => $user, 'txt_password' => $pass],
        ['uname' => $user, 'pass' => $pass],
        ['login' => $user, 'password' => $pass],
    ];
}

function deliveryTryLoginAndOpenIndex($cookieFile) {
    $loginUrl = deliveryPortalUrl('clients/login.php');
    $indexUrl = deliveryPortalUrl('clients/index.php');

    $openIndex = function($referer = '') use ($cookieFile, $indexUrl, $loginUrl) {
        $nonce = str_replace('.', '', sprintf('%.6f', microtime(true))) . '-' . (string)mt_rand(1000, 9999);
        $targetUrl = deliveryAppendQuery($indexUrl, ['dee_idx' => $nonce]);
        $resp = deliveryRequest($targetUrl, 'GET', [], $cookieFile, [
            'referer' => $referer !== '' ? $referer : $loginUrl,
            'headers' => ['Cache-Control: no-cache', 'Pragma: no-cache'],
        ]);
        $html = (string)($resp['body'] ?? '');
        if (!$resp['ok'] || !deliveryIndexLooksReady($html)) {
            return ['ok' => false, 'indexHtml' => $html, 'message' => 'Could not open delivery index page.'];
        }
        return ['ok' => true, 'indexHtml' => $html, 'message' => 'Index page ready'];
    };

    $direct = $openIndex($loginUrl);
    if (!empty($direct['ok'])) return $direct;

    $loginPage = deliveryRequest($loginUrl, 'GET', [], $cookieFile, [
        'headers' => ['Cache-Control: no-cache', 'Pragma: no-cache'],
    ]);
    $loginHtml = (string)($loginPage['body'] ?? '');
    if (deliveryIndexLooksReady($loginHtml)) {
        return ['ok' => true, 'indexHtml' => $loginHtml, 'message' => 'Index page ready'];
    }
    $hidden = deliveryExtractHiddenInputs($loginHtml);

    foreach (deliveryLoginPayloadCandidates(DELIVERY_PORTAL_USERNAME, DELIVERY_PORTAL_PASSWORD) as $cand) {
        $payload = array_merge($hidden, $cand, ['submit' => 'Login']);
        $post = deliveryRequest($loginUrl, 'POST', $payload, $cookieFile, [
            'referer' => $loginUrl,
            'headers' => ['Cache-Control: no-cache', 'Pragma: no-cache'],
        ]);
        if (!$post['ok']) continue;
        $idx = $openIndex($loginUrl);
        if (!empty($idx['ok'])) {
            $idx['message'] = 'Login successful';
            return $idx;
        }
    }

    return ['ok' => false, 'indexHtml' => '', 'message' => 'Login failed for delivery portal'];
}

function deliveryExtractRowByTokens($indexHtml, $tokens) {
    $tokenList = is_array($tokens) ? $tokens : [$tokens];
    $cleanTokens = [];
    foreach ($tokenList as $token) {
        $t = trim((string)$token);
        if ($t === '') continue;
        $cleanTokens[] = $t;
    }
    if (!$cleanTokens) return '';

    $html = (string)$indexHtml;
    if ($html === '') return '';

    if (!preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $rows)) return '';
    foreach ($rows[1] as $rowHtml) {
        $rowText = trackingTextNormalize($rowHtml);
        if ($rowText === '') continue;
        $rowDigits = preg_replace('/\D+/', '', $rowText);
        foreach ($cleanTokens as $token) {
            if (stripos($rowText, $token) !== false) return $rowText;
            $tokDigits = preg_replace('/\D+/', '', $token);
            if ($tokDigits !== '' && strlen($tokDigits) >= 7 && strpos($rowDigits, $tokDigits) !== false) {
                return $rowText;
            }
        }
    }
    return '';
}

function deliveryExtractRowByOrderId($indexHtml, $orderId) {
    $oid = trim((string)$orderId);
    if ($oid === '') return '';
    return deliveryExtractRowByTokens($indexHtml, [$oid, 'Order ' . $oid]);
}

function deliveryExtractRawRowSegmentByOrderId($indexHtml, $orderId) {
    $html = (string)$indexHtml;
    $oid = trim((string)$orderId);
    if ($html === '' || $oid === '') return '';

    $needle = 'Order ' . $oid;
    $pos = stripos($html, $needle);
    if ($pos === false) $pos = stripos($html, $oid);
    if ($pos === false) return '';

    $head = substr($html, 0, $pos);
    $start = strripos($head, '<tr');
    if ($start === false) $start = max(0, $pos - 700);

    $end = stripos($html, '</tr>', $pos);
    if ($end === false) {
        $end = min(strlen($html), $pos + 2600);
    } else {
        $end += 5; // include </tr>
    }

    $len = max(0, $end - $start);
    if ($len === 0) return '';
    return substr($html, $start, $len);
}

function deliveryBookingGet($db, $orderId) {
    $oid = $db->real_escape_string(trim((string)$orderId));
    if ($oid === '') return null;
    $res = $db->query("SELECT * FROM delivery_bookings WHERE order_id='$oid' LIMIT 1");
    return $res ? $res->fetch_assoc() : null;
}

function deliveryBookingSave($db, $orderId, $status, $portalBookingId, $responseText, $lastError) {
    $oid = $db->real_escape_string(trim((string)$orderId));
    if ($oid === '') return;
    $st = $db->real_escape_string(strtolower(trim((string)$status)) ?: 'failed');
    $pid = $db->real_escape_string(trim((string)$portalBookingId));
    $resp = $db->real_escape_string(deliveryTextLimit($responseText, 4000));
    $err = $db->real_escape_string(deliveryTextLimit($lastError, 4000));
    @ $db->query("INSERT INTO delivery_bookings (order_id,status,portal_booking_id,attempts,response_text,last_error)
        VALUES ('$oid','$st','$pid',1,'$resp','$err')
        ON DUPLICATE KEY UPDATE
            status=VALUES(status),
            portal_booking_id=VALUES(portal_booking_id),
            attempts=attempts+1,
            response_text=VALUES(response_text),
            last_error=VALUES(last_error)");
}

function deliveryCleanupCookie($cookieFile) {
    $f = trim((string)$cookieFile);
    if ($f !== '' && file_exists($f)) @unlink($f);
}

function deleteUploadedImageByUrl($url) {
    $u = trim((string)$url);
    if ($u === '' || strpos($u, '/uploads/') !== 0) return false;

    $path = __DIR__ . $u;
    $base = realpath(UPLOAD_DIR);
    $real = realpath($path);

    if ($base && $real && strpos($real, $base) === 0 && is_file($real)) {
        return @unlink($real);
    }
    if (is_file($path)) {
        return @unlink($path);
    }
    return false;
}

function shellCommandOutput($cmd, &$exitCode = null) {
    $exitCode = null;
    $cmd = trim((string)$cmd);
    if ($cmd === '') return '';

    if (function_exists('exec')) {
        $out = [];
        $code = 0;
        @exec($cmd . ' 2>&1', $out, $code);
        $exitCode = (int)$code;
        return trim((string)implode("\n", $out));
    }
    if (function_exists('shell_exec')) {
        $out = @shell_exec($cmd . ' 2>&1');
        if ($out === null) {
            $exitCode = 1;
            return '';
        }
        $exitCode = 0;
        return trim((string)$out);
    }
    $exitCode = 127;
    return '';
}

function findMediaBinary($preferredEnvKey, $fallbackNames) {
    $candidates = [];
    // Check hardcoded paths first
    if ($preferredEnvKey === 'FFMPEG_BIN' && defined('FFMPEG_BIN_PATH')) $candidates[] = FFMPEG_BIN_PATH;
    if ($preferredEnvKey === 'FFPROBE_BIN' && defined('FFPROBE_BIN_PATH')) $candidates[] = FFPROBE_BIN_PATH;
    $envVal = trim((string)getenv($preferredEnvKey));
    if ($envVal !== '') $candidates[] = $envVal;
    foreach ((array)$fallbackNames as $n) {
        $nn = trim((string)$n);
        if ($nn !== '') $candidates[] = $nn;
    }
    $candidates = array_values(array_unique($candidates));
    foreach ($candidates as $bin) {
        $code = 0;
        shellCommandOutput(escapeshellarg($bin) . ' -version', $code);
        if ($code === 0) return $bin;
    }
    return '';
}

function getVideoDurationSeconds($sourcePath, $ffprobeBin) {
    $src = trim((string)$sourcePath);
    $probe = trim((string)$ffprobeBin);
    if ($src === '' || $probe === '' || !is_file($src)) return 0.0;

    $cmd = escapeshellarg($probe) . ' -v error -show_entries format=duration -of default=nk=1:nw=1 ' . escapeshellarg($src);
    $code = 0;
    $out = shellCommandOutput($cmd, $code);
    if ($code !== 0) return 0.0;
    $dur = (float)trim((string)$out);
    return ($dur > 0 && is_finite($dur)) ? $dur : 0.0;
}

function targetVideoSizeMb($durationSec, $originalBytes) {
    $duration = (float)$durationSec;
    $origMb = max(1.0, ((float)$originalBytes) / (1024 * 1024));

    if ($duration <= 0) return min(50.0, max(10.0, $origMb * 0.3));
    if ($duration <= 20) return 10.0;
    if ($duration <= 35) return 18.0;
    if ($duration <= 60) return 50.0;
    if ($duration <= 120) return 70.0;
    return min(120.0, max(50.0, $duration * 0.7));
}

function compressVideoToUploads($tmpPath, $originalName) {
    $tmp = trim((string)$tmpPath);
    if ($tmp === '' || !is_file($tmp)) {
        return ['ok' => false, 'error' => 'Temporary upload missing'];
    }

    $ffmpeg = findMediaBinary('FFMPEG_BIN', ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg']);
    $ffprobe = findMediaBinary('FFPROBE_BIN', ['ffprobe', '/usr/bin/ffprobe', '/usr/local/bin/ffprobe', '/opt/homebrew/bin/ffprobe']);
    if ($ffmpeg === '' || $ffprobe === '') {
        return ['ok' => false, 'error' => 'Video compressor is not available on server (ffmpeg/ffprobe missing).'];
    }

    $duration = getVideoDurationSeconds($tmp, $ffprobe);
    $origBytes = @filesize($tmp) ?: 0;
    $targetMb = targetVideoSizeMb($duration, $origBytes);
    $audioKbps = 128;

    $videoKbps = 1800;
    if ($duration > 0.1) {
        $totalKbps = (int)floor(($targetMb * 8192) / $duration);
        $videoKbps = max(600, $totalKbps - $audioKbps - 80);
    }
    $videoKbps = max(600, min(6500, $videoKbps));
    $maxRateKbps = (int)round($videoKbps * 1.25);
    $bufSizeKbps = (int)round($videoKbps * 2.0);

    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo((string)$originalName, PATHINFO_FILENAME)) ?: 'video';
    $outName = $safe . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.mp4';
    $outPath = rtrim((string)UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $outName;

    $vf = "scale='if(gt(a,1),min(1280,iw),min(1080,iw))':'if(gt(a,1),min(1280,ih),min(1920,ih))':force_original_aspect_ratio=decrease,scale=trunc(iw/2)*2:trunc(ih/2)*2";
    $cmd = escapeshellarg($ffmpeg)
        . ' -y -i ' . escapeshellarg($tmp)
        . ' -vf ' . escapeshellarg($vf)
        . ' -c:v libx264 -preset veryfast'
        . ' -b:v ' . (int)$videoKbps . 'k'
        . ' -maxrate ' . (int)$maxRateKbps . 'k'
        . ' -bufsize ' . (int)$bufSizeKbps . 'k'
        . ' -c:a aac -b:a ' . (int)$audioKbps . 'k'
        . ' -movflags +faststart '
        . escapeshellarg($outPath);

    $code = 0;
    $out = shellCommandOutput($cmd, $code);
    if ($code !== 0 || !is_file($outPath) || (@filesize($outPath) ?: 0) <= 0) {
        if (is_file($outPath)) @unlink($outPath);
        return ['ok' => false, 'error' => 'Video compression failed: ' . deliveryTextLimit($out, 180)];
    }

    return [
        'ok' => true,
        'url' => '/uploads/' . $outName,
        'compressed' => true,
        'durationSec' => $duration,
        'targetMb' => $targetMb,
        'videoKbps' => $videoKbps
    ];
}

function storageCapacityBytes() {
    return 50 * 1024 * 1024 * 1024; // 50 GB
}

function storageNormalizeUploadUrl($value) {
    $raw = trim((string)$value);
    if ($raw === '') return '';
    $raw = str_replace('\\', '/', $raw);

    $qPos = strpos($raw, '?');
    if ($qPos !== false) $raw = substr($raw, 0, $qPos);
    $hPos = strpos($raw, '#');
    if ($hPos !== false) $raw = substr($raw, 0, $hPos);

    if (preg_match('#/uploads/(.+)$#i', $raw, $m)) {
        $rel = trim((string)$m[1], '/');
        if ($rel === '') return '';
        return '/uploads/' . $rel;
    }

    if (stripos($raw, 'uploads/') === 0) {
        $rel = trim(substr($raw, 8), '/');
        if ($rel === '') return '';
        return '/uploads/' . $rel;
    }

    if (preg_match('/^[A-Za-z0-9._\/-]+$/', $raw) && strpos($raw, '/') === false) {
        return '/uploads/' . $raw;
    }

    return '';
}

function storageRelativePathFromUploadUrl($uploadUrl) {
    $u = storageNormalizeUploadUrl($uploadUrl);
    if ($u === '') return '';
    return ltrim(substr($u, strlen('/uploads/')), '/');
}

function storagePathInfoFromInput($fileInput) {
    $base = realpath(UPLOAD_DIR);
    if ($base === false || $base === '') return ['ok' => false, 'error' => 'Upload directory not found.'];

    $raw = trim((string)$fileInput);
    if ($raw === '') return ['ok' => false, 'error' => 'Missing file path.'];
    $raw = str_replace('\\', '/', $raw);

    $relative = '';
    if (stripos($raw, '/uploads/') === 0) {
        $relative = ltrim(substr($raw, strlen('/uploads/')), '/');
    } elseif (stripos($raw, 'uploads/') === 0) {
        $relative = ltrim(substr($raw, strlen('uploads/')), '/');
    } else {
        $relative = ltrim($raw, '/');
    }

    if ($relative === '' || strpos($relative, '..') !== false || !preg_match('/^[A-Za-z0-9._\/-]+$/', $relative)) {
        return ['ok' => false, 'error' => 'Invalid file path.'];
    }

    $target = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $normalizedTarget = str_replace('\\', '/', $target);
    $normalizedBase = rtrim(str_replace('\\', '/', $base), '/');
    if (strpos($normalizedTarget, $normalizedBase . '/') !== 0) {
        return ['ok' => false, 'error' => 'Invalid file path.'];
    }

    return [
        'ok' => true,
        'base' => $base,
        'relative' => str_replace('\\', '/', $relative),
        'url' => '/uploads/' . str_replace('\\', '/', $relative),
        'path' => $target,
    ];
}

function storageCollectReferenceMaps($db) {
    $productRefs = [];
    $proofRefs = [];

    // Product image refs.
    $prodCols = ['id', 'image_urls'];
    $hasProdImg = tableHasColumn($db, 'products', 'img');
    $hasProdImageUrl = tableHasColumn($db, 'products', 'image_url');
    if ($hasProdImg) $prodCols[] = 'img';
    if ($hasProdImageUrl) $prodCols[] = 'image_url';
    $prodSql = 'SELECT ' . implode(',', $prodCols) . ' FROM products';
    $prodRes = $db->query($prodSql);
    while ($prodRes && ($row = $prodRes->fetch_assoc())) {
        $urls = imageUrlsFromValue($row['image_urls'] ?? '');
        if ($hasProdImg) $urls[] = $row['img'] ?? '';
        if ($hasProdImageUrl) $urls[] = $row['image_url'] ?? '';
        foreach ($urls as $candidate) {
            $u = storageNormalizeUploadUrl($candidate);
            if ($u !== '') $productRefs[$u] = true;
        }
    }

    // Payment proof refs.
    if (tableHasColumn($db, 'orders', 'screenshot')) {
        $oRes = $db->query("SELECT screenshot FROM orders WHERE screenshot IS NOT NULL AND screenshot <> ''");
        while ($oRes && ($row = $oRes->fetch_assoc())) {
            $u = storageNormalizeUploadUrl($row['screenshot'] ?? '');
            if ($u !== '') $proofRefs[$u] = true;
        }
    }
    if (tableHasColumn($db, 'payment_reservations', 'screenshot')) {
        $pRes = $db->query("SELECT screenshot FROM payment_reservations WHERE screenshot IS NOT NULL AND screenshot <> ''");
        while ($pRes && ($row = $pRes->fetch_assoc())) {
            $u = storageNormalizeUploadUrl($row['screenshot'] ?? '');
            if ($u !== '') $proofRefs[$u] = true;
        }
    }

    return ['products' => $productRefs, 'proofs' => $proofRefs];
}

function storageBuildData($db) {
    $capacity = storageCapacityBytes();
    $used = 0;
    $files = [];
    $byCategory = [
        'products' => ['count' => 0, 'bytes' => 0],
        'proofs' => ['count' => 0, 'bytes' => 0],
    ];

    $base = realpath(UPLOAD_DIR);
    if ($base === false || $base === '') {
        return [
            'capacityBytes' => $capacity,
            'usedBytes' => 0,
            'freeBytes' => $capacity,
            'usagePercent' => 0,
            'byCategory' => $byCategory,
            'files' => [],
        ];
    }

    $refs = storageCollectReferenceMaps($db);
    $productRefs = $refs['products'];
    $proofRefs = $refs['proofs'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) continue;
        $full = $fileInfo->getPathname();
        $relative = ltrim(str_replace('\\', '/', substr($full, strlen($base))), '/');
        if ($relative === '') continue;
        $ext = strtolower((string)pathinfo($relative, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'avif', 'heic', 'jfif'], true)) {
            continue;
        }

        $url = '/uploads/' . $relative;
        $size = (int)@filesize($full);
        if ($size < 0) $size = 0;
        $mtime = (int)@filemtime($full);
        if ($mtime < 0) $mtime = 0;

        $category = isset($proofRefs[$url]) ? 'proofs' : 'products';
        $referenced = isset($proofRefs[$url]) || isset($productRefs[$url]);

        $used += $size;
        $byCategory[$category]['count'] += 1;
        $byCategory[$category]['bytes'] += $size;

        $files[] = [
            'name' => basename($relative),
            'path' => '/uploads/' . $relative,
            'url' => $url,
            'relativePath' => $relative,
            'size' => $size,
            'modifiedAt' => $mtime,
            'category' => $category,
            'referenced' => $referenced,
        ];
    }

    usort($files, function($a, $b) {
        $am = (int)($a['modifiedAt'] ?? 0);
        $bm = (int)($b['modifiedAt'] ?? 0);
        if ($am === $bm) return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        return $bm <=> $am;
    });

    $percent = $capacity > 0 ? round(($used / $capacity) * 100, 2) : 0;
    if ($percent < 0) $percent = 0;
    if ($percent > 100) $percent = 100;

    return [
        'capacityBytes' => $capacity,
        'usedBytes' => $used,
        'freeBytes' => max($capacity - $used, 0),
        'usagePercent' => $percent,
        'byCategory' => $byCategory,
        'files' => $files,
    ];
}

function storageDetachUrlReferences($db, $uploadUrl) {
    $url = storageNormalizeUploadUrl($uploadUrl);
    if ($url === '') return;
    $safeUrl = $db->real_escape_string($url);

    // Products image references.
    $hasProdImg = tableHasColumn($db, 'products', 'img');
    $hasProdImageUrl = tableHasColumn($db, 'products', 'image_url');
    $prodCols = ['id', 'image_urls'];
    if ($hasProdImg) $prodCols[] = 'img';
    if ($hasProdImageUrl) $prodCols[] = 'image_url';
    $prodSql = 'SELECT ' . implode(',', $prodCols) . ' FROM products';
    $prodRes = $db->query($prodSql);
    while ($prodRes && ($row = $prodRes->fetch_assoc())) {
        $pid = $db->real_escape_string((string)($row['id'] ?? ''));
        if ($pid === '') continue;

        $rawUrls = imageUrlsFromValue($row['image_urls'] ?? '');
        $filtered = [];
        foreach ($rawUrls as $u) {
            if (storageNormalizeUploadUrl($u) === $url) continue;
            $filtered[] = $u;
        }

        $needsImageUrlsUpdate = count($filtered) !== count($rawUrls);
        $newPrimary = $filtered ? (string)$filtered[0] : '';

        if ($needsImageUrlsUpdate) {
            $json = json_encode(array_values($filtered), JSON_UNESCAPED_SLASHES);
            if ($json === false) $json = '[]';
            $db->query("UPDATE products SET image_urls='" . $db->real_escape_string($json) . "' WHERE id='$pid'");
        }

        if ($hasProdImg && storageNormalizeUploadUrl($row['img'] ?? '') === $url) {
            $db->query("UPDATE products SET img='" . $db->real_escape_string($newPrimary) . "' WHERE id='$pid'");
        }
        if ($hasProdImageUrl && storageNormalizeUploadUrl($row['image_url'] ?? '') === $url) {
            $db->query("UPDATE products SET image_url='" . $db->real_escape_string($newPrimary) . "' WHERE id='$pid'");
        }
    }

    // Proof references.
    if (tableHasColumn($db, 'orders', 'screenshot')) {
        $db->query("UPDATE orders SET screenshot='' WHERE screenshot='$safeUrl'");
    }
    if (tableHasColumn($db, 'payment_reservations', 'screenshot')) {
        $db->query("UPDATE payment_reservations SET screenshot='' WHERE screenshot='$safeUrl'");
    }
}

function invoiceAuditNormalizeParcelId($value) {
    $v = strtoupper(trim((string)$value));
    if ($v === '') return '';
    $v = preg_replace('/[^A-Z0-9-]/', '', $v);
    return trim((string)$v);
}

function invoiceAuditNormalizeCity($value) {
    $v = strtolower(trim((string)$value));
    return preg_replace('/\s+/', ' ', $v);
}

function invoiceAuditExpectedShippingByCity($city) {
    $c = invoiceAuditNormalizeCity($city);
    if ($c === '') return 400;
    if (strpos($c, 'karachi') !== false || strpos($c, 'khi') !== false) return 350;
    return 400;
}

function invoiceAuditExtractPdfText($tmpPath) {
    $tmp = trim((string)$tmpPath);
    if ($tmp === '' || !is_file($tmp)) return ['ok' => false, 'text' => '', 'warning' => 'Invoice PDF not found on server temp path.'];
    if (!function_exists('shell_exec')) return ['ok' => false, 'text' => '', 'warning' => 'shell_exec is disabled, PDF parsing unavailable on server.'];

    $escaped = escapeshellarg($tmp);
    $cmd = 'pdftotext -layout ' . $escaped . ' - 2>/dev/null';
    $out = @shell_exec($cmd);
    $text = trim((string)$out);
    if ($text === '') {
        return ['ok' => false, 'text' => '', 'warning' => 'Could not parse PDF text. Install pdftotext on server for invoice audit.'];
    }
    return ['ok' => true, 'text' => $text];
}

function invoiceAuditParsePdfData($text) {
    $raw = (string)$text;
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $parcelIds = [];
    $shippingByParcel = [];
    $shippingTotal = 0;
    $taxTotal = null;
    $invoiceTotal = null;

    foreach ($lines as $line) {
        $ln = trim((string)$line);
        if ($ln === '') continue;

        if (preg_match_all('/\b(?:DEE\d{6,}|[A-Z]{2,}\d{5,}|\d{6,})\b/', strtoupper($ln), $m)) {
            $candidates = $m[0];
            $amounts = [];
            if (preg_match_all('/(?:RS\.?\s*)?([0-9]{2,6}(?:\.[0-9]{1,2})?)/i', $ln, $am)) {
                foreach (($am[1] ?? []) as $a) {
                    $val = (float)$a;
                    if ($val > 0) $amounts[] = $val;
                }
            }
            $lineShipping = null;
            foreach ($amounts as $a) {
                if ($a >= 300 && $a <= 1000) {
                    $lineShipping = (int)round($a);
                }
            }
            foreach ($candidates as $cid) {
                $pid = invoiceAuditNormalizeParcelId($cid);
                if ($pid === '') continue;
                $parcelIds[$pid] = true;
                if ($lineShipping !== null) $shippingByParcel[$pid] = $lineShipping;
            }
        }

        if ($taxTotal === null && preg_match('/\btax\b/i', $ln) && preg_match('/([0-9]{2,8}(?:\.[0-9]{1,2})?)/', $ln, $mt)) {
            $taxTotal = (int)round((float)$mt[1]);
        }
        if ($invoiceTotal === null && preg_match('/\b(grand\s*total|net\s*total|total)\b/i', $ln) && preg_match('/([0-9]{2,9}(?:\.[0-9]{1,2})?)/', $ln, $mg)) {
            $invoiceTotal = (int)round((float)$mg[1]);
        }
    }

    foreach ($shippingByParcel as $v) $shippingTotal += (int)$v;

    return [
        'parcelIds' => array_values(array_keys($parcelIds)),
        'shippingByParcel' => $shippingByParcel,
        'shippingTotal' => (int)$shippingTotal,
        'taxTotal' => $taxTotal,
        'invoiceTotal' => $invoiceTotal,
    ];
}

function invoiceAuditDbDeliveredMap($db, $fromDate, $toDate) {
    $from = trim((string)$fromDate);
    $to = trim((string)$toDate);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        return ['ok' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD.'];
    }

    $fromSafe = $db->real_escape_string($from);
    $toSafe = $db->real_escape_string($to);
    $q = "SELECT o.id, o.city, o.total, o.subtotal, o.status, o.tracking_updated_at, o.created_at,
                 COALESCE(NULLIF(d.portal_booking_id,''), '') AS parcel_id
          FROM orders o
          LEFT JOIN delivery_bookings d ON d.order_id = o.id
          WHERE LOWER(o.status)='delivered'
            AND DATE(COALESCE(o.tracking_updated_at, o.created_at)) BETWEEN '$fromSafe' AND '$toSafe'
          ORDER BY COALESCE(o.tracking_updated_at, o.created_at) DESC";
    $res = $db->query($q);
    if (!$res) return ['ok' => false, 'error' => 'Could not query delivered orders.'];

    $rows = [];
    $byParcel = [];
    $expectedShippingTotal = 0;
    while ($r = $res->fetch_assoc()) {
        $parcel = invoiceAuditNormalizeParcelId($r['parcel_id'] ?? '');
        $orderId = trim((string)($r['id'] ?? ''));
        $city = (string)($r['city'] ?? '');
        $expected = invoiceAuditExpectedShippingByCity($city);
        $expectedShippingTotal += $expected;
        $entry = [
            'orderId' => $orderId,
            'parcelId' => $parcel,
            'city' => $city,
            'expectedShipping' => $expected,
            'total' => (int)($r['total'] ?? 0),
            'subtotal' => (int)($r['subtotal'] ?? 0),
        ];
        $rows[] = $entry;
        if ($parcel !== '') $byParcel[$parcel] = $entry;
    }
    return [
        'ok' => true,
        'rows' => $rows,
        'byParcel' => $byParcel,
        'expectedShippingTotal' => (int)$expectedShippingTotal,
    ];
}

function deliveryTryLoginAndOpenBooking($cookieFile) {
    $loginUrl = deliveryPortalUrl('clients/login.php');
    $indexUrl = deliveryPortalUrl('clients/index.php');

    $openFreshBooking = function() use ($cookieFile, $indexUrl, $loginUrl) {
        $lastBookingHtml = '';
        for ($i = 0; $i < 5; $i++) {
            $nonce = str_replace('.', '', sprintf('%.6f', microtime(true))) . '-' . (string)mt_rand(1000, 9999);
            $freshIndexUrl = deliveryAppendQuery($indexUrl, ['dee_click' => $nonce]);

            $idx = deliveryRequest($freshIndexUrl, 'GET', [], $cookieFile, [
                'referer' => $loginUrl,
                'headers' => ['Cache-Control: no-cache', 'Pragma: no-cache'],
            ]);
            $indexHtml = (string)($idx['body'] ?? '');
            $newPath = deliveryExtractNewBookingPath($indexHtml);
            $targetBookingUrl = deliveryPortalBuildUrl($newPath, 'clients/new_booking.php');
            $targetBookingUrl = deliveryAppendQuery($targetBookingUrl, ['dee_click' => $nonce]);

            $bookingPage = deliveryRequest($targetBookingUrl, 'GET', [], $cookieFile, [
                'referer' => $freshIndexUrl,
                'headers' => ['Cache-Control: no-cache', 'Pragma: no-cache'],
            ]);
            $lastBookingHtml = (string)($bookingPage['body'] ?? '');
            if ($bookingPage['ok'] && deliveryIsBookingFormHtml($lastBookingHtml)) {
                $pid = trim((string)deliveryExtractPortalParcelId($lastBookingHtml));
                if ($pid !== '') {
                    return ['ok' => true, 'bookingHtml' => $lastBookingHtml, 'message' => 'Booking page ready'];
                }
            }
            usleep(250000); // wait 250ms before trying to open a fresh booking form again
        }
        return [
            'ok' => false,
            'bookingHtml' => $lastBookingHtml,
            'message' => 'Could not open fresh booking form with Parcel_Id.',
        ];
    };

    $loginPage = deliveryRequest($loginUrl, 'GET', [], $cookieFile, [
        'headers' => ['Cache-Control: no-cache'],
    ]);
    $loginHtml = (string)($loginPage['body'] ?? '');
    $hidden = deliveryExtractHiddenInputs($loginHtml);
    $alreadyLoggedIn = deliveryIsBookingFormHtml($loginHtml) ||
        (stripos($loginHtml, 'all bookings') !== false && stripos($loginHtml, 'new_booking.php') !== false);
    if ($alreadyLoggedIn) {
        return $openFreshBooking();
    }

    foreach (deliveryLoginPayloadCandidates(DELIVERY_PORTAL_USERNAME, DELIVERY_PORTAL_PASSWORD) as $cand) {
        $payload = array_merge($hidden, $cand, ['submit' => 'Login']);
        $post = deliveryRequest($loginUrl, 'POST', $payload, $cookieFile, [
            'referer' => $loginUrl,
            'headers' => ['Cache-Control: no-cache'],
        ]);
        if (!$post['ok']) continue;
        $fresh = $openFreshBooking();
        if (!empty($fresh['ok'])) {
            $fresh['message'] = 'Login successful';
            return $fresh;
        }
    }

    return ['ok' => false, 'bookingHtml' => '', 'message' => 'Login failed for delivery portal'];
}

function createDeliveryBookingForOrder($db, $orderRow, $options = []) {
    $out = [
        'attempted' => false,
        'ok' => false,
        'already' => false,
        'parcelId' => '',
        'message' => '',
    ];
    $opts = is_array($options) ? $options : [];
    $force = !empty($opts['force']);

    if (!deliveryPortalEnabled()) {
        $out['message'] = 'Delivery portal settings are missing.';
        return $out;
    }

    $orderId = trim((string)($orderRow['id'] ?? ''));
    if ($orderId === '') {
        $out['message'] = 'Order id is missing.';
        return $out;
    }

    $existing = deliveryBookingGet($db, $orderId);
    if (!$force && $existing && strtolower((string)($existing['status'] ?? '')) === 'success') {
        $out['ok'] = true;
        $out['already'] = true;
        $out['parcelId'] = trim((string)($existing['portal_booking_id'] ?? ''));
        $out['message'] = 'Delivery booking already exists for this order.';
        return $out;
    }

    $out['attempted'] = true;
    $cookieFile = rtrim((string)sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dee-delivery-' . bin2hex(random_bytes(6)) . '.cookie';

    try {
        $login = deliveryTryLoginAndOpenBooking($cookieFile);
        if (!$login['ok']) {
            $msg = (string)($login['message'] ?? 'Could not log in to delivery portal.');
            deliveryBookingSave($db, $orderId, 'failed', '', '', $msg);
            $out['message'] = $msg;
            return $out;
        }

        $bookingHtml = (string)($login['bookingHtml'] ?? '');
        if ($bookingHtml === '') {
            $bookingRes = deliveryRequest(deliveryPortalUrl('clients/new_booking.php'), 'GET', [], $cookieFile);
            $bookingHtml = (string)($bookingRes['body'] ?? '');
        }

        $hidden = deliveryExtractHiddenInputs($bookingHtml);
        // Keep the portal-assigned parcel id from new_booking form.
        $parcelId = trim((string)deliveryExtractPortalParcelId($bookingHtml));
        if ($parcelId === '') {
            // Retry once with a fresh "index -> new_booking" click sequence.
            $fresh = deliveryTryLoginAndOpenBooking($cookieFile);
            if (!empty($fresh['ok'])) {
                $bookingHtml = (string)($fresh['bookingHtml'] ?? '');
                $hidden = deliveryExtractHiddenInputs($bookingHtml);
                $parcelId = trim((string)deliveryExtractPortalParcelId($bookingHtml));
            }
        }
        if ($parcelId === '') {
            $msg = 'Could not read portal-assigned Parcel_Id.';
            $preview = deliveryTextLimit(preg_replace('/\s+/', ' ', strip_tags((string)$bookingHtml)), 320);
            deliveryBookingSave($db, $orderId, 'failed', '', $preview, $msg);
            $out['message'] = $msg;
            return $out;
        }

        $shipperId = trim((string)deliveryExtractInputValue($bookingHtml, 'ShipperID', '390'));
        $companyName = trim((string)deliveryExtractInputValue($bookingHtml, 'CompanyName', 'DeeThrift'));
        $contactNumber = trim((string)deliveryExtractInputValue($bookingHtml, 'ContactNumber', ''));
        $companyAddress = trim((string)deliveryExtractInputValue($bookingHtml, 'CompanyAddress', ''));
        $emailAddress = trim((string)deliveryExtractInputValue($bookingHtml, 'EmailAddress', ''));

        $consigneeName = trim((string)($orderRow['name'] ?? ''));
        if ($consigneeName === '') $consigneeName = 'Customer';
        $consigneePhone = trim((string)($orderRow['phone'] ?? ''));
        $consigneeCity = trim((string)($orderRow['city'] ?? ''));
        if ($consigneeCity === '') $consigneeCity = 'Karachi';
        $consigneeAddress = trim((string)($orderRow['address'] ?? ''));
        if ($consigneeAddress === '') $consigneeAddress = 'Address not provided';

        $reference = 'Order ' . $orderId;
        $insta = trim((string)($orderRow['instagram'] ?? ''));
        if ($insta !== '') $reference .= ' | ' . $insta;

        $codAmount = codCollectibleAmountForOrder($orderRow);

        $payload = array_merge($hidden, [
            'ShipperID' => $shipperId !== '' ? $shipperId : '390',
            'CompanyName' => $companyName !== '' ? $companyName : 'DeeThrift',
            'ContactNumber' => $contactNumber,
            'CompanyAddress' => $companyAddress,
            'EmailAddress' => $emailAddress,
            'ConsigneeName' => $consigneeName,
            'ConsigneeContact1' => $consigneePhone,
            'ConsigneeCity' => $consigneeCity,
            'ConsigneeAddress1' => $consigneeAddress,
            'Reference' => deliveryTextLimit($reference, 120),
            'Parcel_Id' => $parcelId,
            'ItemDescription' => deliveryOrderSummary($orderRow),
            'CODAmount' => $codAmount,
            'SpecialInstructions' => deliveryTextLimit((string)($orderRow['notes'] ?? ''), 160),
            'Weight' => (float)DELIVERY_PORTAL_DEFAULT_WEIGHT,
            'ServiceType' => trim((string)DELIVERY_PORTAL_DEFAULT_SERVICE) ?: 'Overnight',
        ]);

        $save = deliveryRequest(deliveryPortalUrl('clients/save_booking.php'), 'POST', $payload, $cookieFile, [
            'referer' => deliveryPortalUrl('clients/new_booking.php'),
            'headers' => ['Cache-Control: no-cache', 'Pragma: no-cache'],
        ]);
        $responseBody = trim((string)($save['body'] ?? ''));
        $success = ($save['ok'] && ($responseBody === '1' || stripos($responseBody, 'success') !== false));

        if ($success) {
            deliveryBookingSave($db, $orderId, 'success', $parcelId, $responseBody, '');
            $out['ok'] = true;
            $out['parcelId'] = $parcelId;
            $out['message'] = 'Delivery booking created.';
            return $out;
        }

        $errMsg = 'Portal did not accept the booking request.';
        if (!$save['ok']) $errMsg = 'Portal request failed (HTTP ' . (int)($save['status'] ?? 0) . ').';
        if ($responseBody !== '') $errMsg .= ' ' . deliveryTextLimit($responseBody, 240);
        deliveryBookingSave($db, $orderId, 'failed', $parcelId, $responseBody, $errMsg);
        $out['parcelId'] = $parcelId;
        $out['message'] = $errMsg;
        return $out;
    } catch (Throwable $e) {
        $msg = 'Delivery booking exception: ' . deliveryTextLimit($e->getMessage(), 300);
        deliveryBookingSave($db, $orderId, 'failed', '', '', $msg);
        $out['message'] = $msg;
        return $out;
    } finally {
        deliveryCleanupCookie($cookieFile);
    }
}

function insertOrderRecord($db, $idsToTry, $order, $productIds, $status) {
    $itemsJson = json_encode($order['items'] ?? [], JSON_UNESCAPED_UNICODE);
    if ($itemsJson === false) $itemsJson = '[]';
    $productNamesJson = json_encode($order['productNames'] ?? [], JSON_UNESCAPED_UNICODE);
    if ($productNamesJson === false) $productNamesJson = '[]';
    $productIdsJson = json_encode($productIds, JSON_UNESCAPED_UNICODE);
    if ($productIdsJson === false) $productIdsJson = '[]';

    $paymentMode = trim((string)($order['paymentMode'] ?? 'cod'));
    if ($paymentMode === '') $paymentMode = 'cod';

    foreach ($idsToTry as $idCandidate) {
        $oidSafe = $db->real_escape_string($idCandidate);
        $ok = $db->query("INSERT INTO orders
            (id,name,phone,phone2,instagram,email,address,city,notes,items,subtotal,total,payment_mode,status,product_ids,product_names,first_order)
            VALUES (
                '$oidSafe',
                '" . $db->real_escape_string($order['name'] ?? '') . "',
                '" . $db->real_escape_string($order['phone'] ?? '') . "',
                '" . $db->real_escape_string($order['secondPhone'] ?? '') . "',
                '" . $db->real_escape_string($order['instagramHandle'] ?? '') . "',
                '" . $db->real_escape_string($order['email'] ?? '') . "',
                '" . $db->real_escape_string($order['address'] ?? '') . "',
                '" . $db->real_escape_string($order['city'] ?? '') . "',
                '" . $db->real_escape_string($order['notes'] ?? '') . "',
                '" . $db->real_escape_string($itemsJson) . "',
                " . (int)($order['subtotal'] ?? 0) . ",
                " . (int)($order['total'] ?? 0) . ",
                '" . $db->real_escape_string($paymentMode) . "',
                '" . $db->real_escape_string($status) . "',
                '" . $db->real_escape_string($productIdsJson) . "',
                '" . $db->real_escape_string($productNamesJson) . "',
                " . (!empty($order['firstOrder']) ? 1 : 0) . "
            )");

        if ($ok) return $idCandidate;
        if ((int)$db->errno !== 1062) {
            throw new Exception('Order insert failed: ' . $db->error);
        }
    }

    throw new Exception('Could not generate a unique order ID.');
}

function hasProductDefectsColumn($db) {
    static $hasDefects = null;
    if ($hasDefects !== null) return $hasDefects;
    $hasDefects = tableHasColumn($db, 'products', 'defects');
    return $hasDefects;
}

function hasProductDescriptionColumn($db) {
    static $hasDescription = null;
    if ($hasDescription !== null) return $hasDescription;
    $hasDescription = tableHasColumn($db, 'products', 'description');
    return $hasDescription;
}

function hasProductVideoColumn($db) {
    static $hasVideo = null;
    if ($hasVideo !== null) return $hasVideo;
    $hasVideo = tableHasColumn($db, 'products', 'video_url');
    return $hasVideo;
}

function hasProductVideoUrlsColumn($db) {
    static $hasVideoUrls = null;
    if ($hasVideoUrls !== null) return $hasVideoUrls;
    $hasVideoUrls = tableHasColumn($db, 'products', 'video_urls');
    return $hasVideoUrls;
}

function getSettings($db) {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [
        'cod_shipping_fee'      => 300,
        'cod_partial_threshold' => 5000,
        'cod_partial_amount'    => 1000,
        'cod_tax_rate'          => 0.08,
    ];
    $res = $db->query("SELECT setting_key, setting_value FROM settings");
    while ($res && ($row = $res->fetch_assoc())) {
        $cache[$row['setting_key']] = $row['setting_value'];
    }
    return $cache;
}

function codShippingFeeAmount() {
    global $db;
    $s = getSettings($db);
    return max(0, (int)$s['cod_shipping_fee']);
}
function codPartialThresholdAmount() {
    global $db;
    $s = getSettings($db);
    return max(0, (int)$s['cod_partial_threshold']);
}
function codPartialAmount() {
    global $db;
    $s = getSettings($db);
    return max(0, (int)$s['cod_partial_amount']);
}
function codTaxRate() {
    global $db;
    $s = getSettings($db);
    return max(0, (float)$s['cod_tax_rate']);
}

ensureSchema($db);
sweepExpiredReservations($db);

// GET
if ($method === 'GET') {
    $action = input('action');

    if ($action === 'products') {
        $res = $db->query('SELECT * FROM products ORDER BY created_at DESC');
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $garmentType = normalizeCategoryValue($r['garment_type'] ?? ($r['category'] ?? 'accessories'));
            $category = normalizeCategoryValue($r['category'] ?? $garmentType, $garmentType);
            $imgs = json_decode((string)($r['image_urls'] ?? ''), true);
            if (!is_array($imgs)) $imgs = [];
            $legacyImg = trim((string)($r['img'] ?? ($r['image_url'] ?? '')));
            if ($legacyImg !== '' && !in_array($legacyImg, $imgs, true)) array_unshift($imgs, $legacyImg);
            $imgs = array_slice($imgs, 0, 5);

            $vids = json_decode((string)($r['video_urls'] ?? ''), true);
            if (!is_array($vids)) $vids = [];
            $legacyVideo = trim((string)($r['video_url'] ?? ''));
            if ($legacyVideo !== '' && !in_array($legacyVideo, $vids, true)) array_unshift($vids, $legacyVideo);
            $vids = array_slice($vids, 0, 5);
            $out[] = [
                'id'           => $r['id'],
                'name'         => $r['name'],
                'garmentType'  => $garmentType,
                'category'     => $category,
                'tags'         => json_decode($r['tags'], true) ?: ['new'],
                'cond'         => normalizeCondition($r['cond'] ?? ''),
                'defects'      => normalizeDefects(array_key_exists('defects', $r) ? $r['defects'] : ''),
                'description'  => normalizeDescription(array_key_exists('description', $r) ? $r['description'] : ''),
                'videoUrl'     => $vids[0] ?? trim((string)(array_key_exists('video_url', $r) ? $r['video_url'] : '')),
                'videoUrls'    => $vids,
                'price'        => (int)$r['price'],
                'basePrice'    => (int)($r['base_price'] ?? 0),
                'salePercent'  => (int)($r['sale_percent'] ?? 0),
                'saleActive'   => (int)($r['sale_active'] ?? 0),
                'measurements' => $r['measurements'],
                'meta'         => $r['measurements'],
                'imageUrls'    => $imgs,
                'imageUrl'     => $imgs[0] ?? '',
                'status'       => $r['status'],
                'is_featured'  => (int)$r['is_featured'],
            ];
        }
        $covers = [];
        $coverRes = $db->query('SELECT category_key, product_id FROM category_covers');
        while ($coverRes && ($cr = $coverRes->fetch_assoc())) {
            $k = normalizeCategoryValue($cr['category_key'] ?? '');
            $pid = trim((string)($cr['product_id'] ?? ''));
            if ($k !== '' && $pid !== '') $covers[$k] = $pid;
        }
        jsonOut(['products' => $out, 'categoryCovers' => $covers]);
    }

    if ($action === 'orders') {
        requireAdminAccess();
        $status = input('status');
        $sql = $status
            ? "SELECT * FROM orders WHERE status='" . $db->real_escape_string($status) . "' ORDER BY created_at DESC"
            : 'SELECT * FROM orders ORDER BY created_at DESC';
        $res = $db->query($sql);
        $out = [];
        while ($r = $res->fetch_assoc()) {
            $r['productNames'] = json_decode($r['product_names'], true) ?: [];
            $r['items']        = json_decode($r['items'], true) ?: [];
            $r['productIds']   = json_decode($r['product_ids'], true) ?: [];
            $out[] = $r;
        }
        jsonOut(['orders' => $out]);
    }

    if ($action === 'order') {
        $id  = $db->real_escape_string(input('id'));
        $res = $db->query("SELECT * FROM orders WHERE id='$id'");
        $r   = $res->fetch_assoc();
        if ($r) {
            $r['productNames'] = json_decode($r['product_names'], true) ?: [];
            $r['items']        = json_decode($r['items'], true) ?: [];
            $r['productIds']   = json_decode($r['product_ids'], true) ?: [];
            jsonOut(['order' => $r]);
        }
        jsonOut(['error' => 'Not found'], 404);
    }

    if ($action === 'tracking_lookup') {
        $orderIdRaw = trim((string)input('orderId'));
        $orderId = preg_replace('/[^a-zA-Z0-9_-]/', '', $orderIdRaw);
        if ($orderId === '') {
            jsonOut([
                'ok' => false,
                'foundOrder' => false,
                'message' => 'Please enter a valid order number.',
            ]);
        }

        $oidSafe = $db->real_escape_string($orderId);
        $orderRes = $db->query("SELECT * FROM orders WHERE id='$oidSafe' LIMIT 1");
        $order = $orderRes ? $orderRes->fetch_assoc() : null;
        if (!$order) {
            jsonOut([
                'ok' => true,
                'foundOrder' => false,
                'orderId' => $orderId,
                'message' => 'Order not found. Please check your order number and try again.',
            ]);
        }

        $result = null;
        $storedTracking = normalizeTrackingNumber((string)($order['tracking_number'] ?? ''));
        if ($storedTracking !== '') {
            $result = trackingResponseForNumber(
                $storedTracking,
                (string)($order['tracking_status_text'] ?? ''),
                'stored_tracking'
            );
        }

        $sources = [];
        $sources[] = ['source' => 'order_status', 'text' => (string)($order['status'] ?? '')];
        $sources[] = ['source' => 'order_notes', 'text' => (string)($order['notes'] ?? '')];

        $booking = deliveryBookingGet($db, $orderId);
        if ($booking) {
            $sources[] = ['source' => 'delivery_response', 'text' => (string)($booking['response_text'] ?? '')];
            $sources[] = ['source' => 'delivery_error', 'text' => (string)($booking['last_error'] ?? '')];
            $sources[] = ['source' => 'delivery_booking_id', 'text' => (string)($booking['portal_booking_id'] ?? '')];
        }

        foreach ($sources as $src) {
            $text = (string)($src['text'] ?? '');
            if (trim($text) === '') continue;
            $trackingNumber = trackingExtractNumberFromText($text);
            if ($trackingNumber === '') continue;
            $result = trackingResponseForNumber($trackingNumber, $text, (string)($src['source'] ?? 'local'));
            break;
        }

        $portalChecked = false;
        $portalMessage = '';
        if ($result === null && deliveryPortalEnabled()) {
            $portalChecked = true;
            $cookieRand = '';
            try {
                $cookieRand = bin2hex(random_bytes(6));
            } catch (Throwable $e) {
                $cookieRand = preg_replace('/[^a-zA-Z0-9]/', '', uniqid('track', true));
            }
            $cookieFile = rtrim((string)sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dee-track-' . $cookieRand . '.cookie';
            try {
                $idx = deliveryTryLoginAndOpenIndex($cookieFile);
                $portalMessage = (string)($idx['message'] ?? '');
                if (!empty($idx['ok'])) {
                    $indexHtml = (string)($idx['indexHtml'] ?? '');
                    $lookupTokens = [
                        $orderId,
                        'Order ' . $orderId,
                        (string)($order['phone'] ?? ''),
                        preg_replace('/\D+/', '', (string)($order['phone'] ?? '')),
                        (string)($order['instagram'] ?? ''),
                    ];
                    $rawRowSegment = deliveryExtractRawRowSegmentByOrderId($indexHtml, $orderId);
                    $rowText = trackingTextNormalize($rawRowSegment);
                    $trackingNumber = trackingExtractNumberFromText($rawRowSegment);

                    if ($rowText === '') {
                        $rowText = deliveryExtractRowByTokens($indexHtml, $lookupTokens);
                    }
                    if ($rowText === '') {
                        $flat = trackingTextNormalize($indexHtml);
                        foreach ($lookupTokens as $tk) {
                            $tk = trim((string)$tk);
                            if ($tk === '') continue;
                            $p = stripos($flat, $tk);
                            if ($p !== false) {
                                $start = max(0, $p - 260);
                                $rowText = substr($flat, $start, 680);
                                break;
                            }
                        }
                    }

                    if ($trackingNumber === '') {
                        $trackingNumber = trackingExtractNumberFromText($rowText);
                    }
                    if ($trackingNumber === '') {
                        // Extra fallback: parse raw HTML chunk near order token for malformed status markup.
                        foreach ($lookupTokens as $tk) {
                            $tk = trim((string)$tk);
                            if ($tk === '') continue;
                            $pRaw = stripos($indexHtml, $tk);
                            if ($pRaw === false) continue;
                            $rawSlice = substr($indexHtml, max(0, $pRaw - 500), 2600);
                            $trackingNumber = trackingExtractNumberFromText($rawSlice);
                            if ($trackingNumber !== '') break;
                        }
                    }
                    if ($trackingNumber === '') {
                        $trackingNumber = trackingExtractFromOrderRow($indexHtml, $orderId);
                    }
                    if ($trackingNumber !== '') {
                        $result = trackingResponseForNumber($trackingNumber, $rowText, 'delivery_portal');
                    }
                }
            } catch (Throwable $e) {
                // Keep customer response friendly; fallback message is returned below.
                $portalMessage = 'Portal lookup failed.';
            } finally {
                deliveryCleanupCookie($cookieFile);
            }
        }

        if ($result === null) {
            $result = trackingResponseForNumber('', '', $portalChecked ? 'pending_portal' : 'pending_local');
        }

        jsonOut(array_merge([
            'ok' => true,
            'foundOrder' => true,
            'orderId' => (string)($order['id'] ?? $orderId),
            'orderStatus' => (string)($order['status'] ?? ''),
            'portalChecked' => $portalChecked,
            'portalMessage' => $portalMessage,
        ], $result));
    }

    if ($action === 'payment_reservation') {
        $rid = trim((string)input('rid'));
        $token = trim((string)input('token'));
        if ($rid === '' || $token === '') jsonOut(['error' => 'Missing reservation details'], 400);

        $ridSafe = $db->real_escape_string($rid);
        $res = $db->query("SELECT id,access_token,status,payload,payment_mode,expires_at,order_id FROM payment_reservations WHERE id='$ridSafe' LIMIT 1");
        $row = $res ? $res->fetch_assoc() : null;
        if (!$row) jsonOut(['error' => 'Reservation not found'], 404);
        if (!hash_equals((string)($row['access_token'] ?? ''), $token)) jsonOut(['error' => 'Invalid reservation token'], 403);

        $status = strtolower((string)($row['status'] ?? ''));
        $expiresAt = (string)($row['expires_at'] ?? '');
        $expiresTs = $expiresAt ? strtotime($expiresAt) : 0;

        if ($status === 'active' && $expiresTs > 0 && $expiresTs <= time()) {
            $db->begin_transaction();
            try {
                $lockRes = $db->query("SELECT id,status,product_ids,expires_at FROM payment_reservations WHERE id='$ridSafe' FOR UPDATE");
                $locked = $lockRes ? $lockRes->fetch_assoc() : null;
                if ($locked && strtolower((string)($locked['status'] ?? '')) === 'active') {
                    $lockedTs = strtotime((string)($locked['expires_at'] ?? '')) ?: 0;
                    if ($lockedTs > 0 && $lockedTs <= time()) {
                        $pids = normalizeProductIdList(json_decode((string)($locked['product_ids'] ?? ''), true) ?: []);
                        releaseReservedProducts($db, $pids);
                        $db->query("UPDATE payment_reservations SET status='expired' WHERE id='$ridSafe'");
                    }
                }
                $db->commit();
                bumpChangeToken();
            } catch (Throwable $e) {
                $db->rollback();
            }
            jsonOut(['error' => 'This payment session expired. Please checkout again.'], 409);
        }

        if ($status === 'converted') {
            jsonOut(['reservation' => [
                'id' => $row['id'],
                'status' => 'converted',
                'orderId' => (string)($row['order_id'] ?? ''),
            ]]);
        }
        if ($status !== 'active') {
            jsonOut(['error' => 'This payment session is no longer active.'], 409);
        }

        $payload = json_decode((string)($row['payload'] ?? ''), true);
        if (!is_array($payload)) $payload = [];
        $secondsLeft = $expiresTs > 0 ? max(0, $expiresTs - time()) : 0;

        jsonOut(['reservation' => [
            'id'           => $row['id'],
            'status'       => 'active',
            'expiresAt'    => $expiresAt,
            'secondsLeft'  => $secondsLeft,
            'paymentMode'  => (string)($row['payment_mode'] ?? ''),
            'total'        => (int)($payload['total'] ?? 0),
            'subtotal'     => (int)($payload['subtotal'] ?? 0),
            'codTax'       => (int)($payload['codTax'] ?? 0),
            'shipping'     => (int)($payload['codDeliveryFee'] ?? 0),
            'items'        => is_array($payload['items'] ?? null) ? $payload['items'] : [],
            'name'         => (string)($payload['name'] ?? ''),
            'phone'        => (string)($payload['phone'] ?? ''),
            'city'         => (string)($payload['city'] ?? ''),
            'productIds'   => normalizeProductIdList($payload['productIds'] ?? []),
        ]]);
    }

    if ($action === 'customer') {
        $phone = $db->real_escape_string(input('phone'));
        $res   = $db->query("SELECT * FROM customers WHERE phone='$phone'");
        $r     = $res->fetch_assoc();
        if ($r) {
            jsonOut([
                'returning'         => (int)$r['order_count'] > 0,
                'codBlocked'        => (int)$r['cod_blocked'] === 1,
                'latestOrderStatus' => $r['latest_order_status'] ?? '',
            ]);
        }
        jsonOut(['returning' => false, 'codBlocked' => false, 'latestOrderStatus' => '']);
    }

    if ($action === 'customers') {
        requireAdminAccess();
        $res = $db->query('SELECT * FROM customers ORDER BY updated_at DESC');
        $out = [];
        while ($r = $res->fetch_assoc()) $out[] = $r;
        jsonOut(['customers' => $out]);
    }

    if ($action === 'donate_cases') {
        $res = $db->query('SELECT * FROM donate_cases ORDER BY created_at DESC');
        $out = [];
        while ($r = $res->fetch_assoc()) $out[] = $r;
        jsonOut(['cases' => $out]);
    }

    if ($action === 'change_token') {
        jsonOut(['token' => getChangeToken()]);
    }

    if ($action === 'storage_files') {
        requireAdminAccess();
        jsonOut(['storage' => storageBuildData($db)]);
    }

    // Database viewer (admin only)
    if ($action === 'db_table') {
        requireAdminAccess();
        $allowed = ['products', 'orders', 'customers', 'donate_cases', 'delivery_bookings'];
        $table   = $db->real_escape_string(input('table'));
        if (!in_array($table, $allowed, true)) jsonOut(['error' => 'Not allowed'], 400);
        $res  = $db->query("SELECT * FROM `$table` ORDER BY 1 DESC LIMIT 200");
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        jsonOut(['rows' => $rows]);
    }

    if ($action === 'settings') {
        $s = getSettings($db);
        jsonOut([
            'codShippingFee'      => (int)$s['cod_shipping_fee'],
            'codPartialThreshold' => (int)$s['cod_partial_threshold'],
            'codPartialAmount'    => (int)$s['cod_partial_amount'],
            'codTaxRate'          => (float)$s['cod_tax_rate'],
        ]);
    }

    jsonOut(['error' => 'Unknown action'], 400);
}

// POST
if ($method === 'POST') {
    $data = jsonInput();
    $type = $data['type'] ?? $_POST['type'] ?? '';

    $adminOnlyPostTypes = [
        'add_product',
        'delete_product',
        'toggle_featured',
        'set_category_cover',
        'set_order_status',
        'rebook_delivery',
        'add_case',
        'edit_case',
        'delete_case',
        'db_update',
        'db_delete_row',
        'delete_storage_file',
        'save_settings',
    ];
    if (in_array($type, $adminOnlyPostTypes, true)) {
        requireAdminAccess($data);
    }

    // Upload image (product photos + payment proofs)
    if ($type === 'upload_image') {
        if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file     = $_FILES['image'];
            $origName = $file['name'] ?: 'image.jpg';
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                jsonOut(['error' => 'Invalid file type'], 400);
            }

            if ($file['size'] > 50 * 1024 * 1024) {
                jsonOut(['error' => 'File too large (max 50MB).'], 400);
            }

            $safe    = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($origName, PATHINFO_FILENAME)) ?: 'img';
            $newName = $safe . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
            $dest    = UPLOAD_DIR . $newName;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                jsonOut(['ok' => true, 'url' => '/uploads/' . $newName]);
            }
            jsonOut(['error' => 'Could not save file'], 500);
        }

        if (!empty($data['imageDataUrl'])) {
            $url = saveBase64Image($data['imageDataUrl'], $data['fileName'] ?? '');
            if ($url) jsonOut(['ok' => true, 'url' => $url]);
        }

        jsonOut(['error' => 'No image data received'], 400);
    }

    // Upload product video
    if ($type === 'upload_video') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
            if (!empty($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
                $file     = $_FILES['video'];
                $origName = $file['name'] ?: 'video.mp4';
                $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, ['mp4', 'webm', 'ogg', 'mov'], true)) {
                    jsonOut(['error' => 'Invalid video type'], 400);
                }
                if ($file['size'] > 500 * 1024 * 1024) {
                    jsonOut(['error' => 'Video too large (max 500MB).'], 400);
                }
                $safe    = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($origName, PATHINFO_FILENAME)) ?: 'video';
                $newName = $safe . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
                $dest    = UPLOAD_DIR . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    jsonOut(['ok' => true, 'url' => '/uploads/' . $newName, 'compressed' => true, 'targetMb' => 0]);
                }
            jsonOut(['error' => 'Could not save file'], 500);
        }
        jsonOut(['error' => 'No video data received'], 400);
    }

    // Add / update product
    if ($type === 'add_product') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);

        $p = $data['product'] ?? $data;
        if (!is_array($p)) $p = [];
        if (empty($p['name']) || empty($p['price'])) jsonOut(['error' => 'Name and price required'], 400);

        $id      = $db->real_escape_string($p['productId'] ?? $p['id'] ?? ('PRD-' . time()));
        $name    = $db->real_escape_string($p['name']);
        $gtRaw   = normalizeCategoryValue($p['garmentType'] ?? 'accessories');
        $catRaw  = normalizeCategoryValue($p['category'] ?? $gtRaw, $gtRaw);
        $gt      = $db->real_escape_string($gtRaw);
        $cat     = $db->real_escape_string($catRaw);
        $tags    = $db->real_escape_string(json_encode($p['tags'] ?? ['new']));
        $cond    = $db->real_escape_string(normalizeCondition($p['cond'] ?? '10/10'));
        $defects = $db->real_escape_string(normalizeDefects($p['defects'] ?? ''));
        $desc    = $db->real_escape_string(normalizeDescription($p['description'] ?? ''));
        $videoEnabled = in_array($gtRaw, ['tech', 'misc'], true);
        $videoUrls = $videoEnabled ? normalizeProductVideoUrls($p) : [];
        $videoJson = json_encode($videoUrls, JSON_UNESCAPED_SLASHES);
        if ($videoJson === false) $videoJson = '[]';
        $videos    = $db->real_escape_string($videoJson);
        $video     = $db->real_escape_string($videoUrls[0] ?? trim((string)($p['videoUrl'] ?? $p['video_url'] ?? '')));
        $price   = (int)$p['price'];
        $meas    = $db->real_escape_string($p['measurements'] ?? $p['meta'] ?? '');
        $imageUrls = normalizeProductImageUrls($p);
        $imgsJson  = json_encode($imageUrls, JSON_UNESCAPED_SLASHES);
        if ($imgsJson === false) $imgsJson = '[]';
        $imgs      = $db->real_escape_string($imgsJson);
        $primaryImg = $db->real_escape_string($imageUrls[0] ?? '');

        $hasDefects = hasProductDefectsColumn($db);
        $hasDesc = hasProductDescriptionColumn($db);
        $hasVideo = hasProductVideoColumn($db);
        $hasVideoUrls = hasProductVideoUrlsColumn($db);

        $insertCols = ['id','name','garment_type','category','tags','cond','price','measurements','image_urls','status'];
        $insertVals = ["'$id'","'$name'","'$gt'","'$cat'","'$tags'","'$cond'","$price","'$meas'","'$imgs'","'available'"];
        $updates = ["name='$name'","garment_type='$gt'","category='$cat'","tags='$tags'","cond='$cond'","price=$price","measurements='$meas'","image_urls='$imgs'"];

        if ($hasDefects) {
            $insertCols[] = 'defects';
            $insertVals[] = "'$defects'";
            $updates[] = "defects='$defects'";
        }
        if ($hasDesc) {
            $insertCols[] = 'description';
            $insertVals[] = "'$desc'";
            $updates[] = "description='$desc'";
        }
        if ($hasVideo) {
            $insertCols[] = 'video_url';
            $insertVals[] = "'$video'";
            $updates[] = "video_url='$video'";
        }
        if ($hasVideoUrls) {
            $insertCols[] = 'video_urls';
            $insertVals[] = "'$videos'";
            $updates[] = "video_urls='$videos'";
        }

        $ok = $db->query("INSERT INTO products (" . implode(',', $insertCols) . ")
            VALUES (" . implode(',', $insertVals) . ")
            ON DUPLICATE KEY UPDATE " . implode(',', $updates));

        if (!$ok) jsonOut(['error' => 'Could not save product'], 500);
        if (tableHasColumn($db, 'products', 'img')) {
            @ $db->query("UPDATE products SET img='$primaryImg' WHERE id='$id'");
        }
        if (tableHasColumn($db, 'products', 'image_url')) {
            @ $db->query("UPDATE products SET image_url='$primaryImg' WHERE id='$id'");
        }
        bumpChangeToken();
        jsonOut(['ok' => true]);
    }

    if ($type === 'apply_sale') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $percent = (int)($data['percent'] ?? 0);
        $productIds = $data['productIds'] ?? [];
        if (!is_array($productIds)) $productIds = [];
        $productIds = array_values(array_filter(array_map('strval', $productIds), function($v) { return trim($v) !== ''; }));
        if ($percent < 1 || $percent > 90) jsonOut(['error' => 'Sale percent must be between 1 and 90.'], 400);
        if (!count($productIds)) jsonOut(['error' => 'No products selected.'], 400);

        $updated = 0;
        foreach ($productIds as $pidRaw) {
            $pid = $db->real_escape_string($pidRaw);
            $res = $db->query("SELECT id, price, base_price FROM products WHERE id='$pid' LIMIT 1");
            if (!$res) continue;
            $row = $res->fetch_assoc();
            if (!$row) continue;
            $price = (int)($row['price'] ?? 0);
            $base = (int)($row['base_price'] ?? 0);
            if ($base <= 0) $base = max(0, $price);
            $newPrice = (int)round($base * (100 - $percent) / 100);
            if ($newPrice < 0) $newPrice = 0;
            $db->query("UPDATE products SET base_price=$base, sale_percent=$percent, sale_active=1, price=$newPrice WHERE id='$pid'");
            if ($db->affected_rows >= 0) $updated++;
        }
        bumpChangeToken();
        jsonOut(['ok' => true, 'updated' => $updated]);
    }

    // Delete product
    if ($type === 'delete_product') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $pid = $db->real_escape_string($data['productId'] ?? '');
        if (!$pid) jsonOut(['error' => 'Missing id'], 400);

        $res = $db->query("SELECT image_urls, video_urls, video_url FROM products WHERE id='$pid'");
        $row = $res ? $res->fetch_assoc() : null;
        if ($row) {
            foreach (json_decode($row['image_urls'], true) ?: [] as $url) {
                if (strpos($url, '/uploads/') === 0) {
                    $f = __DIR__ . $url;
                    if (file_exists($f)) @unlink($f);
                }
            }
            $videoList = json_decode((string)($row['video_urls'] ?? ''), true);
            if (!is_array($videoList)) $videoList = [];
            $legacyVideo = trim((string)($row['video_url'] ?? ''));
            if ($legacyVideo !== '' && !in_array($legacyVideo, $videoList, true)) $videoList[] = $legacyVideo;
            foreach ($videoList as $url) {
                if (strpos((string)$url, '/uploads/') === 0) {
                    $f = __DIR__ . $url;
                    if (file_exists($f)) @unlink($f);
                }
            }
        }

        $db->query("DELETE FROM products WHERE id='$pid'");
        $db->query("DELETE FROM category_covers WHERE product_id='$pid'");
        bumpChangeToken();
        jsonOut(['ok' => true]);
    }

    // Toggle featured
    if ($type === 'toggle_featured') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $pid = $db->real_escape_string($data['productId'] ?? '');
        if (!$pid) jsonOut(['error' => 'Missing id'], 400);
        $db->query("UPDATE products SET is_featured = 1 - is_featured WHERE id='$pid'");
        $r2 = $db->query("SELECT is_featured FROM products WHERE id='$pid'")->fetch_assoc();
        bumpChangeToken();
        jsonOut(['ok' => true, 'is_featured' => (int)($r2['is_featured'] ?? 0)]);
    }

    // Set / clear category cover product
    if ($type === 'set_category_cover') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $category = normalizeCategoryValue($data['category'] ?? '');
        if ($category === '') jsonOut(['error' => 'Missing category'], 400);

        $productId = trim((string)($data['productId'] ?? ''));
        if ($productId === '') {
            $catSafe = $db->real_escape_string($category);
            $db->query("DELETE FROM category_covers WHERE category_key='$catSafe'");
            bumpChangeToken();
            jsonOut(['ok' => true]);
        }

        $pidSafe = $db->real_escape_string($productId);
        $check = $db->query("SELECT id FROM products WHERE id='$pidSafe' LIMIT 1");
        if (!$check || !$check->fetch_assoc()) jsonOut(['error' => 'Product not found'], 404);

        $catSafe = $db->real_escape_string($category);
        $db->query("REPLACE INTO category_covers (category_key, product_id) VALUES ('$catSafe', '$pidSafe')");
        bumpChangeToken();
        jsonOut(['ok' => true]);
    }

    // Create payment reservation (3-minute lock, no order row yet)
    if ($type === 'create_payment_reservation') {
        $o = $data['order'] ?? [];
        $productIds = normalizeProductIdList($o['productIds'] ?? []);
        if (!$productIds) jsonOut(['error' => 'No products in order.'], 400);

        $paymentMode = strtolower(trim((string)($o['paymentMode'] ?? 'prepaid')));
        if ($paymentMode === '') $paymentMode = 'prepaid';

        $payloadJson = json_encode($o, JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) jsonOut(['error' => 'Could not prepare payment session.'], 500);
        $payloadSafe = $db->real_escape_string($payloadJson);
        $productIdsJson = json_encode($productIds, JSON_UNESCAPED_UNICODE);
        if ($productIdsJson === false) $productIdsJson = '[]';
        $productIdsSafe = $db->real_escape_string($productIdsJson);

        $inIds = productIdsToInClause($db, $productIds);
        if ($inIds === '') jsonOut(['error' => 'No products in order.'], 400);

        $expiresAt = date('Y-m-d H:i:s', time() + paymentReservationSeconds());
        $expiresSafe = $db->real_escape_string($expiresAt);

        $db->begin_transaction();
        try {
            $products = [];
            $res = $db->query("SELECT id,name,status,category,garment_type FROM products WHERE id IN ($inIds) FOR UPDATE");
            while ($res && ($r = $res->fetch_assoc())) {
                $products[$r['id']] = $r;
            }

            $missing = [];
            $unavailable = [];
            foreach ($productIds as $pid) {
                if (!isset($products[$pid])) {
                    $missing[] = $pid;
                    continue;
                }
                $status = strtolower((string)($products[$pid]['status'] ?? ''));
                if ($status !== 'available') {
                    $unavailable[] = [
                        'id'     => $pid,
                        'name'   => $products[$pid]['name'] ?? '',
                        'status' => $products[$pid]['status'] ?? 'unavailable',
                    ];
                }
            }

            if ($missing || $unavailable) {
                $db->rollback();
                jsonOut([
                    'error'       => 'One or more items are no longer available.',
                    'unavailable' => $unavailable,
                    'missing'     => $missing,
                    'code'        => 'PRODUCT_UNAVAILABLE',
                ], 409);
            }

            $hasTechItem = false;
            foreach ($productIds as $pid) {
                $pRow = $products[$pid] ?? null;
                if (!$pRow) continue;
                $cat = normalizeCategoryValue((string)($pRow['garment_type'] ?? ($pRow['category'] ?? '')));
                if ($cat === 'tech') { $hasTechItem = true; break; }
            }
            if ($hasTechItem && ($paymentMode === 'cod' || $paymentMode === 'cod_deposit')) {
                $db->rollback();
                jsonOut([
                    'error' => 'Tech items are online payment only.',
                    'code'  => 'TECH_PREPAID_ONLY'
                ], 409);
            }

            $db->query("UPDATE products SET status='confirmation_pending' WHERE id IN ($inIds) AND status='available'");
            if ((int)$db->affected_rows !== count($productIds)) {
                $db->rollback();
                jsonOut([
                    'error' => 'Some items were just taken by another customer.',
                    'code'  => 'PRODUCT_RACE_LOST',
                ], 409);
            }

            $reservationId = '';
            $reservationToken = '';
            $inserted = false;
            for ($i = 0; $i < 5; $i++) {
                $ridCandidate = 'pay-' . time() . '-' . bin2hex(random_bytes(4));
                $tokenCandidate = bin2hex(random_bytes(16));
                $ridSafe = $db->real_escape_string($ridCandidate);
                $tokSafe = $db->real_escape_string($tokenCandidate);

                $ok = $db->query("INSERT INTO payment_reservations
                    (id,access_token,payment_mode,product_ids,payload,status,expires_at)
                    VALUES
                    ('$ridSafe','$tokSafe','" . $db->real_escape_string($paymentMode) . "','$productIdsSafe','$payloadSafe','active','$expiresSafe')");

                if ($ok) {
                    $reservationId = $ridCandidate;
                    $reservationToken = $tokenCandidate;
                    $inserted = true;
                    break;
                }
                if ((int)$db->errno !== 1062) {
                    throw new Exception('Reservation insert failed: ' . $db->error);
                }
            }

            if (!$inserted) {
                throw new Exception('Could not generate reservation ID.');
            }

            $db->commit();
            bumpChangeToken();
            jsonOut([
                'ok'               => true,
                'reservationId'    => $reservationId,
                'reservationToken' => $reservationToken,
                'expiresAt'        => $expiresAt,
                'reservationSecs'  => paymentReservationSeconds(),
            ]);
        } catch (Throwable $e) {
            $db->rollback();
            jsonOut(['error' => 'Could not start payment session. Please try again.'], 500);
        }
    }

    // Create order
    if ($type === 'order') {
        jsonOut([
            'error' => 'Direct order creation is disabled. Start a payment session first.',
            'code'  => 'PAYMENT_RESERVATION_REQUIRED',
        ], 409);
    }

    // Invoice audit (admin): compare invoice PDF with delivered orders in range.
    if ($type === 'invoice_audit') {
        $fromDate = trim((string)($_POST['fromDate'] ?? $data['fromDate'] ?? ''));
        $toDate = trim((string)($_POST['toDate'] ?? $data['toDate'] ?? ''));
        if ($fromDate === '' || $toDate === '') jsonOut(['error' => 'fromDate and toDate are required.'], 400);

        $dbDelivered = invoiceAuditDbDeliveredMap($db, $fromDate, $toDate);
        if (empty($dbDelivered['ok'])) {
            jsonOut(['error' => (string)($dbDelivered['error'] ?? 'Could not load delivered orders.')], 400);
        }

        if (empty($_FILES['invoice_pdf']) || (int)($_FILES['invoice_pdf']['error'] ?? 1) !== UPLOAD_ERR_OK) {
            jsonOut(['error' => 'Invoice PDF upload failed.'], 400);
        }
        $file = $_FILES['invoice_pdf'];
        $name = strtolower((string)($file['name'] ?? ''));
        if (substr($name, -4) !== '.pdf') jsonOut(['error' => 'Only PDF invoice is supported.'], 400);
        $ext = invoiceAuditExtractPdfText((string)($file['tmp_name'] ?? ''));
        if (empty($ext['ok'])) {
            jsonOut(['error' => (string)($ext['warning'] ?? 'Could not parse invoice PDF.')], 400);
        }

        $parsed = invoiceAuditParsePdfData((string)($ext['text'] ?? ''));
        $invoiceParcels = array_map('invoiceAuditNormalizeParcelId', (array)($parsed['parcelIds'] ?? []));
        $invoiceParcels = array_values(array_unique(array_filter($invoiceParcels)));
        $invoiceSet = array_fill_keys($invoiceParcels, true);

        $dbRows = (array)($dbDelivered['rows'] ?? []);
        $dbByParcel = (array)($dbDelivered['byParcel'] ?? []);
        $dbParcels = array_values(array_keys($dbByParcel));
        $dbSet = array_fill_keys($dbParcels, true);

        $missingInInvoice = [];
        foreach ($dbParcels as $pid) {
            if (!isset($invoiceSet[$pid])) $missingInInvoice[] = $pid;
        }
        $extraInInvoice = [];
        foreach ($invoiceParcels as $pid) {
            if (!isset($dbSet[$pid])) $extraInInvoice[] = $pid;
        }

        $shippingByParcel = (array)($parsed['shippingByParcel'] ?? []);
        $shippingOverchargeFlags = [];
        foreach ($shippingByParcel as $pid => $invoiceShipping) {
            $parcelId = invoiceAuditNormalizeParcelId($pid);
            if ($parcelId === '' || !isset($dbByParcel[$parcelId])) continue;
            $row = $dbByParcel[$parcelId];
            $expected = (int)($row['expectedShipping'] ?? 0);
            $invoiceAmt = (int)$invoiceShipping;
            if ($invoiceAmt > $expected) {
                $shippingOverchargeFlags[] = [
                    'orderId' => (string)($row['orderId'] ?? ''),
                    'parcelId' => $parcelId,
                    'city' => (string)($row['city'] ?? ''),
                    'expected' => $expected,
                    'invoice' => $invoiceAmt,
                ];
            }
        }

        $warnings = [];
        if (empty($shippingByParcel)) {
            $warnings[] = 'Could not reliably parse per-parcel shipping amounts from PDF text.';
        }
        if ((int)($parsed['shippingTotal'] ?? 0) <= 0) {
            $warnings[] = 'Invoice shipping total was not detected from PDF summary/rows.';
        }
        if (($parsed['taxTotal'] ?? null) === null) {
            $warnings[] = 'Invoice tax total was not detected (if present, verify manually).';
        }
        if (($parsed['invoiceTotal'] ?? null) === null) {
            $warnings[] = 'Invoice grand/net total was not detected (if present, verify manually).';
        }

        $matchedCount = 0;
        foreach ($invoiceParcels as $pid) if (isset($dbSet[$pid])) $matchedCount++;

        jsonOut([
            'ok' => true,
            'summary' => [
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'dbDeliveredCount' => count($dbRows),
                'invoiceParcelCount' => count($invoiceParcels),
                'matchedCount' => $matchedCount,
                'expectedShippingTotal' => (int)($dbDelivered['expectedShippingTotal'] ?? 0),
                'invoiceShippingTotal' => (int)($parsed['shippingTotal'] ?? 0),
                'invoiceTaxTotal' => ($parsed['taxTotal'] ?? null),
                'invoiceGrandTotal' => ($parsed['invoiceTotal'] ?? null),
            ],
            'shippingOverchargeFlags' => $shippingOverchargeFlags,
            'missingInInvoice' => $missingInInvoice,
            'extraInInvoice' => $extraInInvoice,
            'warnings' => $warnings,
        ]);
    }

    // Set order status
    if ($type === 'set_order_status') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $oid    = $db->real_escape_string($data['orderId'] ?? '');
        $status = $db->real_escape_string($data['status'] ?? '');
        if (!$oid || !$status) jsonOut(['error' => 'Missing fields'], 400);

        $res   = $db->query("SELECT * FROM orders WHERE id='$oid'");
        $order = $res ? $res->fetch_assoc() : null;
        if (!$order) jsonOut(['error' => 'Order not found'], 404);

        $pids  = normalizeProductIdList(json_decode((string)($order['product_ids'] ?? ''), true) ?: []);
        $phone = $order['phone'] ?? '';
        $proofUrl = trim((string)($order['screenshot'] ?? ''));
        $delivery = null;
        $igDm = null;

        if ($status === 'Confirmed') {
            foreach ($pids as $pid) {
                $s = $db->real_escape_string($pid);
                $db->query("UPDATE products SET status='sold_out' WHERE id='$s' AND status <> 'sold_out'");
            }
            if ($proofUrl !== '') {
                deleteUploadedImageByUrl($proofUrl);
            }
        }

        if ($status === 'Delivered') {
            foreach ($pids as $pid) {
                $s    = $db->real_escape_string($pid);
                $res2 = $db->query("SELECT image_urls FROM products WHERE id='$s'");
                $pr   = $res2 ? $res2->fetch_assoc() : null;
                if ($pr) {
                    foreach (json_decode($pr['image_urls'], true) ?: [] as $url) {
                        if (strpos($url, '/uploads/') === 0) {
                            $f = __DIR__ . $url;
                            if (file_exists($f)) @unlink($f);
                        }
                    }
                }
                $db->query("DELETE FROM products WHERE id='$s'");
            }

            if ($phone) {
                $cphone2  = $db->real_escape_string($order['phone2'] ?? '');
                $ccity    = $db->real_escape_string($order['city'] ?? '');
                $caddress = $db->real_escape_string($order['address'] ?? '');
                $cname    = $db->real_escape_string($order['name'] ?? '');
                $cemail   = $db->real_escape_string($order['email'] ?? '');
                $db->query("INSERT INTO customers (phone,phone2,name,email,city,address,order_count,latest_order_status)
                    VALUES ('$phone','$cphone2','$cname','$cemail','$ccity','$caddress',1,'delivered')
                    ON DUPLICATE KEY UPDATE
                    phone2 = IF('$cphone2' != '', '$cphone2', phone2),
                    name = IF('$cname' != '', '$cname', name),
                    email = IF('$cemail' != '', '$cemail', email),
                    city = IF('$ccity' != '', '$ccity', city),
                    address = IF('$caddress' != '', '$caddress', address),
                    order_count = order_count + 1,
                    latest_order_status = 'delivered'");
            }
        }

        if ($status === 'Cancelled' || $status === 'Returned') {
            foreach ($pids as $pid) {
                $s = $db->real_escape_string($pid);
                // Hard lock sold_out: never allow transitions away from sold_out.
                $db->query("UPDATE products SET status='available' WHERE id='$s' AND status <> 'sold_out'");
            }
            if ($status === 'Returned' && $phone) {
                $db->query("UPDATE customers SET cod_blocked=1, latest_order_status='returned' WHERE phone='$phone'");
            }
        }

        if ($status === 'Confirmed') {
            $db->query("UPDATE orders SET status='$status', screenshot='' WHERE id='$oid'");
        } else {
            $db->query("UPDATE orders SET status='$status' WHERE id='$oid'");
        }
        if ($phone && $status !== 'Delivered') {
            $db->query("UPDATE customers SET latest_order_status='" . strtolower($status) . "' WHERE phone='$phone'");
        }

        if ($status === 'Confirmed') {
            $delivery = createDeliveryBookingForOrder($db, $order);
            $igDm = sendInstagramConfirmationDm($order);
        }

        bumpChangeToken();
        $resp = ['ok' => true];
        if ($delivery !== null) $resp['delivery'] = $delivery;
        if ($igDm !== null) $resp['instagramDm'] = $igDm;
        jsonOut($resp);
    }

    // Manually re-send a confirmed order to delivery portal.
    if ($type === 'rebook_delivery') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $oid = $db->real_escape_string($data['orderId'] ?? '');
        if (!$oid) jsonOut(['error' => 'Missing order id'], 400);

        $res = $db->query("SELECT * FROM orders WHERE id='$oid' LIMIT 1");
        $order = $res ? $res->fetch_assoc() : null;
        if (!$order) jsonOut(['error' => 'Order not found'], 404);

        $sl = strtolower(trim((string)($order['status'] ?? '')));
        if ($sl !== 'confirmed') {
            jsonOut(['ok' => false, 'error' => 'Only confirmed orders can be rebooked to delivery portal.'], 409);
        }

        $delivery = createDeliveryBookingForOrder($db, $order, ['force' => true]);
        bumpChangeToken();
        jsonOut([
            'ok' => (bool)($delivery['ok'] ?? false),
            'delivery' => $delivery,
            'error' => (string)($delivery['message'] ?? ''),
        ]);
    }

    // Sync tracking details in batch (for trusted worker/extension).
    if ($type === 'sync_tracking_batch') {
        $expectedToken = trim((string)TRACKING_SYNC_TOKEN);
        if ($expectedToken === '') {
            jsonOut(['ok' => false, 'error' => 'Tracking sync is disabled on server.'], 503);
        }

        $providedToken = trim((string)($data['token'] ?? ''));
        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            jsonOut(['ok' => false, 'error' => 'Invalid sync token.'], 403);
        }

        $records = $data['records'] ?? [];
        if (!is_array($records)) jsonOut(['ok' => false, 'error' => 'Invalid records payload.'], 400);

        $source = trim((string)($data['source'] ?? 'edge_extension'));
        if ($source === '') $source = 'edge_extension';

        $processed = 0;
        $matched = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($records as $rec) {
            if (!is_array($rec)) {
                $skipped++;
                continue;
            }
            $processed++;

            $orderId = preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string)($rec['orderId'] ?? '')));
            if ($orderId === '') {
                $skipped++;
                continue;
            }

            $statusText = trackingTextNormalize((string)($rec['statusText'] ?? ''));
            $syncedOrderStatus = normalizeSyncedOrderStatus((string)($rec['portalStatus'] ?? ''), $statusText);
            $trackingNumber = normalizeTrackingNumber((string)($rec['trackingNumber'] ?? ''));
            if ($trackingNumber === '' && $statusText !== '') {
                $trackingNumber = normalizeTrackingNumber($statusText);
            }
            if ($trackingNumber === '' && $syncedOrderStatus === '') {
                $skipped++;
                continue;
            }

            $oidSafe = $db->real_escape_string($orderId);
            $res = $db->query("SELECT * FROM orders WHERE id='$oidSafe' LIMIT 1");
            $row = $res ? $res->fetch_assoc() : null;
            if (!$row) continue;
            $matched++;

            $sourceSafe = $db->real_escape_string($source);
            $statusCombined = trim($statusText);
            if ($statusCombined === '') $statusCombined = 'Synced via ' . $source;
            $statusCombined = $db->real_escape_string($statusCombined);

            $currentTracking = normalizeTrackingNumber((string)($row['tracking_number'] ?? ''));
            $currentStatusText = trackingTextNormalize((string)($row['tracking_status_text'] ?? ''));
            $currentOrderStatus = trim((string)($row['status'] ?? ''));
            $currentOrderStatusLower = strtolower($currentOrderStatus);

            $didUpdateRow = false;

            if ($trackingNumber !== '' && !($currentTracking === $trackingNumber && $currentStatusText === trackingTextNormalize($statusText))) {
                $carrierMeta = trackingCarrierMeta($trackingNumber);
                $carrierType = trim((string)($carrierMeta['type'] ?? 'other'));
                if ($carrierType === '') $carrierType = 'other';

                $tnSafe = $db->real_escape_string($trackingNumber);
                $carrierSafe = $db->real_escape_string($carrierType);
                $ok = $db->query("UPDATE orders
                    SET tracking_number='$tnSafe',
                        tracking_carrier='$carrierSafe',
                        tracking_status_text='$statusCombined',
                        tracking_updated_at=NOW(),
                        notes=CASE
                            WHEN notes IS NULL OR notes = '' THEN CONCAT('Tracking synced via ', '$sourceSafe')
                            ELSE notes
                        END
                    WHERE id='$oidSafe'");
                if ($ok) $didUpdateRow = true;
            }

            if ($syncedOrderStatus !== '') {
                $syncLower = strtolower($syncedOrderStatus);
                $canMoveStatus = !in_array($currentOrderStatusLower, ['cancelled', 'returned'], true);
                if ($canMoveStatus && $syncLower !== $currentOrderStatusLower) {
                    $syncSafe = $db->real_escape_string($syncedOrderStatus);
                    $okStatus = $db->query("UPDATE orders SET status='$syncSafe' WHERE id='$oidSafe'");
                    if ($okStatus) {
                        $didUpdateRow = true;
                        if ($syncLower === 'delivered') {
                            $pids = normalizeProductIdList(json_decode((string)($row['product_ids'] ?? ''), true) ?: []);
                            foreach ($pids as $pid) {
                                $s = $db->real_escape_string($pid);
                                $res2 = $db->query("SELECT image_urls FROM products WHERE id='$s'");
                                $pr = $res2 ? $res2->fetch_assoc() : null;
                                if ($pr) {
                                    foreach (json_decode((string)($pr['image_urls'] ?? ''), true) ?: [] as $url) {
                                        if (strpos((string)$url, '/uploads/') === 0) {
                                            $f = __DIR__ . $url;
                                            if (file_exists($f)) @unlink($f);
                                        }
                                    }
                                }
                                $db->query("DELETE FROM products WHERE id='$s'");
                            }

                            $phoneRaw = (string)($row['phone'] ?? '');
                            $phone = $db->real_escape_string($phoneRaw);
                            if ($phone !== '') {
                                $cphone2  = $db->real_escape_string((string)($row['phone2'] ?? ''));
                                $ccity    = $db->real_escape_string((string)($row['city'] ?? ''));
                                $caddress = $db->real_escape_string((string)($row['address'] ?? ''));
                                $cname    = $db->real_escape_string((string)($row['name'] ?? ''));
                                $cemail   = $db->real_escape_string((string)($row['email'] ?? ''));
                                $db->query("INSERT INTO customers (phone,phone2,name,email,city,address,order_count,latest_order_status)
                                    VALUES ('$phone','$cphone2','$cname','$cemail','$ccity','$caddress',1,'delivered')
                                    ON DUPLICATE KEY UPDATE
                                    phone2 = IF('$cphone2' != '', '$cphone2', phone2),
                                    name = IF('$cname' != '', '$cname', name),
                                    email = IF('$cemail' != '', '$cemail', email),
                                    city = IF('$ccity' != '', '$ccity', city),
                                    address = IF('$caddress' != '', '$caddress', address),
                                    order_count = order_count + 1,
                                    latest_order_status = 'delivered'");
                            }

                            $proofUrl = trim((string)($row['screenshot'] ?? ''));
                            if ($proofUrl !== '') {
                                deleteUploadedImageByUrl($proofUrl);
                                $db->query("UPDATE orders SET screenshot='' WHERE id='$oidSafe'");
                            }
                        } else if ($syncLower === 'confirmed') {
                            $phone = $db->real_escape_string((string)($row['phone'] ?? ''));
                            if ($phone !== '') {
                                $db->query("UPDATE customers SET latest_order_status='confirmed' WHERE phone='$phone'");
                            }
                        }
                    }
                }
            }

            if ($didUpdateRow) $updated++;
        }

        if ($updated > 0) bumpChangeToken();
        jsonOut([
            'ok' => true,
            'processed' => $processed,
            'matched' => $matched,
            'updated' => $updated,
            'skipped' => $skipped
        ]);
    }

    // Payment proof
    if ($type === 'payment_proof') {
        $screenshot = $db->real_escape_string($data['screenshot'] ?? '');
        if (!$screenshot) jsonOut(['error' => 'Missing screenshot'], 400);

        $reservationId = trim((string)($data['reservationId'] ?? ''));
        $reservationToken = trim((string)($data['reservationToken'] ?? ''));
        if ($reservationId !== '') {
            if ($reservationToken === '') jsonOut(['error' => 'Missing reservation token'], 400);
            $ridSafe = $db->real_escape_string($reservationId);

            $db->begin_transaction();
            try {
                $rRes = $db->query("SELECT id,access_token,status,payment_mode,product_ids,payload,order_id,expires_at
                    FROM payment_reservations WHERE id='$ridSafe' FOR UPDATE");
                $reservation = $rRes ? $rRes->fetch_assoc() : null;
                if (!$reservation) {
                    $db->rollback();
                    jsonOut(['error' => 'Payment session not found.'], 404);
                }

                if (!hash_equals((string)($reservation['access_token'] ?? ''), $reservationToken)) {
                    $db->rollback();
                    jsonOut(['error' => 'Invalid payment session token.'], 403);
                }

                $rStatus = strtolower((string)($reservation['status'] ?? ''));
                if ($rStatus === 'converted') {
                    $existingOrderId = trim((string)($reservation['order_id'] ?? ''));
                    if ($existingOrderId !== '') {
                        $oidSafe = $db->real_escape_string($existingOrderId);
                        $db->query("UPDATE orders SET screenshot='$screenshot' WHERE id='$oidSafe'");
                        $db->query("UPDATE payment_reservations SET screenshot='$screenshot' WHERE id='$ridSafe'");
                    }
                    $db->commit();
                    bumpChangeToken();
                    jsonOut(['ok' => true, 'orderId' => $existingOrderId]);
                }

                if ($rStatus !== 'active') {
                    $db->rollback();
                    jsonOut(['error' => 'This payment session is no longer active.'], 409);
                }

                $expiresTs = strtotime((string)($reservation['expires_at'] ?? '')) ?: 0;
                if ($expiresTs > 0 && $expiresTs <= time()) {
                    $expiredPids = normalizeProductIdList(json_decode((string)($reservation['product_ids'] ?? ''), true) ?: []);
                    releaseReservedProducts($db, $expiredPids);
                    $db->query("UPDATE payment_reservations SET status='expired' WHERE id='$ridSafe'");
                    $db->commit();
                    bumpChangeToken();
                    jsonOut(['error' => 'This payment session expired. Please checkout again.'], 409);
                }

                $orderPayload = json_decode((string)($reservation['payload'] ?? ''), true);
                if (!is_array($orderPayload)) $orderPayload = [];

                $pids = normalizeProductIdList($orderPayload['productIds'] ?? []);
                if (!$pids) {
                    $pids = normalizeProductIdList(json_decode((string)($reservation['product_ids'] ?? ''), true) ?: []);
                }
                if (!$pids) {
                    $db->rollback();
                    jsonOut(['error' => 'No products found for this payment session.'], 409);
                }
                $orderPayload['productIds'] = $pids;
                if (!array_key_exists('paymentMode', $orderPayload) || trim((string)$orderPayload['paymentMode']) === '') {
                    $orderPayload['paymentMode'] = (string)($reservation['payment_mode'] ?? 'prepaid');
                }

                $inIds = productIdsToInClause($db, $pids);
                $products = [];
                $res = $db->query("SELECT id,name,status FROM products WHERE id IN ($inIds) FOR UPDATE");
                while ($res && ($row = $res->fetch_assoc())) {
                    $products[$row['id']] = $row;
                }

                $missing = [];
                $unavailable = [];
                foreach ($pids as $pid) {
                    if (!isset($products[$pid])) {
                        $missing[] = $pid;
                        continue;
                    }
                    $pStatus = strtolower((string)($products[$pid]['status'] ?? ''));
                    if ($pStatus !== 'confirmation_pending') {
                        $unavailable[] = [
                            'id'     => $pid,
                            'name'   => $products[$pid]['name'] ?? '',
                            'status' => $products[$pid]['status'] ?? 'unavailable',
                        ];
                    }
                }

                if ($missing || $unavailable) {
                    $db->rollback();
                    jsonOut([
                        'error'       => 'One or more items are no longer available.',
                        'unavailable' => $unavailable,
                        'missing'     => $missing,
                        'code'        => 'PRODUCT_UNAVAILABLE',
                    ], 409);
                }

                $providedId = trim((string)($orderPayload['id'] ?? ''));
                $providedId = preg_replace('/[^a-zA-Z0-9_-]/', '', $providedId);
                $fallbackId = 'ord-' . time() . '-' . bin2hex(random_bytes(4));
                $idsToTry = array_values(array_unique(array_filter([$providedId, $fallbackId])));
                if (!$idsToTry) $idsToTry = [$fallbackId];

                $oidFinal = insertOrderRecord($db, $idsToTry, $orderPayload, $pids, 'pending_payment');
                $oidSafe = $db->real_escape_string($oidFinal);
                $db->query("UPDATE orders SET screenshot='$screenshot', status='pending_payment' WHERE id='$oidSafe'");
                $db->query("UPDATE payment_reservations
                    SET status='converted', order_id='$oidSafe', screenshot='$screenshot', converted_at=NOW()
                    WHERE id='$ridSafe'");

                $db->commit();
                bumpChangeToken();
                jsonOut(['ok' => true, 'orderId' => $oidFinal]);
            } catch (Throwable $e) {
                $db->rollback();
                jsonOut(['error' => 'Could not save payment proof. Please try again.'], 500);
            }
        }

        $oid = $db->real_escape_string($data['orderId'] ?? '');
        if (!$oid) jsonOut(['error' => 'Missing id'], 400);

        $db->begin_transaction();
        try {
            $pRes   = $db->query("SELECT status, product_ids FROM orders WHERE id='$oid' FOR UPDATE");
            $pOrder = $pRes ? $pRes->fetch_assoc() : null;
            if (!$pOrder) {
                $db->rollback();
                jsonOut(['error' => 'Order not found'], 404);
            }

            $curr = strtolower((string)($pOrder['status'] ?? ''));
            if (in_array($curr, ['delivered', 'cancelled', 'returned', 'confirmed'], true)) {
                $db->rollback();
                jsonOut(['error' => 'This order can no longer receive payment proof.'], 409);
            }

            if ($curr === 'pending_payment') {
                $db->query("UPDATE orders SET screenshot='$screenshot' WHERE id='$oid'");
                $db->commit();
                bumpChangeToken();
                jsonOut(['ok' => true, 'orderId' => $oid]);
            }

            if ($curr !== 'awaiting_payment_proof') {
                $db->rollback();
                jsonOut(['error' => 'This order is not awaiting payment proof.'], 409);
            }

            $pids = normalizeProductIdList(json_decode((string)($pOrder['product_ids'] ?? ''), true) ?: []);
            if (!$pids) {
                $db->query("UPDATE orders SET screenshot='$screenshot', status='pending_payment' WHERE id='$oid'");
                $db->commit();
                bumpChangeToken();
                jsonOut(['ok' => true, 'orderId' => $oid]);
            }

            $escapedIds = array_map(function($pid) use ($db) {
                return $db->real_escape_string($pid);
            }, $pids);
            $inIds = "'" . implode("','", $escapedIds) . "'";

            $products = [];
            $res = $db->query("SELECT id,name,status FROM products WHERE id IN ($inIds) FOR UPDATE");
            while ($res && ($r = $res->fetch_assoc())) {
                $products[$r['id']] = $r;
            }

            $missing = [];
            $unavailable = [];
            foreach ($pids as $pid) {
                if (!isset($products[$pid])) {
                    $missing[] = $pid;
                    continue;
                }
                $status = strtolower((string)($products[$pid]['status'] ?? ''));
                if ($status !== 'available') {
                    $unavailable[] = [
                        'id'     => $pid,
                        'name'   => $products[$pid]['name'] ?? '',
                        'status' => $products[$pid]['status'] ?? 'unavailable',
                    ];
                }
            }

            if ($missing || $unavailable) {
                $db->rollback();
                jsonOut([
                    'error'       => 'One or more items are no longer available.',
                    'unavailable' => $unavailable,
                    'missing'     => $missing,
                    'code'        => 'PRODUCT_UNAVAILABLE',
                ], 409);
            }

            $db->query("UPDATE products SET status='confirmation_pending' WHERE id IN ($inIds) AND status='available'");
            if ((int)$db->affected_rows !== count($pids)) {
                $db->rollback();
                jsonOut([
                    'error' => 'Some items were just taken by another customer.',
                    'code'  => 'PRODUCT_RACE_LOST',
                ], 409);
            }

            $db->query("UPDATE orders SET screenshot='$screenshot', status='pending_payment' WHERE id='$oid'");
            $db->commit();
            bumpChangeToken();
            jsonOut(['ok' => true, 'orderId' => $oid]);
        } catch (Throwable $e) {
            $db->rollback();
            jsonOut(['error' => 'Could not save payment proof. Please try again.'], 500);
        }
    }

    // Donate cases
    if ($type === 'add_case') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $c = $data['case'] ?? [];
        if (empty($c['title'])) jsonOut(['error' => 'Title required'], 400);
        $id    = $db->real_escape_string('CASE-' . time() . '-' . bin2hex(random_bytes(3)));
        $title = $db->real_escape_string($c['title'] ?? '');
        $desc  = $db->real_escape_string($c['description'] ?? '');
        $img   = $db->real_escape_string($c['image_url'] ?? '');
        $link  = $db->real_escape_string($c['link_url'] ?? '');
        $label = $db->real_escape_string($c['link_label'] ?? 'More Info');
        $ok = $db->query("INSERT INTO donate_cases (id,title,description,image_url,link_url,link_label)
            VALUES ('$id','$title','$desc','$img','$link','$label')");
        if (!$ok) jsonOut(['error' => 'Could not add case'], 500);
        bumpChangeToken();
        jsonOut(['ok' => true, 'id' => $id]);
    }

    if ($type === 'edit_case') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $caseId = $db->real_escape_string($data['caseId'] ?? '');
        $c = $data['case'] ?? [];
        if (!$caseId) jsonOut(['error' => 'Missing id'], 400);
        if (empty($c['title'])) jsonOut(['error' => 'Title required'], 400);
        $title = $db->real_escape_string($c['title'] ?? '');
        $desc  = $db->real_escape_string($c['description'] ?? '');
        $img   = $db->real_escape_string($c['image_url'] ?? '');
        $link  = $db->real_escape_string($c['link_url'] ?? '');
        $label = $db->real_escape_string($c['link_label'] ?? 'More Info');
        $ok = $db->query("UPDATE donate_cases
            SET title='$title', description='$desc', image_url='$img', link_url='$link', link_label='$label'
            WHERE id='$caseId'");
        if (!$ok) jsonOut(['error' => 'Could not update case'], 500);
        bumpChangeToken();
        jsonOut(['ok' => true]);
    }

    if ($type === 'delete_case') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $id = $db->real_escape_string($data['caseId'] ?? '');
        if (!$id) jsonOut(['error' => 'Missing id'], 400);
        $db->query("DELETE FROM donate_cases WHERE id='$id'");
        bumpChangeToken();
        jsonOut(['ok' => true]);
    }

    if ($type === 'delete_storage_file') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $fileInput = $data['file'] ?? $data['path'] ?? '';
        $info = storagePathInfoFromInput($fileInput);
        if (empty($info['ok'])) {
            jsonOut(['error' => (string)($info['error'] ?? 'Invalid file path.')], 400);
        }

        $targetPath = (string)$info['path'];
        $targetUrl = (string)$info['url'];
        if (!is_file($targetPath)) {
            // Also clear stale references if the file is already gone.
            storageDetachUrlReferences($db, $targetUrl);
            bumpChangeToken();
            jsonOut(['ok' => true, 'storage' => storageBuildData($db)]);
        }

        storageDetachUrlReferences($db, $targetUrl);
        if (!@unlink($targetPath)) {
            jsonOut(['error' => 'Could not delete file.'], 500);
        }

        bumpChangeToken();
        jsonOut(['ok' => true, 'storage' => storageBuildData($db)]);
    }

    // Admin password check
    if ($type === 'check_admin_pw') {
        $pw = $data['password'] ?? '';
        jsonOut(['ok' => ($pw === ADMIN_PASSWORD)]);
    }

    // Database editor (admin only)
    if ($type === 'db_update') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $allowed = ['products', 'orders', 'customers', 'donate_cases', 'delivery_bookings'];
        $table   = $data['table'] ?? '';
        $pkCol   = $data['pkCol'] ?? '';
        $id      = $data['id'] ?? '';
        $col     = $data['col'] ?? '';
        $value   = $data['value'] ?? '';
        if (!in_array($table, $allowed, true) || !$pkCol || !$id || !$col) {
            jsonOut(['error' => 'Invalid params'], 400);
        }
        $tbl = $db->real_escape_string($table);
        $c   = $db->real_escape_string($col);
        $v   = $db->real_escape_string($value);
        $pk  = $db->real_escape_string($pkCol);
        $rid = $db->real_escape_string($id);

        if ($table === 'products' && strtolower((string)$col) === 'status') {
            $currRes = $db->query("SELECT status FROM products WHERE `$pk`='$rid' LIMIT 1");
            $currRow = $currRes ? $currRes->fetch_assoc() : null;
            $currStatus = strtolower(trim((string)($currRow['status'] ?? '')));
            $nextStatus = strtolower(trim((string)$value));
            if ($currStatus === 'sold_out' && $nextStatus !== 'sold_out') {
                jsonOut(['error' => 'sold_out is hard locked and cannot be reverted.'], 409);
            }
        }

        $db->query("UPDATE `$tbl` SET `$c`='$v' WHERE `$pk`='$rid'");
        bumpChangeToken();
        jsonOut(['ok' => true]);
    }

    if ($type === 'db_delete_row') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $allowed = ['products', 'orders', 'customers', 'donate_cases', 'delivery_bookings'];
        $table   = $data['table'] ?? '';
        $pkCol   = $data['pkCol'] ?? '';
        $id      = $data['id'] ?? '';
        if (!in_array($table, $allowed, true) || !$pkCol || !$id) {
            jsonOut(['error' => 'Invalid params'], 400);
        }
        $tbl = $db->real_escape_string($table);
        $pk  = $db->real_escape_string($pkCol);
        $rid = $db->real_escape_string($id);
        $db->query("DELETE FROM `$tbl` WHERE `$pk`='$rid'");
        bumpChangeToken();
        jsonOut(['ok' => true]);
    }

    if ($type === 'save_settings') {
        if (!isAdmin()) jsonOut(['error' => 'Forbidden'], 403);
        $allowed = ['cod_shipping_fee', 'cod_partial_threshold', 'cod_partial_amount', 'cod_tax_rate'];
        $saved = 0;
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) continue;
            $ks = $db->real_escape_string($key);
            $vs = $db->real_escape_string((string)$data[$key]);
            $db->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$ks','$vs')
                ON DUPLICATE KEY UPDATE setting_value='$vs'");
            $saved++;
        }
        bumpChangeToken();
        jsonOut(['ok' => true, 'saved' => $saved]);
    }

    jsonOut(['error' => 'Unknown type'], 400);
}
