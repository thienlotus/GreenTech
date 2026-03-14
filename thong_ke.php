<?php
require 'db_connect.php';
require_once 'pest_translate.php';

$region_order = [
    'Thôn 1 (Vùng Lúa nước)',
    'Thôn 2 (Vùng Cải xanh)',
    'Thôn 3 (Vùng Cà chua)'
];

$region_data = [];
foreach ($region_order as $region_name) {
    $region_data[$region_name] = [
        'total' => 0,
        'details' => []
    ];
}

$sql = "SELECT ls.khu_vuc, ct.ten_loai_sau, SUM(ct.so_luong) as tong_so
        FROM lich_su_quet ls
        JOIN chi_tiet_dich_hai ct ON ls.id = ct.lich_su_id
        WHERE ls.khu_vuc IS NOT NULL AND ls.khu_vuc != ''
        GROUP BY ls.khu_vuc, ct.ten_loai_sau";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $khu_vuc = $row['khu_vuc'];
        $ten_sau = $row['ten_loai_sau'];
        $so_luong = (int)$row['tong_so'];

        if (!isset($region_data[$khu_vuc])) {
            $region_data[$khu_vuc] = ['total' => 0, 'details' => []];
        }

        $region_data[$khu_vuc]['total'] += $so_luong;
        $region_data[$khu_vuc]['details'][$ten_sau] = $so_luong;
    }
}

$region_labels = [];
$region_totals = [];
$region_colors = [];
$canh_bao_data = [];

foreach ($region_data as $region_name => $data) {
    $total = (int)$data['total'];
    $region_labels[] = $region_name;
    $region_totals[] = $total;

    if ($total >= 25) {
        $region_colors[] = '#ef4444'; // Đỏ (Red)
        $canh_bao_data[$region_name] = 'Báo động đỏ';
    } elseif ($total >= 10) {
        $region_colors[] = '#f59e0b'; // Cam (Amber)
        $canh_bao_data[$region_name] = 'Cảnh báo vàng';
    } else {
        $region_colors[] = '#10b981'; // Xanh lá (Emerald)
        $canh_bao_data[$region_name] = 'An toàn';
    }
}

$tong_ca_the = array_sum($region_totals);
$khu_canh_bao_max = '-';
$gia_tri_max = 0;
if (!empty($region_totals)) {
    $max_index = array_keys($region_totals, max($region_totals))[0];
    $khu_canh_bao_max = $region_labels[$max_index] ?? '-';
    $gia_tri_max = $region_totals[$max_index] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenTech | Thống kê theo vùng</title>
    <link rel="icon" type="image/svg+xml" href="assets/greentech-favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-image: url('https://png.pngtree.com/thumb_back/fh260/background/20240911/pngtree-open-book-with-green-plant-sprouting-in-sunlight-bokeh-background-image_16143810.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: scroll;
            background-repeat: no-repeat;
        }
        .font-semibold { font-weight: 500 !important; }
        
        /* Chỉnh lại giao diện Popup Leaflet cho bản đồ Thống kê
        .leaflet-popup-content-wrapper { background: #1e293b; color: white; border-radius: 12px; }
        .leaflet-popup-tip { background: #1e293b; } */
    </style>
