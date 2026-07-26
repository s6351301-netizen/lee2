<?php
session_start();

// ==========================================
// 1. 資料庫連線設定
// ==========================================
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "lee";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "連線失敗: " . $conn->connect_error]));
}
$conn->set_charset("utf8mb4");

// 獲取目前要修改的族產 ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ==========================================
// 2. 處理 AJAX 請求 (API 路由)
// ==========================================

// 獲取所有"持有"土地資料
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'get_hold_lands') {
    header('Content-Type: application/json');
    $hold_lands = [];
    $res = $conn->query("SELECT id, city, district, section_code, section_name, land_number, area FROM ethnic_property WHERE status = '持有' ORDER BY district ASC, land_number ASC");
    while ($row = $res->fetch_assoc()) {
        $hold_lands[] = $row;
    }
    echo json_encode($hold_lands);
    exit;
}

// 供表單各欄位透過 AJAX 獲取不重複歷史紀錄
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'get_field_suggestions') {
    header('Content-Type: application/json');
    $field = $_GET['field'] ?? '';
    
    $allowed_fields = ['status', 'city', 'district', 'section_code', 'section_name', 'land_number', 'register_date', 'zoning', 'land_use', 'owner_type'];
    
    if (in_array($field, $allowed_fields)) {
        $suggestions = [];
        $sql = "SELECT DISTINCT `$field` FROM ethnic_property WHERE `$field` IS NOT NULL AND `$field` != '' ORDER BY `$field` ASC";
        $res = $conn->query($sql);
        while ($row = $res->fetch_row()) {
            $suggestions[] = $row[0];
        }
        echo json_encode($suggestions);
    } else {
        echo json_encode([]);
    }
    exit;
}

// 處理資料表單修改 (AJAX POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['action']) && $_GET['action'] == 'update') {
    header('Content-Type: application/json');
    
    $target_id     = intval($_POST['id'] ?? 0);
    $status        = trim($_POST['status'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $district      = trim($_POST['district'] ?? '');
    $section_code  = trim($_POST['section_code'] ?? '');
    $section_name  = trim($_POST['section_name'] ?? '');
    $land_number   = trim($_POST['land_number'] ?? '');
    $register_date = trim($_POST['register_date'] ?? '');
    $area          = !empty($_POST['area']) ? floatval($_POST['area']) : null;
    $zoning        = trim($_POST['zoning'] ?? '');
    $land_use      = trim($_POST['land_use'] ?? '');
    $owner_type    = trim($_POST['owner_type'] ?? '');
    
    $record_date          = trim($_POST['record_date'] ?? '');
    $posted_land_value    = !empty($_POST['posted_land_value']) ? floatval($_POST['posted_land_value']) : null;
    $declared_land_value  = !empty($_POST['declared_land_value']) ? floatval($_POST['declared_land_value']) : null;
    $land_area            = !empty($_POST['land_area']) ? floatval($_POST['land_area']) : null;

    if ($target_id <= 0) {
        echo json_encode(["status" => "error", "message" => "❌ 錯誤的流水號 ID"]);
        exit;
    }

    $conn->begin_transaction();
    try {
        // 1. 修改 ethnic_property
        $sql_ethnic = "UPDATE ethnic_property SET status=?, city=?, district=?, section_code=?, section_name=?, land_number=?, register_date=?, area=?, zoning=?, land_use=?, owner_type=? WHERE id=?";
        $stmt_e = $conn->prepare($sql_ethnic);
        $stmt_e->bind_param("sssssssdsssi", $status, $city, $district, $section_code, $section_name, $land_number, $register_date, $area, $zoning, $land_use, $owner_type, $target_id);
        $stmt_e->execute();
        $stmt_e->close();

        // 2. 新增或更新到 land_price (依據修改後的 段名 與 地號)
        if (!empty($record_date)) {
            $sql_price = "INSERT INTO land_price (record_date, section_name, land_number, posted_land_value, declared_land_value, land_area) 
                          VALUES (?, ?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE 
                          posted_land_value = VALUES(posted_land_value), declared_land_value = VALUES(declared_land_value), land_area = VALUES(land_area)";
            $stmt_p = $conn->prepare($sql_price);
            $stmt_p->bind_param("sssddd", $record_date, $section_name, $land_number, $posted_land_value, $declared_land_value, $land_area);
            $stmt_p->execute();
            $stmt_p->close();
        }

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "🎉 資料已成功修改更新！"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "❌ 修改失敗: " . $e->getMessage()]);
    }
    exit;
}

