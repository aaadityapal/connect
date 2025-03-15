ALTER TABLE project_substages
ADD CONSTRAINT fk_stage_id
FOREIGN KEY (stage_id)
REFERENCES project_stages(id)
ON DELETE CASCADE;