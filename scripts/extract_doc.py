import sys
import json
import os
import warnings
import logging

# 🌟 สั่งห้ามไม่ให้ PyTorch เรียกหา C++ Compiler (แก้ Error cl is not found)
os.environ["TORCH_COMPILE_DISABLE"] = "1"
os.environ["TORCHDYNAMO_DISABLE"] = "1"
os.environ["TORCH_CACHING_PRECOMPILE"] = "0"
os.environ["TOKENIZERS_PARALLELISM"] = "false"

# ปิดแจ้งเตือนขยะทั้งหมด
logging.getLogger().setLevel(logging.ERROR)
warnings.filterwarnings("ignore")

from docling.document_converter import DocumentConverter

def extract_text(file_path, output_json_path):
    target_path = file_path
    output = {}
    
    try:
        # 1. ตรวจสอบว่าเป็น PDF หรือไม่ ถ้าใช่ให้หั่นเอาแค่ "หน้าแรก"
        if file_path.lower().endswith('.pdf'):
            try:
                from PyPDF2 import PdfReader, PdfWriter
                reader = PdfReader(file_path)
                
                # ถ้า PDF มีหน้ามากกว่า 0 ให้ตัดหน้าแรกมาสร้างเป็นไฟล์ชั่วคราว
                if len(reader.pages) > 0:
                    writer = PdfWriter()
                    writer.add_page(reader.pages[0])
                    
                    target_path = file_path + "_page1.pdf"
                    with open(target_path, "wb") as fp:
                        writer.write(fp)
            except Exception:
                pass 

        # 2. ให้ Docling อ่านไฟล์ (ถ้าเป็น PDF ก็จะอ่านแค่หน้าที่ถูกหั่นมาแล้ว)
        converter = DocumentConverter()
        result = converter.convert(source=target_path)
        markdown_text = result.document.export_to_markdown()
        
        output = {
            "status": "success", 
            "text": markdown_text
        }

    except Exception as e:
        output = {
            "status": "error", 
            "message": str(e)
        }
        
    finally:
        # 3. ลบไฟล์ชั่วคราว (หน้าแรก) ทิ้ง เพื่อไม่ให้รกเซิร์ฟเวอร์
        if target_path != file_path and os.path.exists(target_path):
            try:
                os.remove(target_path)
            except Exception:
                pass
                
        # 4. เขียนผลลัพธ์ลงไฟล์ .json โดยตรง
        with open(output_json_path, 'w', encoding='utf-8') as f:
            json.dump(output, f, ensure_ascii=False)

if __name__ == "__main__":
    # ต้องรับพารามิเตอร์ 2 ตัว: 1=ไฟล์ที่อ่าน, 2=ไฟล์ที่เซฟ
    if len(sys.argv) > 2:
        extract_text(sys.argv[1], sys.argv[2])
    else:
        # ถ้าพารามิเตอร์ไม่ครบ ให้สร้างไฟล์ JSON แจ้ง Error
        if len(sys.argv) > 1:
            with open(sys.argv[1] + '_error.json', 'w', encoding='utf-8') as f:
                json.dump({"status": "error", "message": "พารามิเตอร์ไม่ครบถ้วน"}, f, ensure_ascii=False)
