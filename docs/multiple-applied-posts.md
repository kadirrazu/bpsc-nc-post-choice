# Multiple Applied Posts — Workflow

This update extends multiple-post events only. Existing single-post import, sign-in, choice UI and receipt behavior are retained.

## Initial setup
1. Create a new event with Multiple applied posts selected. An existing event's eligibility mode cannot be changed.
2. Keep the event Draft while preparing its applied posts, choices and candidate files.
3. Add each applied post and its choice options on the event page. Choice codes remain unique within the event.
4. Open Import / View Candidates for a post. Download its Sample Excel file, replace the example row and upload that post's candidates.
5. Repeat for other applied posts; one file belongs to one selected post.

## Import fields
Required for multiple-post files: user, reg, name, b_date, ssc_roll, ssc_year.
Optional: fname, mname, dist_code, dist_name, unit, post_code, post_name, ministry, hsc_roll, hsc_year, nid.
Parent names may be blank. Keep identifiers as Text; SSC leading zeros are significant. User/reg remain at most 10 characters.
Use the selected post's file; metadata columns do not change the selected post association.
Supported: CSV, XLS, XLSX. Maximum 5 MB / 10,000 rows; Excel uses the first worksheet.
DOB uses the existing DDMMYYYY/DDMMYY normalization rules. Required sample headers are highlighted.

## Matching rules
- Exact: name, normalized full DOB, SSC roll and SSC year all match an existing identity exactly. Names are case-sensitive after the existing reader trims surrounding whitespace; punctuation/internal whitespace are not normalized for automatic links. SSC leading zeros are preserved.
- Optional-field differences do not prevent an exact match. They are shown in the comparison.
- Review: meaningful name similarity requires a matching DOB, SSC roll/year pair, or similar parent name. Both parent names together require a matching DOB or SSC pair. Exact NID or HSC roll/year pairs also suggest review. Honorifics and shared surnames alone are insufficient. Minor spelling, case and punctuation differences are review evidence only; suggestions never automatically link.
- Multiple exact candidate targets are ambiguous and require review.
- New: no matching identity is found.
- The same exact identity may not appear twice in a selected post's file. One candidate may have only one application per applied post.
- Extremely ambiguous rows with more than 50 possible candidates are rejected; correct the mandatory identity fields before retrying.

## Review and confirm
The preview writes no candidates or applications. It displays 25 rows per page and expires after 60 minutes.
For each Review row, compare the incoming and existing identity, then select Link, Create a separate candidate, or Skip.
Save decisions on every page. Changed but unsaved selections disable Confirm Import.
Confirm only after all review rows have a saved decision. Import is transactional: an error leaves the database unchanged.
A preview becomes invalid when event candidate/application data changes, including another post import or dataset reset.

Established candidate identity is not overwritten. Absent optional supporting values may be filled from a linked application.
Each application's original identity is retained separately. Administrator-confirmed variants are also available as identities for later exact matching.

## Candidate submission
After imports and review are complete, publish the event.
A linked application's User ID + DOB signs into the same event candidate. For manually accepted DOB variants, the original application's DOB and the established candidate DOB are recognized.
The candidate sees all linked applied posts and the union of their applicable choices.
One active submission covers the event; signing in through another linked application opens the same submitted receipt.
Receipt identity uses the submitting application's User/Reg and the established candidate identity, with the existing snapshot, QR, token, timestamp and IP behavior.
Import remains locked once any active or cancelled submission record exists.

## Management and exports
- Multiple submission list: one row per candidate submission, with all applied posts and identifiers expandable; search any linked User/Reg and filter by applied post.
- XLSX/DBF: one row per application, using that application's original identity. Cancelled submissions are excluded; All scope retains pending applications with FALSE status.
- Multiple exports add applied_post_code in XLSX and POSTCODE (20 characters) in DBF. Single-post export fields are unchanged.
- Filter exports by an applied post, or export all. Choice lists retain the complete event preference order, not a separate submission per post.
- Event record counts remain unique candidate counts, not application counts.
- Cancel/delete/clear submissions operate on the candidate's event submission and retain the existing confirmation workflow.
- Post dataset reset requires a current Draft event and no active or cancelled submissions. Clear all event submissions first if replacement is needed.
- Reset removes the selected post's applications and import batches. Shared candidates remain linked to their other posts; candidates with no remaining applications are removed.
- Administrator controls import/review/reset/exports. Operator remains read-only on submission status.

## Installation
Back up the project and database. Copy Paste-Replace into the project root, replacing matching files.
Run:

```bash
php artisan migrate
php artisan optimize:clear
```

