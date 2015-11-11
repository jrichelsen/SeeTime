/*
THINGS I HATE ABOUT MYSQL 5.5
no multiple trigger conditions
no multiple triggers for the same condition
delete triggers on referenced tables not activated on cascade deletes
syntax errors are useless
Swedish characters?

TODO
disallow insert for modified fields
make sole_admin work
*/

USE ozidar;

SET collation_connection = 'utf8_general_ci';
ALTER DATABASE ozidar CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE users (
	username VARCHAR(15) PRIMARY KEY, /* NO UPDATE */ -- see triggers for regex
	pwd_hash CHAR(32) NOT NULL, -- see triggers for pwd (not pwd_hash) regex
	salt CHAR(36) NOT NULL, /* NO UPDATE */
	ts_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
ALTER TABLE users CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE calendars (
	calendar_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT, /* NO UPDATE */
	calendar_name VARCHAR(127),
	ts_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
ALTER TABLE calendars CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE members (
	username VARCHAR(15), /* NO UPDATE */
	calendar_id INT UNSIGNED, /* NO UPDATE */
	role ENUM('admin', 'viewer') NOT NULL,
	ts_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY(username, calendar_id), -- each user can only have one role per calendar
	FOREIGN KEY(username) REFERENCES users(username) ON DELETE CASCADE,
	FOREIGN KEY(calendar_id) REFERENCES calendars(calendar_id) ON DELETE CASCADE
);
ALTER TABLE members CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE events (
	event_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT, /* NO UPDATE */
	calendar_id INT UNSIGNED,
	event_title VARCHAR(63) NOT NULL,
	start_date DATETIME NOT NULL,
	duration INT UNSIGNED, -- TODO: TEST IF THIS PREVENTS NEGATIVE NUMBERS
	details VARCHAR(510),
	priority ENUM('low', 'medium', 'high'),
	repetition VARCHAR(5), -- see triggers for regex
	alert VARCHAR(5), -- see triggers for regex
	ts_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY(calendar_id) REFERENCES calendars(calendar_id)
);
ALTER TABLE events CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

DELIMITER $$

CREATE FUNCTION username_regex (
	in_username VARCHAR(15)
)
RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN(in_username REGEXP '^[[:alnum:]]{1,15}$');
END$$

CREATE FUNCTION pwd_regex (
	in_pwd VARCHAR(31)
)
RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN(in_pwd REGEXP '^[[:alnum:]]{8,31}$');
END$$

CREATE FUNCTION repetition_regex (
	in_repetition VARCHAR(5)
)
RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN(in_repetition REGEXP '^[[:digit:]]{1,4}[dwmy]$');
END$$

CREATE FUNCTION alert_regex (
	in_alert VARCHAR(5)
)
RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN(in_alert REGEXP '^[[:digit:]]{1,4}[MHdwmy]$');
END$$

CREATE FUNCTION authenticate_user (
	in_username VARCHAR(15),
	in_pwd VARCHAR(31)
)
RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN(EXISTS(SELECT username FROM users WHERE username = in_username AND pwd_hash = MD5(CONCAT(in_pwd, salt))));
END$$

CREATE FUNCTION user_exists (
	in_username VARCHAR(15)
)
RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN(EXISTS(SELECT username FROM users WHERE username = in_username));
END$$

CREATE FUNCTION calendar_exists (
	in_calendar_id INT UNSIGNED
)
RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN(EXISTS(SELECT calendar_id FROM calendars WHERE calendar_id = in_calendar_id));
END$$

CREATE FUNCTION sole_admin (
	in_username VARCHAR(15)
)
RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN FALSE;
END$$

CREATE TRIGGER users_insert BEFORE INSERT ON users FOR EACH ROW BEGIN
	IF NOT username_regex(NEW.username) THEN
		SIGNAL
			SQLSTATE '45008'
			SET MESSAGE_TEXT = 'username does not meet restrictions ^[[:alnum:]]{1,15}$';
	END IF;
END$$

CREATE TRIGGER users_update BEFORE UPDATE ON users FOR EACH ROW BEGIN
	IF NEW.username <> OLD.username THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'cannot change username';
	END IF;
	IF NEW.salt <> OLD.salt THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'cannot change salt';
	END IF;
END$$

CREATE TRIGGER calendars_update BEFORE UPDATE ON calendars FOR EACH ROW BEGIN
	IF NEW.calendar_id <> OLD.calendar_id THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'cannot change calendar_id';
	END IF;
END$$

CREATE TRIGGER members_update BEFORE UPDATE ON members FOR EACH ROW BEGIN
	IF NEW.username <> OLD.username THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'cannot change username for member';
	END IF;
	IF NEW.calendar_id <> OLD.calendar_id THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'cannot change calendar_id for member';
	END IF;
	IF (OLD.role = 'admin') AND (NEW.role <> 'admin') AND ('admin' NOT IN (SELECT role FROM members WHERE calendar_id = OLD.calendar_id AND username <> OLD.username)) THEN
		SIGNAL
			SQLSTATE '45021'
			SET MESSAGE_TEXT = 'cannot remove sole admin of calendar';
	END IF;
END$$

CREATE TRIGGER members_delete BEFORE DELETE ON members FOR EACH ROW BEGIN -- NOTE: not run on cascade delete for deleting member
	IF (OLD.role = 'admin') AND ('admin' NOT IN (SELECT role FROM members WHERE calendar_id = OLD.calendar_id AND username <> OLD.username)) THEN
		SIGNAL
			SQLSTATE '45021'
			SET MESSAGE_TEXT = 'cannot remove sole admin of calendar';
	END IF;
END$$

CREATE TRIGGER events_insert BEFORE INSERT ON events FOR EACH ROW BEGIN
	IF NOT repetition_regex(NEW.repetition) THEN
		SIGNAL
			SQLSTATE '45100' -- TODO: NEED CORRECT ERROR CODE AND MESSAGE
			SET MESSAGE_TEXT = 'repetition regex failed';
	END IF;
	IF NOT alert_regex(NEW.alert) THEN
		SIGNAL
			SQLSTATE '45101' -- TODO: NEED CORRECT ERROR CODE AND MESSAGE
			SET MESSAGE_TEXT = 'alert regex failed';
	END IF;
END$$

CREATE TRIGGER events_update BEFORE UPDATE ON events FOR EACH ROW BEGIN
	IF NEW.event_id <> OLD.event_id THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'cannot change event_id';
	END IF;
	IF NEW.calendar_id <> OLD.calendar_id THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'cannot change event calendar_id';
	END IF;
	IF NOT repetition_regex(NEW.repetition) THEN
		SIGNAL
			SQLSTATE '45100' -- TODO: NEED CORRECT ERROR CODE AND MESSAGE
			SET MESSAGE_TEXT = 'repetition regex failed';
	END IF;
	IF NOT alert_regex(NEW.alert) THEN
		SIGNAL
			SQLSTATE '45101' -- TODO: NEED CORRECT ERROR CODE AND MESSAGE
			SET MESSAGE_TEXT = 'alert regex failed';
	END IF;
END$$

CREATE PROCEDURE create_user (
	IN in_username VARCHAR(15),
	IN in_pwd VARCHAR(31),
	OUT out_error_8 BOOLEAN,
	OUT out_error_9 BOOLEAN,
	OUT out_error_10 BOOLEAN
) BEGIN
	DECLARE EXIT HANDLER FOR SQLSTATE '45008' SET out_error_8 = TRUE;
	DECLARE EXIT HANDLER FOR 1062 SET out_error_10 = TRUE;
	SET out_error_8 = FALSE;
	SET out_error_9 = FALSE;
	SET out_error_10 = FALSE;
	IF NOT pwd_regex(in_pwd) THEN
		SET out_error_9 = TRUE;
		IF NOT username_regex(in_username) THEN
			SET out_error_8 = TRUE;
		END IF;
		IF user_exists(in_username) THEN
			SET out_error_10 = TRUE;
		END IF;
	ELSE
		SET @my_salt = UUID();
		INSERT INTO users (
			username,
			pwd_hash,
			salt
		)
		VALUES (
			in_username,
			MD5(CONCAT(in_pwd, @my_salt)),
			@my_salt
		);
	END IF;
END$$

CREATE PROCEDURE change_pwd (
	IN in_username VARCHAR(15),
	IN in_old_pwd VARCHAR(31),
	IN in_new_pwd VARCHAR(31),
	OUT out_error_8 BOOLEAN,
	OUT out_error_16 BOOLEAN,
	OUT out_error_17 BOOLEAN,
	OUT out_error_18 BOOLEAN
) BEGIN
	SET out_error_8 = FALSE;
	SET out_error_16 = FALSE;
	SET out_error_17 = FALSE;
	SET out_error_18 = FALSE;
	SET @do_change = TRUE;
	IF NOT username_regex(in_username) THEN
		SET out_error_8 = TRUE;
		SET @do_change = FALSE;
	END IF;
	IF NOT pwd_regex(in_old_pwd) THEN
		SET out_error_16 = TRUE;
		SET @do_change = FALSE;
	END IF;
	IF NOT pwd_regex(in_new_pwd) THEN
		SET out_error_17 = TRUE;
		SET @do_change = FALSE;
	END IF;
	IF authenticate_user(in_username, in_old_pwd) THEN
		IF @do_change THEN
			UPDATE users
			SET pwd_hash = MD5(CONCAT(in_new_pwd, salt))
			WHERE username = in_username;
		END IF;
	ELSE
		SET out_error_18 = TRUE;
	END IF;
END$$

CREATE PROCEDURE delete_user (
	IN in_username VARCHAR(15),
	OUT out_error_20 BOOLEAN,
	OUT out_error_21 BOOLEAN
) BEGIN
	DECLARE EXIT HANDLER FOR SQLSTATE '45021' SET out_error_21 = TRUE;
	SET out_error_20 = FALSE;
	SET out_error_21 = FALSE;
	IF NOT user_exists(in_username) THEN
		SET out_error_20 = TRUE;
	ELSE
		DELETE FROM users
		WHERE username = in_username;
	END IF;
END$$

CREATE PROCEDURE get_calendars_roles (
	IN in_username VARCHAR(15),
	OUT out_error_20 BOOLEAN
) BEGIN 
	SET out_error_20 = FALSE;
	IF NOT user_exists(in_username) THEN
		SET out_error_20 = TRUE;
	ELSE
		SELECT calendar_id, role
		FROM members
		WHERE username = in_username;
	END IF;
END$$

CREATE PROCEDURE create_calendar (
	IN in_calendar_name VARCHAR(127),
	IN in_username VARCHAR(15),
	OUT out_error_20 BOOLEAN,
	OUT out_calendar_id INT UNSIGNED
) BEGIN
	SET out_error_20 = FALSE;
	SET out_calendar_id = NULL;
	IF NOT user_exists(in_username) THEN
		SET out_error_20 = TRUE;
	ELSE
		INSERT INTO calendars (calendar_name)
		VALUES (in_calendar_name);

		SET out_calendar_id = LAST_INSERT_ID();

		INSERT INTO members (
			username,
			calendar_id,
			role
		)
		VALUES (
			in_username,
			out_calendar_id,
			'admin'
		);
	END IF;
END$$

DELIMITER ;