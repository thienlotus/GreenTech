<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_connect.php';
require_once 'pest_translate.php';
require_once 'ai_config.php';

function normalize_role_value(string $role): string
{
    $value = trim($role);
    if (function_exists('mb_strtolower')) {
        $value = mb_strtolower($value, 'UTF-8');
    } else {
        $value = strtolower($value);
    }

    $value = str_replace(['-', ' '], '_', $value);

    if (in_array($value, ['nong_dan', 'nongdan', 'nông_dân', 'nôngdân'], true)) {
        return 'nong_dan';
    }

    if (in_array($value, ['ho_gia_dinh', 'hogiadinh', 'hộ_gia_đình', 'hộ_gia_dinh'], true)) {
        return 'ho_gia_dinh';
    }

    if (in_array($value, ['khach', 'khách', 'khach_vang_lai', 'khách_vãng_lai'], true)) {
        return 'khach';
    }

    return $value;
}

$isLoggedIn = isset($_SESSION['user_id']);
$isGuestUser = !$isLoggedIn;
$currentUserName = '';
$currentRole = 'khach';
$currentRoleLabel = 'Khách vãng lai';
$dashboardLink = 'index.php';
$avatarPath = trim((string)($_SESSION['avatar_path'] ?? ''));
$avatarUrl = '';
$isHouseholdUser = false;
$householdUserId = 0;
$householdAddress = '';
$householdCoords = null;
$householdScanLabel = '';
$householdScanKey = '';

