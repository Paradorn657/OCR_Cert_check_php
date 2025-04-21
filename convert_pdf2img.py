from pdf2image import convert_from_path
import sys
import os

# รับ path ไฟล์จาก PHP
pdf_path = sys.argv[1]
output_folder = sys.argv[2]

# แปลง PDF ทุกหน้าเป็นภาพ
images = convert_from_path(pdf_path, dpi=300)

# บันทึกไฟล์ภาพ
image_paths = []
for i, image in enumerate(images):
    image_path = os.path.join(output_folder, f"page_{i+1}.png")
    image.save(image_path, "PNG")
    image_paths.append(image_path)

# ส่ง path ของไฟล์ภาพที่แปลงแล้วกลับไป
print(image_paths[0])  # ส่งกลับ path ของไฟล์ภาพแรก
