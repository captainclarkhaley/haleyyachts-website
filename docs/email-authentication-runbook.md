======================================================================
EMAIL AUTHENTICATION RUNBOOK - haleyyachts.com
Fixing SPF, DKIM, and DMARC so app mail and your normal mail deliver
======================================================================
Prepared for: Clark Haley
Prepared by: Terry (engineering)
Last verified against live DNS: 2026-07-01

----------------------------------------------------------------------
PLAIN-ENGLISH SUMMARY (read this first)
----------------------------------------------------------------------
Right now your domain sends email from a few different places: Microsoft
365 (your normal mailbox and, soon, the Broker Suite app mail from
no-reply@haleyyachts.com), and Mailchimp (marketing). For each stream,
receiving servers like Gmail run three checks:

  - SPF   = "Is this sending server on the approved list for the domain?"
  - DKIM  = "Does the message carry a valid cryptographic signature
             tied to the domain?"
  - DMARC = "Do SPF and/or DKIM PASS *and* line up with the visible
             From: address?" That lining-up part is called ALIGNMENT.

DMARC only trusts a PASS when the domain that passed SPF or DKIM matches
the domain in the From: address the recipient sees. That match is
"alignment." A message can pass raw SPF and still fail DMARC if it is
not aligned.

Current state of haleyyachts.com:
  - MX points to Microsoft 365 (GoDaddy-provisioned tenant,
    NETORG19019087.onmicrosoft.com). Good.
  - SPF is published and correct for M365:
        v=spf1 include:secureserver.net -all
  - DKIM: ONLY Mailchimp is signed (k2 and k3 selectors). There is NO
    Microsoft 365 DKIM (selector1 / selector2 are missing) and NO DKIM
    for the app's no-reply@ mail. This is the main gap.
  - DMARC is published but toothless: p=none, no reporting, no alignment
    tags.

What this runbook does, in order:
  1. Turn on Microsoft 365 DKIM so your M365 mail is signed and aligned.
  2. Fix the Broker Suite app mail so no-reply@haleyyachts.com is signed
     and aligned (switch it to send through a real mailbox).
  3. ONLY after 1 and 2 verify as passing, tighten DMARC step by step.

Do the steps in order. Do NOT jump to step 3 early. Details and the
reason why are in that section.

======================================================================
STEP 1 - ENABLE DKIM FOR MICROSOFT 365
======================================================================
Goal: get selector1._domainkey and selector2._domainkey published and
turn DKIM signing ON in Microsoft 365, so your normal M365 mail (and any
app mail you later route through M365) is signed and aligned.

First, figure out WHICH kind of Microsoft 365 you have. It changes where
you click.

  - If you manage your email from INSIDE your GoDaddy account
    (you log into godaddy.com, go to your products, and see an Email /
    Microsoft 365 section there): you have GODADDY-PROVISIONED M365.
    Use path (a). This is almost certainly you. Your DNS confirms it:
    the tenant is NETORG19019087.onmicrosoft.com, which is GoDaddy's
    naming pattern, and SPF uses include:secureserver.net.

  - If you log directly into admin.microsoft.com with a Microsoft 365
    admin account to manage mailboxes: you have a DIRECT M365 TENANT.
    Use path (b).

If unsure, try path (a) first. If GoDaddy sends you into the Microsoft
admin center to finish, you are effectively on path (b) for the DKIM
screen.

----------------------------------------------------------------------
PATH (a) - GoDaddy-provisioned Microsoft 365 (MOST LIKELY)
----------------------------------------------------------------------
1a-1. Sign in at godaddy.com. Go to your product list and open the
      Email & Office / Microsoft 365 dashboard (sometimes labeled
      "Email & Office" then "Manage").

1a-2. Find the domain haleyyachts.com and look for its security or
      advanced email settings. GoDaddy exposes DKIM in one of two ways
      depending on account age:
        - A "DKIM" or "Email authentication" toggle right in the
          GoDaddy Microsoft 365 settings. If present, enable it. GoDaddy
          will either add the two selector records for you automatically
          (because it also runs your DNS) or show you two CNAME records
          to add.
        - A button that hands you off to the Microsoft admin center /
          Defender portal to complete DKIM. If it does that, follow
          path (b) from step 1b-2 onward. Because GoDaddy also controls
          your DNS, adding the records there is quick.