// 整批儲存年度地價資料
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['action']) && $_GET['action'] == 'batch_insert_price') {
    header('Content-Type: application/json');
    $record_date = trim($_POST['batch_record_date'] ?? '');
    $prices = $_POST['price'] ?? [];
    
    if (empty($record_date)) {
        echo json_encode(["status" => "error", "message" => "❌ 請填寫公告年月。"]);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        $sql_price = "INSERT INTO land_price (record_date, section_name, land_number, posted_land_value, declared_land_value, land_area) 
                      VALUES (?, ?, ?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE 
                      posted_land_value = VALUES(posted_land_value), declared_land_value = VALUES(declared_land_value), land_area = VALUES(land_area)";
        $stmt_p = $conn->prepare($sql_price);
        
        foreach ($prices as $p) {
            $sec_name = trim($p['section_name'] ?? '');
            $l_num    = trim($p['land_number'] ?? '');
            $p_val    = !empty($p['posted_land_value']) ? floatval($p['posted_land_value']) : null;
            $d_val    = !empty($p['declared_land_value']) ? floatval($p['declared_land_value']) : null;
            $l_area   = !empty($p['land_area']) ? floatval($p['land_area']) : null;
            
            if (!empty($sec_name) && !empty($l_num)) {
                $stmt_p->bind_param("sssddd", $record_date, $sec_name, $l_num, $p_val, $d_val, $l_area);
                $stmt_p->execute();
            }
        }
        $stmt_p->close();
        $conn->commit();
        echo json_encode(["status" => "success", "message" => "🎉 批量年度地價資料更新成功！"]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "❌ 批量寫入失敗: " . $e->getMessage()]);
    }
    exit;
}

// 獲取歷史紀錄
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'get_history') {
    header('Content-Type: application/json');
    $history_data = [];
    
    $res_ethnic = $conn->query("SELECT id, status, city, district, section_code, section_name, land_number, register_date, area, zoning, land_use, owner_type FROM ethnic_property ORDER BY district ASC, land_number ASC");
    while ($row = $res_ethnic->fetch_assoc()) {
        $history_data['ethnic'][] = $row;
    }
    
    echo json_encode($history_data);
    exit;
}

// ==========================================
// 3. 讀取欲修改的單筆原始資料
// ==========================================
$prop = [
    'status'=>'', 'city'=>'', 'district'=>'', 'section_code'=>'', 'section_name'=>'', 
    'land_number'=>'', 'register_date'=>'', 'area'=>'', 'zoning'=>'', 'land_use'=>'', 'owner_type'=>''
];
$price = [
    'record_date'=>'', 'posted_land_value'=>'', 'declared_land_value'=>'', 'land_area'=>''
];

if ($id > 0) {
    // 撈取主資產
    $stmt = $conn->prepare("SELECT * FROM ethnic_property WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $prop = $row;
        
        // 關聯撈取該資產最新的一筆公告地價
        $stmt_p = $conn->prepare("SELECT * FROM land_price WHERE section_name = ? AND land_number = ? ORDER BY record_date DESC LIMIT 1");
        $stmt_p->bind_param("ss", $prop['section_name'], $prop['land_number']);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();
        if ($row_p = $res_p->fetch_assoc()) {
            $price = $row_p;
        }
        $stmt_p->close();
    }
    $stmt->close();
}

// 計算當前程式公告年月預設值 (如果原本沒地價歷史紀錄時使用)
$current_year = intval(date('Y'));
$current_md = date('m-d');
$default_record_date = ($current_md <= '01-02') ? ($current_year . "-01") : (($current_year + 1) . "-01");
if (empty($price['record_date'])) {
    $price['record_date'] = $default_record_date;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>族產與地價修改管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: "Microsoft JhengHei", sans-serif; }
        .custom-container { max-width: 95%; margin: 0 auto; }
        .card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: none; }
        .table-responsive { max-height: 400px; overflow-y: auto; }
        .required-star { color: red; margin-left: 3px; }
    </style>
