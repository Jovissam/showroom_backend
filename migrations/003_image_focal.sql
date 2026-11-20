-- Focal point of each portfolio photo (0..1 from the left/top), used for object-position
-- wherever a photo is cropped to a frame. Computed on upload; an admin can override it.
ALTER TABLE portfolio_images
    ADD COLUMN focal_x DECIMAL(4,3) NOT NULL DEFAULT 0.500 AFTER height,
    ADD COLUMN focal_y DECIMAL(4,3) NOT NULL DEFAULT 0.500 AFTER focal_x,
    ADD COLUMN focal_source ENUM('auto', 'manual') NOT NULL DEFAULT 'auto' AFTER focal_y;