1a-3. The two records you are enabling look like this (values will be
      shown to you; do not invent them):
        Type:  CNAME
        Host:  selector1._domainkey
        Value: selector1-haleyyachts-com._domainkey.<tenant>.onmicrosoft.com

        Type:  CNAME
        Host:  selector2._domainkey
        Value: selector2-haleyyachts-com._domainkey.<tenant>.onmicrosoft.com

      Your tenant is NETORG19019087.onmicrosoft.com, so the values will
      point into that. Add both in GoDaddy DNS (Domains > haleyyachts.com
      > DNS) if GoDaddy does not add them for you automatically.

1a-4. After the two CNAMEs exist, make sure DKIM SIGNING is turned ON
      (not just the records published). In GoDaddy's flow this is the
      DKIM toggle; if you were handed off to Microsoft, see step 1b-4.

----------------------------------------------------------------------
PATH (b) - Direct Microsoft 365 tenant
----------------------------------------------------------------------
1b-1. Sign in at admin.microsoft.com with a Microsoft 365 admin account.

1b-2. Go to the Microsoft Defender portal: security.microsoft.com
      Navigate: Email & collaboration > Policies & rules > Threat
      policies > (under Rules) Email authentication settings > DKIM.

1b-3. In the DKIM list, click the domain haleyyachts.com. Microsoft will
      show two CNAME records to publish, named like:
        Host:  selector1._domainkey.haleyyachts.com
        Value: selector1-haleyyachts-com._domainkey.<tenant>.onmicrosoft.com
        Host:  selector2._domainkey.haleyyachts.com
        Value: selector2-haleyyachts-com._domainkey.<tenant>.onmicrosoft.com

      Copy both. Add them in GoDaddy DNS (Domains > haleyyachts.com >
      DNS > Add > CNAME). For Host, enter selector1._domainkey and
      selector2._domainkey (GoDaddy appends the domain automatically, so
      do NOT type the full haleyyachts.com on the host side).

1b-4. Go back to the Defender DKIM screen for haleyyachts.com and toggle
      "Sign messages for this domain with DKIM signatures" to ON. If it
      refuses, it usually means the two CNAMEs have not propagated yet.
      Wait, re-verify with the dig commands below, then toggle again.

----------------------------------------------------------------------
VERIFY STEP 1
----------------------------------------------------------------------
Give DNS up to an hour (often faster). Then run:

    dig +short TXT selector1._domainkey.haleyyachts.com
    dig +short TXT selector2._domainkey.haleyyachts.com

Each should return a record (a CNAME chain resolving to a long
v=DKIM1... key at Microsoft). If both come back empty, the CNAMEs are
not published yet or the host field was typed wrong. Do not proceed to
enabling the signing toggle until these resolve.

Final sign-off for Step 1: send yourself a normal email from your
haleyyachts.com mailbox to a Gmail address, open it, use the Gmail
"Show original" check (see cheat sheet at the bottom), and confirm
DKIM=PASS with the signing domain shown as haleyyachts.com.

======================================================================
STEP 2 - FIX THE APP MAIL (no-reply@haleyyachts.com)
======================================================================
The problem: the Broker Suite app currently sends via PHP mail() from
no-reply@haleyyachts.com. PHP mail() hands the message to the web
server's local mail system with no authentication and no DKIM signature.
On top of that there is a split-delivery gotcha:

  SPLIT-DELIVERY GOTCHA: your MX is Microsoft 365, but the web host may
  also run a local mail server for the domain (mail.haleyyachts.com). If
  the web host believes it is the local handler for haleyyachts.com, PHP
  mail() can try to deliver the message LOCALLY on the web host instead
  of sending it out to the real recipient. Result: password resets and
  onboarding mail silently vanish or land in the wrong place, and even
  when they go out they are unsigned and fail DMARC alignment once you
  tighten it.

The fix is the same in both cases: stop using PHP mail() and send the
app mail through a REAL, AUTHENTICATED no-reply@haleyyachts.com mailbox
over SMTP. That mailbox's outbound path is SPF-authorized and DKIM-signed
already, so the app mail inherits proper authentication and alignment.

You pick the mailbox. Two options:

----------------------------------------------------------------------
OPTION (a) - PREFERRED: no-reply@ mailbox in Microsoft 365
----------------------------------------------------------------------
Why preferred: once Step 1 is done, M365 mail is already SPF-authorized
(include:secureserver.net) and DKIM-signed (selector1 / selector2). App
mail sent through this mailbox is automatically aligned. Nothing extra
to add to DNS, and one fewer sending system to babysit.

