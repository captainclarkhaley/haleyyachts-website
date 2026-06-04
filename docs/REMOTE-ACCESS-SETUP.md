# Remote Access Setup - The Box (macOS Monterey 12.7.6)

**Goal:** text Claude from your iPhone and get push notifications, both wired into
the main Claude session on the box (the one that has William, Terry, and Patrick).

**Two features, working together:**
- **iMessage channel** - you text the box, your message lands in the running
  session, Claude texts back.
- **Remote Control + push** - you steer the same session from the Claude mobile
  app, and your phone buzzes when a task finishes.

Both features are **research preview**. Flag names and behavior may shift; if a
command below is rejected, the wording may have changed. Just say so and we'll
re-check the docs.

**This document is written for macOS Monterey (12.7.6).** Newer Macs call it
"System Settings"; on Monterey it is **System Preferences**, and several panes
have different names. Those differences are already applied below.

**Legend:**
- **[Apple / GUI - you only]** = clicking in macOS, Messages, or your phone. Claude cannot do these.
- **[Shell]** = a command typed into Terminal on the box.
- **[Inside Claude]** = a slash command typed at the Claude Code prompt.

---

## Part 0 - Two prerequisites people always miss

Do these first. They are the usual reason the setup fails.

### A. Keep the box awake  [Apple / GUI - you only]
If the box sleeps or loses network for about 10 minutes, the Remote Control
session times out and the process exits.

1. Apple menu  > **System Preferences** > **Energy Saver**.
   (If the box is a laptop, the pane is called **Battery** - use its **Power Adapter** tab.)
2. Check **"Prevent your Mac from automatically sleeping when the display is off."**
3. **Uncheck** "Put hard disks to sleep when possible."
4. Optional, good for an always-on box: check **"Start up automatically after a
   power failure"** and **"Wake for network access."**
5. Plug it into power and leave it on.

Optional belt-and-suspenders [Shell] (runs only while that Terminal stays open):
```
caffeinate -dimsu
```

### B. Full Disk Access for Terminal  [Apple / GUI - you only]
The iMessage channel reads the Messages database, which macOS protects. The
Terminal you launch Claude from needs Full Disk Access.

1. Apple menu  > **System Preferences** > **Security & Privacy**.
2. Click the **Privacy** tab.
3. In the left list, scroll down and click **Full Disk Access**.
4. Click the **lock** at the bottom-left and enter your Mac password to unlock changes.
5. Click **+**, go to **Applications > Utilities > Terminal** (or whichever
   terminal you use), and add it.
6. Make sure its checkbox is **ticked**.
7. Click the lock again to save, then **quit and reopen Terminal**.

If the channel ever exits with `authorization denied`, this is the fix.

---

## Part 1 - Account and base setup (do once)

### 1.1 Sign Claude into your claude.ai account  [Shell] + [Apple / GUI]
Both features require a **Pro or Max** claude.ai login. **API keys do not work.**
If `ANTHROPIC_API_KEY` is set on the box, it must be unset.

```
claude
```
Then at the Claude prompt:
```
/login
```
Choose the **claude.ai** option and finish in the browser.
(Equivalent shell form: `claude auth login`.)

Confirm  [Inside Claude]:
```
/status
```
It should show your claude.ai account on a Pro or Max plan.

### 1.2 Accept workspace trust  [Shell]
```
cd ~/Desktop/Claude
claude
```
Accept the trust dialog if it appears, then exit.

### 1.3 Install Bun  [Shell]
The iMessage channel is a Bun script. Check first:
```
bun --version
```
If it prints a version, skip ahead. If it errors, install it:
```
curl -fsSL https://bun.sh/install | bash
```
Close and reopen Terminal, then re-run `bun --version` to confirm.

### 1.4 Sign the box into iMessage  [Apple / GUI - you only]
Open the **Messages** app on the box, then menu bar **Messages > Preferences**
(Monterey says "Preferences," not "Settings") > **iMessage** tab > sign in with
your Apple ID. This is the inbox Claude reads and replies from.

### 1.5 Confirm Claude Code is current enough  [Shell]
```
claude --version
```
Channels need v2.1.80+, Remote Control v2.1.51+, push v2.1.110+. If it is behind,
update Claude Code before continuing. (Monterey is a few years old; the CLI runs
fine on it, but if a feature refuses to start even after updating, the OS age is
the likely cause.)

