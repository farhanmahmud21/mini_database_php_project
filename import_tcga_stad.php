<?php
require_once 'includes/config.php';

function importTcgaStadImages($base_path) {
    global $conn;
    
    // Get all DICOM files recursively
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_path)
    );
    
    $imported_count = 0;
    $errors = [];
    
    // Make sure dcmtk tools are available
    if (!file_exists('/usr/bin/dcmj2pnm')) {
        die("Error: dcmtk tools not found. Please install dcmtk package.");
    }
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'dcm') {
            // Extract TCGA-STAD specific information from the path
            $relative_path = str_replace($base_path, '', $file->getPathname());
            $path_parts = explode(DIRECTORY_SEPARATOR, trim($relative_path, DIRECTORY_SEPARATOR));
            
            $subject_id = $path_parts[0];
            
            // Create directories for both DICOM and image files
            $upload_base = 'E:\data\dicom-management\TCGA-STAD/' . $subject_id . '/' . date('Y-m-d');
            $dicom_dir = $upload_base . '/dicom';
            $image_dir = $upload_base . '/images';
            
            foreach ([$dicom_dir, $image_dir] as $dir) {
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
            }
            
            // Generate unique filenames
            $unique_id = uniqid();
            $dicom_destination = $dicom_dir . '/' . $unique_id . '.dcm';
            $image_destination = $image_dir . '/' . $unique_id . '.png';
            
            // Copy DICOM file
            if (!copy($file->getPathname(), $dicom_destination)) {
                $errors[] = "Failed to copy DICOM file: " . $file->getPathname();
                continue;
            }
            
            // Convert DICOM to PNG using dcmtk
            $cmd = sprintf(
                'dcmj2pnm --write-png +oj +Jq 95 %s %s 2>&1',
                escapeshellarg($dicom_destination),
                escapeshellarg($image_destination)
            );
            
            exec($cmd, $output, $return_var);
            
            if ($return_var !== 0) {
                $errors[] = "Failed to convert DICOM to PNG: " . implode("\n", $output);
                continue;
            }
            
            // Extract DICOM metadata
            $metadata = getDicomMetadata($file->getPathname());
            if (!$metadata) {
                $errors[] = "Failed to read metadata for: " . $file->getPathname();
                continue;
            }
            
            // Calculate file size
            $size_bytes = $file->getSize();
            $file_size = formatFileSize($size_bytes);
            
            // Insert record into database
            $stmt = $conn->prepare("INSERT INTO studies (
                series_uid, collection, subject_id, study_uid,
                study_description, study_date, series_description,
                manufacturer, modality, sop_class_name,
                sop_class_uid, number_of_images, file_size,
                file_location, image_path, download_timestamp
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $collection = 'TCGA-STAD';
            $number_of_images = 1;
            
            $stmt->bind_param('sssssssssssisss',
                $metadata['series_uid'],
                $collection,
                $subject_id,
                $metadata['study_uid'],
                $metadata['study_description'],
                $metadata['study_date'],
                $metadata['series_description'],
                $metadata['manufacturer'],
                $metadata['modality'],
                $metadata['sop_class_name'],
                $metadata['sop_class_uid'],
                $number_of_images,
                $file_size,
                $dicom_destination,
                $image_destination
            );
            
            if ($stmt->execute()) {
                $imported_count++;
            } else {
                $errors[] = "Database error for file: " . $file->getPathname();
            }
            
            $stmt->close();
        }
    }
    
    return [
        'imported_count' => $imported_count,
        'errors' => $errors
    ];
}

// ... rest of the code remains the same ... 