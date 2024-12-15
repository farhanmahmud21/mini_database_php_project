CREATE DATABASE medical_images;
USE medical_images;

CREATE TABLE studies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    series_uid VARCHAR(255) NOT NULL,
    collection VARCHAR(50),
    third_party_analysis VARCHAR(50),
    data_description_uri VARCHAR(255),
    subject_id VARCHAR(50),
    study_uid VARCHAR(255),
    study_description VARCHAR(100),
    study_date DATE,
    series_description VARCHAR(100),
    manufacturer VARCHAR(50),
    modality VARCHAR(10),
    sop_class_name VARCHAR(50),
    sop_class_uid VARCHAR(255),
    number_of_images INT,
    file_size VARCHAR(20),
    file_location VARCHAR(255),
    download_timestamp DATETIME,
    image_path VARCHAR(255),
    INDEX idx_subject (subject_id),
    INDEX idx_series (series_uid)
);