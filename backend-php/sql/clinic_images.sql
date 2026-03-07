-- Clinic Images Table
-- Used to store multiple clinic images for the About Us page gallery

CREATE TABLE IF NOT EXISTS clinic_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Default sample images (optional - for demo purposes)
-- INSERT INTO clinic_images (image_path) VALUES ('clinic1.jpg');

