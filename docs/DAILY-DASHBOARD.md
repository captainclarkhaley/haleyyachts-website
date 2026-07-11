# Haley Yachts - Daily Dashboard (Clark-facing)

> **Owned + maintained by William.** Single-writer rule holds: William writes this file and `TASK-LIST.md`; Terry writes `tasks/terry.md`; Patrick writes `tasks/patrick.md`.
>
> **What this is:** a short, phone-friendly "who owes what" view. Two lists - what is pending for **Clark** to do, and what is pending for **the team**. This is a different lens from the backlogs. It does NOT replace them.
>
> **How it relates to the task lists:** `TASK-LIST.md` (master) + `tasks/terry.md` + `tasks/patrick.md` are the full engineering / marketing backlogs (everything we could build). This dashboard is the daily action layer skimmed off the top: the handful of items actually waiting on Clark or in-flight with the team right now. When an item here needs full detail or history, it points back to the owner files rather than restating them. Committed work lives in the task lists; this is just today's actionable slice.

**Last updated: July 10, 2026 (William)**

---

## Pending: Clark

- **Pull tenant-app commit `c62fde8` and test on the server.** Carries everything since Clark's last pull. Test surface:
  - Archive-instead-of-delete users: archive, reinstate, "Show archived", login blocked for an archived user, and archived users gone from View Brokers.
  - Mobile pass: slim header + collapsible "Search Options" accordion.
  - 2-hour idle timeout.
  - Off Market Deals send flow: Email / Edit / Close, hero-photo-required-to-send, "N emails sent" confirmation + progress bar.
  - Off Market print: up to 16 photos on headed pages, company logo, pinned footer, broker headshot.
- **Lint the recent PHP on the server.** Several changes touch the DB schema + auth (archive users, activity/analytics, the Off Market mailer). Catch parse/notice issues before wider use.
- **Set or intentionally leave blank the Off Market notification recipient in Settings.** Blank now means "all active brokers." Decide and set.
- **Print-test a listing as a broker who HAS a profile photo** - confirm the headshot renders through the gated photo endpoint.
- **Provide the expired-document screenshot** (long-outstanding) for the vendor-document help section.
- **Capture the help screenshots Terry is compiling.** A separate list is coming to Terry for Clark to review.
- **Send the Broker Requests spec.** Clark is saving it for the weekend. Blocks the Broker Requests build.
- **Greenlight the staging + release-cadence plan when ready.** Target end of July: separate dev/staging deploy, move OWYG to weekly / semi-monthly planned releases. See memory `staging-release-cadence-plan.md`.
- **15-user OWYG rollout - readiness/scheduling, when Clark is ready to line it up.** First real production rollout. See memory `ybs-test-rollout-approved.md`.

---

## Pending: The team

- **Help-page updates for the recent features** - Terry, in progress now (`yacht-broker-support` repo). Detail: `tasks/terry.md`.
- **Screenshot list for Clark to review** - Terry, in progress now. Feeds Clark's "capture help screenshots" item above.
- **This daily dashboard + the daily cadence** - William, done today (this file). Ongoing: refresh daily per the cadence below.
- **Broker Requests build** - BLOCKED on Clark's spec (see Clark list). Desktop team ready to build. See memory `ybs-broker-requests-product.md`.
- **Staging / release-cadence plan draft** - William, on hold until Clark greenlights. See memory `staging-release-cadence-plan.md`.
- **15-user rollout scoping** - William, on hold until Clark is ready.
- **BCC scaling + send-progress-at-scale for the Off Market all-users email** - PARKED on the Wish List (`WISH-LIST.md`); revisit when active-user counts grow across offices.

---

## Daily cadence (standing practice)

Each working day, the team runs three quick updates so nothing drifts and Clark always has a current picture:

1. **In-app Change Log** - add a dated entry for that day's user-facing changes in `yacht-broker-support/app/changelog-data.php`. This log is **broker-facing** (what our users see changed).
2. **Help pages** - update the relevant help pages in the `yacht-broker-support` repo for anything that changed how a feature works. (Terry owns the help pages; the `yacht-broker-support` repo is separate from this website repo, so edits there never collide with this dashboard.)
3. **This dashboard** - refresh both sections and bump the "Last updated" date. This dashboard is **Clark-facing** (what Clark and the team owe).

Change Log = broker-facing. Dashboard = Clark-facing. Keep them distinct; a change often deserves both.
