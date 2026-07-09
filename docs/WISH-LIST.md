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
