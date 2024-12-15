<?php
require_once 'includes/config.php';

// Specify your PNG images folder path
$png_path = "E:/data/dicom-management/png/";

// Run the PNG import
$png_result = importPngImages($png_path);

// Display results
echo "Import Summary:\n";
echo "---------------\n";
echo "Successfully imported PNG: " . $png_result['imported_count'] . " images\n";

if (!empty($png_result['errors'])) {
    echo "\nErrors encountered:\n";
    foreach ($png_result['errors'] as $error) {
        echo "- " . $error . "\n";
    }
}

function importPngImages($png_path) {
    global $conn;
    
    $result = array(
        'imported_count' => 0,
        'errors' => array()
    );
    
    try {
        // Debug: Check directory path
        echo "Checking directory: " . $png_path . "\n";
        
        // Check if directory exists
        if (!is_dir($png_path)) {
            throw new Exception("PNG directory not found: " . $png_path);
        }

        // Get all folders
        $folders = array_filter(glob($png_path . '/*'), 'is_dir');
        
        // Debug: Print number of folders found
        echo "Found " . count($folders) . " folders\n";
        
        foreach ($folders as $folder) {
            $subject_id = basename($folder);
            echo "Processing folder: " . $subject_id . "\n";
            
            // Get all PNG files in the current folder
            $png_files = glob($folder . '/*.png');
            
            // Debug: Print number of PNG files found
            echo "Found " . count($png_files) . " PNG files in " . $subject_id . "\n";
            
            foreach ($png_files as $png_file) {
                try {
                    // Get file information
                    $file_name = basename($png_file);
                    $file_size = filesize($png_file);
                    $file_date = date("Y-m-d H:i:s", filemtime($png_file));
                    
                    // Format file size
                    $formatted_size = formatFileSize($file_size);
                    
                    // Insert into database
                    $sql = "INSERT INTO studies (
                        series_uid,
                        collection,
                        subject_id,
                        study_description,
                        study_date,
                        series_description,
                        modality,
                        number_of_images,
                        file_size,
                        file_location,
                        image_path,
                        download_timestamp
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $stmt = $conn->prepare($sql);
                    
                    // Generate a unique series UID
                    $series_uid = uniqid('PNG_', true);
                    $collection = 'PNG_COLLECTION';
                    $study_description = 'PNG Image Import';
                    $series_description = pathinfo($file_name, PATHINFO_FILENAME);
                    $modality = 'PNG';
                    $number_of_images = 1;
                    
                    $stmt->bind_param('sssssssssss',
                        $series_uid,
                        $collection,
                        $subject_id,
                        $study_description,
                        $file_date,
                        $series_description,
                        $modality,
                        $number_of_images,
                        $formatted_size,
                        $png_file,
                        $png_file
                    );
                    
                    if ($stmt->execute()) {
                        $result['imported_count']++;
                    } else {
                        $result['errors'][] = "Failed to insert record for: " . $png_file;
                    }
                    
                    $stmt->close();
                    
                } catch (Exception $e) {
                    $result['errors'][] = "Error processing file {$png_file}: " . $e->getMessage();
                }
            }
        }
        
    } catch (Exception $e) {
        $result['errors'][] = $e->getMessage();
    }
    
    return $result;
}

// Helper function to format file size
function formatFileSize($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>