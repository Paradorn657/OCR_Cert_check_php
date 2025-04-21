<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OCR Web App</title>
    <style>
        .pdf-result {
            border: 1px solid #ccc;
            padding: 10px;
            margin: 10px 0;
        }
        .pdf-result img {
            max-width: 300px;
        }
    </style>
</head>
<body>
    <h2>แปลง PDF เป็นข้อความ</h2>
    <form action="" method="POST">
        <label>เพิ่มลิงก์ PDF (หนึ่งลิงก์ต่อบรรทัด):</label><br>
        <textarea name="pdf_links" rows="10" cols="100" required></textarea><br>
        <button type="submit" name="submit">แปลง PDF เป็นข้อความ</button>
    </form>

    <?php
require_once __DIR__ . '/vendor/autoload.php';

use thiagoalessio\TesseractOCR\TesseractOCR;
use Intervention\Image\ImageManagerStatic as Image;

if (isset($_POST['submit']) && isset($_POST['pdf_links'])) {
    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

    // Get PDF links from textarea (one per line)
    $pdfLinks = array_filter(array_map('trim', explode("\n", $_POST['pdf_links'])));

    // Path to pdftoppm
    $pdftoppm = "C:\\Users\\danai\\Downloads\\Release-24.08.0-0\\poppler-24.08.0\\Library\\bin\\pdftoppm.exe";

    foreach ($pdfLinks as $index => $pdfUrl) {
        echo "<div class='pdf-result'>";
        echo "<h3>PDF " . ($index + 1) . ": " . htmlspecialchars($pdfUrl) . "</h3>";

        // Validate URL
        if (!filter_var($pdfUrl, FILTER_VALIDATE_URL)) {
            echo "<p style='color:red;'>URL ไม่ถูกต้อง: $pdfUrl</p>";
            echo "</div>";
            continue;
        }

        // Download PDF using cURL
        $pdfFileName = $uploadDir . "pdf_" . time() . "_" . $index . ".pdf";
        $ch = curl_init($pdfUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Temporary for testing; remove in production
        $pdfContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($pdfContent === false || $httpCode !== 200) {
            echo "<p style='color:red;'>ไม่สามารถดาวน์โหลด PDF: $pdfUrl<br>HTTP Code: $httpCode<br>Error: " . htmlspecialchars($curlError) . "</p>";
            echo "</div>";
            continue;
        }

        // Save PDF
        if (!file_put_contents($pdfFileName, $pdfContent)) {
            echo "<p style='color:red;'>ไม่สามารถบันทึก PDF: $pdfFileName</p>";
            echo "</div>";
            continue;
        }

        // Unique output prefix for images
        $uniqueId = time() . "_" . $index;
        $outputPrefix = $uploadDir . "output_" . $uniqueId;

        // Convert PDF to images
        $command = "\"$pdftoppm\" -png -r 300 \"$pdfFileName\" \"$outputPrefix\"";
        $result = shell_exec($command . " 2>&1");
        echo "<p>Command: $command</p>";
        echo "<p>Command Result: " . nl2br(htmlspecialchars($result)) . "</p>";

        // Process the first page image
        $imagePath = $outputPrefix . "-1.png";
        if (file_exists($imagePath)) {
            echo "<p><strong>ภาพเดิม:</strong><br><img src='$imagePath'></p>";

            // Center crop image
            $croppedImagePath = $uploadDir . "cropped_output_" . $uniqueId . "-1.png";
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
            

            echo "<p><strong>ภาพหลัง crop:</strong><br><img src='$croppedImagePath'></p>";

            // Run Tesseract OCR
            try {
                $ocr = new TesseractOCR($croppedImagePath);
                $ocr->executable('C:\Program Files\Tesseract-OCR\tesseract.exe');
                $ocr->lang('tha');
                $ocr->psm(7)->oem(1);
                $text = $ocr->run();
                echo "<h3>ข้อความที่อ่านได้:</h3>";
                echo "<pre>" . htmlspecialchars($text) . "</pre>";
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

        // Clean up: Remove downloaded PDF
        if (file_exists($pdfFileName)) {
            unlink($pdfFileName);
        }

        echo "</div>";
    }
}
?>
</body>
</html>