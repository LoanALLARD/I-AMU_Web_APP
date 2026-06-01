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
