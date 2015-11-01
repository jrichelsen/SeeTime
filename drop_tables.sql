USE ozidar;

DROP PROCEDURE IF EXISTS describe_event;
DROP PROCEDURE IF EXISTS describe_calendar;
DROP PROCEDURE IF EXISTS get_events;
DROP PROCEDURE IF EXISTS get_role;
DROP PROCEDURE IF EXISTS get_editable_calendars;
DROP PROCEDURE IF EXISTS get_viewable_calendars;
DROP PROCEDURE IF EXISTS downgrade_admin_to_viewer;
DROP PROCEDURE IF EXISTS upgrade_viewer_to_admin;
DROP PROCEDURE IF EXISTS remove_viewer;
DROP PROCEDURE IF EXISTS add_viewer;
DROP PROCEDURE IF EXISTS remove_admin;
DROP PROCEDURE IF EXISTS add_admin;
DROP PROCEDURE IF EXISTS create_event;
DROP PROCEDURE IF EXISTS create_calendar;
DROP PROCEDURE IF EXISTS create_person;

DROP TRIGGER IF EXISTS ensure_admin_delete;
DROP TRIGGER IF EXISTS ensure_admin_update;

DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS calendars;
DROP TABLE IF EXISTS persons;