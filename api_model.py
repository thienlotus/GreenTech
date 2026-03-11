from flask import Flask, request, jsonify
from ultralytics import YOLO
import os
import time
from werkzeug.utils import secure_filename
import cv2  # <--- THÊM VŨ KHÍ MỚI ĐỂ VẼ ẢNH

app = Flask(__name__)

CONFIDENCE_THRESHOLD = 0.30

UPLOAD_FOLDER = '../uploads'
RESULT_FOLDER = '../results'
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

@app.route('/detect', methods=['POST'])
def detect_insects():
    if 'image' not in request.files:
        return jsonify({'error': 'Không tìm thấy file ảnh'}), 400
    
    file = request.files['image']
    if file.filename == '':
        return jsonify({'error': 'Chưa chọn file'}), 400

    filename = secure_filename(file.filename)
    timestamp = str(int(time.time()))
    save_name = f"{timestamp}_{filename}"
    filepath = os.path.join(UPLOAD_FOLDER, save_name)
    file.save(filepath)

    results = model.predict(source=filepath, save=False, conf=CONFIDENCE_THRESHOLD)
    
    detections = []
    pest_counts = {}

    for r in results:
        # --- PHÉP THUẬT MỚI: ÉP TỪ ĐIỂN VÀO TỪNG KẾT QUẢ VÀ TỰ VẼ ---
        r.names = correct_names  # Ép cái kết quả này phải dùng từ điển chuẩn
        plotted_img = r.plot()   # Xuất khung hình đã vẽ chữ chuẩn ra bộ nhớ
        
        result_img_name = f"result_{save_name}"
        result_img_path = os.path.join(RESULT_FOLDER, result_img_name)
        
        # Dùng OpenCV lưu cái khung hình đó lại thành file
        cv2.imwrite(result_img_path, plotted_img)
        # -----------------------------------------------------------

        boxes = r.boxes
        for box in boxes:
            class_id = int(box.cls[0])
            if class_id not in ALLOWED_CLASS_IDS:
                continue

            class_name = correct_names[class_id]
            confidence = float(box.conf[0])
            
            if confidence >= CONFIDENCE_THRESHOLD:
                detections.append({
                    'class_name': class_name,
                    'confidence': round(confidence, 2)
                })
                if class_name in pest_counts:
                    pest_counts[class_name] += 1
                else:
                    pest_counts[class_name] = 1

    return jsonify({
        'success': True,
        'original_image': save_name,
        'result_image': result_img_name,
        'found_pest': len(detections) > 0,
        'message': 'Không tìm thấy sâu bệnh' if len(detections) == 0 else 'Phát hiện sâu bệnh',
        'total_insects': len(detections),
        'pest_counts': pest_counts
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)