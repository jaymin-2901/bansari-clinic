# TODO: Update Clinic Address for Follow-up Emails

## Task: Replace old address with new address in follow-up email system

### Old Address (replaced):
212 A, Ratnadeep Flora 2nd Floor, Opposite Sv Square, Smruti Circle, New Ranip, Ahmedabad-382480, Gujarat.

### New Address:
212 A, Ratnadeep Flora 2nd Floor, Opposite Sv Square, Smruti Circle, New Ranip, Ahmedabad-382480, Gujarat.

## Changes Completed:

- [x] 1. Update database/schema.sql
- [x] 2. Update setup_database.php
- [x] 3. Update backend-php/api/clinic/seed_settings.php
- [x] 4. Update frontend-nextjs/src/lib/email.ts
- [x] 5. Update frontend-nextjs/src/lib/confirmation-email.ts

## Additional Note:
- The .env file (not readable) should have CLINIC_ADDRESS set. If not present, the default fallback in email.ts and confirmation-email.ts will now use the new address.
- The database settings will also need to be updated in the live database. Run the seed_settings.php API or update the setting directly in the database.

## Status: COMPLETED ✅

