/*
THINGS I HATE ABOUT MYSQL 5.5
* no multiple trigger conditions
* delete triggers on referenced tables not activated on cascade deletes
* syntax errors are useless
* Swedish characters?
* strict mode per database
* no microsecond precision for timestamp

TODO
* disable meddling with ts_timestamp
* disallow inserting IDs
*/

USE eonattu;
SET SESSION sql_mode = 'strict_all_tables';

SET collation_connection := 'utf8_general_ci';
ALTER DATABASE eonattu CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE users (
	username VARCHAR(15) PRIMARY KEY, /* NO UPDATE */ -- checked by username_regex
	pwd_hash CHAR(32) NOT NULL, -- checked by is_hex_string
	salt CHAR(32) NOT NULL, /* NO UPDATE */ -- checked by is_hex_string
	ts_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
ALTER TABLE users CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE tokens (
	username VARCHAR(15) PRIMARY KEY, /* NO UPDATE */
	token CHAR(32) NOT NULL, -- checked by is_hex_string
	ts_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY(username) REFERENCES users(username) ON DELETE CASCADE
);
ALTER TABLE tokens CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE calendars (
	calendar_id CHAR(128) PRIMARY KEY, /* NO UPDATE */ -- checked by is_hex_string
	calendar_name VARCHAR(127) NOT NULL, -- empty strings are converted to NULL
	ts_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
ALTER TABLE calendars CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE members (
	username VARCHAR(15), /* NO UPDATE */
	calendar_id CHAR(128), /* NO UPDATE */
	role ENUM('admin', 'viewer') NOT NULL,
	ts_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY(username, calendar_id), -- each user can only have one role per calendar
	FOREIGN KEY(username) REFERENCES users(username) ON DELETE CASCADE, -- WARNING: members delete trigger not called
	FOREIGN KEY(calendar_id) REFERENCES calendars(calendar_id) ON DELETE CASCADE -- WARNING: members delete trigger not called
);
ALTER TABLE members CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE events (
	event_id CHAR(128) PRIMARY KEY, /* NO UPDATE */ -- checked by is_hex_string
	calendar_id CHAR(128) NOT NULL,
	event_title VARCHAR(127) NOT NULL, -- empty strings are converted to NULL
	start_date DATETIME NOT NULL,
	duration INT UNSIGNED, -- 0 converted to NULL
	details VARCHAR(510), -- empty strings are converted to NULL
	priority ENUM('low', 'medium', 'high'),
	repetition VARCHAR(5), -- ^[[:digit:]]{1,4}[dwmy]$
	alert VARCHAR(5), -- ^[[:digit:]]{1,4}[MHdwmy]$
	ts_modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY(calendar_id) REFERENCES calendars(calendar_id) ON DELETE CASCADE
);
ALTER TABLE events CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci;

DELIMITER $$

CREATE FUNCTION username_regex (
	in_username VARCHAR(1022)
) RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN in_username REGEXP '^[[:alpha:]][[:alnum:]]{0,14}$';
END$$

CREATE FUNCTION pwd_regex (
	in_pwd VARCHAR(1022)
) RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN in_pwd REGEXP '^[[:alnum:]!@#\$%^&*]{8,31}$';
END$$

CREATE FUNCTION is_hex_string (
	in_str VARCHAR(1022),
	in_min INT,
	in_max INT
) RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN in_str REGEXP '^[[:xdigit:]]*$' AND (CHAR_LENGTH(in_str) BETWEEN in_min AND in_max);
END$$

CREATE FUNCTION is_string (
	in_str VARCHAR(1022),
	in_min INT,
	in_max INT
) RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN CHAR_LENGTH(in_str) BETWEEN in_min AND in_max;
END$$

CREATE FUNCTION is_valid_role (
	in_role VARCHAR(1022)
) RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN in_role = 'admin' OR in_role = 'viewer';
END$$

CREATE FUNCTION datetime_regex (
	in_datetime VARCHAR(20)
) RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN in_datetime REGEXP '^(19|20)[[:digit:]]{2}-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01]) ([01][[:digit:]]|2[0-3]):([0-5][[:digit:]]):([0-5][[:digit:]])$';
END$$

CREATE FUNCTION is_valid_priority (
	in_priority VARCHAR(1022)
) RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN in_priority = 'low' OR in_priority = 'medium' OR in_priority = 'high';
END$$

CREATE FUNCTION repetition_regex (
	in_repetition VARCHAR(1022)
) RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN in_repetition REGEXP '^[[:digit:]]{1,4}[dwmy]$';
END$$

CREATE FUNCTION alert_regex (
	in_alert VARCHAR(1022)
) RETURNS BOOLEAN DETERMINISTIC BEGIN
	RETURN in_alert REGEXP '^[[:digit:]]{1,4}[MHdwmy]$';
END$$

CREATE FUNCTION token_exists (
	in_token VARCHAR(1022)
) RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN(EXISTS(SELECT token
					FROM tokens
					WHERE token = in_token));
END$$

CREATE FUNCTION token_to_username (
	in_token VARCHAR(1022)
) RETURNS VARCHAR(15) NOT DETERMINISTIC BEGIN
	DECLARE return_username VARCHAR(15);
	SET return_username := '';

	SELECT username
	INTO return_username
    FROM tokens
    WHERE token = in_token;

	RETURN return_username;
END$$

CREATE FUNCTION authenticate_user (
	in_username VARCHAR(1022),
	in_pwd VARCHAR(1022)
) RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN EXISTS(SELECT username
					FROM users
					WHERE
						username = in_username AND
						pwd_hash = MD5(CONCAT(in_pwd, salt)));
END$$

CREATE FUNCTION user_exists (
	in_username VARCHAR(1022)
) RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN EXISTS(SELECT username
					FROM users
					WHERE username = in_username);
END$$

CREATE FUNCTION sole_admin_specific_calendar (
	in_username VARCHAR(1022),
	in_calendar_id VARCHAR(1022)
) RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN is_an_admin(in_username, in_calendar_id) AND ('admin' NOT IN (SELECT role FROM members WHERE calendar_id = in_calendar_id AND username <> in_username));
END$$

CREATE FUNCTION sole_admin_any_calendar (
	in_username VARCHAR(1022)
) RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN EXISTS(SELECT username
					FROM members
					WHERE sole_admin_specific_calendar(in_username, calendar_id));
END$$

CREATE FUNCTION is_a_member (
	in_username VARCHAR(1022),
	in_calendar_id VARCHAR(1022)
) RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN EXISTS(SELECT role
					FROM members
					WHERE
						username = in_username AND
						calendar_id = in_calendar_id);
END$$

CREATE FUNCTION is_an_admin (
	in_username VARCHAR(1022),
	in_calendar_id VARCHAR(1022)
) RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN EXISTS(SELECT role
					FROM members
					WHERE
						username = in_username AND
						calendar_id = in_calendar_id AND
						role = 'admin');
END$$

CREATE FUNCTION n_admins (
	in_calendar_id VARCHAR(1022)
) RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	DECLARE return_n_admins INT;

	SELECT COUNT(username)
	INTO return_n_admins
	FROM members
	WHERE
		calendar_id = in_calendar_id AND
		role = 'admin';

	RETURN return_n_admins;
END$$

CREATE FUNCTION is_event_in_calendar (
	in_event_id VARCHAR(1022),
	in_calendar_id VARCHAR(1022)
) RETURNS BOOLEAN NOT DETERMINISTIC BEGIN
	RETURN EXISTS(SELECT event_id
					FROM events
					WHERE
						event_id = in_event_id AND
						calendar_id = in_calendar_id);
END$$

CREATE TRIGGER users_insert BEFORE INSERT ON users FOR EACH ROW BEGIN
	IF NOT username_regex(NEW.username) THEN
		SIGNAL
			SQLSTATE '45000'
			SET
				MYSQL_ERRNO = 8,
				MESSAGE_TEXT = 'username must be between 1 and 15 alphanumeric characters and must start with a letter';
	END IF;
	IF NOT is_hex_string(NEW.pwd_hash, 32, 32) THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'password hash must be 32-character hexadecimal string';
	END IF;
	IF NOT is_hex_string(NEW.salt, 32, 32) THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'salt must be 32-character hexadecimal string';
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
	IF NOT is_hex_string(NEW.pwd_hash, 32, 32) THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'password hash must be 32-character hexadecimal string';
	END IF;
END$$

CREATE TRIGGER users_delete BEFORE DELETE ON users FOR EACH ROW BEGIN
	IF sole_admin_any_calendar(OLD.username) THEN
		SIGNAL
			SQLSTATE '45000'
			SET
				MYSQL_ERRNO = 21,
				MESSAGE_TEXT = 'cannot remove sole admin of calendar';
	END IF;
END$$

CREATE TRIGGER tokens_insert BEFORE INSERT ON tokens FOR EACH ROW BEGIN
	IF NOT is_hex_string(NEW.token, 32, 32) THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'token must be 32-character hexadecimal string';
	END IF;
END$$

CREATE TRIGGER tokens_update BEFORE UPDATE ON tokens FOR EACH ROW BEGIN
	IF NEW.username <> OLD.username THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'cannot change username';
	END IF;
	IF NOT is_hex_string(NEW.token, 32, 32) THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'token must be 32-character hexadecimal string';
	END IF;
END$$

CREATE TRIGGER tokens_delete BEFORE DELETE ON tokens FOR EACH ROW BEGIN
	SIGNAL
		SQLSTATE '45000'
		SET MESSAGE_TEXT = 'cannot delete token';
END$$

CREATE TRIGGER calendars_insert BEFORE INSERT ON calendars FOR EACH ROW BEGIN
	IF NEW.calendar_name = '' THEN
		SET NEW.calendar_name := NULL;
	END IF;
	IF NOT is_hex_string(NEW.calendar_id, 128, 128) THEN
		SIGNAL
			SQLSTATE '45000'
			SET
				MYSQL_ERRNO = 28,
				MESSAGE_TEXT = 'calendar_id must be 128-character hexadecimal string';
	END IF;
END$$

CREATE TRIGGER calendars_update BEFORE UPDATE ON calendars FOR EACH ROW BEGIN
	IF NEW.calendar_name = '' THEN
		SET NEW.calendar_name := NULL;
	END IF;
	IF NEW.calendar_id <> OLD.calendar_id THEN
		SIGNAL
			SQLSTATE '45000'
			SET MESSAGE_TEXT = 'cannot change calendar_id';
	END IF;
END$$

CREATE TRIGGER calendars_delete BEFORE DELETE ON calendars FOR EACH ROW BEGIN
	IF n_admins(OLD.calendar_id) > 1 THEN
		SIGNAL
			SQLSTATE '45000'
			SET
				MYSQL_ERRNO = 34,
				MESSAGE_TEXT = 'cannot delete calendar when other admins exist';
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
	IF sole_admin_specific_calendar(OLD.username, OLD.calendar_id) AND NEW.role <> 'admin' THEN
		SIGNAL
			SQLSTATE '45000'
			SET
				MYSQL_ERRNO = 21,
				MESSAGE_TEXT = 'cannot remove sole admin of calendar';
	END IF;
END$$

CREATE TRIGGER members_delete BEFORE DELETE ON members FOR EACH ROW BEGIN 
	IF sole_admin_specific_calendar(OLD.username, OLD.calendar_id) THEN
		SIGNAL
			SQLSTATE '45000'
			SET
				MYSQL_ERRNO = 21,
				MESSAGE_TEXT = 'cannot remove sole admin of calendar';
	END IF;
END$$

CREATE TRIGGER events_insert BEFORE INSERT ON events FOR EACH ROW BEGIN
	IF NEW.event_title = '' THEN
		SET NEW.event_title := NULL;
	END IF;
	IF NEW.duration = 0 THEN
		SET NEW.duration := NULL;
	END IF;
	IF NEW.details = '' THEN
		SET NEW.details := NULL;
	END IF;
	IF NOT is_hex_string(NEW.event_id, 128, 128) THEN
		SIGNAL
			SQLSTATE '45000'
			SET
				MYSQL_ERRNO = 65,
				MESSAGE_TEXT = 'event_id must be 128-character hexadecimal string';
	END IF;
	IF NOT repetition_regex(NEW.repetition) THEN
		SIGNAL
			SQLSTATE '45000'
			SET
				MYSQL_ERRNO = 71,
				MESSAGE_TEXT = 'repetition does not meet restrictions ^[[:digit:]]{1,4}[dwmy]$';
	END IF;
	IF NOT alert_regex(NEW.alert) THEN
		SIGNAL
			SQLSTATE '45000'
			SET
				MYSQL_ERRNO = 72,
				MESSAGE_TEXT = 'alert does not meet restrictions ^[[:digit:]]{1,4}[MHdwmy]$';
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
	IF NEW.event_title = '' THEN
		SET NEW.event_title := NULL;
	END IF;
	IF NEW.duration = 0 THEN
		SET NEW.duration := NULL;
	END IF;
	IF NEW.details = '' THEN
		SET NEW.details := NULL;
	END IF;
	IF NOT repetition_regex(NEW.repetition) THEN
		SIGNAL
			SQLSTATE '45000'
			SET
				MYSQL_ERRNO = 71,
				MESSAGE_TEXT = 'repetition does not meet restrictions ^[[:digit:]]{1,4}[dwmy]$';
	END IF;
	IF NOT alert_regex(NEW.alert) THEN
		SIGNAL
			SQLSTATE '45000'
			SET
				MYSQL_ERRNO = 72,
				MESSAGE_TEXT = 'alert does not meet restrictions ^[[:digit:]]{1,4}[MHdwmy]$';
	END IF;
END$$

CREATE PROCEDURE create_user (
	IN in_username VARCHAR(1022),
	IN in_pwd VARCHAR(1022),
	OUT out_error_8 BOOLEAN,
	OUT out_error_9 BOOLEAN,
	OUT out_error_10 BOOLEAN
) BEGIN
	DECLARE EXIT HANDLER FOR 1406 SET out_error_8 := TRUE;
	DECLARE EXIT HANDLER FOR 8 SET out_error_8 := TRUE;
	DECLARE EXIT HANDLER FOR 1062 SET out_error_10 := TRUE;

	IF NOT pwd_regex(in_pwd) THEN
		SET out_error_9 := TRUE;
		IF NOT username_regex(in_username) THEN
			SET out_error_8 := TRUE;
		END IF;
		IF user_exists(in_username) THEN
			SET out_error_10 := TRUE;
		END IF;
	ELSE
		SET @my_salt := REPLACE(UUID(),'-','');
		INSERT INTO users (
			username,
			pwd_hash,
			salt
		) VALUES (
			in_username,
			MD5(CONCAT(in_pwd, @my_salt)),
			@my_salt
		);
	END IF;
END$$

CREATE PROCEDURE change_pwd (
	IN in_token VARCHAR(1022),
	IN in_username VARCHAR(1022),
	IN in_old_pwd VARCHAR(1022),
	IN in_new_pwd VARCHAR(1022),
	OUT out_error_17 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_19 BOOLEAN
) BEGIN
	SET @do_change := TRUE;
	IF NOT pwd_regex(in_new_pwd) THEN
		SET out_error_17 := TRUE;
		SET @do_change := FALSE;
	END IF;
	IF token_to_username(in_token) <> in_username THEN
		SET out_error_18 := TRUE;
	ELSEIF NOT authenticate_user(in_username, in_old_pwd) THEN
		SET out_error_19 := TRUE;
	ELSEIF @do_change THEN
		UPDATE users
		SET pwd_hash := MD5(CONCAT(in_new_pwd, salt))
		WHERE username = in_username;
	END IF;
END$$

CREATE PROCEDURE delete_user (
	IN in_token VARCHAR(1022),
	IN in_username VARCHAR(1022),
	OUT out_error_18 BOOLEAN,
	OUT out_error_21 BOOLEAN
) BEGIN
	DECLARE EXIT HANDLER FOR 21 SET out_error_21 := TRUE;

	IF token_to_username(in_token) <> in_username THEN
		SET out_error_18 = TRUE;
	ELSE
		DELETE FROM users
		WHERE username = in_username;
	END IF;
END$$

CREATE PROCEDURE get_token (
	IN in_username VARCHAR(1022),
	IN in_pwd VARCHAR(1022),
	OUT out_error_19 BOOLEAN,
	OUT out_token CHAR(32)
) BEGIN
	IF NOT authenticate_user(in_username, in_pwd) THEN
		SET out_error_19 := TRUE;
	ELSE
		SET @my_token := REPLACE(UUID(),'-','');
		INSERT INTO tokens (
			username,
			token
		) VALUES (
			in_username,
			@my_token
		) ON DUPLICATE KEY UPDATE token = @my_token;
		SET out_token := @my_token;
	END IF;
END$$

CREATE PROCEDURE get_calendars (
	IN in_token VARCHAR(1022),
	OUT out_error_18 BOOLEAN
) BEGIN 
	IF NOT token_exists(in_token) THEN
		SET out_error_18 := TRUE;
		SELECT NULL;
	ELSE
		SELECT calendar_id
		FROM members
		WHERE username = token_to_username(in_token);
	END IF;
END$$

CREATE PROCEDURE create_calendar (
	IN in_token VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	IN in_calendar_name VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_29 BOOLEAN,
	OUT out_error_30 BOOLEAN,
	OUT out_calendar_id CHAR(128)
) BEGIN
	DECLARE EXIT HANDLER FOR 28 SET out_error_28 := TRUE;
	DECLARE EXIT HANDLER FOR 1062 SET out_error_30 := TRUE;

	SET @do_insert := TRUE;
	IF NOT token_exists(in_token) THEN
		SET out_error_18 := TRUE;
		SET @do_insert := FALSE;
	END IF;
	IF NOT is_string(in_calendar_name, 1, 127) THEN
		SET out_error_29 := TRUE;
		SET @do_insert := FALSE;
	END IF;

	IF @do_insert THEN
		INSERT INTO calendars (
			calendar_id,
			calendar_name
		) VALUES (
			in_calendar_id,
			in_calendar_name
		);

		INSERT INTO members (
			username,
			calendar_id,
			role
		) VALUES (
			token_to_username(in_token),
			in_calendar_id,
			'admin'
		);
		SET out_calendar_id := in_calendar_id;
	ELSEIF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
		SET @do_insert := FALSE;
	END IF;
END$$

CREATE PROCEDURE get_calendar (
	IN in_token VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN
) BEGIN
	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
		SELECT NULL;
	ELSEIF NOT is_a_member(token_to_username(in_token), in_calendar_id) THEN
		SET out_error_18 := TRUE;		
		SELECT NULL;
	ELSE
		SELECT
			calendar_name,
			ts_modified
		FROM calendars
		WHERE calendars.calendar_id = in_calendar_id;
	END IF;
END$$

CREATE PROCEDURE edit_calendar (
	IN in_token VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	IN in_calendar_name VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_29 BOOLEAN
) BEGIN
	DECLARE EXIT HANDLER FOR 28 SET out_error_28 := TRUE;
	DECLARE EXIT HANDLER FOR 1048 SET out_error_29 := TRUE;
	DECLARE EXIT HANDLER FOR 1406 SET out_error_29 := TRUE;

	SET @do_update := TRUE;
	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
		SET @do_insert := FALSE;
	ELSEIF NOT is_an_admin(token_to_username(in_token), in_calendar_id) THEN
		SET out_error_18 := TRUE;
		SET @do_update := FALSE;
	END IF;
	
	IF @do_update THEN
		UPDATE calendars
		SET calendar_name := in_calendar_name
		WHERE calendar_id = in_calendar_id;
	ELSEIF NOT is_string(in_calendar_name, 1, 127) THEN
		SET out_error_29 := TRUE;
		SET @do_update := FALSE;
	END IF;
END$$

CREATE PROCEDURE delete_calendar (
	IN in_token VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_34 BOOLEAN
) BEGIN
	DECLARE EXIT HANDLER FOR 34 SET out_error_34 := TRUE;

	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
	ELSEIF NOT is_an_admin(token_to_username(in_token), in_calendar_id) THEN
		SET out_error_18 := TRUE;
	ELSE
		DELETE FROM calendars
		WHERE calendar_id = in_calendar_id;
	END IF;
END$$

CREATE PROCEDURE get_members (
	IN in_token VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN
) BEGIN
	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
		SELECT NULL;
	ELSEIF NOT is_a_member(token_to_username(in_token), in_calendar_id) THEN
		SET out_error_18 := TRUE;
		SELECT NULL;
	ELSE
		SELECT
			username,
			ts_modified
		FROM members
		WHERE calendar_id = in_calendar_id;
	END IF;
END$$

CREATE PROCEDURE add_member (
	IN in_token VARCHAR(1022),
	IN in_username VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	IN in_role VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_39 BOOLEAN,
	OUT out_error_40 BOOLEAN
) BEGIN
	DECLARE EXIT HANDLER FOR 1452 SET out_error_18 := TRUE;
	DECLARE EXIT HANDLER FOR 1062 SET out_error_40 := TRUE;

	SET @do_insert := TRUE;
	IF NOT user_exists(in_username) THEN
		SET out_error_18 := TRUE;
		SET @do_insert := FALSE;
	END IF;
	IF NOT is_valid_role(in_role) THEN
		SET out_error_39 := TRUE;
		SET @do_insert := FALSE;
	END IF;
	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
	ELSEIF NOT is_an_admin(token_to_username(in_token), in_calendar_id) THEN
		SET out_error_18 := TRUE;
	ELSEIF @do_insert THEN
		INSERT INTO members (
			username,
			calendar_id,
			role
		) VALUES (
			in_username,
			in_calendar_id,
			in_role
		);
	END IF;
END$$

CREATE PROCEDURE get_member (
	IN in_token VARCHAR(1022),
	IN in_username VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_42 BOOLEAN
) BEGIN
	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
		SELECT NULL;
	ELSE
		IF NOT is_a_member(token_to_username(in_token), in_calendar_id) THEN
			SET out_error_18 := TRUE;
			SELECT NULL;
		ELSEIF NOT is_a_member(in_username, in_calendar_id) THEN
			SET out_error_42 := TRUE;
			SELECT NULL;
		ELSE
			SELECT
				role,
				ts_modified
			FROM members
			WHERE
				username = in_username AND
				calendar_id = in_calendar_id;
		END IF;
	END IF;
END$$

CREATE PROCEDURE edit_member (
	IN in_token VARCHAR(1022),
	IN in_username VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	IN in_role VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_39 BOOLEAN,
	OUT out_error_42 BOOLEAN,
	OUT out_error_44 BOOLEAN,
	OUT out_error_21 BOOLEAN
) BEGIN
	DECLARE EXIT HANDLER FOR 1265 SET out_error_39 := TRUE;
	DECLARE EXIT HANDLER FOR 21 SET out_error_21 := TRUE;

	SET @do_update := TRUE;
	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
		SET @do_update := FALSE;
	ELSE
		IF NOT is_an_admin(token_to_username(in_token), in_calendar_id) THEN
			SET out_error_18 := TRUE;
			SET @do_update := FALSE;
		ELSEIF NOT is_a_member(in_username, in_calendar_id) THEN
			SET out_error_42 := TRUE;
			SET @do_update := FALSE;
		ELSEIF is_an_admin(in_username, in_calendar_id) AND token_to_username(in_token) <> in_username THEN
			SET out_error_44 := TRUE;
			SET @do_update := FALSE;
		END IF;
	END IF;

	IF @do_update THEN
		UPDATE members
		SET role := in_role
		WHERE
			username = in_username AND
			calendar_id = in_calendar_id;
	ELSEIF NOT is_valid_role(in_role) THEN
		SET out_error_39 := TRUE;
	END IF;
END$$

CREATE PROCEDURE delete_member (
	IN in_token VARCHAR(1022),
	IN in_username VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_42 BOOLEAN,
	OUT out_error_46 BOOLEAN,
	OUT out_error_21 BOOLEAN
) BEGIN
	DECLARE EXIT HANDLER FOR 21 SET out_error_21 := TRUE;

	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
	ELSE
		IF NOT is_a_member(token_to_username(in_token), in_calendar_id) THEN
			SET out_error_18 := TRUE;
		ELSEIF NOT is_a_member(in_username, in_calendar_id) THEN
			SET out_error_42 := TRUE;
		ELSEIF (NOT is_an_admin(token_to_username(in_token), in_calendar_id)) AND token_to_username(in_token) <> in_username THEN
			SET out_error_18 := TRUE;
		ELSEIF is_an_admin(in_username, in_calendar_id) AND token_to_username(in_token) <> in_username THEN
			SET out_error_46 := TRUE;
		ELSE
			DELETE FROM members
			WHERE
				username = in_username AND
				calendar_id = in_calendar_id;
		END IF;
	END IF;
END$$

CREATE PROCEDURE get_events (
	IN in_token VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN
) BEGIN 
	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
		SELECT NULL;
	ELSEIF NOT is_a_member(token_to_username(in_token), in_calendar_id) THEN
		SET out_error_18 := TRUE;
		SELECT NULL;
	ELSE
		SELECT event_id
		FROM events
		WHERE calendar_id = in_calendar_id;
	END IF;
END$$

CREATE PROCEDURE create_event (
	IN in_token VARCHAR(1022),
	IN in_event_id VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	IN in_event_title VARCHAR(1022),
	IN in_start_date VARCHAR(1022),
	IN in_duration INT,
	IN in_details VARCHAR(1022),
	IN in_priority VARCHAR(1022),
	IN in_repetition VARCHAR(1022),
	IN in_alert VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_65 BOOLEAN,
	OUT out_error_66 BOOLEAN,
	OUT out_error_67 BOOLEAN,
	OUT out_error_68 BOOLEAN,
	OUT out_error_69 BOOLEAN,
	OUT out_error_70 BOOLEAN,
	OUT out_error_71 BOOLEAN,
	OUT out_error_72 BOOLEAN,
	OUT out_error_73 BOOLEAN,
	OUT out_event_id VARCHAR(1022)
) BEGIN
	DECLARE EXIT HANDLER FOR 65 SET out_error_65 := TRUE;
	DECLARE EXIT HANDLER FOR 1292 SET out_error_67 := TRUE;
	DECLARE EXIT HANDLER FOR 1264 SET out_error_68 := TRUE;
	DECLARE EXIT HANDLER FOR 1265 SET out_error_70 := TRUE;
	DECLARE EXIT HANDLER FOR 71 SET out_error_71 := TRUE;
	DECLARE EXIT HANDLER FOR 72 SET out_error_72 := TRUE;
	DECLARE EXIT HANDLER FOR 1062 SET out_error_73 := TRUE;

	SET @do_insert := TRUE;
	IF NOT is_string(in_event_title, 1, 127) THEN
		SET out_error_66 := TRUE;
		SET @do_insert := FALSE;
	END IF;
	IF NOT datetime_regex(in_start_date) THEN
		SET out_error_67 := TRUE;
		SET @do_insert := FALSE;
	END IF;
	IF is_string(in_details, 511, 1022) THEN
		SET out_error_69 := TRUE;
		SET @do_insert := FALSE;
	END IF;
	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
		SET @do_insert := FALSE;
	ELSEIF NOT is_an_admin(token_to_username(in_token), in_calendar_id) THEN
		SET out_error_18 := TRUE;
		SET @do_insert := FALSE;
	END IF;

	IF @do_insert THEN
		INSERT INTO events (
			event_id,
			calendar_id,
			event_title,
			start_date,
			duration,
			details,
			priority,
			repetition,
			alert
		) VALUES (
			in_event_id,
			in_calendar_id,
			in_event_title,
			in_start_date,
			in_duration,
			in_details,
			in_priority,
			in_repetition,
			in_alert
		);
		SET out_event_id := in_event_id;
	ELSE
		IF NOT is_hex_string(in_event_id, 128, 128) THEN
			SET out_error_65 := TRUE;
		END IF;
		IF in_duration < 0 THEN
			SET out_error_68 := TRUE;
		END IF;
		IF NOT is_valid_priority(in_priority) THEN
			SET out_error_70 := TRUE;
		END IF;
		IF NOT repetition_regex(in_repetition) THEN
			SET out_error_71 := TRUE;
		END IF;
		IF NOT alert_regex(in_alert) THEN
			SET out_error_72 := TRUE;
		END IF;
	END IF;
END$$

CREATE PROCEDURE get_event (
	IN in_token VARCHAR(1022),
	IN in_event_id VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_65 BOOLEAN,
	OUT out_error_75 BOOLEAN
) BEGIN
	SET @do_select := TRUE;
	IF NOT is_hex_string(in_event_id, 128, 128) THEN
		SET out_error_65 := TRUE;
		SET @do_select := FALSE;
	END IF;
	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
		SET @do_select := FALSE;
	ELSEIF NOT is_a_member(token_to_username(in_token), in_calendar_id) THEN
		SET out_error_18 := TRUE;
		SET @do_select := FALSE;
	ELSEIF out_error_65 IS NULL AND NOT is_event_in_calendar(in_event_id, in_calendar_id) THEN
		SET out_error_75 := TRUE;
		SET @do_select := FALSE;
	END IF;

	IF @do_select THEN
		SELECT
			event_title,
			start_date,
			duration,
			details,
			priority,
			repetition,
			alert,
			ts_modified
		FROM events
		WHERE
			event_id = in_event_id AND
			calendar_id = in_calendar_id;
	ELSE
		SELECT NULL;
	END IF;
END$$

CREATE PROCEDURE edit_event (
	IN in_token VARCHAR(1022),
	IN in_event_id VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	IN in_event_title VARCHAR(1022),
	IN in_start_date VARCHAR(1022),
	IN in_duration INT,
	IN in_details VARCHAR(1022),
	IN in_priority VARCHAR(1022),
	IN in_repetition VARCHAR(1022),
	IN in_alert VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_65 BOOLEAN,
	OUT out_error_75 BOOLEAN,
	OUT out_error_66 BOOLEAN,
	OUT out_error_67 BOOLEAN,
	OUT out_error_68 BOOLEAN,
	OUT out_error_69 BOOLEAN,
	OUT out_error_70 BOOLEAN,
	OUT out_error_71 BOOLEAN,
	OUT out_error_72 BOOLEAN
) BEGIN
	DECLARE EXIT HANDLER FOR 1292 SET out_error_67 := TRUE;
	DECLARE EXIT HANDLER FOR 1264 SET out_error_68 := TRUE;
	DECLARE EXIT HANDLER FOR 1265 SET out_error_70 := TRUE;
	DECLARE EXIT HANDLER FOR 71 SET out_error_71 := TRUE;
	DECLARE EXIT HANDLER FOR 72 SET out_error_72 := TRUE;

	SET @do_update := TRUE;
	IF NOT is_string(in_event_title, 1, 127) THEN
		SET out_error_66 := TRUE;
		SET @do_update := FALSE;
	END IF;
	IF NOT datetime_regex(in_start_date) THEN
		SET out_error_67 := TRUE;
		SET @do_update := FALSE;
	END IF;
	IF is_string(in_details, 511, 1022) THEN
		SET out_error_69 := TRUE;
		SET @do_update := FALSE;
	END IF;
	IF NOT is_hex_string(in_event_id, 128, 128) THEN
		SET out_error_65 := TRUE;
		SET @do_update := FALSE;
	END IF;
	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
		SET @do_update := FALSE;
	ELSEIF NOT is_an_admin(token_to_username(in_token), in_calendar_id) THEN
		SET out_error_18 := TRUE;
		SET @do_update := FALSE;
	ELSEIF out_error_65 IS NULL AND NOT is_event_in_calendar(in_event_id, in_calendar_id) THEN
		SET out_error_75 := TRUE;
		SET @do_update := FALSE;
	END IF;

	IF @do_update THEN
		UPDATE events
		SET
			event_title := in_event_title,
			start_date := in_start_date,
			duration := in_duration,
			details := in_details,
			priority := in_priority,
			repetition := in_repetition,
			alert := in_alert
		WHERE
			event_id = in_event_id AND
			calendar_id = in_calendar_id;
	ELSE
		IF in_duration < 0 THEN
			SET out_error_68 := TRUE;
		END IF;
		IF NOT is_valid_priority(in_priority) THEN
			SET out_error_70 := TRUE;
		END IF;
		IF NOT repetition_regex(in_repetition) THEN
			SET out_error_71 := TRUE;
		END IF;
		IF NOT alert_regex(in_alert) THEN
			SET out_error_72 := TRUE;
		END IF;
	END IF;
END$$

CREATE PROCEDURE delete_event (
	IN in_token VARCHAR(1022),
	IN in_event_id VARCHAR(1022),
	IN in_calendar_id VARCHAR(1022),
	OUT out_error_28 BOOLEAN,
	OUT out_error_18 BOOLEAN,
	OUT out_error_65 BOOLEAN,
	OUT out_error_75 BOOLEAN
) BEGIN

	SET @do_delete := TRUE;
	IF NOT is_hex_string(in_event_id, 128, 128) THEN
		SET out_error_65 := TRUE;
		SET @do_delete := FALSE;
	END IF;
	IF NOT is_hex_string(in_calendar_id, 128, 128) THEN
		SET out_error_28 := TRUE;
		SET @do_delete := FALSE;
	ELSEIF NOT is_an_admin(token_to_username(in_token), in_calendar_id) THEN
		SET out_error_18 := TRUE;
		SET @do_delete := FALSE;
	ELSEIF out_error_65 IS NULL AND NOT is_event_in_calendar(in_event_id, in_calendar_id) THEN
		SET out_error_75 := TRUE;
		SET @do_delete := FALSE;
	END IF;

	IF @do_delete THEN
		DELETE FROM events
		WHERE
			event_id = in_event_id AND
			calendar_id = in_calendar_id;
	END IF;
END$$

DELIMITER ;