import json
import os
import sys
import warnings


# บังคับ UTF-8 เพื่อให้ข้อความภาษาไทยส่งกลับไปยัง PHP ได้ถูกต้องบน Windows
os.environ.setdefault("PYTHONIOENCODING", "utf-8")
os.environ.setdefault("TOKENIZERS_PARALLELISM", "false")
warnings.filterwarnings("ignore")

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")


def _is_useful_text(text):
    """ตรวจว่าข้อความจาก text layer มีมากพอที่จะส่งให้ AI หรือไม่"""
    return len("".join((text or "").split())) >= 40


def _ocr(source):
    # EasyOCR มีโมเดลภาษาไทยโดยตรง และไม่โหลด Docling/Layout Heron/Hugging Face
    import easyocr

    model_directory = os.path.join(
        os.path.dirname(os.path.abspath(__file__)),
        "storage",
        "app",
        "python_home",
        ".EasyOCR",
        "model",
    )
    os.makedirs(model_directory, exist_ok=True)
    reader = easyocr.Reader(
        ["th", "en"],
        gpu=False,
        model_storage_directory=model_directory,
        download_enabled=True,
        verbose=False,
    )
    lines = reader.readtext(source, detail=0, paragraph=False, workers=0)
    return "\n".join(str(line).strip() for line in lines if str(line).strip())


def _extract_pdf(file_path):
    import fitz  # PyMuPDF

    with fitz.open(file_path) as document:
        if document.page_count == 0:
            raise ValueError("ไฟล์ PDF ไม่มีหน้าเอกสาร")

        page = document.load_page(0)
        embedded_text = page.get_text("text").strip()
        if _is_useful_text(embedded_text):
            return embedded_text

        # PDF จากเครื่องสแกนมักเป็นรูปภาพ จึงแปลงเฉพาะหน้าแรกเป็น PNG แล้ว OCR
        pixmap = page.get_pixmap(matrix=fitz.Matrix(2.5, 2.5), alpha=False)
        return _ocr(pixmap.tobytes("png"))


def extract_text(file_path, output_json_path):
    try:
        extension = os.path.splitext(file_path)[1].lower()
        if extension == ".pdf":
            text = _extract_pdf(file_path)
        elif extension in {".jpg", ".jpeg", ".png"}:
            text = _ocr(file_path)
        else:
            raise ValueError("รองรับเฉพาะไฟล์ PDF, JPG, JPEG และ PNG")

        if not text.strip():
            raise ValueError("ไม่พบข้อความในหน้าแรกของเอกสาร กรุณาถ่ายภาพให้ชัดและไม่เอียง")

        output = {"status": "success", "text": text}
    except Exception as error:
        output = {"status": "error", "message": str(error)}

    with open(output_json_path, "w", encoding="utf-8") as output_file:
        json.dump(output, output_file, ensure_ascii=False)


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.stderr.write("Usage: extract_doc.py <input-file> <output-json>\n")
        sys.exit(2)

    extract_text(sys.argv[1], sys.argv[2])
