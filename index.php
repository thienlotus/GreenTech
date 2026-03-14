<?php
require 'db_connect.php';
require_once 'pest_translate.php';

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
        $api_url = 'http://127.0.0.1:5000/detect';
        $cfile = new CURLFile($file_tmp, $file_type, $file_name);
        $post_data = array('image' => $cfile);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);

            if (isset($data['success']) && $data['success'] === true) {
                $original_image = $data['original_image'] ?? '';
                $result_image = $data['result_image'] ?? '';
                $total_insects = (int)($data['total_insects'] ?? 0);
                $pest_counts = is_array($data['pest_counts'] ?? null) ? $data['pest_counts'] : array();

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
                    'khu_vuc' => $khu_vuc
                );
            } else {
                $error_message = 'Lỗi từ AI: ' . htmlspecialchars($data['error'] ?? 'Không rõ nguyên nhân', ENT_QUOTES, 'UTF-8');
            }
        } else {
            $error_message = 'Không thể kết nối đến AI. Hãy kiểm tra lại dịch vụ Python.';
        }

        if ($is_temp_capture && is_file($file_tmp)) {
            unlink($file_tmp);
        }
    } else {
        $error_message = 'Vui lòng chọn ảnh hợp lệ hoặc chụp ảnh bằng camera trước khi phân tích.';
    }
}

// -------------------------------------------------------------
// 2. LẤY DỮ LIỆU BẢN ĐỒ WEBGIS (BÓC TÁCH CHI TIẾT)
// -------------------------------------------------------------
$gis_data = [];
$sql_gis = "SELECT ls.khu_vuc, ct.ten_loai_sau, SUM(ct.so_luong) as tong_so_luong 
            FROM lich_su_quet ls 
            JOIN chi_tiet_dich_hai ct ON ls.id = ct.lich_su_id 
            WHERE ls.khu_vuc IS NOT NULL AND ls.khu_vuc != '' 
            GROUP BY ls.khu_vuc, ct.ten_loai_sau";
$result_gis = $conn->query($sql_gis);

if ($result_gis && $result_gis->num_rows > 0) {
    while ($row = $result_gis->fetch_assoc()) {
        $kv = $row['khu_vuc'];
        $loai_sau = $row['ten_loai_sau'];
        $sl = (int)$row['tong_so_luong'];

        if (!isset($gis_data[$kv])) {
            $gis_data[$kv] = ['total' => 0, 'details' => []];
        }
        $gis_data[$kv]['total'] += $sl;
        $gis_data[$kv]['details'][$loai_sau] = $sl;
    }
}

// Chuẩn bị dữ liệu mảng màu sắc cảnh báo cho bản đồ
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
?>

<!DOCTYPE html>
<html lang="vi" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
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

        /* Fix Leaflet popup style in dark mode
        .leaflet-popup-content-wrapper { background: #1e293b; color: white; border-radius: 12px; }
        .leaflet-popup-tip { background: #1e293b; } */
    </style>
