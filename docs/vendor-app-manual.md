# OneWater Vendor Database - User Manual

The OneWater Vendor Database (the "Vendor App") is the OWYG staff directory of surveyors, mechanics, and trade vendors. Everyone on the team uses the same shared list, so when one person adds a good surveyor or rates a mechanic, the whole group sees it. This guide walks through signing in, finding a vendor, adding and editing records, rating vendors, and pulling vendor info into a client email. A shorter section at the end covers the admin tools for Clark and Annika.

[Screenshot: the Vendor Database main screen]

## Contents

- [Getting in (signing in and out)](#getting-in)
- [Forgot your password](#forgot-your-password)
- [Finding a vendor](#finding-a-vendor)
- [On a phone](#on-a-phone)
- [Viewing a vendor](#viewing-a-vendor)
- [Adding a vendor](#adding-a-vendor)
- [Editing a vendor](#editing-a-vendor)
- [Rating a vendor](#rating-a-vendor)
- [Deleting a vendor](#deleting-a-vendor)
- [Copying vendors into an email](#copying-vendors-into-an-email)
- [My Profile](#my-profile)
- [For Administrators](#for-administrators)
- [Need help?](#need-help)

---

## Getting in

### Signing in

1. Open the Vendor App. You will land on the sign-in screen.
2. Enter your **Account ID**. This should match your OWYG email address.
3. Enter your **password**.
4. Click **Sign In**.

[Screenshot: the sign-in screen with Account ID and Password fields]

### First-time sign-in (set your own password)

The very first time you sign in, you use the temporary password an administrator emailed you. The app then requires you to set your own password before you can go any further.

1. Sign in with your Account ID and the temporary password.
2. On the "Set Your Password" screen, type a new password (at least 8 characters), then type it again to confirm.
3. Click **Set Password & Continue**. You go straight into the app.

This same step happens again if an administrator ever resets your password for you. There is no way around it except to sign out, so plan to set a password you will remember.

[Screenshot: the "Set Your Password" screen]

### Signing out

Click **Log out** in the top-right corner whenever you are done. This ends your session right away.

### The 10-minute inactivity timeout

For security, the app signs you out after 10 minutes with no activity (no clicks, typing, or scrolling).

- At 9 minutes, a **"Still there?"** box appears with a 60-second countdown.
- Click **Stay signed in** to keep working. The timer resets.
- If you do nothing, the app signs you out and returns you to the sign-in screen at the end of the countdown.

As long as you are actively clicking and typing, you stay signed in.

[Screenshot: the "Still there?" inactivity warning box]

---

## Forgot your password

If you cannot remember your password:

1. On the sign-in screen, click **Forgot password?**
2. Enter the email on your account.
3. Click **Send Reset Link**.
4. Check your email for a message from **no-reply@haleyyachts.com** with a reset link.
5. Click the link, set a new password (at least 8 characters, entered twice), and you are sent back to sign in.

The reset link **expires in 1 hour**. If it expires, just request a new one from the sign-in screen.

For privacy, the app always shows the same confirmation message whether or not the email is on file, so the screen will not tell you if you typed the wrong address. If no email arrives, double-check the address with an administrator.

---

## Finding a vendor

The top of the main screen is the search-and-filter bar. You can use one filter or stack several together. The result count under the bar updates as you go.

[Screenshot: the search-and-filter bar with all four filters labeled]

### Search by name

The **Vendor or Contact Name** box matches either the vendor's name or the name of one of its contacts. Type any part of the name and the list narrows as you type.

### Filter by Vendor Type

1. Use the **Search types...** box to narrow the long list of types.
2. Check one or more types.
3. The **Type match** toggle controls how multiple types combine:
   - **Any** (the default): show vendors that have at least one of the checked types.
   - **All**: show only vendors that have every checked type.

### Filter by Coverage Area

Coverage areas are organized in tiers, from broad to specific:

- **USA** (Nationwide) at the top
- **State**
- **Region**
- **County**

The list is indented to show this structure. Checking an area is smart about the tiers in both directions:

- Pick a **broad** area (like a state) and you also get vendors tagged to areas **inside** it (its regions and counties).
- Pick a **specific** area (like a county) and you also get vendors tagged to the broader areas that **contain** it (its region, state).
- Any vendor tagged **Nationwide** shows up for any area you pick, since they cover everywhere.

So you rarely have to guess the exact tier. Pick the area you care about and the app fills in the rest.

[Screenshot: the Coverage Area filter showing the indented USA / State / Region / County tiers]

### Filter by Rating

Check one or more rating buckets to narrow the list:

- 5 stars, 4 stars, 3 stars, 2 stars, 1 star
- **Not rated** for vendors that have no ratings yet

A vendor lands in a bucket based on its average rounded to the nearest whole star.

### Results, sorting, and clearing

- The **result count** above the table tells you how many vendors match.
- Click any **column header** (Vendor Name, Type(s), Coverage Area(s), Primary Phone, Primary Email, Contacts, Avg Rating) to sort by it. Click the same header again to flip between ascending and descending. A small arrow shows the active sort.
- Click **Clear** to reset every filter, the search boxes, and the sort, and start fresh.

---

## On a phone

The app adjusts for small screens:

- Each result shows as a stacked **card** instead of a wide table row, with the vendor name as the card title.
- The filter bar stacks into a single column so everything is easy to tap.
- A **Select all shown** control appears above the results for the copy-for-email feature (see below).

Everything else works the same as on a computer. Tap a vendor name to open it.

[Screenshot: the phone view showing stacked vendor cards]

---

## Viewing a vendor

Click (or tap) a **vendor name** in the results to open its full detail view. You will see:

- The rating summary at the top (average and number of ratings)
- Name, address, primary phone, primary email
- Vendor types and coverage areas
- Vendor notes
- All **contacts** on file, with the primary contact marked
- The **ratings** section, where you can rate the vendor and see its rating history

Phone numbers and email addresses are clickable, so on a phone you can tap to call or email.

From here you can also **Edit** or **Delete** the vendor, or **Close** to go back to the list.

[Screenshot: the vendor detail view]

---

## Adding a vendor

1. Click **+ Add Vendor** at the top of the main screen.
2. Fill in the fields:
   - **Vendor Name** (required)
   - **Address**
   - **Primary Phone** and **Primary Email**
   - **Vendor Notes** (up to 150 characters)
   - **Vendor Types** - check all that apply
   - **Coverage Areas** - check all that apply
3. Add one or more **contacts** (optional):
   - Click **+ Add Contact**.
   - Enter the contact's **name, phone, email**, and optional notes.
   - Mark one contact as the **Primary contact** if you like. The first contact you add is set as primary automatically; you can change it.
   - Use **Remove** to drop a contact row.
4. Click **Save Vendor**.

[Screenshot: the Add Vendor form with the contacts section]

### The duplicate check

When you save a brand-new vendor, the app checks whether it might already exist. If the name matches an existing vendor, or a phone number matches, it pauses and shows a **Possible duplicate** panel listing the likely matches and why each one matched. You have three choices:

- **Open** an existing match to go to that record instead. Note: this discards what you just typed.
- **Create anyway** to save your new vendor despite the match.
- **Keep editing** to go back to your form with everything you typed still there.

This check only runs when you add a new vendor, not when you edit an existing one.

[Screenshot: the "Possible duplicate" panel]

---

## Editing a vendor

1. Open the vendor (click its name).
2. Click **Edit**.
3. Change any fields, types, areas, or contacts.
4. Click **Save Vendor**.

---

## Rating a vendor

Ratings are shared with the whole team and now show **who left them**, so they build up a useful track record over time.

1. Open the vendor and scroll to the **Ratings** section.
2. Click the stars to choose a rating from **1 to 5**.
3. Optionally type a short **note** (up to 150 characters).
4. Click **Submit rating**.

Your rating is added to the history with your name and the date, and the vendor's average updates right away. Each entry in the history has a **Delete** button if a rating needs to be removed.

[Screenshot: the Ratings section with the star picker and history]

---

## Deleting a vendor

How delete works depends on whether you are an administrator:

- **Administrators** delete the vendor directly. You get a confirmation prompt, and on OK the vendor and its contacts are removed. This cannot be undone.
- **Everyone else** cannot delete a vendor. When you click Delete, the app tells you an administrator will be notified, and it emails your request to the admin. The vendor is **not** deleted; an admin decides.

To start either way, open the vendor and click **Delete**.

---

## Copying vendors into an email

This feature builds clean contact text for several vendors at once so you can paste it into a client email.

1. In the results, **check the box** next to each vendor you want. To grab them all, use the **select-all** checkbox in the table header (or **Select all shown** on a phone).
2. Click **Copy ... for Email** (the button shows the count, like "Copy 3 for Email").
3. The text is copied to your clipboard, and a review box opens so you can see exactly what was copied.
4. Click into your email and paste with **Cmd+V** (Mac) or **Ctrl+V** (Windows).

Each vendor comes out as up to two lines:

- **Line 1:** vendor name, primary phone, primary email
- **Line 2** (only if the vendor has a primary contact): `Contact: name, phone, email`

Vendors are separated by a blank line, and any missing piece (say, no email) is simply left out so you never get a stray comma.

Closing the review box clears your selection, so start fresh next time.

[Screenshot: the Copy for Email review box with sample text]

---

## My Profile

Click **My Profile** in the top-right corner to manage your own account.

You can update your:

- **Name**
- **Email**
- **Cell**
- **Home Office**

Your **Account ID** is shown but cannot be changed here; an administrator assigns it.

The same screen has a **Change Password** section. Enter your current password, then your new password twice (at least 8 characters), and click **Change Password**.

[Screenshot: the My Profile screen with profile fields and the change-password section]

---

## For Administrators

This section is for Clark and Annika. The admin tools live in the password-protected admin area, separate from the staff app.

### Managing staff accounts

Open **Staff Accounts** (`admin/users.html`).

**Create an account:**

1. Click **+ New Account**.
2. Fill in:
   - **Account ID** - use the person's OWYG email.
   - **Name**
   - **Email** - used for self-service password resets.
   - **Cell**
   - **Home Office**
   - **Admin privileges** - see below.
   - **Initial Password** - at least 8 characters.
3. Click **Save**.

The new user is emailed their temporary password and is required to change it the first time they sign in.

[Screenshot: the New Account form]

**Admin privileges:** the **Administrator** checkbox controls whether someone can delete vendors directly. Admins can delete; non-admins can only request a delete (which notifies an admin by email). Everyone defaults to non-admin, so be sure to flag at least one admin, or nobody can delete vendors.

**Managing existing accounts:** each row in the accounts table has buttons to:

- **Edit** the account details (the Admin checkbox is here too). The password is not edited from Edit; use Reset PW.
- **Reset PW** to set a new password for someone. They are emailed the new temporary password and must change it on their next sign-in. This also cancels any pending email-reset links.
- **Disable / Enable** to block or restore login without deleting the account.
- **Delete** to remove the account permanently.

### Managing the predefined lists

Open **Vendor Lists** (`admin/vendor-lists.html`). These two lists are what staff choose from in the app; staff cannot edit them.

[Screenshot: the Vendor Lists admin page with Vendor Types and Coverage Areas]

**Vendor Types** is a flat, alphabetical list. Use the box to add a type, **Rename** to change one, and **Delete** to remove one. If a type is in use, deleting it only unassigns it from those vendors; the vendors themselves stay.

**Coverage Areas** is the tiered tree: **Nationwide (USA) / State / Region / County**. To add an area:

1. Type the **area name**.
2. Pick the **Tier** (County, Region, State, or Nationwide).
3. For a Region or County, choose its **Parent** (the broader area it sits under). State and Nationwide have no parent.
4. Click **Add area**.

To **edit or re-parent** an area, click **Edit** on its row and follow the prompts for name, tier, and parent. As with types, deleting an in-use area only unassigns it from vendors; it does not delete them.

The page also has an audit panel listing any vendors that have **no coverage area**, so you can find and tag them.

### Export CSV (admin only)

The **Export CSV** button (top-right of the staff app, visible only to admins) downloads the vendors that are **currently shown**. With no filters active, that is the whole database, so it doubles as a backup. With filters applied, you export just that filtered slice.

### Delete-request notifications

When a non-admin requests a vendor delete, the system emails the request to the admin notification address so an admin can act on it.

### A note on system emails

All system emails (onboarding/temporary passwords, password resets, and delete-request notifications) are sent from **no-reply@haleyyachts.com**. Let staff know to expect mail from that address and to check spam if it does not arrive.

---

## Need help?

If something is not working or you are locked out, contact your administrator (Clark or Annika) and they can reset your access.