</head>
<body>

<div class="container-fluid custom-container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📂 族產與地價資料修改系統</h2>
        <a href="property_search.php" class="btn btn-outline-primary">🔍 切換至歷年地價查詢頁面</a>
    </div>

    <?php if ($id <= 0 || empty($prop['section_name'])): ?>
        <div class="alert alert-warning mb-4">⚠️ 請先從下方「族產歷史資料清單」中點選 <b>[編輯]</b> 按鈕，以載入指定土地資產。</div>
    <?php endif; ?>

    <!-- 📜 族產歷史資料清單 (不重複) -->
    <div class="card mb-5">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">📜 族產歷史資料清單 (不重複)</h5>
            <button class="btn btn-sm btn-outline-light" onclick="loadHistory()">🔄 重新整理</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle text-center" style="font-size:0.9rem;">
                    <thead class="table-secondary sticky-top">
                        <tr>
                            <th>操作</th><th>狀態</th><th>縣市</th><th>鄉鎮</th><th>代碼</th><th>段小段</th><th>地號</th><th>登記日</th><th>面積</th><th>分區</th><th>用途</th><th>權利人</th>
                        </tr>
                    </thead>
                    <tbody id="ethnic-history-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 貳、族產資料與地價修改表單 -->
    <div class="card mb-5">
        <div class="card-header bg-warning text-dark p-3 fw-bold">
            <h4 class="mb-0">📋 貳、族產資料與地價修改表單 (目前流水號 ID: <?php echo $id; ?>)</h4>
        </div>
        <div class="card-body p-4">
            
            <div id="alert-box" class="alert d-none alert-dismissible fade show" role="alert">
                <span id="alert-msg"></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <form id="property-form">
                <!-- 隱藏表單傳遞 ID -->
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <!-- 第一部分：族產基本資料 -->
                <h5 class="text-secondary border-bottom pb-2 mb-3">第一部分：族產基本資料</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">狀態<span class="required-star">*</span></label>
                        <input type="text" class="form-control ajax-suggest" name="status" id="form_status" list="suggest-status" placeholder="持有或已賣" value="<?php echo htmlspecialchars($prop['status']); ?>" required>
                        <datalist id="suggest-status"></datalist>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">縣市</label>
                        <input type="text" class="form-control ajax-suggest" name="city" list="suggest-city" placeholder="例如：臺中市" value="<?php echo htmlspecialchars($prop['city']); ?>">
                        <datalist id="suggest-city"></datalist>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">鄉鎮市區</label>
                        <input type="text" class="form-control ajax-suggest" name="district" list="suggest-district" placeholder="例如：大甲區" value="<?php echo htmlspecialchars($prop['district']); ?>">
                        <datalist id="suggest-district"></datalist>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">段小段代碼</label>
                        <input type="text" class="form-control ajax-suggest" name="section_code" list="suggest-section_code" placeholder="例如：3652" value="<?php echo htmlspecialchars($prop['section_code']); ?>">
                        <datalist id="suggest-section_code"></datalist>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">段小段名稱<span class="required-star">*</span></label>
                        <input type="text" class="form-control ajax-suggest" name="section_name" list="suggest-section_name" placeholder="例如：義水段" value="<?php echo htmlspecialchars($prop['section_name']); ?>" required>
                        <datalist id="suggest-section_name"></datalist>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">地號<span class="required-star">*</span></label>
                        <input type="text" class="form-control ajax-suggest" name="land_number" list="suggest-land_number" placeholder="例如：0855-0000" value="<?php echo htmlspecialchars($prop['land_number']); ?>" required>
                        <datalist id="suggest-land_number"></datalist>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">登記日期</label>
                        <input type="text" class="form-control ajax-suggest" name="register_date" list="suggest-register_date" placeholder="例如：840519" value="<?php echo htmlspecialchars($prop['register_date']); ?>">
                        <datalist id="suggest-register_date"></datalist>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">面積 (平方公尺)</label>
                        <input type="number" step="0.01" class="form-control" name="area" placeholder="例如：12.73" value="<?php echo htmlspecialchars($prop['area']); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">使用分區</label>
                        <input type="text" class="form-control ajax-suggest" name="zoning" list="suggest-zoning" placeholder="例如：城鄉發展地區第一類" value="<?php echo htmlspecialchars($prop['zoning']); ?>">
                        <datalist id="suggest-zoning"></datalist>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">使用地類別</label>
                        <input type="text" class="form-control ajax-suggest" name="land_use" list="suggest-land_use" placeholder="例如：住宅區" value="<?php echo htmlspecialchars($prop['land_use']); ?>">
                        <datalist id="suggest-land_use"></datalist>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">土地權利人類別</label>
                    <input type="text" class="form-control ajax-suggest" name="owner_type" list="suggest-owner_type" placeholder="例如：祭祀公業:100.00%" value="<?php echo htmlspecialchars($prop['owner_type']); ?>">
                    <datalist id="suggest-owner_type"></datalist>
                </div>

                <!-- 第二部分：土地公告地價資料 -->
                <h5 class="text-secondary border-bottom pb-2 mb-3 mt-4">第二部分：土地公告地價資料 (land_price)</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">公告年月<span class="required-star">*</span></label>
                        <input type="text" class="form-control" name="record_date" placeholder="格式如: 2026-01" value="<?php echo htmlspecialchars($price['record_date']); ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">公告土地現值 (元/㎡)</label>
                        <input type="number" step="0.01" class="form-control" name="posted_land_value" placeholder="24398" value="<?php echo htmlspecialchars($price['posted_land_value']); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">公告地價 (元/㎡)</label>
                        <input type="number" step="0.01" class="form-control" name="declared_land_value" placeholder="3049" value="<?php echo htmlspecialchars($price['declared_land_value']); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">地價面積 (㎡)</label>
                        <input type="number" step="0.01" class="form-control" name="land_area" placeholder="8278.29" value="<?php echo htmlspecialchars($price['land_area'] ? $price['land_area'] : $prop['area']); ?>">
                    </div>
                </div>

                <div class="text-end mt-3">
                    <button type="button" class="btn btn-secondary me-2" onclick="location.href='property_edit.php'">清除重選</button>
                    <button type="submit" class="btn btn-warning px-5 fw-bold" <?php echo ($id <= 0)?'disabled':''; ?>>確認修改送出</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 📋 壹、填寫年度地價資料 (批次快速更新) - 已移至最下方 -->
    <div class="card mb-5">
        <div class="card-header bg-dark text-white p-3">
            <h4 class="mb-0">📋 壹、填寫年度地價資料 (批次快速更新)</h4>
        </div>
        <div class="card-body p-0">
            <form id="batch-price-form">
                <div class="row align-items-center mb-4 ps-3 pt-3 pe-3">
                    <div class="col-md-4">
                        <label class="form-label font-weight-bold">公告年月*</label>
                        <input type="text" class="form-control" name="batch_record_date" value="<?php echo $default_record_date; ?>" required>
                    </div>
                </div>
                <div class="table-responsive mb-0">
                    <table class="table table-bordered align-middle text-center mb-0" style="font-size:0.9rem;">
                        <thead class="table-dark">
                            <tr>
                                <th>縣市</th><th>地區</th><th>段小段代碼</th><th>段小段名稱</th><th>地號</th><th>面積(㎡)</th><th>公告土地現值(元/㎡)</th><th>公告地價(元/㎡)</th><th>地價面積(㎡)</th>
                            </tr>
                        </thead>
                        <tbody id="hold-lands-tbody">
                            <tr><td colspan="9" class="text-muted py-3">正在載入持有土地資料...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-end pe-3 pb-3 pt-3">
                    <button type="submit" class="btn btn-primary px-5">全部新增地價資料</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
