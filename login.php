<?php
session_start();
require 'db_connect.php';

$error = '';
$success = '';
$action = ''; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // XỬ LÝ ĐĂNG KÝ
    if ($action === 'register') {
        $ho_ten = $_POST['ho_ten'] ?? '';
        $so_dien_thoai = $_POST['so_dien_thoai'] ?? '';
        $khu_vuc = $_POST['khu_vuc'] ?? '';
        $mat_khau = $_POST['mat_khau'] ?? '';
        $mat_khau_cf = $_POST['mat_khau_cf'] ?? '';
        $role = $_POST['role'] ?? 'farmer';

        if ($mat_khau !== $mat_khau_cf) {
            $error = "Mật khẩu nhập lại không khớp!";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE so_dien_thoai = ?");
            $stmt->bind_param("s", $so_dien_thoai);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Số điện thoại này đã được đăng ký!";
            } else {
                $hashed_password = password_hash($mat_khau, PASSWORD_DEFAULT);
                $insert = $conn->prepare("INSERT INTO users (ho_ten, so_dien_thoai, mat_khau, khu_vuc, role) VALUES (?, ?, ?, ?, ?)");
                $insert->bind_param("sssss", $ho_ten, $so_dien_thoai, $hashed_password, $khu_vuc, $role);
                
                if ($insert->execute()) {
                    $success = "Đăng ký thành công! Vui lòng đăng nhập.";
                } else {
                    $error = "Có lỗi xảy ra, vui lòng thử lại.";
                }
            }
        }
    }

    // XỬ LÝ ĐĂNG NHẬP
    if ($action === 'login') {
        $so_dien_thoai = $_POST['so_dien_thoai'] ?? '';
        $mat_khau = $_POST['mat_khau'] ?? '';

        $stmt = $conn->prepare("SELECT id, ho_ten, role, mat_khau FROM users WHERE so_dien_thoai = ?");
        $stmt->bind_param("s", $so_dien_thoai);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($mat_khau, $row['mat_khau'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['ho_ten'] = $row['ho_ten'];
                $_SESSION['role'] = $row['role']; 
                
                header("Location: index.php");
                exit();
            } else {
                $error = "Mật khẩu không chính xác!";
            }
        } else {
            $error = "Không tìm thấy tài khoản với số điện thoại này!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenTech - Cùng AI bảo vệ mùa màng</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.iconify.design/iconify-icon/1.0.7/iconify-icon.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        #view-login, #view-register {
            display: none; opacity: 0; transform: translateY(10px); animation: fadeUp 0.4s ease-out forwards;
        }
        #tab-login:checked ~ #form-container #view-login { display: block; }
        #tab-register:checked ~ #form-container #view-register { display: block; }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="min-h-screen bg-white text-slate-600 antialiased selection:bg-emerald-100 selection:text-emerald-900">

    <div class="flex min-h-screen w-full">
        
        <div class="relative hidden lg:flex w-[55%] bg-slate-900 items-end p-12 overflow-hidden">
            
            <img src="donglua.jpg" alt="Rice field" class="absolute inset-0 object-cover w-full h-full">
            
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
            
            <div class="relative z-10 w-full max-w-xl">
                <div class="mb-6 flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-emerald-500 text-white shadow-lg">
                        <iconify-icon icon="solar:leaf-linear" class="text-lg" stroke-width="1.5"></iconify-icon>
                    </div>
                    <span class="text-xl font-bold tracking-tighter text-white drop-shadow-md">GREENTECH</span>
                </div>
                <h2 class="text-4xl font-medium tracking-tight text-white mb-4 leading-tight drop-shadow-md">Cùng AI bảo vệ<br>mùa màng của bạn.</h2>
                <p class="text-base text-slate-100 max-w-md leading-relaxed drop-shadow">Hệ thống chẩn đoán và theo dõi sức khỏe cây trồng tự động, giúp tối ưu năng suất và bảo vệ môi trường nông nghiệp.</p>
            </div>
        </div>

        <div class="flex-1 flex flex-col justify-center px-6 py-12 lg:px-16 relative hide-scrollbar overflow-y-auto">
            
            <?php if(!empty($error)): ?>
                <div class="absolute top-4 right-4 left-4 lg:left-auto bg-red-100 text-red-700 p-3 rounded-lg text-sm border border-red-200"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if(!empty($success)): ?>
                <div class="absolute top-4 right-4 left-4 lg:left-auto bg-emerald-100 text-emerald-700 p-3 rounded-lg text-sm border border-emerald-200"><?php echo $success; ?></div>
            <?php endif; ?>

            <input type="radio" name="view_state" id="tab-login" class="hidden" <?php echo ($action !== 'register') ? 'checked' : ''; ?>>
            <input type="radio" name="view_state" id="tab-register" class="hidden" <?php echo ($action === 'register') ? 'checked' : ''; ?>>

            <div id="form-container" class="w-full max-w-sm mx-auto sm:max-w-md mt-10 lg:mt-0">
                
                <div id="view-login" class="w-full">
                    <div class="mb-8">
                        <h1 class="text-2xl font-medium tracking-tight text-slate-900 mb-2">Chào mừng trở lại! 👋</h1>
                        <p class="text-sm text-slate-500">Vui lòng đăng nhập để tiếp tục quản lý mùa màng.</p>
                    </div>

                    <form method="POST" action="" class="space-y-5">
                        <input type="hidden" name="action" value="login">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Số điện thoại</label>
                            <input type="tel" name="so_dien_thoai" required placeholder="09xx xxx xxx" class="w-full appearance-none rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 shadow-sm transition-colors focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Mật khẩu</label>
                            <div class="relative">
                                <input type="password" name="mat_khau" required placeholder="••••••••" class="w-full appearance-none rounded-lg border border-slate-200 bg-white pl-3.5 pr-10 py-2.5 text-sm text-slate-900 placeholder-slate-400 shadow-sm transition-colors focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>
                        </div>
                        <button type="submit" class="mt-4 w-full flex items-center justify-center rounded-lg bg-emerald-500 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                            Đăng Nhập
                        </button>
                    </form>
                    <p class="mt-8 text-center text-sm text-slate-500">
                        Chưa có tài khoản? <label for="tab-register" class="font-medium text-emerald-600 hover:text-emerald-700 cursor-pointer transition-colors">Đăng ký ngay</label>
                    </p>
                </div>


                <div id="view-register" class="w-full">
                    <div class="mb-8">
                        <h1 class="text-2xl font-medium tracking-tight text-slate-900 mb-2">Bắt đầu với GreenTech 🌱</h1>
                        <p class="text-sm text-slate-500">Tạo tài khoản để tham gia mạng lưới bảo vệ thực vật.</p>
                    </div>

                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="action" value="register">
                        
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-slate-700">Mục đích sử dụng của bạn</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="relative cursor-pointer group">
                                    <input type="radio" name="role" value="farmer" class="peer sr-only" checked>
                                    <div class="flex flex-col gap-1.5 h-full rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all peer-checked:border-emerald-500 peer-checked:ring-1 peer-checked:ring-emerald-500 peer-checked:bg-emerald-50/30">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-2xl">👨‍🌾</span>
                                            <div class="h-4 w-4 rounded-full border border-slate-300 flex items-center justify-center"><div class="h-2 w-2 rounded-full bg-emerald-500 opacity-0 transition-opacity"></div></div>
                                        </div>
                                        <span class="block text-sm font-medium text-slate-900">Nông dân</span>
                                    </div>
                                    <style>input[value="farmer"]:checked ~ div div > div { opacity: 1; }</style>
                                </label>

                                <label class="relative cursor-pointer group">
                                    <input type="radio" name="role" value="home" class="peer sr-only">
                                    <div class="flex flex-col gap-1.5 h-full rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition-all peer-checked:border-emerald-500 peer-checked:ring-1 peer-checked:ring-emerald-500 peer-checked:bg-emerald-50/30">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-2xl">🪴</span>
                                            <div class="h-4 w-4 rounded-full border border-slate-300 flex items-center justify-center"><div class="h-2 w-2 rounded-full bg-emerald-500 opacity-0 transition-opacity"></div></div>
                                        </div>
                                        <span class="block text-sm font-medium text-slate-900">Cá nhân/ Gia đình</span>
                                    </div>
                                    <style>input[value="home"]:checked ~ div div > div { opacity: 1; }</style>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-4 pt-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Họ và Tên</label>
                                <input type="text" name="ho_ten" required placeholder="Nguyễn Văn A" class="w-full appearance-none rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Số điện thoại</label>
                                    <input type="tel" name="so_dien_thoai" required placeholder="09xx xxx xxx" class="w-full appearance-none rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Khu vực</label>
                                    <select name="khu_vuc" required class="w-full appearance-none rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                        <option value="Thôn 1 (Vùng Lúa nước)">Thôn 1 (Lúa nước)</option>
                                        <option value="Thôn 2 (Vùng Cải xanh)">Thôn 2 (Cải xanh)</option>
                                        <option value="Thôn 3 (Vùng Cà chua)">Thôn 3 (Cà chua)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4 pt-2">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Mật khẩu</label>
                                    <input type="password" name="mat_khau" required placeholder="••••••••" class="w-full appearance-none rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Nhập lại mật khẩu</label>
                                    <input type="password" name="mat_khau_cf" required placeholder="••••••••" class="w-full appearance-none rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="mt-4 w-full flex items-center justify-center rounded-lg bg-emerald-500 px-4 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all">
                            Tạo Tài Khoản
                        </button>
                    </form>

                    <p class="mt-8 text-center text-sm text-slate-500 border-t border-slate-100 pt-6">
                        Đã có tài khoản? <label for="tab-login" class="font-medium text-emerald-600 hover:text-emerald-700 cursor-pointer transition-colors">Đăng nhập ngay</label>
                    </p>
                </div>

            </div>
        </div>
    </div>
</body>
</html>