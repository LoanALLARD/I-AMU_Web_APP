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

-- A resource-scoped model may only be shared with resources of its own
-- department; see the two RAISE EXCEPTION cases below.
CREATE OR REPLACE FUNCTION enforce_model_resource_access_same_dept()
RETURNS TRIGGER AS $$
DECLARE
    owner_department_id BIGINT;
    target_department_id BIGINT;
BEGIN
    SELECT r.department_id INTO owner_department_id
    FROM models m
    JOIN resources r ON r.id = m.resource_id
    WHERE m.id = NEW.model_id;

    IF owner_department_id IS NULL THEN
        RAISE EXCEPTION 'model % is not resource-scoped: use model_department_accesses', NEW.model_id
            USING ERRCODE = '23514';
    END IF;

    SELECT department_id INTO target_department_id
    FROM resources
    WHERE id = NEW.resource_id;

    IF target_department_id IS DISTINCT FROM owner_department_id THEN
        RAISE EXCEPTION 'resource % is not in the model''s department %', NEW.resource_id, owner_department_id
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_model_resource_access_same_dept
    BEFORE INSERT OR UPDATE ON model_resource_accesses
    FOR EACH ROW EXECUTE FUNCTION enforce_model_resource_access_same_dept();