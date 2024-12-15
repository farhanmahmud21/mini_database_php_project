<?php
require_once 'includes/config.php';

function convertFileSize($size) {
    $size = trim($size);
    $units = ['B', 'KB', 'MB', 'GB'];
    $unit = substr($size, -2);
    $number = floatval(str_replace(' ' . $unit, '', $size));
    
    return $number . ' ' . $unit;
}

function formatDate($date) {
    if (empty($date)) return null;
    return date('Y-m-d', strtotime($date));
}

$file = fopen('metadata.csv', 'r');
if ($file) {
    // Skip header row
    fgetcsv($file);
    
    // Prepare the SQL statement
    $sql = "INSERT INTO studies (
        series_uid, collection, third_party_analysis, data_description_uri,
        subject_id, study_uid, study_description, study_date,
        series_description, manufacturer, modality, sop_class_name,
        sop_class_uid, number_of_images, file_size, file_location,
        download_timestamp
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    while (($row = fgetcsv($file)) !== false) {
        $study_date = formatDate($row[7]);
        $file_size = convertFileSize($row[14]);
        
        $stmt->bind_param(
            'sssssssssssssssss',
            $row[0],  // series_uid
            $row[1],  // collection
            $row[2],  // third_party_analysis
            $row[3],  // data_description_uri
            $row[4],  // subject_id
            $row[5],  // study_uid
            $row[6],  // study_description
            $study_date,  // study_date
            $row[8],  // series_description
            $row[9],  // manufacturer
            $row[10], // modality
            $row[11], // sop_class_name
            $row[12], // sop_class_uid
            $row[13], // number_of_images
            $file_size,  // file_size
            $row[15], // file_location
            $row[16]  // download_timestamp
        );
        
        if (!$stmt->execute()) {
            echo "Error inserting row: " . $stmt->error . "\n";
        }
    }
    
    fclose($file);
    $stmt->close();
    echo "Import completed successfully!";
} else {
    echo "Error opening file";
}

$conn->close();
?> 