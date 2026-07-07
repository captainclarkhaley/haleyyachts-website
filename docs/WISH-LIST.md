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
