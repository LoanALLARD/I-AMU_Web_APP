-- Generate a unique 6-character access code when a session is scheduled.
CREATE OR REPLACE FUNCTION generate_session_access_code()
RETURNS TRIGGER AS $$
DECLARE
    candidate CHAR(6);
BEGIN
    IF NEW.status IN ('SCHEDULED', 'ACTIVE') AND NEW.access_code IS NULL THEN
        LOOP
            candidate := upper(substring(md5(random()::text) FROM 1 FOR 6));
            EXIT WHEN NOT EXISTS (SELECT 1 FROM sessions WHERE access_code = candidate);
        END LOOP;
        NEW.access_code := candidate;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_generate_session_access_code
BEFORE INSERT OR UPDATE ON sessions
FOR EACH ROW
EXECUTE FUNCTION generate_session_access_code();

-- Enforce single-role ownership: a user may hold at most one role.
-- Inserting any role row fails if the user already has a row in any other
-- role table (students / teachers / researchers / department_administrators).
CREATE OR REPLACE FUNCTION enforce_role_exclusivity()
RETURNS TRIGGER AS $$
DECLARE
    new_role TEXT := TG_ARGV[0];
    has_other_role BOOLEAN;
BEGIN
    SELECT
        EXISTS(SELECT 1 FROM students                  WHERE id = NEW.id)
     OR EXISTS(SELECT 1 FROM teachers                  WHERE id = NEW.id)
     OR EXISTS(SELECT 1 FROM researchers               WHERE id = NEW.id)
     OR EXISTS(SELECT 1 FROM department_administrators WHERE id = NEW.id)
    INTO has_other_role;

    IF has_other_role THEN
        RAISE EXCEPTION 'cannot add % role: user already holds another role', new_role
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_students_role_exclusivity
    BEFORE INSERT ON students
    FOR EACH ROW EXECUTE FUNCTION enforce_role_exclusivity('student');

CREATE TRIGGER trg_teachers_role_exclusivity
    BEFORE INSERT ON teachers
    FOR EACH ROW EXECUTE FUNCTION enforce_role_exclusivity('teacher');

CREATE TRIGGER trg_researchers_role_exclusivity
    BEFORE INSERT ON researchers
    FOR EACH ROW EXECUTE FUNCTION enforce_role_exclusivity('researcher');

CREATE TRIGGER trg_dept_admins_role_exclusivity
    BEFORE INSERT ON department_administrators
    FOR EACH ROW EXECUTE FUNCTION enforce_role_exclusivity('department_administrator');