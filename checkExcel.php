<?php
set_time_limit(0);
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Intervention\Image\ImageManagerStatic as Image;

// Read Excel file
$inputExcel = 'C:\Users\danai\Downloads\670530_TINT_ตรวจสอบcert_ครั้งที่2_v1.xlsx'; 
$spreadsheet = IOFactory::load($inputExcel);
$sheet = $spreadsheet->getActiveSheet();

// Path
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
// $i = 0;
// $maxRows = 10;
// Loop through each row
foreach ($sheet->getRowIterator(2) as $row) { // row 1 = header
    // if ($i >= $maxRows) {
    //     break; // หยุดลูปเมื่อครบ 10 แถว
    // }
    $rowIndex = $row->getRowIndex();
    $rowData = [
        'description' => $sheet->getCell("B{$rowIndex}")->getValue(),
        'file_path' => $sheet->getCell("D{$rowIndex}")->getValue(),
        'tfname'    => $sheet->getCell("F{$rowIndex}")->getValue(),
        'tlname'    => $sheet->getCell("G{$rowIndex}")->getValue(),
    ];

    $url = 'baselink'.$rowData['file_path'];
    $targetPdf = $uploadDir . "pdf_" . time() . "_{$rowIndex}.pdf";
    // echo "Checking URL: $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ปิด SSL ชั่วคราว
    $pdfContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($pdfContent === false || $httpCode !== 200) {
        $sheet->setCellValue("H{$rowIndex}", "Download Failed: HTTP $httpCode");
        continue;
    }

    if (!file_put_contents($targetPdf, $pdfContent)) {
        $sheet->setCellValue("H{$rowIndex}", "Save Failed");
        continue;
    }

    // Convert PDF to Image
    $pdftoppm = 'C:\\Users\\danai\\Downloads\\Release-24.08.0-0\\poppler-24.08.0\\Library\\bin\\pdftoppm.exe';
    $outputPrefix = $uploadDir . "output_" . time() . "_{$rowIndex}";
    $command = "\"$pdftoppm\" -png -r 300 \"$targetPdf\" \"$outputPrefix\"";
    shell_exec($command);

    $imagePath = $outputPrefix . "-1.png";
    if (!file_exists($imagePath)) {
        $sheet->setCellValue("H{$rowIndex}", 'Image Not Found');
        continue;
    }

    // Crop Image
    $image = Image::make($imagePath);
    $cropWidth = round($image->width() * 0.6);
    $cropHeight = round($image->height() * 0.058);
    $image->crop($cropWidth, $cropHeight, 500, 1260)
          ->sharpen(15)
          ->greyscale()
          ->contrast(15);

    $croppedPath = $uploadDir . "cropped_" . basename($imagePath);
    $image->save($croppedPath);


    //หลักสูตร
    $image = Image::make($imagePath);
    $width = $image->width();
    $height = $image->height();
    $image->crop(1910, 340, 322, 1713); // ตรวจตำแหน่งดี ๆ
    
    $image->sharpen(15)
          ->greyscale()
          ->contrast(15);
    $croppedinfoImagePath = $uploadDir . "croppedinfo_" . basename($imagePath);
    $image->save($croppedinfoImagePath);

    // OCR
    $ocrText = (new TesseractOCR($croppedPath))
        ->executable('C:\Program Files\Tesseract-OCR\tesseract.exe')
        ->lang('tha')
        ->psm(7)->oem(1)
        ->run();

    $ocrCourceText = (new TesseractOCR($croppedinfoImagePath))
        ->executable('C:\Program Files\Tesseract-OCR\tesseract.exe')
        ->lang('tha','eng')
        ->psm(6)->oem(1)
        ->run();

    // Clean up text
    $ocrName = normalizeThaiName($ocrText);
    echo "<h3>อ่านได้: $ocrText ลบสระแล้วได้: $ocrName</h3><br>";
    $ocrCourcename = normalizeThaiName($ocrCourceText);
    echo "<h3>อ่านได้: $ocrCourcename</h3><hr>";


    $expectedName = normalizeThaiName($rowData['tfname'] . $rowData['tlname']);
    $expectedCourseName = normalizeThaiName($rowData['description']);

    // Compare similarity
    similar_text($ocrName, $expectedName, $percent);
    similar_text($ocrCourcename, $expectedCourseName, $percentcourse);
    
    $sheet->setCellValue("H{$rowIndex}", $ocrText);
    $sheet->setCellValue("I{$rowIndex}", round($percent) . '%');

    $sheet->setCellValue("J{$rowIndex}", $ocrCourceText);
    $sheet->setCellValue("K{$rowIndex}", round($percentcourse) . '%');
    // Optional: delete temp files
    unlink($targetPdf);
    unlink($imagePath);
    unlink($croppedPath);

    // $i++;
}

// Save output
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
$outputFile = 'ocr_checked_' . date('Ymd_His') . '.xlsx';
$writer->save($outputFile);

echo "Done! Saved to $outputFile";


// --- Utility: Normalize Thai name ---
function normalizeThaiName($name) {
    $name = mb_strtolower($name, 'UTF-8');
    $name = preg_replace('/\s+/', '', $name);


    $name = preg_replace('/[่-๋็์ิีึืัุูํ์เแโใไฺ]/u', '', $name);
    
    return $name;
}
