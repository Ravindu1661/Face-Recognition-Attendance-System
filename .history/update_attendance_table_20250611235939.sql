-- Add these columns if they don't exist
ALTER TABLE attendance 
ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS notes TEXT NULL,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL;

-- Update existing records to have updated_at = created_at
UPDATE attendance SET updated_at = created_at WHERE updated_at IS NULL;