</head>
<body class="bg-brandBg/90 text-brandText antialiased min-h-screen">
    <nav class="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur-xl border-b border-slate-200/50">
        <div class="max-w-[1400px] mx-auto px-6 h-16 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <iconify-icon icon="solar:leaf-bold-duotone" width="24" class="text-brand"></iconify-icon>
                <span class="font-semibold tracking-tighter text-lg text-brand">GREENTECH</span>
            </div>
            <div class="hidden md:flex space-x-8">
                <a href="index.php#home" class="text-sm text-slate-500 hover:text-brand transition-colors">Trang chủ</a>
                <a href="index.php#scanner" class="text-sm text-slate-500 hover:text-brand transition-colors">Quét AI</a>
                <a href="index.php#webgis" class="text-sm text-slate-500 hover:text-brand transition-colors">Bản đồ</a>
                <a href="thong_ke.php" class="text-sm text-brand font-semibold">Thống kê vùng</a>
            </div>
            <a href="index.php#scanner" class="inline-flex items-center gap-1 bg-brand text-white text-xs px-3 py-2 rounded-lg hover:bg-green-700 transition-colors">
                <iconify-icon icon="solar:camera-linear" width="14"></iconify-icon> Quét ảnh mới
            </a>
        </div>
    </nav>

    <main class="pt-24 pb-12 px-6">
        <section class="max-w-[1240px] mx-auto bg-white/90 backdrop-blur-md rounded-3xl border border-white/60 shadow-xl overflow-hidden">
            <div class="p-8 md:p-10 border-b border-slate-200/70 bg-gradient-to-r from-green-50 to-slate-50">
                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-brandText">Thống Kê Mật Độ & Bản Đồ Phân Bố</h1>
                <p class="text-sm text-slate-600 mt-2">Dữ liệu được cập nhật theo thời gian thực từ hệ thống nhận diện AI.</p>
            </div>

            <div class="p-8 md:p-10 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-[11px] uppercase tracking-wider text-slate-500">Tổng cá thể ghi nhận</div>
                    <div class="text-3xl font-semibold text-slate-900 mt-2"><?php echo (int)$tong_ca_the; ?></div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-[11px] uppercase tracking-wider text-slate-500">Số vùng giám sát</div>
                    <div class="text-3xl font-semibold text-slate-900 mt-2"><?php echo count($region_data); ?></div>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4">
                    <div class="text-[11px] uppercase tracking-wider text-amber-700">Vùng rủi ro cao nhất</div>
                    <div class="text-base font-semibold text-amber-800 mt-2"><?php echo htmlspecialchars($khu_canh_bao_max, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-sm text-amber-700 mt-1"><?php echo (int)$gia_tri_max; ?> cá thể</div>
                </div>
            </div>

            <div class="px-8 md:px-10 pb-10 grid grid-cols-1 xl:grid-cols-[58%_42%] gap-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 md:p-6 shadow-sm flex flex-col gap-6">
                    
                    <div>
                        <h2 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
                            <iconify-icon icon="solar:map-point-bold-duotone" class="text-brand text-lg"></iconify-icon> 
                            Bản đồ Mật độ (Khu vực Hà Nội)
                        </h2>
                        <div id="webgis-map" class="w-full h-[350px] rounded-xl border border-slate-200 z-0"></div>
                    </div>

                    <hr class="border-slate-100">

                    <div>
                        <h2 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
                            <iconify-icon icon="solar:chart-square-bold-duotone" class="text-brand text-lg"></iconify-icon> 
                            So sánh biểu đồ cột
                        </h2>
                        <div class="w-full h-[250px]">
                            <canvas id="regionChart"></canvas>
                        </div>
                    </div>

                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 md:p-6 shadow-sm">
                    <h2 class="text-sm font-semibold text-slate-700 mb-4">Cảnh báo nhanh theo vùng</h2>
                    <div class="space-y-3">
                        <?php foreach ($region_data as $region_name => $data) : ?>
                            <?php
                                $total = (int)$data['total'];
                                $badge_class = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                                if ($total >= 25) {
                                    $badge_class = 'bg-red-100 text-red-700 border-red-200';
                                } elseif ($total >= 10) {
                                    $badge_class = 'bg-amber-100 text-amber-700 border-amber-200';
                                }
                            ?>
                            <div class="rounded-xl border border-slate-200 p-3 bg-slate-50">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($region_name, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <span class="text-[11px] border px-2 py-1 rounded-full <?php echo $badge_class; ?>"><?php echo $canh_bao_data[$region_name] ?? 'An toàn'; ?></span>
                                </div>
                                <div class="text-xs text-slate-600 mt-1">Tổng số ghi nhận: <strong><?php echo $total; ?></strong></div>

                                <?php if (!empty($data['details'])) : ?>
                                    <ul class="mt-2 space-y-1 text-xs text-slate-700">
                                        <?php foreach ($data['details'] as $ten_sau => $sl) : ?>
                                            <li class="flex items-center justify-between border-b border-slate-200/70 last:border-0 pb-1 last:pb-0">
                                                <span><?php echo htmlspecialchars(translate_pest_name_vi($ten_sau), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <strong><?php echo (int)$sl; ?></strong>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <p class="text-xs text-slate-500 mt-2">Chưa có dữ liệu chi tiết.</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="text-center pb-8">
        <p class="text-[11px] text-slate-500 font-medium tracking-wide">© 2026 GreenTech. Hệ thống nhận diện côn trùng bằng AI.</p>
    </footer>

    <script>
        const regionLabels = <?php echo json_encode(array_values($region_labels)); ?>;
        const regionTotals = <?php echo json_encode(array_values($region_totals)); ?>;
        const regionColors = <?php echo json_encode(array_values($region_colors)); ?>;

        const ctx = document.getElementById('regionChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: regionLabels,
                datasets: [{
                    label: 'Tổng số cá thể phát hiện',
                    data: regionTotals,
                    backgroundColor: regionColors,
                    borderColor: '#ffffff',
                    borderWidth: 1.5,
                    borderRadius: 8,
                    maxBarThickness: 52
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => ' ' + context.parsed.y + ' cá thể'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#334155', font: { size: 11 } },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#64748b', precision: 0 },
                        grid: { color: 'rgba(148,163,184,0.18)' }
                    }
                }
            }
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const regionCoordinates = {
                'Thôn 1 (Vùng Lúa nước)': [20.760, 105.775],
                'Thôn 2 (Vùng Cải xanh)': [20.710, 105.760],
                'Thôn 3 (Vùng Cà chua)': [20.715, 105.810]
            };

            const unghoaBounds = L.latLngBounds(
                L.latLng(20.650, 105.680),
                L.latLng(20.820, 105.850)
            );

            const map = L.map('webgis-map', {
                center: [20.734, 105.770],
                zoom: 12,                    
                minZoom: 12,
                maxZoom: 18,
                maxBounds: unghoaBounds,
                maxBoundsViscosity: 1.0,
                zoomControl: false
            });

            // Nền sáng OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);
            
            // Viền đậm
            L.rectangle(unghoaBounds, {color: "#1d4ed8", weight: 4, fill: false, dashArray: '12, 12', opacity: 0.9}).addTo(map);

            for (let i = 0; i < regionLabels.length; i++) {
                let rName = regionLabels[i];
                let rTotal = regionTotals[i];
                let rColor = regionColors[i];

                if (regionCoordinates[rName]) {
                    let coords = regionCoordinates[rName];

                    let circle = L.circleMarker(coords, {
                        radius: rTotal > 0 ? Math.min(Math.max(rTotal * 1.5, 12), 35) : 8,
                        fillColor: rColor,
                        color: rColor,
                        weight: 2,
                        opacity: 0.8,
                        fillOpacity: 0.6
                    }).addTo(map);

                    L.circleMarker(coords, {radius: 3, fillColor: '#ffffff', color: 'transparent', fillOpacity: 1}).addTo(map);

                    let levelText = rTotal >= 25 ? 'BÁO ĐỘNG ĐỎ' : (rTotal >= 10 ? 'CẢNH BÁO' : 'AN TOÀN');

                    // Popup chữ tối
                    circle.bindPopup(`
                        <div style="font-family: Inter, sans-serif; text-align: center; min-width: 130px;">
                            <strong style="color: #1e293b; font-size: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; display: block; margin-bottom: 10px;">${rName}</strong>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                <span style="color: #64748b; font-size: 12px;">Mức độ:</span>
                                <span style="color: ${rColor}; font-size: 12px; font-weight: bold;">${levelText}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #64748b; font-size: 12px;">Phát hiện:</span>
                                <strong style="color: #0f172a; font-size: 18px;">${rTotal}</strong>
                            </div>
                        </div>
                    `);
                }
            }
        });
    </script>
</body>
</html>