No npm build, Composer dependency changes or user seeding are needed.
The migration adds only nullable candidate_applications.source_identity; existing single-post data stays intact.

## Verification
78 PHP tests passed (713 assertions), including the previous 64 baseline tests and 14 multiple-workflow tests.
4 existing choice-selection UI tests passed. PHP syntax checks and Blade compilation passed.
Tests cover exact/partial/Unicode matching, optional parents, CSV/XLS/XLSX, manual linking/new/skip, pagination, ambiguous identities, alias identity login, stale previews, duplicate prevention, atomic rollback, one-time submission, source-preserving exports, post filters, permissions and dataset reset.

## Post-specific matched exports and statistics

The event page's Submissions & Administrative Records section lists every applied post. Each row downloads one XLSX or one plain DBF file, with a post-code and Bangladesh-time timestamp in the filename. Exports contain all confirmed imported applications for that selected post, including candidates who have not submitted. Pending import previews are excluded.

Original identity fields are preserved per application. Other-post registration/user columns are filled only from confirmed links to the same event candidate; unlinked values are blank. The selected post uses the base user/reg columns. XLSX headers are reg_POSTCODE and user_POSTCODE. DBF headers are REG_POSTCODE and USR_POSTCODE with 10-character values. Codes that cannot fit DBF's 10-character field-name limit use R_P{post ID} and U_P{post ID}. DBF retains its existing Windows-1252 encoding and 254-byte choice-field limit; XLSX preserves Unicode and longer text. No value is silently truncated.

Unique candidates count event candidates once. Unique submissions count only current SUBMITTED records once per person. Per-post counts reflect all that person's applied posts, regardless of which post was used to sign in; they are not added together as unique people. Not submitted = confirmed applications minus actively submitted applications. Cancelled history does not count as an active submission. Statistics are event-wide and independent of list search/filter settings.

The same summary appears on the event and Candidate Submissions pages. Operators may view submission statistics but cannot download administrative matched exports. Event Record XLSX remains two sheets: Event (metadata, unique counts, per-post statistics) and Choices. The Event Record PDF includes the same statistics and keeps its repeating header, table headers, timestamp/page footer and credit line. Single-post exports and submission behavior remain unchanged.

## Combined list export

Combined List XLSX/DBF downloads appear beside the individual per-post exports on the event page. Each confirmed application produces one row; the same person can appear under several applied posts. Rows sort by applied post code ASC, registration ASC, then application ID to resolve ties. Post codes and registrations remain strings and retain leading zeros. This combines the individual datasets without deduplicating applications.

The combined schema contains the base identity, submission fields, applied_post_code, and reg_POSTCODE/user_POSTCODE columns for every post. DBF uses the same REG_/USR_ aliases (or R_P{ID}/U_P{ID} for long/nonstandard codes) and exports one plain DBF. Original identities and only confirmed matching links are used; unmatched values are blank. The filename contains the event post code and Bangladesh-time timestamp. Administrator access is required. Individual exports and the single-post workflow are unchanged.

The current and archive event lists show title and post code in one column. Submitted counts unique event candidates with a current SUBMITTED record; multiple applications and cancelled history do not inflate the count. Counts are calculated in the list query without a per-event database query.

## Combined unique candidate export

Both Full Combined List and Combined Unique Candidate List are available as XLSX or plain DBF downloads. Full keeps every confirmed application. Unique keeps each confirmed event_candidate_id once, not each USER, REG or name.

For Unique, sort applications by post code ASC, registration ASC, then application ID. Keep the first occurrence of each candidate and skip that candidate in later posts. Candidates found only in later posts are appended in that post's registration order. The retained application's original identity, user, registration and applied post code become the base row; user/reg columns for all linked posts remain available. Unmatched candidates are not merged.

Unique export row count matches unique imported candidates (assuming every candidate has a confirmed application); the number of TRUE rows matches unique active submissions. Cancelled-only candidates have FALSE status and blank choices. Full/per-post exports, matching rules, single-post workflow and administrative permissions remain unchanged. Unique filenames begin combined-unique-candidates and include event post code plus Bangladesh-time timestamp.

## Export layout

On the event page, keep the post-wise statistics and individual export table first. Place a separate Combined Exports table below it, with Combined All and Combined Unique rows, short descriptions and XLSX/DBF actions. This layout change does not alter export data, ordering or permissions.

## Add Post form placement

The event page places Add Post below the existing post cards. The form is collapsed by default and toggles through the Add Post control using native details/summary, with no JavaScript dependency. A failed Add Post validation keeps the form open and restores entered values. Existing Draft-only configuration permissions remain unchanged.