---

## Part 2 - iMessage channel

### How the addressing works (read this first)
The simplest model is **text yourself.** From your iPhone, send an iMessage to
your **own Apple ID / your own number** - the same identity the box is signed into.
Because it is a self-chat, it reaches Claude immediately with **no pairing code.**
Claude's reply comes back in that same self-thread.

In practice: open Messages on your iPhone, message yourself, type
`have Terry fix the buy.html header`, and it lands in the box's running session.
The reply shows up in that thread on your phone. (In the box's Terminal you will
see the inbound message and a "sent" confirmation, but not the reply text - that
is expected.)

### 2.1 Install the plugin  [Inside Claude]
Start Claude on the box (`cd ~/Desktop/Claude && claude`), then:
```
/plugin install imessage@claude-plugins-official
```
If it says the plugin is not found in any marketplace:
```
/plugin marketplace add anthropics/claude-plugins-official
```
(or `/plugin marketplace update claude-plugins-official` if you added it before),
then retry the install.

### 2.2 Restart with the channel enabled  [Shell]
Exit Claude, then relaunch with the channel flag from the project directory:
```
cd ~/Desktop/Claude
claude --channels plugin:imessage@claude-plugins-official
```
This is the session that listens for your texts. **It must stay running.**

### 2.3 First text + Automation prompt  [Apple / GUI - you only]
From your iPhone, text yourself: `hello from my phone`. It should arrive in the
box's session.

The **first time Claude replies**, macOS shows an Automation prompt asking if
Terminal can control Messages. Click **OK**. (You must be at the box for this one
click.) If you need to change it later, it lives under **Security & Privacy >
Privacy > Automation**.

### 2.4 (Optional) Let another person text the box  [Inside Claude]
Only your own messages pass by default. To allow someone else:
```
/imessage:access allow +15551234567
```
Use `+country` phone format or an Apple ID email. Skip this unless you want a
second person texting the team.

---

## Part 3 - Remote Control + push

Separate from the channel. Gives you the mobile-app interface plus the
buzz-when-done notifications.

### 3.1 Install and sign in to the Claude mobile app  [Apple / GUI - you only]
1. Install the **Claude** app on your iPhone/iPad.
2. Sign in with the **same** claude.ai account the box uses (Part 1.1).
3. **Accept the notification permission prompt** so pushes can arrive.

### 3.2 Start Remote Control on the box  [Shell]
```
cd ~/Desktop/Claude
claude remote-control --name "Haley Yachts box"
```
This runs a server in that Terminal, prints a session URL, and (press **spacebar**)
shows a QR code. **It must stay running.** The box keeps doing all the work
locally; the phone is just a window into it.

### 3.3 Connect from your phone  [Apple / GUI - you only]
Either scan the QR code from the Terminal, or open the **Claude app > Code** tab
and pick the session named "Haley Yachts box." Online sessions show a computer
icon with a green dot.

### 3.4 Turn on push  [Inside Claude] + [Apple / GUI]
In a Claude session on the box:
```
/config
```
Enable **Push when Claude decides**. Claude then pushes when a long task finishes
or it needs a decision. You can also just ask in a prompt, e.g.
`notify me when Terry's done`.

If `/config` says **No mobile registered**, open the Claude app on your phone once
to refresh its push token, then reconnect.

---

## How it all ties to the team
Both paths land in the **main Claude session on the box** - the orchestrator that
holds William, Terry, and Patrick. So when you text "have Terry fix the buy.html
header" or send it from the mobile app, it routes through William and the team
exactly the way it does at the console. Same room, not a different assistant.

---

## Running two sessions
`claude --channels plugin:imessage@...` (Part 2.2) and `claude remote-control`
(Part 3.2) are two different launch commands. Cleanest setup is **two persistent
Terminal windows on the box**, one running each. They share your files and account
but are separate sessions, so context typed into one is not visible in the other.

A one-tap launcher script can open both at once so you are not retyping commands.
Ask and Terry will build it.

---

Sources:
- Channels: https://code.claude.com/docs/en/channels.md
- Remote Control: https://code.claude.com/docs/en/remote-control.md
