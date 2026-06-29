-- BIM 2.7.14 Booking Protection Decision Engine
-- No required schema change for this phase.
-- Required existing tables:
-- 1) business_client_allowlist
-- 2) business_client_relationships
-- 3) user_guarantees
-- 4) guarantee_levels
-- 5) bookings
--
-- Important business rule:
-- Skipping guarantee or deposit is only a booking protection decision.
-- Platform service fees remain payable and must not be waived by this engine.

SELECT 'BIM 2.7.14 Booking Protection Decision Engine ready' AS status;
