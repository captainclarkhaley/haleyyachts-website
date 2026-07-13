# Haley Yachts - Wish List (CANDIDATE FEATURES)

> **Owned and maintained by William.** This is the holding place for feature ideas we want to CONSIDER before building. It is NOT committed work.

## What this file is (and is not)

- The Wish List holds features we would like to consider before adding. These are candidates, not commitments.
- It is distinct from the committed / in-flight work tracked in:
  - `TASK-LIST.md` - the master rollup of committed open work.
  - `tasks/terry.md` - Terry's engineering list.
  - `tasks/patrick.md` - Patrick's marketing / content list.
- Clark adds wish-list items over time, often from customer feedback.
- "Must Have" items are NOT parked here. Clark hands those over to build right away, and they go straight onto the task list (`tasks/terry.md`, then rolled into `TASK-LIST.md`).

## The flow

```
idea  ->  Wish List (consider)  ->  if approved, promote to the task list to build
```

When an item is approved to build, move it out of this file and onto the owner task file (`tasks/terry.md` for engineering, `tasks/patrick.md` for marketing), then regenerate `TASK-LIST.md`. Leave a short note here that it was promoted, with the date.

---

## Candidate features

### Broker reassign - admin ability to reassign a survey to a different broker
- **Raised:** 2026-07-07
- **Status:** Parked / consider later
- **Detail:** Change the owning broker of a survey (`surveys.broker_id`) to a different broker. Today the owner is fixed to whoever created the survey and is not changeable in the app.
- **Why parked now:** Clark decided 2026-07-07 NOT to build this yet. Normal brokers each have a single login, so this is an edge case. Clark only hit it because he personally has several logins.
- **Why worth considering later:** Would also cover reassigning a departing broker's surveys to someone else.
- **Likely shape if built:** an admin-only "Assign to broker" dropdown on the survey.

### Off Market Deals "all active users" send - revisit at scale (BCC batching + send-progress UX)
- **Raised:** 2026-07-09
- **Status:** Parked / consider later (triggered by scale, not needed at the ~15-user test size)
- **Detail:** The Off Market Deals listing announcement now sends to all active users via a single BCC email when the notification-recipient setting is blank (built 2026-07-09, in `yacht-broker-support`). Two things will need rethinking once the active-user count grows large (hundreds across multiple offices): (1) a single very large BCC can trip mail-provider per-send recipient limits, so the send should move to CHUNKED BCC BATCHES; (2) the in-flight progress UX is currently an indeterminate "Sending emails..." bar, which is fine for a quick 15-user send but not for a large multi-batch send - it should show real progress or move to a background/queued job so the broker is not blocked waiting on a spinner.
- **Why parked now:** At the ~15-user OWYG test scale a single BCC + a simple progress bar work fine. This only becomes necessary as usage scales to many offices / hundreds of users.
- **Why worth considering later:** Directly gates scaling the product beyond the first office. Both items (batching + progress) are one connected piece of work.
- **Likely shape if built:** chunked BCC send loop with per-batch throttling, plus either a real progress indicator driven by batch completion or an async/queued send with a "sending in the background" notice.

### Cross-module matching - Broker Requests "Need: Boat" <-> active Off Market Deals listings
- **Raised:** 2026-07-12
- **Status:** Parked - Clark is floating the idea with the group to gauge interest; the answer likely drives whether Broker Requests needs richer data first (see below).
- **Detail:** Extend the Broker Requests matching engine (built in P3) so a "Need: Boat" request also matches ACTIVE Off Market Deals listings, not just other Broker Requests. Both modules live in the same tenant database, so it is a natural extension of the existing resolver. Boats-only cross-over (Off Market is boats-only, so slip/apartment/etc. requests have nothing to match). Bidirectional and notification-only, consistent with the current matching: posting a "Need: Boat" pings the requester about active off-market boats, and listing a new Off Market boat pings brokers who have an active "Need: Boat." No new privacy exposure - Off Market is already firm-wide visible to every broker.
- **Why parked now:** Broker Requests capture FREE TEXT (title, description, location), not structured boat criteria (make/model/year/price/length) like Off Market listings have. So a cross-match would be COARSE - "here are the active off-market boats, you judge the fit" - not a precise spec-match. Precise auto-matching would require adding structured boat fields to the request form, which is added UI complexity Clark wants to avoid unless the demand justifies it. He is floating the idea and gathering feedback to decide whether to fine-tune the data Broker Requests collects before this is worth building.
- **Why worth considering later:** Turns the suite into a connected internal marketplace - a broker's buyer-need automatically meets another broker's off-market inventory. Strong adoption / ROI / demo story ("watch my 'need a boat' surface an unadvertised listing").
- **Likely shape if built:** extend the P3 match resolver to also query `pocket_listings` (active) for Boat-type requests; bidirectional notification-only pings; optionally add structured boat-criteria fields to the request for precise matching (the open question above).
