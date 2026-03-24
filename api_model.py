from flask import Flask, request, jsonify
from flask_cors import CORS  # Thư viện cho phép gọi API khác miền
from ultralytics import YOLO
import os
import time
from werkzeug.utils import secure_filename
import cv2 
import base64 # Thư viện mã hóa ảnh

app = Flask(__name__)
CORS(app) # Kích hoạt CORS

MODEL_CONFIDENCE_THRESHOLD = 0.25
DEFAULT_CLASS_THRESHOLD = 0.30
SINGLE_DETECTION_STRICT_THRESHOLD = 0.72
SINGLE_DETECTION_MIN_AREA_RATIO = 0.018
MIN_BOX_AREA_RATIO = 0.0004
MAX_STORED_FILES_PER_DIR = 400
MAX_FILE_AGE_SECONDS = 60 * 60 * 24 * 3
ALLOWED_EXTENSIONS = {'.jpg', '.jpeg', '.png', '.webp'}
STRICT_SINGLE_DETECTION_CLASSES = {14}  # Chỉ siết mạnh với class dễ nhầm (snail)

CLASS_CONFIDENCE_THRESHOLDS = {
    0: 0.25,   # Rice Stemfly
    1: 0.25,   # asiatic rice borer
    2: 0.25,   # paddy stem maggot
    3: 0.25,   # rice gall midge
    4: 0.25,   # rice leaf caterpillar
    5: 0.25,   # rice leaf roller
    6: 0.25,   # yellow rice borer
    7: 0.25,   # aphids
    8: 0.25,   # caterpillar
    9: 0.25,   # cutworm
    10: 0.25,  # flea beetle
    11: 0.25,  # grasshopper
    12: 0.25,  # mites
    13: 0.25,  # mole cricket
    14: 0.20,  # snail
    15: 0.25,  # thrips
    16: 0.40,  # unknown_bug
    17: 0.25,  # whitefly
    18: 0.25,  # wireworm
}

# Sửa lại đường dẫn an toàn cho Docker
UPLOAD_FOLDER = 'uploads'
RESULT_FOLDER = 'results'
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(RESULT_FOLDER, exist_ok=True)

print("Đang tải mô hình AI...")
model = YOLO('best.pt')
print("Mô hình đã sẵn sàng!")

# BỘ TỪ ĐIỂN CHUẨN A-Z
correct_names = {
    0: 'Rice Stemfly', 1: 'asiatic rice borer', 2: 'paddy stem maggot', 
    3: 'rice gall midge', 4: 'rice leaf caterpillar', 5: 'rice leaf roller', 6: 'yellow rice borer',
    7: 'aphids', 8: 'caterpillar', 9: 'cutworm', 10: 'flea beetle', 11: 'grasshopper',
    12: 'mites', 13: 'mole cricket', 14: 'snail', 15: 'thrips', 
    16: 'unknown_bug', 17: 'whitefly', 18: 'wireworm'
}

ALLOWED_CLASS_IDS = set(correct_names.keys())


def get_threshold_for_class(class_id: int) -> float:
    return CLASS_CONFIDENCE_THRESHOLDS.get(class_id, DEFAULT_CLASS_THRESHOLD)


def draw_detections(image, detections):
    output = image.copy()
    for item in detections:
        x1, y1, x2, y2 = item['bbox']
        label = f"{item['class_name']} {item['confidence']:.2f}"
        color = (25, 196, 126)

        cv2.rectangle(output, (x1, y1), (x2, y2), color, 2)

        (text_w, text_h), _ = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.55, 2)
        text_y = y1 - 8 if y1 - 8 > text_h else y1 + text_h + 6
        cv2.rectangle(output, (x1, text_y - text_h - 6), (x1 + text_w + 8, text_y + 2), color, -1)
        cv2.putText(output, label, (x1 + 4, text_y - 3), cv2.FONT_HERSHEY_SIMPLEX, 0.55, (0, 0, 0), 2, cv2.LINE_AA)

    return output


def prune_folder(folder_path: str, max_files: int, max_age_seconds: int) -> None:
    try:
        file_entries = []
        now = time.time()
        for name in os.listdir(folder_path):
            full_path = os.path.join(folder_path, name)
            if not os.path.isfile(full_path):
                continue
            try:
                mtime = os.path.getmtime(full_path)
                file_entries.append((full_path, mtime))
            except OSError:
                continue

        # Xóa file quá hạn trước.
        for full_path, mtime in file_entries:
            if (now - mtime) > max_age_seconds:
                try:
                    os.remove(full_path)
                except OSError:
                    pass

        # Nếu vẫn còn nhiều file, xóa tiếp từ cũ nhất.
        file_entries = []
        for name in os.listdir(folder_path):
            full_path = os.path.join(folder_path, name)
            if os.path.isfile(full_path):
                try:
                    file_entries.append((full_path, os.path.getmtime(full_path)))
                except OSError:
                    continue

        if len(file_entries) > max_files:
            file_entries.sort(key=lambda item: item[1])
            excess_count = len(file_entries) - max_files
            for full_path, _ in file_entries[:excess_count]:
                try:
                    os.remove(full_path)
                except OSError:
                    pass
    except OSError:
        pass