</head>
<body class="bg-brandBg/90 text-brandText antialiased flex flex-col min-h-screen">
    <nav class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur-xl border-b border-slate-200/50">
        <div class="max-w-[1400px] mx-auto px-6 h-16 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <iconify-icon icon="solar:leaf-bold-duotone" width="24" class="text-brand"></iconify-icon>
                <span class="font-semibold tracking-tighter text-lg text-brand">GREENTECH</span>
            </div>
            <div class="hidden md:flex space-x-8">
                <a href="#home" class="text-sm text-slate-500 hover:text-brand transition-colors">Trang chủ</a>
                <a href="#scanner" class="text-sm text-slate-500 hover:text-brand transition-colors">Quét AI</a>
                <a href="#encyclopedia" class="text-sm text-slate-500 hover:text-brand transition-colors">Cẩm nang</a>
                <a href="#webgis" class="text-sm text-slate-500 hover:text-brand transition-colors">Bản đồ</a>
                <a href="thong_ke.php" class="text-sm text-slate-500 hover:text-brand transition-colors">Thống kê</a>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right hidden md:block">
                    <div class="text-xs font-medium text-slate-900">Nông dân 4.0</div>
                    <div class="text-[10px] text-slate-500">Cấp bậc: Chuyên gia</div>
                </div>
                <div class="w-9 h-9 rounded-full bg-slate-200 border-2 border-white shadow-sm overflow-hidden">
                    <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Felix" alt="avatar" class="w-full h-full object-cover">
                </div>
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
                <h1 class="text-4xl md:text-5xl font-semibold tracking-tight text-brandText leading-tight mb-6">
                    Bảo Vệ Mùa Màng Bằng <br> Trí Tuệ Nhân Tạo
                </h1>
                <p class="text-sm md:text-base text-slate-700 mb-10 font-medium leading-relaxed">
                    Hệ thống chẩn đoán hình ảnh tức thì và giám sát côn trùng bằng dữ liệu số. Quyết định nhanh hơn, năng suất tốt hơn.
                </p>
                <a href="#scanner" class="inline-flex bg-brand hover:bg-green-700 text-white text-sm font-medium py-3 px-8 rounded-full shadow-[0_8px_20px_rgba(46,125,50,0.25)] transition-all items-center gap-2 mx-auto">
                    <iconify-icon icon="solar:scanner-linear" width="18"></iconify-icon> Bắt đầu Quét AI
                </a>
            </div>
        </section>

        <section id="scanner" class="py-20 bg-white/90 backdrop-blur-md border-y border-slate-200/50">
            <div class="max-w-[1200px] mx-auto px-6">
                <div class="mb-12">
                    <h2 class="text-2xl font-semibold tracking-tight text-brandText">Trạm Quét AI & Chẩn Đoán Bệnh</h2>
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
                                    <iconify-icon icon="solar:cpu-linear" width="24" class="animate-pulse"></iconify-icon>
                                    Đang phân tích...
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
                                <select name="khu_vuc" required class="w-full appearance-none bg-white border border-slate-200 rounded-xl py-2.5 pl-4 pr-10 text-sm text-slate-600 font-medium focus:outline-none focus:border-brand shadow-sm cursor-pointer">
                                    <option value="" disabled selected>📍 Chọn khu vực và loại cây trồng...</option>
                                    <option value="Thôn 1 (Vùng Lúa nước)">Thôn 1 (Vùng Lúa nước)</option>
                                    <option value="Thôn 2 (Vùng Cải xanh)">Thôn 2 (Vùng Cải xanh)</option>
                                    <option value="Thôn 3 (Vùng Cà chua)">Thôn 3 (Vùng Cà chua)</option>
                                </select>
                                <iconify-icon icon="solar:alt-arrow-down-linear" class="absolute right-3 top-3 text-slate-400 pointer-events-none" width="16"></iconify-icon>
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
                                <h3 class="text-sm font-medium text-slate-900">Đang chờ dữ liệu...</h3>
                                <p class="text-xs text-slate-500 mt-1 max-w-xs">Tải lên hình ảnh có dấu hiệu bất thường để AI phát hiện côn trùng và chẩn đoán bệnh.</p>
                            </div>
                        <?php else : ?>
                            <div class="w-full h-full flex flex-col gap-4 overflow-y-auto pr-2">
                                <div class="bg-green-50 p-4 rounded-xl border border-green-100 shadow-sm md:col-span-2 flex items-center justify-between">
                                    <div>
                                        <div class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-1">Kết quả chẩn đoán</div>
                                        <h4 class="text-lg font-semibold tracking-tight text-green-700">Đã phát hiện <?php echo (int)$result_data['total_insects']; ?> vùng có côn trùng</h4>
                                        <p class="text-xs text-slate-600 mt-1">Khu vực: <strong><?php echo htmlspecialchars($result_data['khu_vuc'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                                    </div>
                                </div>

                                <div class="w-full h-56 bg-slate-900 rounded-xl overflow-hidden relative shadow-inner shrink-0">
                                    <img src="results/<?php echo htmlspecialchars($result_data['result_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Kết quả AI" class="w-full h-full object-contain bg-black/70">
                                </div>

                                <?php if (!empty($result_data['pest_counts'])) : ?>
                                    <div class="space-y-3 mt-2">
                                        <h3 class="text-sm font-bold text-slate-800 border-b border-slate-200 pb-2 uppercase">Chi tiết Mật độ & Bệnh học</h3>
                                        
                                        <?php foreach ($result_data['pest_counts'] as $ten_sau => $so_luong) : 
                                            // Tra cứu trong Hệ chuyên gia
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
                                                
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pl-3">
                                                    <div class="bg-orange-50 p-3 rounded-lg border border-orange-100">
                                                        <div class="text-[10px] font-bold text-orange-600 uppercase mb-1 flex items-center gap-1">
                                                            <iconify-icon icon="solar:danger-triangle-bold"></iconify-icon> Nguy cơ bệnh hại
                                                        </div>
                                                        <p class="text-xs text-slate-700 leading-relaxed"><?php echo $chuyen_gia['benh']; ?></p>
                                                    </div>
                                                    <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-100">
                                                        <div class="text-[10px] font-bold text-emerald-600 uppercase mb-1 flex items-center gap-1">
                                                            <iconify-icon icon="solar:shield-check-bold"></iconify-icon> Khuyến nghị xử lý
                                                        </div>
                                                        <p class="text-xs text-slate-700 leading-relaxed"><?php echo $chuyen_gia['chua']; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
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
                    <h2 class="text-2xl font-semibold tracking-tight text-brandText mb-6">Cẩm Nang Tra Cứu</h2>
                    <p class="text-sm text-slate-600">Tổng hợp thông tin triệu chứng phổ biến để tham khảo nhanh trong quá trình theo dõi.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-shadow group flex flex-col">
                        <div class="h-40 bg-slate-100 overflow-hidden relative">
                            <img src="https://images.unsplash.com/photo-1590682680695-43b964a3ae17?q=80&w=400&auto=format&fit=crop" alt="Bệnh" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur text-[10px] font-semibold px-2 py-1 rounded text-red-500">Sâu bệnh</div>
                        </div>
                        <div class="p-5 flex-1 flex flex-col">
                            <h4 class="text-sm font-semibold text-brandText mb-1">Dấu hiệu lá bị hại</h4>
                            <p class="text-xs text-slate-500 font-light line-clamp-2 mb-4">Theo dõi các vết đốm bất thường, màu sắc thay đổi và tốc độ lan rộng để xử lý kịp thời.</p>
                        </div>
                    </div>
                    </div>
            </div>
        </section>

        <section id="webgis" class="w-full h-[80vh] relative overflow-hidden bg-slate-900 border-t border-slate-700">
            <div id="main-webgis-map" class="absolute inset-0 w-full h-full z-0"></div>

            <div class="absolute top-6 left-6 w-72 bg-slate-900/85 backdrop-blur-md border border-white/20 rounded-2xl shadow-2xl p-5 z-10 flex flex-col gap-4 pointer-events-auto">
                <div class="flex items-center gap-2">
                    <iconify-icon icon="solar:map-bold-duotone" width="20" class="text-brand"></iconify-icon>
                    <h3 class="text-sm font-semibold text-white tracking-wide">Trung Tâm Chỉ Huy</h3>
                </div>
                <p class="text-xs text-slate-300">Bản đồ mô phỏng ổ dịch tại khu vực ngoại thành Hà Nội với sự kết hợp phân tích của Hệ Chuyên Gia AI.</p>
                <a href="thong_ke.php" class="inline-flex justify-center bg-brand text-white text-xs font-medium px-3 py-2 rounded-lg hover:bg-green-700 transition-colors">Mở bảng thống kê chi tiết</a>
            </div>

            <div class="absolute top-6 right-6 w-64 bg-slate-900/85 backdrop-blur-md border border-slate-600/60 rounded-2xl p-4 z-10 space-y-3 pointer-events-auto">
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
            </div>
        </section>
    </main>

    <div id="cameraModal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/70"></div>
        <div class="relative h-full w-full flex items-center justify-center p-4">
            <div class="w-full max-w-xl bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-800">Chụp ảnh trực tiếp</h3>
                    <button id="closeCameraBtn" type="button" class="text-slate-500 hover:text-slate-700">
                        <iconify-icon icon="solar:close-circle-linear" width="22"></iconify-icon>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    <div class="w-full aspect-[4/3] bg-slate-900 rounded-xl overflow-hidden relative">
                        <video id="cameraVideo" class="w-full h-full object-cover" autoplay playsinline></video>
                        <canvas id="cameraCanvas" class="hidden w-full h-full"></canvas>
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

        let cameraStream = null;
        let capturedDataUrl = '';
        let currentFacingMode = 'environment';
        let isSwitchingCamera = false;
        let previewObjectUrl = null;

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
                    video: { facingMode: { ideal: facingMode } },
                    audio: false
                });

                cameraVideo.srcObject = cameraStream;
                cameraVideo.classList.remove('hidden');
                cameraCanvas.classList.add('hidden');
                capturedDataUrl = '';
                currentFacingMode = facingMode;
                return true;
            } catch (error) {
                if (facingMode !== 'environment') {
                    return false;
                }
                try {
                    cameraStream = await navigator.mediaDevices.getUserMedia({
                        video: true,
                        audio: false
                    });
                    cameraVideo.srcObject = cameraStream;
                    cameraVideo.classList.remove('hidden');
                    cameraCanvas.classList.add('hidden');
                    capturedDataUrl = '';
                    return true;
                } catch (fallbackError) {
                    return false;
                }
            }
        };

        const openCamera = async () => {
            const started = await startCamera(currentFacingMode);
            if (!started) {
                alert('Không mở được camera. Hãy cấp quyền camera và dùng HTTPS hoặc truy cập localhost.');
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
            if (!files || !files.length) {
                return;
            }
            imageInput.files = files;
            setPreview(files[0]);
        });

        openCameraBtn.addEventListener('click', openCamera);
        closeCameraBtn.addEventListener('click', closeCamera);

        captureBtn.addEventListener('click', () => {
            if (!cameraStream) { return; }
            const width = cameraVideo.videoWidth || 1280;
            const height = cameraVideo.videoHeight || 720;
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
            if (isSwitchingCamera) { return; }
            isSwitchingCamera = true;
            const nextFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
            const started = await startCamera(nextFacingMode);
            if (!started) {
                alert('Thiết bị không hỗ trợ đổi camera hoặc camera còn lại không khả dụng.');
            }
            isSwitchingCamera = false;
        });

        usePhotoBtn.addEventListener('click', () => {
            if (!capturedDataUrl) {
                alert('Bạn chưa chụp ảnh.');
                return;
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

        scanForm.addEventListener('submit', (event) => {
            scanOverlay.classList.remove('hidden');
            scanOverlay.classList.add('flex');
            btnAnalyze.innerHTML = '<iconify-icon icon="solar:spinner-linear" width="20" class="animate-spin"></iconify-icon> Đang phân tích...';
            btnAnalyze.classList.add('opacity-70', 'cursor-not-allowed');
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const gisData = <?php echo json_encode($gis_data); ?>;
            const regionStyles = <?php echo json_encode($region_style); ?>;

            const regionCoordinates = {
                'Thôn 1 (Vùng Lúa nước)': [20.760, 105.775], 
                'Thôn 2 (Vùng Cải xanh)': [20.710, 105.760], 
                'Thôn 3 (Vùng Cà chua)': [20.715, 105.810]   
            };

            const unghoaBounds = L.latLngBounds(
                L.latLng(20.650, 105.680), 
                L.latLng(20.820, 105.850)  
            );

            const map = L.map('main-webgis-map', {
                center: [20.734, 105.770], 
                zoom: 13,                    
                minZoom: 12,                 
                maxZoom: 18,
                maxBounds: unghoaBounds,     
                maxBoundsViscosity: 1.0,     
                zoomControl: false
            });

            // THAY ĐỔI 1: Nền bản đồ Sáng (OpenStreetMap) quen thuộc với nông dân
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);

            // THAY ĐỔI 2: Viền ranh giới Xanh đậm, Nét đứt to, Dày dặn
            L.rectangle(unghoaBounds, {
                color: "#1d4ed8", // Màu xanh dương đậm (Blue-700)
                weight: 4,        // Dày hơn (4px)
                fill: false, 
                dashArray: '12, 12', // Nét đứt to và thưa hơn
                opacity: 0.9      // Đậm nét
            }).addTo(map);

            for (const [kv_name, data] of Object.entries(gisData)) {
                if (regionCoordinates[kv_name]) {
                    const coords = regionCoordinates[kv_name];
                    const total = parseInt(data.total);
                    const style = regionStyles[kv_name] || { fill: '#10b981', level: 'AN TOÀN' };

                    let circle = L.circleMarker(coords, {
                        radius: total > 0 ? Math.min(Math.max(total * 1.5, 12), 35) : 8, 
                        fillColor: style.fill,
                        color: style.fill,
                        weight: 2,
                        opacity: 0.8,
                        fillOpacity: 0.6 // Tăng độ đặc của màu lên chút để nổi trên nền sáng
                    }).addTo(map);

                    L.circleMarker(coords, {
                        radius: 3, fillColor: '#ffffff', color: 'transparent', fillOpacity: 1
                    }).addTo(map);

                    // THAY ĐỔI 3: Popup nền sáng, chữ tối màu dễ đọc
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