if ($isLoggedIn) {
    $currentUserName = trim((string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng'));
    $role = normalize_role_value((string)($_SESSION['role'] ?? 'khach'));
    $currentRole = $role;
    $roleLabelMap = [
        'nong_dan' => 'Nông dân',
        'ho_gia_dinh' => 'Hộ gia đình',
        'khach' => 'Khách vãng lai'
    ];
    $currentRoleLabel = $roleLabelMap[$role] ?? 'Khách vãng lai';
    $_SESSION['role'] = $role;

    if ($role === 'nong_dan') {
        $dashboardLink = 'dashboard_nongdan.php';
    } elseif ($role === 'ho_gia_dinh') {
        $dashboardLink = 'dashboard_giadinh.php';
    }

    if ($role === 'ho_gia_dinh') {
        $isHouseholdUser = true;
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $householdUserId = $userId;
        if ($userId > 0) {
            $householdScanKey = 'HOGD_USER_' . $userId;
            $userStmt = $conn->prepare('SELECT dia_chi_nha FROM users WHERE id = ? LIMIT 1');
            if ($userStmt) {
                $userStmt->bind_param('i', $userId);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                $userRow = $userResult ? $userResult->fetch_assoc() : null;
                $userStmt->close();

                $householdAddress = trim((string)($userRow['dia_chi_nha'] ?? ''));
                $householdScanLabel = $householdAddress !== '' ? $householdAddress : 'Vị trí hộ gia đình';

                if (preg_match('/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/', $householdAddress, $matches)) {
                    $lat = (float)$matches[1];
                    $lng = (float)$matches[2];
                    if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                        $householdCoords = ['lat' => $lat, 'lng' => $lng];
                    }
                }
            }
        }
    }
}

if ($avatarPath !== '') {
    $avatarUrl = $avatarPath;
} else {
    $avatarUrl = 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . urlencode($currentUserName !== '' ? $currentUserName : 'guest');
}

// =========================================================================
// HỆ CHUYÊN GIA: TỪ ĐIỂN SUY LUẬN BỆNH & CÁCH CHỮA TỪ 19 LOÀI CÔN TRÙNG
// =========================================================================
$expert_system = [
    'aphids' => [
        'benh' => 'Bệnh khảm lá, lùn xoắn lá (Truyền virus). Nấm bồ hóng làm đen lá.',
        'chua' => 'Sử dụng thiên địch (bọ rùa). Phun chế phẩm sinh học nấm xanh/nấm trắng hoặc xà phòng sinh học.'
    ],
    'whitefly' => [
        'benh' => 'Bệnh khảm lá (Mosaic virus), héo rũ, thui chột quả non.',
        'chua' => 'Treo bẫy dính màu vàng. Phun tinh dầu Neem hoặc thuốc trừ sâu sinh học Bt.'
    ],
    'snail' => [
        'benh' => 'Đứt rễ, cụt mầm non. Vết cắn tạo điều kiện cho vi khuẩn thối nhũn xâm nhập.',
        'chua' => 'Rắc vôi bột quanh luống. Dùng bẫy bả chua ngọt hoặc rắc thuốc bả sên lúc chiều tối.'
    ],
    'mites' => [
        'benh' => 'Bệnh cháy lá, vàng đọt, rụng lá non hàng loạt do bị hút kiệt nhựa.',
        'chua' => 'Phun nước áp lực cao lên mặt dưới lá. Sử dụng thuốc đặc trị nhện đỏ hoặc dầu khoáng.'
    ],
    'thrips' => [
        'benh' => 'Bệnh xoăn đọt non, sần sùi và biến dạng quả (da lu lu).',
        'chua' => 'Tỉa cành tạo độ thông thoáng. Phun thuốc trừ bọ trĩ lưu dẫn vào sáng sớm hoặc chiều mát.'
    ],
    'rice leaf roller' => [
        'benh' => 'Bệnh bạc trắng lá, suy giảm quang hợp nghiêm trọng, lép hạt.',
        'chua' => 'Thả ong mắt đỏ (thiên địch). Phun thuốc vi sinh Bacillus thuringiensis (Bt) khi sâu non mới nở.'
    ],
    'rice leaf caterpillar' => [
        'benh' => 'Lá bị ăn khuyết, xơ xác, cây còi cọc không thể quang hợp.',
        'chua' => 'Dọn sạch cỏ bờ. Phun các loại thuốc trừ sâu thảo mộc hoặc chế phẩm sinh học.'
    ],
    'asiatic rice borer' => [
        'benh' => 'Hội chứng "Bông bạc" (lúa trổ trắng) hoặc héo rũ (Deadheart) chồi non.',
        'chua' => 'Nhổ bỏ và tiêu hủy cây bệnh. Phun thuốc nội hấp, lưu dẫn ngay khi bướm rộ.'
    ],
    'yellow rice borer' => [
        'benh' => 'Hội chứng "Bông bạc" (lúa trổ trắng) hoặc héo rũ (Deadheart) chồi non.',
        'chua' => 'Cày ải phơi ruộng diệt nhộng. Rải thuốc hạt lưu dẫn xuống gốc.'
    ],
    'flea beetle' => [
        'benh' => 'Lá thủng lỗ chỗ như rây bột. Sâu non cắn rễ gây thối rễ chết héo.',
        'chua' => 'Luân canh cây trồng. Xử lý đất bằng vôi bột trước khi trồng, phun thuốc nhóm cúc tổng hợp.'
    ],
    'cutworm' => [
        'benh' => 'Cắn đứt ngang thân cây con sát mặt đất, thối gốc do nấm xâm nhập.',
        'chua' => 'Cày bừa kỹ đất. Dùng cám rang trộn thuốc làm bả độc rắc quanh gốc.'
    ],
    'rice gall midge' => [
        'benh' => 'Bệnh "Cọng hành" (ống hành), chồi lúa biến dạng không thể trổ bông.',
        'chua' => 'Nhổ bỏ dảnh lúa bị cọng hành. Tránh gieo cấy quá dày, giảm bón phân đạm.'
    ],
    'paddy stem maggot' => [
        'benh' => 'Thối đọt non, lá non bị héo vàng và dễ dàng rút đứt ra.',
        'chua' => 'Vệ sinh đồng ruộng, diệt cỏ dại. Rải thuốc hột lưu dẫn xuống ruộng.'
    ]
];
// Gộp các loại côn trùng nhai cắn
$nhai_can = ['benh' => 'Lá bị cắn khuyết, đứt rễ. Dễ nhiễm nấm cơ hội qua vết thương hở.', 'chua' => 'Sử dụng bẫy đèn bắt bướm/côn trùng trưởng thành. Phun thuốc trừ sâu sinh học.'];
$expert_system['grasshopper'] = $nhai_can;
$expert_system['caterpillar'] = $nhai_can;
$expert_system['mole cricket'] = $nhai_can;
$expert_system['wireworm'] = $nhai_can;
$expert_system['unknown_bug'] = ['benh' => 'Cần theo dõi thêm diễn biến trên lá.', 'chua' => 'Tiếp tục giám sát mật độ. Áp dụng các biện pháp canh tác an toàn.'];


$result_data = null;
$error_message = '';

$captured_image_data = $_POST['captured_image'] ?? '';
$khu_vuc = $_POST['khu_vuc'] ?? 'Chưa xác định';
if ($isGuestUser) {
    $khu_vuc = 'Khách vãng lai (Demo)';
}
if ($isHouseholdUser && ($khu_vuc === 'Chưa xác định' || $khu_vuc === '')) {
    if ($householdScanKey !== '') {
        $khu_vuc = $householdScanKey;
    } elseif ($householdScanLabel !== '') {
        $khu_vuc = $householdScanLabel;
    }
}

// -------------------------------------------------------------
// 1. XỬ LÝ UPLOAD ẢNH & NHẬN DIỆN AI
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['image']) || !empty($captured_image_data))) {
    $file_tmp = '';
    $file_name = '';
    $file_type = 'image/jpeg';
    $is_temp_capture = false;

    if (!empty($captured_image_data)) {
        if (preg_match('/^data:image\/(\w+);base64,/', $captured_image_data, $matches)) {
            $extension = strtolower($matches[1]);
            if ($extension === 'jpeg') {
                $extension = 'jpg';
            }

            $raw_data = preg_replace('/^data:image\/\w+;base64,/', '', $captured_image_data);
            $binary_data = base64_decode($raw_data, true);

            if ($binary_data !== false) {
                $temp_file = tempnam(sys_get_temp_dir(), 'cam_');
                if ($temp_file !== false && file_put_contents($temp_file, $binary_data) !== false) {
                    $file_tmp = $temp_file;
                    $file_name = 'camera_capture.' . $extension;
                    $file_type = 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);
                    $is_temp_capture = true;
                }
            }
        }
    } else {
        $file_tmp = $_FILES['image']['tmp_name'] ?? '';
        $file_name = $_FILES['image']['name'] ?? '';
        $file_type = $_FILES['image']['type'] ?? 'image/jpeg';
    }

    $is_valid_source = $is_temp_capture ? is_file($file_tmp) : is_uploaded_file($file_tmp);

    if (!empty($file_name) && $is_valid_source) {
        
        // Dùng endpoint cấu hình để dễ chuyển giữa local/prod và rollout model mới.
        $api_url = get_ai_detect_endpoint();
        
        $cfile = new CURLFile($file_tmp, $file_type, $file_name);
        $post_data = array('image' => $cfile);

        $response = false;
        $http_code = 0;
        $curl_error = '';

        $max_attempts = get_ai_retry_attempts();
        $request_timeout = get_ai_timeout_seconds();
        $relax_ssl_verify = should_relax_ai_ssl_verify();

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
            curl_setopt($ch, CURLOPT_TIMEOUT, $request_timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'Expect:'
            ));
            curl_setopt($ch, CURLOPT_USERAGENT, 'GreenTech-Web/1.0');

            if (stripos($api_url, 'https://') === 0) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$relax_ssl_verify);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $relax_ssl_verify ? 0 : 2);
            }

            $response = curl_exec($ch);
            $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = (string)curl_error($ch);
            curl_close($ch);

            if ($response !== false && $http_code >= 200 && $http_code < 300) {
                break;
            }

            $is_retryable_http = in_array($http_code, array(408, 425, 429, 500, 502, 503, 504, 522, 524), true);
            $is_retryable_network = $response === false;
            if (($is_retryable_http || $is_retryable_network) && $attempt < $max_attempts) {
                usleep(600000);
            }
        }

        if ($response !== false && $http_code >= 200 && $http_code < 300) {
            $data = json_decode($response, true);

            if (isset($data['success']) && $data['success'] === true) {
                $original_image = $data['original_image'] ?? time() . '_' . $file_name;
                
                // Giải mã Base64 lưu ảnh kết quả
                $result_image = '';
                if (isset($data['image_base64']) && $data['image_base64'] !== "") {
                    $result_image = 'result_' . $original_image;
                    $img_data = base64_decode($data['image_base64']);
                    file_put_contents(__DIR__ . '/results/' . $result_image, $img_data);
                } else {
                    $result_image = $data['result_image'] ?? '';
                }

                $total_insects = (int)($data['total_insects'] ?? 0);
                $pest_counts = is_array($data['pest_counts'] ?? null) ? $data['pest_counts'] : array();

                // Lưu dữ liệu quét vào DB
                $stmt = $conn->prepare('INSERT INTO lich_su_quet (hinh_anh_goc, hinh_anh_ket_qua, khu_vuc) VALUES (?, ?, ?)');
                if ($stmt) {
                    $stmt->bind_param('sss', $original_image, $result_image, $khu_vuc);
                    $stmt->execute();
                    $lich_su_id = $stmt->insert_id;
                    $stmt->close();

                    foreach ($pest_counts as $ten_sau => $so_luong) {
                        $stmt2 = $conn->prepare('INSERT INTO chi_tiet_dich_hai (lich_su_id, ten_loai_sau, so_luong) VALUES (?, ?, ?)');
                        if ($stmt2) {
                            $count = (int)$so_luong;
                            $stmt2->bind_param('isi', $lich_su_id, $ten_sau, $count);
                            $stmt2->execute();
                            $stmt2->close();
                        }
                    }
                }

                $result_data = array(
                    'result_image' => $result_image,
                    'total_insects' => $total_insects,
                    'pest_counts' => $pest_counts,
                    'khu_vuc' => $isHouseholdUser ? ($householdScanLabel !== '' ? $householdScanLabel : 'Vị trí hộ gia đình') : $khu_vuc
                );
            } else {
                $error_message = 'Lỗi từ AI: ' . htmlspecialchars($data['error'] ?? 'Không rõ nguyên nhân', ENT_QUOTES, 'UTF-8');
            }
        } else {
            $details = array();
            if ($http_code > 0) {
                $details[] = 'HTTP ' . $http_code;
            }
            if ($curl_error !== '') {
                $details[] = $curl_error;
            }

            $detail_text = !empty($details) ? (' Chi tiết: ' . implode(' | ', $details)) : '';
            $error_message = 'Không thể kết nối đến AI. Vui lòng kiểm tra endpoint Hugging Face.' . htmlspecialchars($detail_text, ENT_QUOTES, 'UTF-8');
        }

        if ($is_temp_capture && is_file($file_tmp)) {
            unlink($file_tmp);
        }
    } else {
        $error_message = 'Vui lòng chọn ảnh hợp lệ hoặc chụp ảnh bằng camera trước khi phân tích.';
    }
}

// -------------------------------------------------------------
// 2. LẤY DỮ LIỆU BẢN ĐỒ WEBGIS
// -------------------------------------------------------------
$gis_data = [];