const currentId = <?php echo $id; ?>;

document.addEventListener("DOMContentLoaded", function() {
    loadHoldLands();            
    loadHistory();              
    fetchAllFieldSuggestions(); 

    // 監聽批次年度地價表單提交
    document.getElementById('batch-price-form').addEventListener('submit', function(e){
        e.preventDefault();
        const formData = new FormData(this);
        fetch('property_edit.php?action=batch_insert_price', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if(data.status === 'success') {
                document.getElementById('batch-price-form').reset();
                loadHoldLands();
            }
        });
    });

    // 監聽單筆資料表單提交 (修改用途)
    document.getElementById('property-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('property_edit.php?action=update', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const alertBox = document.getElementById('alert-box');
            const alertMsg = document.getElementById('alert-msg');
            alertBox.classList.remove('d-none', 'alert-success', 'alert-danger');
            
            if(data.status === 'success') {
                alertBox.classList.add('alert-success');
                alertMsg.innerText = data.message;
                loadHistory();              
                loadHoldLands();            
                fetchAllFieldSuggestions(); 
            } else {
                alertBox.classList.add('alert-danger');
                alertMsg.innerText = data.message;
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
});

function loadHoldLands() {
    fetch('property_edit.php?action=get_hold_lands')
    .then(res => res.json())
    .then(lands => {
        const tbody = document.getElementById('hold-lands-tbody');
        tbody.innerHTML = '';
        if(lands.length > 0) {
            lands.forEach((item, index) => {
                tbody.innerHTML += `
                    <tr>
                        <td><input type="text" class="form-control form-control-sm text-center" name="price[${index}][city]" value="${item.city || ''}"></td>
                        <td><input type="text" class="form-control form-control-sm text-center" name="price[${index}][district]" value="${item.district || ''}"></td>
                        <td><input type="text" class="form-control form-control-sm text-center" name="price[${index}][section_code]" value="${item.section_code || ''}"></td>
                        <td><input type="text" class="form-control form-control-sm text-center" name="price[${index}][section_name]" value="${item.section_name || ''}"></td>
                        <td><input type="text" class="form-control form-control-sm text-center" name="price[${index}][land_number]" value="${item.land_number || ''}"></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm text-center" name="price[${index}][area]" value="${item.area || ''}"></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm text-center" name="price[${index}][posted_land_value]" placeholder="土地現值"></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm text-center" name="price[${index}][declared_land_value]" placeholder="公告地價"></td>
                        <td><input type="number" step="0.01" class="form-control form-control-sm text-center" name="price[${index}][land_area]" value="${item.area || ''}"></td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="9" class="text-muted py-3">目前無任何持有土地資料</td></tr>';
        }
    });
}

function fetchAllFieldSuggestions() {
    const inputs = document.querySelectorAll('.ajax-suggest');
    inputs.forEach(input => {
        const fieldName = input.getAttribute('name');
        const datalist = document.getElementById(`suggest-${fieldName}`);
        
        if (datalist) {
            fetch(`property_edit.php?action=get_field_suggestions&field=${fieldName}`)
            .then(res => res.json())
            .then(options => {
                datalist.innerHTML = ''; 
                options.forEach(val => {
                    const option = document.createElement('option');
                    option.value = val;
                    datalist.appendChild(option);
                });
            });
        }
    });
}

function loadHistory() {
    fetch('property_edit.php?action=get_history')
    .then(response => response.json())
    .then(data => {
        const ethnicData = data.ethnic || [];
        const ethnicTbody = document.getElementById('ethnic-history-tbody');
        ethnicTbody.innerHTML = '';
        if(ethnicData.length > 0) {
            ethnicData.forEach(item => {
                const isCurrent = (parseInt(item.id) === currentId) ? 'table-warning font-weight-bold' : '';
                ethnicTbody.innerHTML += `
                    <tr class="${isCurrent}">
                        <td>
                            <a href="property_edit.php?id=${item.id}" class="btn btn-sm btn-primary py-0 px-2">編輯</a>
                        </td>
                        <td><span class="badge ${item.status === '持有' ? 'bg-success' : 'bg-danger'}">${item.status}</span></td>
                        <td>${item.city || '-'}</td>
                        <td>${item.district || '-'}</td>
                        <td>${item.section_code || '-'}</td>
                        <td>${item.section_name || '-'}</td>
                        <td>${item.land_number || '-'}</td>
                        <td>${item.register_date || '-'}</td>
                        <td>${item.area || '-'}</td>
                        <td>${item.zoning || '-'}</td>
                        <td>${item.land_use || '-'}</td>
                        <td class="text-start">${item.owner_type || '-'}</td>
                    </tr>`;
            });
        } else {
            ethnicTbody.innerHTML = '<tr><td colspan="12" class="text-muted py-3">暫無資料</td></tr>';
        }
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>