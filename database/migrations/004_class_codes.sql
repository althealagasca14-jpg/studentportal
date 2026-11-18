-- 004: verified subject_sections exists; existing sections retain a NULL code.
-- Apply only through database/apply_classroom.php after a full local backup.
ALTER TABLE subject_sections ADD COLUMN class_code CHAR(10) CHARACTER SET ascii COLLATE ascii_bin NULL;
CREATE UNIQUE INDEX classroom_class_code ON subject_sections(class_code);
