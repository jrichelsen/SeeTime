USE ozidar;

DROP TRIGGER IF EXISTS ensure_admin_delete;
DROP TRIGGER IF EXISTS ensure_admin_update;

DROP TABLE IF EXISTS event;
DROP TABLE IF EXISTS member;
DROP TABLE IF EXISTS calendar;
DROP TABLE IF EXISTS person;