@app.route('/detect', methods=['POST'])
def detect_insects():
    if 'image' not in request.files:
        return jsonify({'error': 'Không tìm thấy file ảnh'}), 400
    
    file = request.files['image']
    if file.filename == '':
        return jsonify({'error': 'Chưa chọn file'}), 400

    original_name = file.filename or 'upload.jpg'
    file_ext = os.path.splitext(original_name.lower())[1]
    if file_ext not in ALLOWED_EXTENSIONS:
        return jsonify({'error': 'Định dạng ảnh không hợp lệ. Chỉ hỗ trợ JPG, JPEG, PNG, WEBP.'}), 400

    filename = secure_filename(original_name)
    if filename == '':
        filename = f"upload{file_ext if file_ext else '.jpg'}"

    timestamp = str(int(time.time()))
    save_name = f"{timestamp}_{filename}"
    filepath = os.path.join(UPLOAD_FOLDER, save_name)
    try:
        file.save(filepath)
    except Exception:
        return jsonify({'error': 'Không thể lưu ảnh tải lên.'}), 500

    prune_folder(UPLOAD_FOLDER, MAX_STORED_FILES_PER_DIR, MAX_FILE_AGE_SECONDS)
    prune_folder(RESULT_FOLDER, MAX_STORED_FILES_PER_DIR, MAX_FILE_AGE_SECONDS)

    try:
        results = model.predict(source=filepath, save=False, conf=MODEL_CONFIDENCE_THRESHOLD)
    except Exception:
        return jsonify({'error': 'Mô hình AI xử lý ảnh thất bại.'}), 500

    detections = []
    pest_counts = {}
    img_base64 = ""

    original_img = cv2.imread(filepath)
    candidates = []

    if results:
        r = results[0]
        if r.orig_img is not None:
            original_img = r.orig_img.copy()

        img_h, img_w = r.orig_shape
        image_area = float(max(img_h * img_w, 1))

        for box in r.boxes:
            class_id = int(box.cls[0])
            if class_id not in ALLOWED_CLASS_IDS:
                continue

            confidence = float(box.conf[0])
            required_conf = get_threshold_for_class(class_id)
            if confidence < required_conf:
                continue

            x1, y1, x2, y2 = box.xyxy[0].tolist()
            x1 = max(0, int(round(x1)))
            y1 = max(0, int(round(y1)))
            x2 = min(img_w, int(round(x2)))
            y2 = min(img_h, int(round(y2)))

            box_w = max(0, x2 - x1)
            box_h = max(0, y2 - y1)
            if box_w == 0 or box_h == 0:
                continue

            area_ratio = (box_w * box_h) / image_area
            if area_ratio < MIN_BOX_AREA_RATIO:
                continue

            class_name = correct_names[class_id]
            candidates.append({
                'class_id': class_id,
                'class_name': class_name,
                'confidence': confidence,
                'area_ratio': area_ratio,
                'bbox': [x1, y1, x2, y2],
            })

    # Ảnh ngoại miền (vd: ảnh người) thường tạo 1 box snail mơ hồ.
    # Chỉ loại khi vừa tin cậy thấp vừa box quá nhỏ để tránh bỏ sót ảnh ốc sên rõ.
    if len(candidates) == 1:
        single_item = candidates[0]
        if (
            single_item['class_id'] in STRICT_SINGLE_DETECTION_CLASSES
            and single_item['confidence'] < SINGLE_DETECTION_STRICT_THRESHOLD
            and single_item.get('area_ratio', 0.0) < SINGLE_DETECTION_MIN_AREA_RATIO
        ):
            candidates = []

    detections = [
        {
            'class_name': item['class_name'],
            'confidence': round(float(item['confidence']), 2)
        }
        for item in candidates
    ]

    for item in candidates:
        pest_counts[item['class_name']] = pest_counts.get(item['class_name'], 0) + 1

    if original_img is None:
        original_img = cv2.imread(filepath)

    plotted_img = draw_detections(original_img, candidates) if original_img is not None else None
    result_image_name = ''
    if plotted_img is not None:
        _, buffer = cv2.imencode('.jpg', plotted_img)
        img_base64 = base64.b64encode(buffer).decode('utf-8')

        result_image_name = f"result_{save_name}.jpg"
        result_path = os.path.join(RESULT_FOLDER, result_image_name)
        try:
            cv2.imwrite(result_path, plotted_img)
        except Exception:
            result_image_name = ''

    return jsonify({
        'success': True,
        'original_image': save_name,
        'result_image': result_image_name,
        'image_base64': img_base64, # Truyền thẳng ảnh dạng Base64 về DataOnline
        'found_pest': len(detections) > 0,
        'message': 'Không tìm thấy sâu bệnh' if len(detections) == 0 else 'Phát hiện sâu bệnh',
        'total_insects': len(detections),
        'pest_counts': pest_counts,
        'detections': detections
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=7860)
