<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OCR Web App</title>
</head>
<body>
    <h2>เลือกรูปเพื่อแปลงเป็นข้อความ</h2>
    <form action="" method="POST" enctype="multipart/form-data">
        <input type="file" name="image" required>
        <button type="submit" name="submit">แปลงภาพเป็นข้อความ</button>
    </form>

    <?php
require_once __DIR__ . '/vendor/autoload.php';

use thiagoalessio\TesseractOCR\TesseractOCR;
use Intervention\Image\ImageManagerStatic as Image; // เพิ่ม Intervention Image

if (isset($_POST['submit']) && isset($_FILES['image'])) {
    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

    $pdfPath = $_FILES['image']['tmp_name'];
    $outputPrefix = $uploadDir . "output";

    // Debug
    echo "<p>PDF Path: $pdfPath</p>";
    echo "<p>Output Prefix: $outputPrefix</p>";

    // ใช้ pdftoppm แปลง PDF เป็นภาพ
    $pdftoppm = "C:\\Users\\danai\\Downloads\\Release-24.08.0-0\\poppler-24.08.0\\Library\\bin\\pdftoppm.exe";
    $command = "\"$pdftoppm\" -png -r 300 \"$pdfPath\" \"$outputPrefix\"";
    $result = shell_exec($command . " 2>&1");
    echo "<p>Command: $command</p>";
    echo "<p>Command Result: " . nl2br(htmlspecialchars($result)) . "</p>";

    // เปลี่ยนชื่อไฟล์ภาพที่แปลงจาก PDF
    $imagePath = $outputPrefix . "-1.png";

    // ตรวจสอบว่าไฟล์ภาพถูกสร้างขึ้นหรือไม่
    if (file_exists($imagePath)) {
        echo "<p><strong>ภาพเดิม:</strong><br><img src='$imagePath' style='max-width:300px;'></p>";

        // Center crop รูปภาพ
        $croppedImagePath = $uploadDir . "cropped_output-1.png";
        $image = Image::make($imagePath);
        $width = $image->width();
        $height = $image->height();
        $cropWidth = round($width * 0.6);
        $cropHeight = round($height * 0.058); // ลองปรับนิดหน่อย
        $image->crop($cropWidth, $cropHeight, 500, 1260); // ตรวจตำแหน่งดี ๆ
        
        $image->sharpen(15)
              ->greyscale()
              ->contrast(15);

        $image->save($croppedImagePath);

        echo "<p><strong>ภาพหลัง crop:</strong><br><img src='$croppedImagePath' style='max-width:300px;'></p>";
        
        //ส่วนชื่อหลักสูตร
         // Center crop รูปภาพ
         $croppedinfoImagePath = $uploadDir . "cropped_info_output-1.png";
         $image = Image::make($imagePath);
         $width = $image->width();
         $height = $image->height();
         $image->crop(1910, 237, 322, 1713); // ตรวจตำแหน่งดี ๆ
         
         $image->sharpen(15)
               ->greyscale()
               ->contrast(15);
 
         $image->save($croppedinfoImagePath);
 
         echo "<p><strong>ภาพหลักสูตรหลัง crop:</strong><br><img src='$croppedinfoImagePath' style='max-width:300px;'></p>";




        // ใช้ Tesseract OCR อ่านข้อความจากภาพที่ crop แล้ว
        try {
            $ocr = new TesseractOCR($croppedImagePath); // ใช้ภาพที่ crop
            $ocr->executable('C:\Program Files\Tesseract-OCR\tesseract.exe');
            $ocr->lang('tha');
            $ocr->psm(7)->oem(1);

            $text = $ocr->run();
            echo "<h3>ข้อความที่อ่านได้:</h3>";
            echo "<pre>$text</pre>";

            $ocr = new TesseractOCR($croppedinfoImagePath); // ใช้ภาพที่ crop
            $ocr->executable('C:\Program Files\Tesseract-OCR\tesseract.exe');
            $ocr->lang('tha','eng');
            $ocr->psm(6)->oem(1);

            $text = $ocr->run();
            echo "<h3>ข้อความหลักสูตรที่อ่านได้:</h3>";
            echo "<pre>$text</pre>";



        } catch (Exception $e) {
            echo "<p>Error with OCR: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:red;'>ไม่พบไฟล์ภาพ: $imagePath</p>";
        echo "<p>ไฟล์ที่มีในโฟลเดอร์:</p><ul>";
        $files = glob($uploadDir . '*');
        foreach ($files as $file) {
            echo "<li>" . basename($file) . "</li>";
        }
        echo "</ul>";
    }
}
?>
</body>
</html>
