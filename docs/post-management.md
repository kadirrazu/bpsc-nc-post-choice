# Individual applied post management

Scope: multiple-applied-post events only. Single-post workflow is unchanged. Administrators can use Edit Post and Delete Post in each post card on a current Draft event. Operator access is blocked server-side. Both operations are locked if any active or cancelled submission records remain in the event. Existing submission/receipt data is never removed by these operations.

## Edit

Edit post code, title, organization and ministry. Post code remains unique within the event. Keep all choices, original imported identities, applications, candidate links and import records. Original application metadata is retained as imported; current post labels/codes drive display and exports. Audit old/new metadata with administrator and timestamp.

Changing post metadata invalidates pending multiple-post import previews event-wide; upload/review the file again. Existing confirmed matching links remain unchanged.

## Delete

The confirmation screen shows application, exclusive candidate, shared candidate and choice counts. Type DELETE to confirm. Atomically delete the selected post's applications, import batches, choices and post record. Delete a candidate only if that candidate has no application in another post. Preserve all shared candidates and their remaining links, original identities, choices in other posts and the event itself. Keep event audit history and record POST_DELETED with affected counts.

Post operations lock the event in the same way as import/submission operations. Edit/delete forms carry a revision of post metadata, choices, applications and import IDs. If any of that data changes after opening the form, reject the old form and require a fresh review. Referenced submission choices cannot be deleted. Deleted-post previews cannot commit; other matching previews become stale when post configuration changes.

No schema change or frontend asset build is required. README.md stays unchanged.
