-- إضافة حقول جديدة لجدول notifications
ALTER TABLE notifications 
ADD COLUMN case_id INT DEFAULT NULL AFTER to_user,
ADD COLUMN delivery_method VARCHAR(50) DEFAULT NULL AFTER case_id,
ADD FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE;

