import sys
import json
import fitz  # PyMuPDF

def stamp_signature_auto(pdf_path, sig_path, output_path):
    try:
        # เปิดไฟล์ PDF
        doc = fitz.open(pdf_path)
        
        # 🌟 กำหนดคำค้นหา พร้อมระยะชดเชย (Offset) 🌟
        # dx = ขยับซ้ายขวา (บวกคือไปทางขวา, ลบคือไปทางซ้าย)
        # dy = ขยับขึ้นลง (บวกคือเลื่อนลง, ลบคือเลื่อนขึ้น)
        target_configs = [
            # สำหรับหนังสือส่งออก
            {"word": "ขอแสดงความนับถือ", "dx": -10, "dy": 15},
            {"word": "แสดงความนับถือ", "dx": -10, "dy": 15},
            
            # สำหรับบันทึกข้อความภายใน (แก้พิกัดให้ลอยเหนือเส้นประ)
            {"word": "(ลงชื่อ)", "dx": 45, "dy": -45},       
            {"word": "ผู้เสนอเรื่อง", "dx": -70, "dy": -30} 
        ]
        
        coords = None
        
        # 1. ค้นหาพิกัด
        for page_num in range(len(doc)):
            page = doc[page_num]
            
            for config in target_configs:
                word = config["word"]
                text_instances = page.search_for(word)
                
                if text_instances:
                    # เจอคำเป้าหมายแล้ว!
                    rect = text_instances[0]
                    coords = {
                        "page": page_num, 
                        "x": rect.x0 + config["dx"], 
                        "y": rect.y1 + config["dy"]
                    }
                    break # เจอแล้วหยุดหาคำอื่น
            
            if coords:
                break # เจอหน้าที่มีคำแล้ว หยุดค้นหาหน้าถัดไป

        # 2. ถ้าเจอพิกัด ให้ประทับลายเซ็น
        if coords:
            page = doc[coords['page']]
            
            # กำหนดขนาดลายเซ็น
            sig_width = 100
            sig_height = 40
            
            x_pos = coords['x']
            y_pos = coords['y']
            
            # สร้างกรอบสี่เหลี่ยมสำหรับวางรูป
            stamp_rect = fitz.Rect(x_pos, y_pos, x_pos + sig_width, y_pos + sig_height)
            
            # แปะรูปลงไป
            page.insert_image(stamp_rect, filename=sig_path)
            
            # บันทึกเป็นไฟล์ใหม่
            doc.save(output_path)
            doc.close()
            
            return {"status": "success", "message": "พบพิกัดและประทับลายเซ็นเรียบร้อย"}
        else:
            doc.close()
            return {"status": "error", "message": "ไม่พบคำเป้าหมายสำหรับวางลายเซ็นในเอกสาร"}

    except Exception as e:
        return {"status": "error", "message": str(e)}

if __name__ == "__main__":
    pdf_in = sys.argv[1]
    sig_in = sys.argv[2]
    pdf_out = sys.argv[3]
    
    result = stamp_signature_auto(pdf_in, sig_in, pdf_out)
    print(json.dumps(result))