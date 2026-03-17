-- ============================================================
-- Step 1: Add the column (skip if already done)
-- ============================================================
ALTER TABLE `studio_assigned_tasks`
    ADD COLUMN IF NOT EXISTS `extension_history` JSON DEFAULT NULL
        COMMENT 'Full per-user extension audit trail as a JSON array'
    AFTER `extended_by`;

-- ============================================================
-- Step 2: Backfill existing tasks that have been extended
--   Reads from global_activity_logs (action_type='extend_deadline')
--   and rebuilds extension_history per task.
--
--   Run this ONCE after adding the column.
-- ============================================================

-- For each task that has extension logs, build history from logs.
-- We use a stored procedure to iterate and build the JSON array.

DROP PROCEDURE IF EXISTS backfill_ext_history;

DELIMITER $$

CREATE PROCEDURE backfill_ext_history()
BEGIN
    DECLARE done        INT DEFAULT FALSE;
    DECLARE v_task_id   INT;
    DECLARE v_meta      JSON;
    DECLARE v_user_id   INT;
    DECLARE v_desc      TEXT;
    DECLARE v_created   DATETIME;
    DECLARE v_entry_num INT DEFAULT 0;
    DECLARE v_history   JSON;
    DECLARE v_prev_task INT DEFAULT -1;

    -- Cursor over all extend_deadline logs ordered by task + time
    DECLARE cur CURSOR FOR
        SELECT entity_id, user_id, metadata, description, created_at
        FROM global_activity_logs
        WHERE action_type = 'extend_deadline'
          AND entity_type = 'task'
        ORDER BY entity_id ASC, created_at ASC;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_task_id, v_user_id, v_meta, v_desc, v_created;
        IF done THEN LEAVE read_loop; END IF;

        -- New task group → reset counter and history
        IF v_task_id <> v_prev_task THEN
            -- Save previous task's history (if any)
            IF v_prev_task > 0 AND v_history IS NOT NULL THEN
                UPDATE studio_assigned_tasks
                SET extension_history = v_history
                WHERE id = v_prev_task;
            END IF;
            SET v_entry_num = 0;
            SET v_history   = JSON_ARRAY();
            SET v_prev_task = v_task_id;
        END IF;

        SET v_entry_num = v_entry_num + 1;

        -- Extract from existing metadata JSON stored by extend_task_deadline.php
        SET v_history = JSON_ARRAY_APPEND(
            v_history,
            '$',
            JSON_OBJECT(
                'extension_number',  v_entry_num,
                'user_id',           v_user_id,
                'user_name',         COALESCE(JSON_UNQUOTE(JSON_EXTRACT(v_meta, '$.extended_by_user')), 'Unknown'),
                'previous_due_date', JSON_UNQUOTE(JSON_EXTRACT(v_meta, '$.old_date')),
                'previous_due_time', JSON_UNQUOTE(JSON_EXTRACT(v_meta, '$.old_time')),
                'new_due_date',      JSON_UNQUOTE(JSON_EXTRACT(v_meta, '$.new_date')),
                'new_due_time',      JSON_UNQUOTE(JSON_EXTRACT(v_meta, '$.new_time')),
                'extended_at',       DATE_FORMAT(v_created, '%Y-%m-%d %H:%i:%s'),
                'days_added',        COALESCE(JSON_EXTRACT(v_meta, '$.days_added'), NULL)
            )
        );
    END LOOP;

    -- Save the last task's history
    IF v_prev_task > 0 AND v_history IS NOT NULL THEN
        UPDATE studio_assigned_tasks
        SET extension_history = v_history
        WHERE id = v_prev_task;
    END IF;

    CLOSE cur;
END$$

DELIMITER ;

-- Run it
CALL backfill_ext_history();

-- Cleanup
DROP PROCEDURE IF EXISTS backfill_ext_history;

-- ============================================================
-- Verify: check tasks that now have history
-- ============================================================
SELECT id, project_name, extension_count,
       JSON_LENGTH(extension_history) AS history_entries
FROM studio_assigned_tasks
WHERE extension_history IS NOT NULL
  AND JSON_LENGTH(extension_history) > 0;
