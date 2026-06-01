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

-- Enforce role exclusivity rules:
--   student     : exclusive with every other role
--   researcher  : exclusive with every other role
--   teacher + department_administrator may coexist
-- TG_ARGV[0] carries the role being inserted.
CREATE OR REPLACE FUNCTION enforce_role_exclusivity()
RETURNS TRIGGER AS $$
DECLARE
    new_role TEXT := TG_ARGV[0];
    has_student BOOLEAN;
    has_teacher BOOLEAN;
    has_researcher BOOLEAN;
    has_dept_admin BOOLEAN;
BEGIN
    SELECT EXISTS(SELECT 1 FROM students                  WHERE id = NEW.id) INTO has_student;
    SELECT EXISTS(SELECT 1 FROM teachers                  WHERE id = NEW.id) INTO has_teacher;
    SELECT EXISTS(SELECT 1 FROM researchers               WHERE id = NEW.id) INTO has_researcher;
    SELECT EXISTS(SELECT 1 FROM department_administrators WHERE id = NEW.id) INTO has_dept_admin;

    IF new_role = 'student' AND (has_teacher OR has_researcher OR has_dept_admin) THEN
        RAISE EXCEPTION 'student role cannot be combined with any other role'
            USING ERRCODE = '23514';
    END IF;
    IF new_role <> 'student' AND has_student THEN
        RAISE EXCEPTION 'cannot add % role to a user that is already a student', new_role
            USING ERRCODE = '23514';
    END IF;

    IF new_role = 'researcher' AND (has_teacher OR has_dept_admin) THEN
        RAISE EXCEPTION 'researcher role cannot be combined with any other role'
            USING ERRCODE = '23514';
    END IF;
    IF new_role <> 'researcher' AND has_researcher THEN
        RAISE EXCEPTION 'cannot add % role to a user that is already a researcher', new_role
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