if ($isHouseholdUser) {
    $scanKey = $householdScanKey !== '' ? $householdScanKey : 'HOGD_USER_0';
    $legacyLabel = $householdScanLabel !== '' ? $householdScanLabel : $scanKey;

    $stmtGis = $conn->prepare("SELECT ls.khu_vuc, ct.ten_loai_sau, SUM(ct.so_luong) as tong_so_luong
                              FROM lich_su_quet ls
                              JOIN chi_tiet_dich_hai ct ON ls.id = ct.lich_su_id
                              WHERE ls.khu_vuc IN (?, ?)
                              GROUP BY ls.khu_vuc, ct.ten_loai_sau");
    $result_gis = null;

    if ($stmtGis) {
        $stmtGis->bind_param('ss', $scanKey, $legacyLabel);
        $stmtGis->execute();
        $result_gis = $stmtGis->get_result();
    }
} else {
    $sql_gis = "SELECT ls.khu_vuc, ct.ten_loai_sau, SUM(ct.so_luong) as tong_so_luong
                FROM lich_su_quet ls
                JOIN chi_tiet_dich_hai ct ON ls.id = ct.lich_su_id
                WHERE ls.khu_vuc IN ('Thôn 1 (Vùng Lúa nước)', 'Thôn 2 (Vùng Cải xanh)', 'Thôn 3 (Vùng Cà chua)')
                GROUP BY ls.khu_vuc, ct.ten_loai_sau";
    $result_gis = $conn->query($sql_gis);
}

if ($result_gis && $result_gis->num_rows > 0) {
    while ($row = $result_gis->fetch_assoc()) {
        $kv = $isHouseholdUser ? ($householdScanLabel !== '' ? $householdScanLabel : 'Vị trí hộ gia đình') : $row['khu_vuc'];
        $loai_sau = $row['ten_loai_sau'];
        $sl = (int)$row['tong_so_luong'];

        if (!isset($gis_data[$kv])) {
            $gis_data[$kv] = ['total' => 0, 'details' => []];
        }
        $gis_data[$kv]['total'] += $sl;
        $gis_data[$kv]['details'][$loai_sau] = $sl;
    }
}

if (isset($stmtGis) && $stmtGis) {
    $stmtGis->close();
}

// Màu sắc cảnh báo WebGIS
$region_style = [];
foreach ($gis_data as $kv_name => $data) {
    $count = (int)$data['total'];
    if ($count >= 25) {
        $region_style[$kv_name] = ['fill' => '#ef4444', 'badge' => 'bg-red-500', 'text' => 'text-red-200', 'level' => 'BÁO ĐỘNG ĐỎ'];
    } elseif ($count >= 10) {
        $region_style[$kv_name] = ['fill' => '#f59e0b', 'badge' => 'bg-amber-500', 'text' => 'text-amber-200', 'level' => 'CẢNH BÁO VÀNG'];
    } else {
        $region_style[$kv_name] = ['fill' => '#10b981', 'badge' => 'bg-emerald-500', 'text' => 'text-emerald-200', 'level' => 'AN TOÀN'];
    }
}

$householdPestTotal = 0;
$householdPestLevel = 'AN TOÀN';
if ($isHouseholdUser) {
    foreach ($gis_data as $data) {
        $householdPestTotal += (int)($data['total'] ?? 0);
    }
    if ($householdPestTotal >= 30) {
        $householdPestLevel = 'BÁO ĐỘNG ĐỎ';
    } elseif ($householdPestTotal >= 10) {
        $householdPestLevel = 'CẢNH BÁO VÀNG';
    }
}

$webgisJsData = $isGuestUser ? [] : $gis_data;
$webgisJsStyles = $isGuestUser ? [] : $region_style;

// Lấy Thống kê tổng quan (Dashboard trên cùng)
$aiAnalysisCount = 0;
$aiCountQuery = $conn->query('SELECT COUNT(*) AS total FROM lich_su_quet');
if ($aiCountQuery) {
    $aiCountRow = $aiCountQuery->fetch_assoc();
    $aiAnalysisCount = (int)($aiCountRow['total'] ?? 0);
}

$recognizedSpeciesCount = 0;
$speciesCountQuery = $conn->query('SELECT COUNT(DISTINCT ten_loai_sau) AS total FROM chi_tiet_dich_hai');
if ($speciesCountQuery) {
    $speciesCountRow = $speciesCountQuery->fetch_assoc();
    $recognizedSpeciesCount = (int)($speciesCountRow['total'] ?? 0);
}

$monitoredRegionCount = 0;
$regionBaseSet = [];
$regionQuery = $conn->query('SELECT DISTINCT khu_vuc FROM lich_su_quet WHERE khu_vuc IS NOT NULL AND khu_vuc <> ""');
if ($regionQuery) {
    while ($regionRow = $regionQuery->fetch_assoc()) {
        $regionName = trim((string)($regionRow['khu_vuc'] ?? ''));
        if ($regionName === '') continue;
        if (stripos($regionName, 'HOGD_USER_') === 0 || stripos($regionName, 'Khách vãng lai') !== false) continue;

        if (preg_match('/^(Thôn\s*\d+)/u', $regionName, $matches)) {
            $regionBase = $matches[1];
        } else {
            $regionBase = $regionName;
        }
        $regionBaseSet[$regionBase] = true;
    }
}
$monitoredRegionCount = count($regionBaseSet);

$aiAnalysisDisplay = number_format($aiAnalysisCount);
$recognizedSpeciesDisplay = number_format($recognizedSpeciesCount);
$monitoredRegionDisplay = $monitoredRegionCount;
?>

<!DOCTYPE html>
<html lang="vi" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="greentech-favicon.svg">
    <title>GreenTech | Nhận diện côn trùng bằng AI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: '#2E7D32',
                        brandBg: '#F8FAFC',
                        brandText: '#1E293B'
                    }
                }
            }
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-image: url('https://png.pngtree.com/thumb_back/fh260/background/20240911/pngtree-open-book-with-green-plant-sprouting-in-sunlight-bokeh-background-image_16143810.jpg');
            background-size: cover;
            background-position: center center;
            background-attachment: scroll;
            background-repeat: no-repeat;
        }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        @keyframes laserScan {
            0% { top: 0; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }

        .animate-laser { animation: laserScan 2s ease-in-out infinite; }

        @keyframes ticker {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        .animate-ticker {
            display: inline-block;
            white-space: nowrap;
            animation: ticker 25s linear infinite;
        }

        @keyframes headerSheen {
            0% { transform: translateX(-120%); opacity: 0; }
            20% { opacity: 0.45; }
            100% { transform: translateX(220%); opacity: 0; }
        }

        @keyframes headerPulse {
            0%, 100% { box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06); }
            50% { box-shadow: 0 10px 38px rgba(16, 185, 129, 0.14); }
        }

        .header-shell {
            animation: headerPulse 7s ease-in-out infinite;
            background: linear-gradient(120deg, rgba(255, 255, 255, 0.88) 0%, rgba(248, 250, 252, 0.83) 58%, rgba(236, 253, 245, 0.86) 100%);
        }

        .display-heading {
            font-family: 'Be Vietnam Pro', 'Inter', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .header-aura {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .header-aura::after {
            content: '';
            position: absolute;
            top: -40%;
            left: -35%;
            width: 28%;
            height: 200%;
            transform: skewX(-20deg);
            background: linear-gradient(to right, rgba(255, 255, 255, 0), rgba(16, 185, 129, 0.24), rgba(255, 255, 255, 0));
            animation: headerSheen 7.5s linear infinite;
        }

        .nav-link { position: relative; }
        .nav-link::after {
            content: ''; position: absolute; left: 0; bottom: -7px; height: 2px; width: 100%;
            border-radius: 999px; background: linear-gradient(90deg, #16a34a, #0ea5e9);
            transform: scaleX(0); transform-origin: left; transition: transform 220ms ease;
        }
        .nav-link:hover::after { transform: scaleX(1); }

        .leaflet-popup.guest-lock-popup .leaflet-popup-content-wrapper {
            background: rgba(255, 255, 255, 0.86); backdrop-filter: blur(8px);
            border: 1px solid rgba(226, 232, 240, 0.9); border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        }
        .leaflet-popup.guest-lock-popup .leaflet-popup-tip { background: rgba(255, 255, 255, 0.9); }
    </style>
</head>
<body class="bg-brandBg/90 text-brandText antialiased flex flex-col min-h-screen">
    <nav class="header-shell fixed top-0 inset-x-0 z-50 backdrop-blur-xl border-b border-slate-200/50">
        <div class="header-aura"></div>
        <div class="relative max-w-[1400px] mx-auto px-6 h-16 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <iconify-icon icon="solar:leaf-bold-duotone" width="24" class="text-brand"></iconify-icon>
                <span class="font-semibold tracking-tighter text-lg text-brand">GREENTECH</span>
            </div>
            <div class="hidden md:flex space-x-8">
                <a href="#home" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Trang chủ</a>
                <a href="#scanner" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Quét AI</a>
                <a href="#encyclopedia" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Cẩm nang</a>
                <a href="#webgis" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Bản đồ</a>
                <a href="thong_ke.php" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Thống kê</a>
                <?php if ($isLoggedIn) : ?>
                    <a href="<?php echo htmlspecialchars($dashboardLink, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link text-sm text-slate-500 hover:text-brand transition-colors">Dashboard</a>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($isLoggedIn) : ?>
                    <div class="text-right hidden md:block">
                        <div class="text-xs font-medium text-slate-900"><?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="text-[10px] text-slate-500">Vai trò: <?php echo htmlspecialchars($currentRoleLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="w-9 h-9 rounded-full bg-slate-200 border-2 border-white shadow-sm overflow-hidden">
                        <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="avatar" class="w-full h-full object-cover">
                    </div>
                    <a href="profile.php" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Hồ sơ</a>
                    <a href="logout.php" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">Đăng xuất</a>
                <?php else : ?>
                    <a href="auth.php?tab=login" class="inline-flex items-center rounded-lg border border-brand/20 bg-white px-3 py-2 text-xs font-semibold text-brand hover:bg-green-50 transition-colors">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="mt-16 bg-red-50/90 backdrop-blur-sm border-b border-red-100 overflow-hidden relative h-8 flex items-center">
        <div class="absolute left-0 bg-red-50 z-10 px-4 h-full flex items-center shadow-[10px_0_15px_-5px_rgba(254,226,226,1)]">
            <span class="text-xs font-semibold text-red-600 tracking-tight flex items-center gap-1">
                <iconify-icon icon="solar:bell-bing-linear" width="14" class="animate-pulse"></iconify-icon> CẢNH BÁO:
            </span>
        </div>
        <div class="w-full pl-28">
            <div class="animate-ticker text-xs text-red-500">
                Theo dõi sâu bệnh trên đồng ruộng và quét hình ảnh thường xuyên để phát hiện sớm nguy cơ lây lan thông qua Hệ Chuyên Gia AI.
            </div>
        </div>
    </div>

    <main class="flex-1">
        <section id="home" class="pt-16 pb-24 px-6 relative max-w-[1200px] mx-auto min-h-[60vh] flex flex-col justify-center">
            <div class="text-center max-w-3xl mx-auto mb-16 bg-white/60 backdrop-blur-md p-8 rounded-3xl border border-white/50 shadow-lg">
                <h1 class="display-heading text-4xl md:text-5xl tracking-tight text-brandText leading-tight mb-6">Bảo Vệ Mùa Màng Bằng <br> Trí Tuệ Nhân Tạo</h1>
                <p class="text-sm md:text-base text-slate-700 mb-10 font-medium leading-relaxed">
                    Hệ thống chẩn đoán hình ảnh tức thì và giám sát côn trùng bằng dữ liệu số. Quyết định nhanh hơn, năng suất tốt hơn.
                </p>
                <a href="#scanner" class="inline-flex bg-brand hover:bg-green-700 text-white text-sm font-medium py-3 px-8 rounded-full shadow-[0_8px_20px_rgba(46,125,50,0.25)] transition-all items-center gap-2 mx-auto">
                    <iconify-icon icon="solar:scanner-linear" width="18"></iconify-icon> Bắt đầu Quét AI
                </a>
            </div>
        </section>

        <section id="global-stats" class="pb-8 px-6">
            <div class="max-w-[1200px] mx-auto">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 backdrop-blur-sm p-5 shadow-sm">
                        <p class="text-[11px] uppercase tracking-widest text-emerald-700">Lượt AI phân tích</p>
                        <p class="mt-2 text-2xl font-extrabold text-emerald-800"><?php echo htmlspecialchars($aiAnalysisDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="rounded-2xl border border-sky-200 bg-sky-50/80 backdrop-blur-sm p-5 shadow-sm">
                        <p class="text-[11px] uppercase tracking-widest text-sky-700">Loài sâu hại nhận diện</p>
                        <p class="mt-2 text-2xl font-extrabold text-sky-800"><?php echo htmlspecialchars($recognizedSpeciesDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/80 backdrop-blur-sm p-5 shadow-sm">
                        <p class="text-[11px] uppercase tracking-widest text-amber-700">Vùng chuyên canh giám sát</p>
                        <p class="mt-2 text-2xl font-extrabold text-amber-800"><?php echo (int)$monitoredRegionDisplay; ?></p>
                    </div>
                </div>
            </div>
        </section>

        <section id="scanner" class="py-20 bg-white/90 backdrop-blur-md border-y border-slate-200/50">
            <div class="max-w-[1200px] mx-auto px-6">
                <div class="mb-12">
                    <h2 class="display-heading text-2xl tracking-tight text-brandText">Trạm Quét AI & Chẩn Đoán Bệnh</h2>
                    <p class="text-sm text-slate-500 mt-1 font-light">Tải ảnh mẫu vật để AI phát hiện côn trùng hại và phân tích nguy cơ.</p>
                </div>

                <?php if (!empty($error_message)) : ?>
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm px-4 py-3">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="flex flex-col lg:flex-row gap-8">
                    <form id="scanForm" action="" method="POST" enctype="multipart/form-data" class="w-full lg:w-[40%] flex flex-col gap-4">
                        <input id="capturedImageInput" type="hidden" name="captured_image" value="">

                        <label for="imageInput" id="dropzone" class="w-full aspect-square border-2 border-dashed border-slate-300 hover:border-brand rounded-2xl bg-white flex flex-col items-center justify-center cursor-pointer transition-colors relative overflow-hidden group">
                            <img id="previewImage" src="" alt="Xem trước ảnh" class="hidden absolute inset-0 w-full h-full object-cover">
                            <div id="dropzoneShade" class="hidden absolute inset-0 bg-black/25"></div>
                            <div class="text-slate-400 group-hover:text-brand transition-colors mb-3">
                                <iconify-icon icon="solar:gallery-add-linear" width="32"></iconify-icon>
                            </div>
                            <span class="text-sm font-medium text-slate-600 text-center px-4" id="fileLabel">Kéo thả hoặc bấm để chọn ảnh</span>
                            <input id="imageInput" type="file" name="image" accept="image/*" class="hidden">

                            <div id="scanOverlay" class="absolute inset-0 bg-white/90 backdrop-blur-sm hidden flex-col items-center justify-center z-10">
                                <div class="absolute left-0 w-full h-[1px] bg-brand shadow-[0_0_15px_#2E7D32] animate-laser"></div>
                                <div class="text-brand text-xs font-semibold tracking-widest uppercase flex flex-col items-center gap-2 z-20">
                                    <iconify-icon icon="solar:cpu-linear" width="24" class="animate-pulse"></iconify-icon> Đang phân tích...
                                </div>
                            </div>
                        </label>

                        <div class="flex gap-3">
                            <button id="openCameraBtn" type="button" class="flex-1 bg-white border border-slate-200 text-slate-600 text-sm font-medium py-2.5 rounded-xl shadow-sm hover:bg-slate-50 flex items-center justify-center gap-2">
                                <iconify-icon icon="solar:camera-bold" width="16"></iconify-icon> Mở camera
                            </button>
                            <a href="thong_ke.php" class="flex-1 bg-white border border-slate-200 text-slate-600 text-sm font-medium py-2.5 rounded-xl shadow-sm hover:bg-slate-50 flex items-center justify-center gap-2">
                                <iconify-icon icon="solar:chart-square-linear" width="16"></iconify-icon> Xem thống kê
                            </a>
                        </div>

                        <div class="flex gap-3">
                            <div class="flex-1 relative">
                                <?php if ($isHouseholdUser) : ?>
                                    <input type="hidden" name="khu_vuc" value="<?php echo htmlspecialchars($householdScanKey !== '' ? $householdScanKey : ($householdScanLabel !== '' ? $householdScanLabel : 'HOGD_USER_0'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="w-full rounded-xl border border-sky-200 bg-sky-50 py-2.5 px-4 text-sm text-sky-800 font-medium">
                                        📍 Vị trí hộ gia đình: <?php echo htmlspecialchars($householdScanLabel !== '' ? $householdScanLabel : 'Chưa cập nhật vị trí', ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php elseif ($isGuestUser) : ?>
                                    <div class="w-full rounded-xl border border-amber-200 bg-amber-50 py-2.5 px-4 text-sm text-amber-800 font-medium">
                                        👤 Chế độ khách: quét thử nghiệm (không gắn khu vực bản đồ).
                                    </div>
                                <?php else : ?>
                                    <select name="khu_vuc" required class="w-full appearance-none bg-white border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 text-sm text-slate-600 font-medium focus:outline-none focus:border-brand shadow-sm cursor-pointer">
                                        <option value="" disabled selected>📍 Chọn khu vực và loại cây trồng...</option>
                                        <option value="Thôn 1 (Vùng Lúa nước)">Thôn 1 (Vùng Lúa nước)</option>
                                        <option value="Thôn 2 (Vùng Cải xanh)">Thôn 2 (Vùng Cải xanh)</option>
                                        <option value="Thôn 3 (Vùng Cà chua)">Thôn 3 (Vùng Cà chua)</option>
                                    </select>
                                    <iconify-icon icon="solar:alt-arrow-down-linear" class="absolute right-3 top-3 text-slate-400 pointer-events-none" width="16"></iconify-icon>
                                <?php endif; ?>
                            </div>
                        </div>

                        <button id="btnAnalyze" type="submit" class="w-full bg-brand hover:bg-green-700 text-white text-base font-semibold py-4 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 mt-2">
                            <iconify-icon icon="solar:magic-stick-3-linear" width="20"></iconify-icon> Phân tích dữ liệu
                        </button>
                    </form>

                    <div class="w-full lg:w-[60%] bg-white rounded-2xl border border-slate-200/60 p-6 flex flex-col relative overflow-hidden shadow-sm min-h-[420px]">
                        <?php if ($result_data === null) : ?>
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center p-6 z-10 transition-opacity duration-300">
                                <div class="w-24 h-24 bg-slate-50 rounded-full shadow-sm border border-slate-100 flex items-center justify-center text-slate-300 mb-4">
                                    <iconify-icon icon="solar:documents-minimalistic-linear" width="32"></iconify-icon>
                                </div>
                                <h3 class="display-heading text-sm text-slate-900">Đang chờ dữ liệu...</h3>
                                <p class="text-xs text-slate-500 mt-1 max-w-xs">Tải lên hình ảnh có dấu hiệu bất thường để AI phát hiện côn trùng và chẩn đoán bệnh.</p>
                            </div>
                        <?php else : ?>
                            <div class="w-full h-full flex flex-col gap-4 overflow-y-auto pr-2">
                                <div class="bg-green-50 p-4 rounded-xl border border-green-100 shadow-sm md:col-span-2 flex items-center justify-between">
                                    <div>
                                        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-1">Kết quả chẩn đoán</div>
                                        <h4 class="display-heading text-lg tracking-tight text-green-700">Đã phát hiện <?php echo (int)$result_data['total_insects']; ?> vùng có côn trùng</h4>
                                        <p class="text-xs text-slate-600 mt-1">Khu vực: <strong><?php echo htmlspecialchars($result_data['khu_vuc'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                                    </div>
                                </div>

                                <div class="w-full h-56 bg-slate-900 rounded-xl overflow-hidden relative shadow-inner shrink-0">
                                    <img src="results/<?php echo htmlspecialchars($result_data['result_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Kết quả AI" class="w-full h-full object-contain bg-black/70">
                                </div>

                                <?php if (!empty($result_data['pest_counts'])) : ?>
                                    <div class="space-y-3 mt-2">
                                        <h3 class="display-heading text-sm text-slate-800 border-b border-slate-200 pb-2">Chi tiết Mật độ & Bệnh học</h3>
                                        
                                        <?php foreach ($result_data['pest_counts'] as $ten_sau => $so_luong) : 
                                            $key_ai = strtolower(trim($ten_sau));
                                            $chuyen_gia = $expert_system[$key_ai] ?? $expert_system['unknown_bug'];
                                            $ten_tieng_viet = translate_pest_name_vi($ten_sau);
                                        ?>
                                            <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm relative overflow-hidden">
                                                <div class="absolute left-0 top-0 bottom-0 w-1.5 bg-red-500"></div>
                                                <div class="flex justify-between items-center mb-3 pl-3">
                                                    <span class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($ten_tieng_viet, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span class="bg-red-100 text-red-700 text-xs font-bold px-3 py-1 rounded-full">Phát hiện: <?php echo (int)$so_luong; ?> cá thể</span>
                                                </div>
                                                
                                                <div class="grid grid-cols-1 <?php echo $isGuestUser ? '' : 'md:grid-cols-2'; ?> gap-3 pl-3">
                                                    <?php if (!$isGuestUser) : ?>
                                                        <div class="bg-orange-50 p-3 rounded-lg border border-orange-100">
                                                            <div class="text-[10px] font-bold text-orange-600 uppercase mb-1 flex items-center gap-1">
                                                                <iconify-icon icon="solar:danger-triangle-bold"></iconify-icon> Nguy cơ bệnh hại
                                                            </div>
                                                            <p class="text-xs text-slate-700 leading-relaxed"><?php echo $chuyen_gia['benh']; ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-100">
                                                        <div class="text-[10px] font-bold text-emerald-600 uppercase mb-1 flex items-center gap-1">
                                                            <iconify-icon icon="solar:shield-check-bold"></iconify-icon> Khuyến nghị xử lý
                                                        </div>
                                                        <p class="text-xs text-slate-700 leading-relaxed"><?php echo $chuyen_gia['chua']; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if ($isGuestUser) : ?>
                                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                                                🔒 Để xem nguy cơ bùng phát dịch tễ và phác đồ điều trị chuyên sâu, vui lòng <a href="auth.php" class="font-bold underline hover:text-amber-900">Đăng nhập / Đăng ký</a> tài khoản Nông dân.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else : ?>
                                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col">
                                        <p class="text-xs text-slate-500">Môi trường an toàn, chưa phát hiện mối đe dọa từ AI.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section id="encyclopedia" class="py-20 bg-brandBg/80 backdrop-blur-sm">
            <div class="max-w-[1200px] mx-auto px-6">
                <div class="max-w-2xl mx-auto text-center mb-12">
                    <h2 class="display-heading text-2xl tracking-tight text-brandText mb-6">Cẩm Nang Tra Cứu</h2>
                    <p class="text-sm text-slate-600">Tổng hợp thông tin triệu chứng phổ biến để tham khảo nhanh trong quá trình theo dõi.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col cursor-pointer">
                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                            <img src="https://images.unsplash.com/photo-1590682680695-43b964a3ae17?q=80&w=400&auto=format&fit=crop" alt="Bệnh" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur text-[10px] font-semibold px-2 py-1 rounded text-red-500">Kỹ năng</div>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h4 class="display-heading text-sm text-brandText mb-1">"Đọc vị" sức khỏe cây qua lá</h4>
                            <p class="text-xs text-slate-500 font-light line-clamp-2 mb-4">Nhận biết sớm các triệu chứng khuyết lá, đốm vàng, xoăn ngọn để phát hiện sâu bệnh trước khi bùng phát trên diện rộng.</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col cursor-pointer">
                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                            <img src="https://images.pexels.com/photos/2255935/pexels-photo-2255935.jpeg?auto=compress&cs=tinysrgb&w=900" alt="Sâu lúa" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur text-[10px] font-semibold px-2 py-1 rounded text-orange-600">Sâu bệnh lúa</div>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h4 class="display-heading text-sm text-brandText mb-1">Sâu cuốn lá nhỏ hại lúa</h4>
                            <p class="text-xs text-slate-500 font-light line-clamp-2 mb-4">Cơn ác mộng của ruộng lúa giai đoạn đẻ nhánh. Hướng dẫn cách tìm ổ bướm và nhận diện vệt bạc trắng trên lá.</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col cursor-pointer">
                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                            <img src="https://images.unsplash.com/photo-1591857177580-dc82b9ac4e1e?q=80&w=400&auto=format&fit=crop" alt="Bọ nhảy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur text-[10px] font-semibold px-2 py-1 rounded text-orange-600">Sâu bệnh rau</div>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h4 class="display-heading text-sm text-brandText mb-1">Bọ nhảy bắp cải: Nhỏ có võ</h4>
                            <p class="text-xs text-slate-500 font-light line-clamp-2 mb-4">Kẻ thù số 1 của vùng chuyên canh rau họ thập tự. Cách xử lý đất và luân canh cắt đứt vòng đời bọ nhảy hiệu quả.</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col cursor-pointer">
                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                            <img src="https://images.pexels.com/photos/772807/pexels-photo-772807.jpeg?auto=compress&cs=tinysrgb&w=900" alt="Sinh học" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur text-[10px] font-semibold px-2 py-1 rounded text-emerald-600">Sinh học</div>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h4 class="display-heading text-sm text-brandText mb-1">Chế phẩm nấm diệt sâu an toàn</h4>
                            <p class="text-xs text-slate-500 font-light line-clamp-2 mb-4">Sử dụng nấm xanh (Metarhizium) và nấm trắng (Beauveria) thay thế thuốc hóa học, bảo vệ thiên địch và môi trường.</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col cursor-pointer">
                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                            <img src="https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?q=80&w=400&auto=format&fit=crop" alt="AI Scan" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur text-[10px] font-semibold px-2 py-1 rounded text-sky-600">Hướng dẫn App</div>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h4 class="display-heading text-sm text-brandText mb-1">Mẹo chụp ảnh để AI chuẩn 99%</h4>
                            <p class="text-xs text-slate-500 font-light line-clamp-2 mb-4">Hướng dẫn lấy nét, căn sáng và chọn góc chụp mẫu vật côn trùng giúp hệ thống GreenTech phân tích tốt nhất.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="webgis" class="w-full h-[80vh] relative overflow-hidden bg-slate-900 border-t border-slate-700">
            <div id="main-webgis-map" class="absolute inset-0 w-full h-full z-0"></div>

            <?php if ($isGuestUser) : ?>
                <div class="absolute inset-0 z-[1000] bg-slate-900/50 backdrop-blur-md flex flex-col items-center justify-center p-4">
                    <div class="w-full max-w-lg rounded-2xl border border-white/20 bg-slate-900/80 p-6 text-center shadow-2xl">
                        <div class="mx-auto mb-3 inline-flex h-12 w-12 items-center justify-center rounded-full bg-amber-400/20 text-amber-300 text-2xl">⚠️</div>
                        <h3 class="display-heading text-lg text-white">⚠️ Phát hiện khu vực có nguy cơ dịch hại!</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-200">Hệ thống AI đã quét được các vùng nguy cơ cao. Bản đồ dịch tễ thời gian thực là tính năng chuyên sâu. Vui lòng Đăng nhập hoặc Tạo tài khoản để xem tọa độ chi tiết.</p>
                        <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                            <a href="auth.php?tab=login" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Đăng nhập</a>
                            <a href="auth.php?tab=register" class="rounded-lg border border-white/25 bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20">Đăng ký</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="absolute top-6 left-6 w-72 bg-slate-900/85 backdrop-blur-md border border-white/20 rounded-2xl shadow-2xl p-5 z-10 flex flex-col gap-4 pointer-events-auto">
                <div class="flex items-center gap-2">
                    <iconify-icon icon="solar:map-bold-duotone" width="20" class="text-brand"></iconify-icon>
                    <h3 class="display-heading text-sm text-white tracking-wide"><?php echo $isHouseholdUser ? 'Bản Đồ Vị Trí Nhà' : 'Trung Tâm Chỉ Huy'; ?></h3>
                </div>
                <?php if ($isHouseholdUser) : ?>
                    <p class="text-xs text-slate-300">Hệ thống đang hiển thị vị trí hộ gia đình để theo dõi cảnh báo xung quanh khu vực sinh sống.</p>
                <?php else : ?>
                    <p class="text-xs text-slate-300">Bản đồ mô phỏng ổ dịch tại khu vực ngoại thành Hà Nội với sự kết hợp phân tích của Hệ Chuyên Gia AI.</p>
                <?php endif; ?>
                <a href="thong_ke.php" class="inline-flex justify-center bg-brand text-white text-xs font-medium px-3 py-2 rounded-lg hover:bg-green-700 transition-colors">Mở bảng thống kê chi tiết</a>
            </div>

            <div class="absolute top-6 right-6 w-64 bg-slate-900/85 backdrop-blur-md border border-slate-600/60 rounded-2xl p-4 z-10 space-y-3 pointer-events-auto">
                <?php if ($isGuestUser) : ?>
                    <div class="text-[11px] uppercase tracking-widest text-slate-300">🔒 Tổng quan mật độ</div>
                    <div class="rounded-xl border border-amber-300/30 bg-amber-400/10 p-3 text-xs text-amber-100">
                        Dữ liệu chi tiết đang bị khóa. Vui lòng đăng nhập để xem.
                    </div>
                <?php elseif ($isHouseholdUser) : ?>
                    <div class="text-[11px] uppercase tracking-widest text-slate-300">Thông tin hộ gia đình</div>
                    <div class="text-xs text-slate-100 leading-relaxed">
                        <?php echo htmlspecialchars($householdAddress !== '' ? $householdAddress : 'Bạn chưa cập nhật địa chỉ hoặc tọa độ nhà.', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php if ($householdCoords !== null) : ?>
                        <div class="text-[11px] text-slate-300">Tọa độ: <?php echo number_format((float)$householdCoords['lat'], 6); ?>, <?php echo number_format((float)$householdCoords['lng'], 6); ?></div>
                    <?php endif; ?>
                    <div class="rounded-lg border border-slate-600 bg-slate-800/60 p-2">
                        <div class="text-[11px] text-slate-300">Mật độ sâu hại hộ gia đình</div>
                        <div class="mt-1 text-sm font-semibold text-white"><?php echo (int)$householdPestTotal; ?> cá thể - <?php echo htmlspecialchars($householdPestLevel, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                <?php else : ?>
                    <div class="text-[11px] uppercase tracking-widest text-slate-300">Tổng quan Mật độ</div>
                    <?php foreach ($gis_data as $kv_name => $data) : ?>
                        <div class="flex items-center justify-between text-xs text-slate-200">
                            <span class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-sm <?php echo $region_style[$kv_name]['badge'] ?? 'bg-emerald-500'; ?>"></span>
                                <?php echo htmlspecialchars($kv_name, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <strong><?php echo (int)$data['total']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="cameraModal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/70"></div>
        <div class="relative h-full w-full flex items-center justify-center p-4">
            <div class="w-full max-w-xl bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="display-heading text-sm text-slate-800">Chụp ảnh trực tiếp</h3>
                    <button id="closeCameraBtn" type="button" class="text-slate-500 hover:text-slate-700">
                        <iconify-icon icon="solar:close-circle-linear" width="22"></iconify-icon>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    <div id="cameraFrame" class="w-full bg-slate-900 rounded-xl overflow-hidden relative" style="aspect-ratio: 4 / 3;">
                        <video id="cameraVideo" class="w-full h-full object-contain bg-black" autoplay playsinline></video>
                        <canvas id="cameraCanvas" class="hidden w-full h-full object-contain bg-black"></canvas>
                    </div>
                    <p class="text-xs text-slate-500">Trên điện thoại, vui lòng cho phép quyền camera để chụp ảnh mẫu vật.</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <button id="captureBtn" type="button" class="bg-brand text-white text-sm py-2 rounded-lg hover:bg-green-700">Chụp ảnh</button>
                        <button id="retakeBtn" type="button" class="bg-slate-100 text-slate-700 text-sm py-2 rounded-lg hover:bg-slate-200">Chụp lại</button>
                        <button id="switchCameraBtn" type="button" class="bg-sky-600 text-white text-sm py-2 rounded-lg hover:bg-sky-700">Đổi camera</button>
                        <button id="usePhotoBtn" type="button" class="bg-emerald-600 text-white text-sm py-2 rounded-lg hover:bg-emerald-700">Dùng ảnh này</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white/90 backdrop-blur-md border-t border-slate-200 py-8 text-center">
        <p class="text-[11px] text-slate-400 font-medium tracking-wide">© 2026 GreenTech. Hệ thống nhận diện côn trùng bằng AI.</p>
    </footer>

    <script>
        const imageInput = document.getElementById('imageInput');
        const fileLabel = document.getElementById('fileLabel');
        const dropzone = document.getElementById('dropzone');
        const previewImage = document.getElementById('previewImage');
        const dropzoneShade = document.getElementById('dropzoneShade');
        const capturedImageInput = document.getElementById('capturedImageInput');
        const scanForm = document.getElementById('scanForm');
        const scanOverlay = document.getElementById('scanOverlay');
        const btnAnalyze = document.getElementById('btnAnalyze');
        const openCameraBtn = document.getElementById('openCameraBtn');
        const cameraModal = document.getElementById('cameraModal');
        const closeCameraBtn = document.getElementById('closeCameraBtn');
        const captureBtn = document.getElementById('captureBtn');
        const retakeBtn = document.getElementById('retakeBtn');
        const switchCameraBtn = document.getElementById('switchCameraBtn');
        const usePhotoBtn = document.getElementById('usePhotoBtn');
        const cameraVideo = document.getElementById('cameraVideo');
        const cameraCanvas = document.getElementById('cameraCanvas');
        const cameraFrame = document.getElementById('cameraFrame');

        let cameraStream = null;
        let capturedDataUrl = '';
        let currentFacingMode = 'environment';
        let isSwitchingCamera = false;
        let previewObjectUrl = null;

        const getCameraFrameSize = () => {
            let width = cameraVideo.videoWidth || 0;
            let height = cameraVideo.videoHeight || 0;

            if ((!width || !height) && cameraStream) {
                const track = cameraStream.getVideoTracks()[0];
                const settings = track ? track.getSettings() : null;
                width = Number(settings && settings.width ? settings.width : 0);
                height = Number(settings && settings.height ? settings.height : 0);
            }

            return { width, height };
        };

        const updateCameraFrameRatio = () => {
            if (!cameraFrame) return;
            const size = getCameraFrameSize();
            if (size.width > 0 && size.height > 0) {
                cameraFrame.style.aspectRatio = `${size.width} / ${size.height}`;
            } else {
                cameraFrame.style.aspectRatio = '4 / 3';
            }
        };

        const setPreviewDataUrl = (dataUrl, label) => {
            previewImage.src = dataUrl;
            previewImage.classList.remove('hidden');
            dropzoneShade.classList.remove('hidden');
            fileLabel.textContent = label;
        };


        const setPreview = (file) => {
            if (!file || !file.type.startsWith('image/')) {
                if (previewObjectUrl) {
                    URL.revokeObjectURL(previewObjectUrl);
                    previewObjectUrl = null;
                }
                previewImage.src = '';
                previewImage.classList.add('hidden');
                dropzoneShade.classList.add('hidden');
                fileLabel.textContent = 'Kéo thả hoặc bấm để chọn ảnh';
                capturedImageInput.value = '';
                return;
            }

            if (previewObjectUrl) {
                URL.revokeObjectURL(previewObjectUrl);
            }
            previewObjectUrl = URL.createObjectURL(file);
            previewImage.src = previewObjectUrl;
            previewImage.classList.remove('hidden');
            dropzoneShade.classList.remove('hidden');
            fileLabel.textContent = file.name;
            capturedImageInput.value = '';
        };

        const stopCamera = () => {
            if (cameraStream) {
                cameraStream.getTracks().forEach((track) => track.stop());
                cameraStream = null;
            }
        };

        const startCamera = async (facingMode) => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Trình duyệt chưa hỗ trợ mở camera.');
                return false;
            }
            stopCamera();
            try {
                cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: facingMode } }, audio: false
                });
                cameraVideo.srcObject = cameraStream;
                cameraVideo.onloadedmetadata = () => {
                    updateCameraFrameRatio();
                };
                cameraVideo.classList.remove('hidden');
                cameraCanvas.classList.add('hidden');
                capturedDataUrl = '';
                currentFacingMode = facingMode;
                return true;
            } catch (error) {
                if (facingMode !== 'environment') return false;
                try {
                    cameraStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                    cameraVideo.srcObject = cameraStream;
                    cameraVideo.onloadedmetadata = () => {
                        updateCameraFrameRatio();
                    };
                    cameraVideo.classList.remove('hidden');
                    cameraCanvas.classList.add('hidden');
                    capturedDataUrl = '';
                    return true;
                } catch (fallbackError) { return false; }
            }
        };

        const openCamera = async () => {
            const started = await startCamera(currentFacingMode);
            if (!started) {
                alert('Không mở được camera. Hãy cấp quyền camera và truy cập bằng HTTPS.');
                return;
            }
            cameraModal.classList.remove('hidden');
        };

        const closeCamera = () => {
            cameraModal.classList.add('hidden');
            stopCamera();
        };

        imageInput.addEventListener('change', () => {
            const hasFile = imageInput.files && imageInput.files[0];
            setPreview(hasFile ? imageInput.files[0] : null);
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.add('border-brand', 'ring-2', 'ring-green-100');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.remove('ring-2', 'ring-green-100');
            });
        });

        dropzone.addEventListener('drop', (event) => {
            const files = event.dataTransfer.files;
            if (!files || !files.length) return;
            imageInput.files = files;
            setPreview(files[0]);
        });

        openCameraBtn.addEventListener('click', openCamera);
        closeCameraBtn.addEventListener('click', closeCamera);

        captureBtn.addEventListener('click', () => {
            if (!cameraStream) return;
            const size = getCameraFrameSize();
            const width = size.width;
            const height = size.height;
            if (!width || !height) {
                alert('Camera đang khởi tạo, vui lòng chờ 1-2 giây rồi chụp lại.');
                return;
            }
            cameraCanvas.width = width;
            cameraCanvas.height = height;
            const ctx = cameraCanvas.getContext('2d');
            ctx.drawImage(cameraVideo, 0, 0, width, height);
            capturedDataUrl = cameraCanvas.toDataURL('image/jpeg', 0.92);
            cameraVideo.classList.add('hidden');
            cameraCanvas.classList.remove('hidden');
        });

        retakeBtn.addEventListener('click', () => {
            capturedDataUrl = '';
            cameraCanvas.classList.add('hidden');
            cameraVideo.classList.remove('hidden');
        });

        switchCameraBtn.addEventListener('click', async () => {
            if (isSwitchingCamera) return;
            isSwitchingCamera = true;
            const nextFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
            const started = await startCamera(nextFacingMode);
            if (!started) alert('Thiết bị không hỗ trợ đổi camera hoặc camera còn lại không khả dụng.');
            isSwitchingCamera = false;
        });

        usePhotoBtn.addEventListener('click', () => {
            if (!capturedDataUrl) {
                alert('Bạn chưa chụp ảnh.'); return;
            }
            capturedImageInput.value = capturedDataUrl;
            imageInput.value = '';
            setPreviewDataUrl(capturedDataUrl, 'Ảnh chụp từ camera');
            closeCamera();
        });

        window.addEventListener('beforeunload', () => {
            stopCamera();
            if (previewObjectUrl) {
                URL.revokeObjectURL(previewObjectUrl);
                previewObjectUrl = null;
            }
        });

        scanForm.addEventListener('submit', () => {
            scanOverlay.classList.remove('hidden');
            scanOverlay.classList.add('flex');
            btnAnalyze.innerHTML = '<iconify-icon icon="solar:spinner-linear" width="20" class="animate-spin"></iconify-icon> Đang phân tích...';
            btnAnalyze.classList.add('opacity-70', 'cursor-not-allowed');
        });
    </script>

   <script>
        document.addEventListener("DOMContentLoaded", function() {
            // --- BỘ GIÁP CHỐNG LỖI JSON_ENCODE TỪ DATABASE ---
            const isHouseholdUser = <?php echo $isHouseholdUser ? 'true' : 'false'; ?>;
            const isGuestUser = <?php echo $isGuestUser ? 'true' : 'false'; ?>;
            const gisData = <?php $enc = json_encode($webgisJsData, JSON_INVALID_UTF8_SUBSTITUTE); echo $enc ? $enc : '{}'; ?>;
            const regionStyles = <?php $enc = json_encode($webgisJsStyles, JSON_INVALID_UTF8_SUBSTITUTE); echo $enc ? $enc : '{}'; ?>;
            const householdCoords = <?php $enc = json_encode($householdCoords); echo $enc ? $enc : 'null'; ?>;
            const householdAddress = <?php $enc = json_encode($householdAddress, JSON_INVALID_UTF8_SUBSTITUTE); echo $enc ? $enc : '""'; ?>;
            const householdPestTotal = <?php echo (int)$householdPestTotal; ?>;

            const regionCoordinates = {
                'Thôn 1 (Vùng Lúa nước)': [20.760, 105.775], 
                'Thôn 2 (Vùng Cải xanh)': [20.710, 105.760], 
                'Thôn 3 (Vùng Cà chua)': [20.715, 105.810]   
            };

            // ==========================================
            // 1. CHẾ ĐỘ KHÁCH VÃNG LAI
            // ==========================================
            if (isGuestUser) {
                const guestCenter = [20.734, 105.770];
                const map = L.map('main-webgis-map', {
                    center: guestCenter, zoom: 13, minZoom: 12, maxZoom: 18, zoomControl: false
                });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);

                const teaserPoints = [[20.7365, 105.7672], [20.7289, 105.7766], [20.7412, 105.7820]];
                teaserPoints.forEach((point) => {
                    const fakeIcon = L.divIcon({
                        className: 'teaser-risk-marker',
                        html: '<div class="relative flex items-center justify-center"><span class="absolute inline-flex h-7 w-7 rounded-full bg-red-500 opacity-70 animate-ping"></span><span class="relative inline-flex h-7 w-7 items-center justify-center rounded-full bg-red-600 text-white text-xs font-bold">?</span></div>',
                        iconSize: [28, 28], iconAnchor: [14, 14]
                    });
                    L.marker(point, { icon: fakeIcon }).addTo(map);
                });
                return;
            }

            // ==========================================
            // 2. CHẾ ĐỘ HỘ GIA ĐÌNH
            // ==========================================
            if (isHouseholdUser) {
                const defaultCenter = [21.028511, 105.804817];
                const map = L.map('main-webgis-map', {
                    center: defaultCenter, zoom: 13, minZoom: 5, maxZoom: 19, zoomControl: false
                });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
                
                setTimeout(() => { map.invalidateSize(); }, 200);

                const focusHousehold = (lat, lng, label) => {
                    const validLat = Number(lat);
                    const validLng = Number(lng);
                    if (isNaN(validLat) || isNaN(validLng)) return;

                    const latLng = [validLat, validLng];
                    map.setView(latLng, 16);
                    const activeLevel = householdPestTotal >= 30 ? 'BÁO ĐỘNG ĐỎ' : (householdPestTotal >= 10 ? 'CẢNH BÁO VÀNG' : 'AN TOÀN');
                    const activeColor = householdPestTotal >= 30 ? '#ef4444' : (householdPestTotal >= 10 ? '#f59e0b' : '#10b981');

                    L.marker(latLng).addTo(map).bindPopup(`
                        <div style="font-family: Inter, sans-serif; min-width: 180px;">
                            <strong style="font-size: 14px; color: #0f172a;">Vị trí hộ gia đình</strong>
                            <div style="margin-top: 6px; font-size: 12px; color: #475569;">${label || 'Đã đồng bộ từ hồ sơ tài khoản'}</div>
                            <div style="margin-top: 6px; font-size: 12px; color: #0369a1;">Tọa độ: ${validLat.toFixed(6)}, ${validLng.toFixed(6)}</div>
                            <div style="margin-top: 6px; font-size: 12px; color: #0f172a;">Mật độ: <strong>${householdPestTotal}</strong> - ${activeLevel}</div>
                        </div>
                    `).openPopup();

                    L.circle(latLng, {
                        radius: 420, color: activeColor, fillColor: activeColor, fillOpacity: 0.16, opacity: 0.9, weight: 3
                    }).addTo(map);
                };

                if (householdCoords && householdCoords.lat && householdCoords.lng) {
                    focusHousehold(householdCoords.lat, householdCoords.lng, householdAddress);
                } else if (householdAddress && householdAddress.trim() !== '') {
                    fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(householdAddress)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.length > 0) {
                                focusHousehold(data[0].lat, data[0].lon, householdAddress);
                            } else {
                                L.popup().setLatLng(defaultCenter).setContent('Không tìm thấy tọa độ từ địa chỉ đã lưu.').openOn(map);
                            }
                        }).catch(() => console.log('Không thể tải bản đồ Nominatim'));
                } else {
                    L.popup().setLatLng(defaultCenter).setContent('Bạn chưa cập nhật vị trí nhà trong hồ sơ.').openOn(map);
                }
                return; 
            }

            // ==========================================
            // 3. CHẾ ĐỘ NÔNG DÂN
            // ==========================================
            const unghoaBounds = L.latLngBounds(L.latLng(20.650, 105.680), L.latLng(20.820, 105.850));
            const map = L.map('main-webgis-map', {
                center: [20.734, 105.770], zoom: 13, minZoom: 12, maxZoom: 18,
                maxBounds: unghoaBounds, maxBoundsViscosity: 1.0, zoomControl: false
            });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
            L.rectangle(unghoaBounds, { color: "#1d4ed8", weight: 4, fill: false, dashArray: '12, 12', opacity: 0.9 }).addTo(map);

            for (const [kv_name, data] of Object.entries(gisData)) {
                if (regionCoordinates[kv_name]) {
                    const coords = regionCoordinates[kv_name];
                    const total = parseInt(data.total);
                    const style = regionStyles[kv_name] || { fill: '#10b981', level: 'AN TOÀN' };

                    let circle = L.circleMarker(coords, {
                        radius: total > 0 ? Math.min(Math.max(total * 1.5, 12), 35) : 8, 
                        fillColor: style.fill, color: style.fill, weight: 2, opacity: 0.8, fillOpacity: 0.6
                    }).addTo(map);
                    L.circleMarker(coords, { radius: 3, fillColor: '#ffffff', color: 'transparent', fillOpacity: 1 }).addTo(map);

                    circle.bindPopup(`
                        <div style="font-family: Inter, sans-serif; text-align: center; min-width: 130px;">
                            <strong style="color: #1e293b; font-size: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; display: block; margin-bottom: 10px;">${kv_name}</strong>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <span style="color: #64748b; font-size: 12px;">Mức độ:</span>
                                <span style="color: ${style.fill}; font-size: 12px; font-weight: bold;">${style.level}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #64748b; font-size: 12px;">Phát hiện:</span>
                                <strong style="color: #0f172a; font-size: 18px;">${total}</strong>
                            </div>
                        </div>
                    `);
                }
            }
        });
    </script>
</body>
</html>