Setup:
  2a-1. Create a mailbox no-reply@haleyyachts.com in Microsoft 365 (a
        licensed user, or a shared mailbox with SMTP send enabled).
  2a-2. Enable SMTP AUTH for that mailbox. In modern M365, SMTP AUTH is
        often OFF by default. Turn it on for this mailbox (admin.
        microsoft.com > that user > Mail > Manage email apps >
        Authenticated SMTP).
  2a-3. If the tenant enforces MFA, generate an APP PASSWORD for this
        mailbox (or use a modern-auth send method). The app uses that
        app password, not the human login password.
  2a-4. SMTP settings the app will use:
            Host:     smtp.office365.com
            Port:     587
            Security: STARTTLS
            Username: no-reply@haleyyachts.com
            Password: (the app password from 2a-3)

----------------------------------------------------------------------
OPTION (b) - ALTERNATIVE: cPanel mailbox over mail.haleyyachts.com
----------------------------------------------------------------------
Workable, but more moving parts, because the web host becomes a second
sending system you must separately authorize and sign.

Setup:
  2b-1. Create the mailbox no-reply@haleyyachts.com in cPanel.
  2b-2. Authorize the web host in SPF. Right now SPF only allows
        secureserver.net. You must add the web host's sending path, for
        example:
            v=spf1 include:secureserver.net include:<webhost-spf> -all
        (get the exact include or IP from the host; do NOT drop the
        existing secureserver include or you will break M365 mail).
  2b-3. Turn on DKIM for the host. In cPanel use "Email Deliverability"
        for haleyyachts.com; it generates a default._domainkey TXT
        record. Add that record to GoDaddy DNS.
  2b-4. SMTP settings the app will use:
            Host:     mail.haleyyachts.com
            Port:     587 (or as the host specifies)
            Security: STARTTLS
            Username: no-reply@haleyyachts.com
            Password: (that mailbox's password)

Note: this is the setup vendors/pocket/mail-config.sample.php already
gestures at (host mail.haleyyachts.com, user no-reply@haleyyachts.com).

----------------------------------------------------------------------
CODE CHANGE (Terry follow-up, after you pick a mailbox)
----------------------------------------------------------------------
Once you choose option (a) or (b) and the mailbox exists, I wire the app
to use authenticated SMTP instead of PHP mail():
  - vendors/api/mail-lib.php (currently PHP mail() from VMAIL_FROM)
  - vendors/pocket/mailer.php (currently PHP mail())
Both point at the SMTP settings above via the mail-config file. This is
my task, not yours. It is listed here only so the sequence is clear:
your part is choosing the mailbox and getting it authenticated; I do the
wiring.

----------------------------------------------------------------------
VERIFY STEP 2
----------------------------------------------------------------------
After the code is wired to SMTP, trigger a real password reset (or
onboarding email) from the app to a Gmail address. Open the message, run
Gmail "Show original," and confirm all three:
    SPF:   PASS
    DKIM:  PASS
    DMARC: PASS
with the signing/authenticated domain shown as haleyyachts.com (that is
the alignment part). If DKIM shows a different domain, or DMARC says
"fail" while SPF/DKIM pass, it is an alignment problem, not a raw-auth
problem. Stop and send me the "Show original" text.

======================================================================
STEP 3 - STRENGTHEN DMARC (ONLY AFTER 1 AND 2 PASS)
======================================================================

*** STRONG WARNING - READ BEFORE TOUCHING DMARC ***
Do NOT move DMARC to quarantine or reject until BOTH of these are
verified passing in Gmail "Show original":
    - Microsoft 365 DKIM (Step 1), and
    - the app mail from no-reply@ (Step 2).
If you enforce early, you risk your OWN legitimate mail, the Broker Suite
app mail, and marketing being quarantined or rejected. Enforcement is
the last step, not the first.

Also, before you enforce:
    - Mailchimp is already DKIM-signed (k2 and k3 selectors). Keep those
      records in place. Do not remove them.
    - If you also send through Constant Contact (or any other service),
      confirm that stream authenticates for haleyyachts.com FIRST. An
      unauthenticated stream will start failing the moment you enforce.

----------------------------------------------------------------------
3.1 - Turn on reporting while STILL at p=none
----------------------------------------------------------------------
This keeps enforcement off (nothing gets blocked) but starts sending you
daily aggregate reports so you can watch which streams pass and which
fail. Replace the current bare DMARC record with:

    Host:  _dmarc
    Type:  TXT
    Value: v=DMARC1; p=none; rua=mailto:dmarc@haleyyachts.com; fo=1; adkim=s; aspf=s

What the tags mean:
    p=none      still monitoring, nothing blocked yet.
    rua=mailto: where daily AGGREGATE reports are sent. Use a mailbox
                you will actually read. Create dmarc@haleyyachts.com, or
                point rua at your own address.
    fo=1        ask for failure detail when SPF or DKIM fails.
    adkim=s     strict DKIM alignment (signing domain must match exactly).
    aspf=s      strict SPF alignment.

Note on strict alignment: adkim=s / aspf=s are stricter than the default
(relaxed). They are the right target, but if a legitimate stream uses a
subdomain and only aligns in relaxed mode, a report may show it failing.
That is exactly why you watch reports at p=none first. If a real stream
only aligns relaxed, drop those two tags (default is relaxed) rather
than blocking the stream.

Let this run for one to two weeks. Read the reports. Confirm your M365
mail, the app mail, and Mailchimp all show up as aligned/passing.

----------------------------------------------------------------------
3.2 - Raise to quarantine (after clean reports)
----------------------------------------------------------------------
When reports are clean for all your real streams:

    Value: v=DMARC1; p=quarantine; rua=mailto:dmarc@haleyyachts.com; fo=1; adkim=s; aspf=s

Now anything that fails DMARC goes to spam/junk rather than the inbox.
Watch reports for another week or two.

----------------------------------------------------------------------
3.3 - Raise to reject (final)
----------------------------------------------------------------------
When quarantine has been clean:

    Value: v=DMARC1; p=reject; rua=mailto:dmarc@haleyyachts.com; fo=1; adkim=s; aspf=s

Now spoofed mail claiming to be haleyyachts.com is rejected outright.
This is the strongest protection and the end state you want, but only
after everything legitimate has been proven to pass.

======================================================================
VERIFICATION CHEAT SHEET
======================================================================
Run these from Terminal (or ask me to). Empty output means "not
published."

SPF (root TXT, look for the v=spf1 line):
    dig +short TXT haleyyachts.com

Microsoft 365 DKIM (should return records after Step 1):
    dig +short TXT selector1._domainkey.haleyyachts.com
    dig +short TXT selector2._domainkey.haleyyachts.com

cPanel DKIM (only relevant if you chose Step 2 option b):
    dig +short TXT default._domainkey.haleyyachts.com

Mailchimp DKIM (should already resolve - leave it alone):
    dig +short CNAME k2._domainkey.haleyyachts.com
    dig +short CNAME k3._domainkey.haleyyachts.com

DMARC (watch this change as you go through Step 3):
    dig +short TXT _dmarc.haleyyachts.com

MX (should be Microsoft 365):
    dig +short MX haleyyachts.com

GMAIL "SHOW ORIGINAL" CHECK (the real test):
    1. Send a test message to a Gmail address (from your mailbox for
       Step 1, from the app for Step 2).
    2. In Gmail, open the message, click the three-dot menu, choose
       "Show original."
    3. Gmail prints a summary box at the top:
           SPF:   PASS
           DKIM:  PASS
           DMARC: PASS
       For DMARC to pass, the domain next to SPF/DKIM must be
       haleyyachts.com. That match is alignment. If SPF/DKIM say pass
       but DMARC says fail, it is an alignment problem - send me the
       full "Show original" text.

======================================================================
QUICK REFERENCE - WHAT'S DONE vs WHAT'S NEEDED
======================================================================
    SPF ......... DONE (correct for M365, leave as-is unless you pick
                  Step 2 option b, which needs the web host added)
    M365 DKIM ... NEEDED (Step 1)
    App DKIM .... NEEDED (Step 2 - fixed by routing through a real
                  mailbox; no separate app DKIM once it sends via M365)
    Mailchimp ... DONE (k2/k3 signed - do not remove)
    DMARC ....... NEEDS strengthening, but LAST (Step 3, after 1 and 2)

THE ONE DECISION THAT UNBLOCKS THE APP:
    Which no-reply@haleyyachts.com mailbox do you want the app to send
    through?
        (a) Microsoft 365 mailbox  - recommended, auto-aligned, fewer
            moving parts, or
        (b) cPanel mailbox on mail.haleyyachts.com - workable but adds
            SPF + DKIM work on the web host.
    Tell me (a) or (b) and I wire the app to it.
======================================================================
