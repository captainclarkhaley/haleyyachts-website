# AEO Copy + Spec: Money-Page FAQs and Clark Haley Entity Page

Status: DRAFT for Clark's approval, then Terry builds.
Author: Patrick (marketing)
Date: 2026-07-20

Purpose: approval-ready copy for the AEO sprint Clark green-lit. Part 1 is the full FAQ set (four money pages + top three guides), written for AI answer-engine extraction and structured so Terry can wrap each in FAQPage schema. Part 2 is the canonical "About Clark Haley" expert-page copy plus the exact fields that feed the `Person` schema and `sameAs`.

Build notes for Terry:
- Each FAQ group below maps to one page. Render the Q&A visibly on the page (question as a heading or `<summary>`, answer as body) AND emit one `FAQPage` JSON-LD block per page containing every Q/A on that page.
- Answers are written to stand alone (40-60 words each). Do not trim them below the visible copy, the length is deliberate for extraction.
- Do not change wording on `[CONFIRM: ...]` lines until Clark resolves them. Where a number is bracketed, leave the FAQ out or use the placeholder until confirmed.
- Standing rules honored: no em dashes, "Co-Brokerage" (not co-broke), no Denison branding.

---

# PART 1: FAQ SETS BY PAGE

## Page: buy.html (Buy a Yacht)

**Q: Do I pay a yacht broker when I buy a boat?**
A: In almost every case, no. On a listed yacht the seller pays the brokerage commission, and that fee is shared between the listing side and the buyer's side under Co-Brokerage. That means you get a dedicated broker working your interests, negotiating on your behalf, at no direct cost to you as the buyer.

**Q: How long does it take to buy a yacht?**
A: From serious search to closing, plan on four to eight weeks for a brokerage yacht once you find the right one. An accepted offer typically moves to sea trial and survey within a couple of weeks, then closing follows acceptance of the vessel. New builds run longer and depend on the production slot.

**Q: Can I buy a yacht that is not listed with Haley Yachts?**
A: Yes. Through Co-Brokerage we can represent you on almost any yacht listed anywhere, not just our own inventory. You get one broker who knows your priorities handling the search, the offer, and the closing, while we coordinate with the listing side. You are never limited to a single brokerage's boats.

**Q: Do I need a survey before buying a used yacht?**
A: Yes, on any brokerage purchase we strongly recommend it. An independent marine survey and sea trial protect you before you commit, confirming the vessel's condition, systems, and value. The survey also gives you real leverage: findings can reopen price or require repairs. We build the survey and sea trial into the offer as contingencies.

**Q: What size and type of yachts does Haley Yachts specialize in?**
A: We focus on motor and sail yachts in the 50 to 80 foot range, the size where an owner wants real accommodations, range, and resale value. Clark Haley also represents the full Riviera line of luxury motor yachts. If you are cruising South Florida and the Bahamas, this is our home water.

**Q: What is Co-Brokerage?**
A: Co-Brokerage is the industry practice of two brokers cooperating on one sale: one represents the seller, one represents the buyer, and they share the commission the seller already pays. It is how a buyer gets dedicated representation on a boat listed by another brokerage, at no added cost, and it widens the inventory we can put in front of you.

---

## Page: sell.html (Sell Your Yacht)

**Q: How much does a yacht broker charge to sell my boat in Florida?**
A: The standard yacht brokerage commission in Florida is [CONFIRM: state 10 percent? industry standard], paid by the seller at closing and typically shared with a cooperating buyer's broker under Co-Brokerage. There is no upfront cost to list with us. You pay only when your yacht sells, and that fee covers full marketing, negotiation, and closing.

**Q: How do you decide what to list my yacht for?**
A: We price to the market, not to a wish. That means recent comparable sales, current competing listings, your yacht's condition, equipment, and hours, and where demand sits right now. We would rather set a defensible asking price that draws real buyers than an inflated one that lets your listing go stale. You see the comps we use.

**Q: How long does it take to sell a yacht?**
A: It depends on price, condition, and how in-demand the model is, but a well-priced, well-presented yacht usually finds serious interest within the first several weeks. The single biggest factor is pricing to the current market. Professional photography, video, and broad exposure across the major platforms shorten the timeline considerably.

**Q: How will you market my yacht?**
A: Professional photography and video first, because presentation drives inquiries. From there your yacht goes onto the major search platforms, in front of our own buyer network, and through One Water Yacht Group's reach. We handle inquiries, qualify buyers, and coordinate showings and sea trials so your boat is only shown to people who are real.

**Q: What is a central listing agreement?**
A: A central listing agreement makes one broker the single point of coordination for your sale. It is the standard, professional way to list: it gives you one accountable broker managing marketing, buyer inquiries, Co-Brokerage with other brokers, and the closing, rather than a scattered, unmanaged listing spread across brokerages with no one truly in charge.

**Q: Do I have to be there for showings and sea trials?**
A: No. Handling showings and sea trials so you do not have to is part of the job. We qualify buyers before anyone steps aboard, represent your yacht professionally, and keep you informed. For the sea trial and survey stage we coordinate the logistics and walk you through anything that needs your decision.

---

## Page: services.html (Services)

**Q: What does Haley Yachts do?**
A: We represent buyers and sellers of motor and sail yachts, primarily in the 50 to 80 foot range, and we represent the full Riviera line of new luxury motor yachts. That covers listing and marketing your yacht, buyer representation through Co-Brokerage, pricing and valuation, and managing sea trial, survey, and closing start to finish.

**Q: Can you help me find a specific yacht that is not currently for sale?**
A: Yes. Off-market and pocket searches are part of what a well-connected broker does. Tell us the make, model, and specification you want, and we work our network and One Water Yacht Group's reach to find it, including boats whose owners would sell to the right buyer but have not formally listed.

**Q: Do you handle the paperwork and closing?**
A: Yes, end to end. Offers, the purchase and sale agreement, acceptance, deposit and escrow, title and documentation, and the closing itself are all coordinated for you. A yacht transaction has real legal and financial moving parts, and managing them correctly so nothing falls through is a core part of the service.

**Q: Do you only work in Florida?**
A: Haley Yachts is based in South Florida, but yacht deals are not bound by state lines. Through Co-Brokerage and One Water Yacht Group we represent clients on yachts listed across the country and coordinate transactions wherever the boat is. Our home water is South Florida and the Bahamas, our reach is national.

**Q: What is Co-Brokerage and how does it help me?**
A: Co-Brokerage lets two brokers cooperate on one sale and share the seller-paid commission. For a buyer, it means dedicated representation on almost any listed yacht at no direct cost. For a seller, it means every cooperating broker in the market can bring you a qualified buyer. It widens the market on both sides.

---

## Page: valuation.html (Yacht Valuation)

**Q: How do I find out what my yacht is worth?**
A: Request a broker valuation. We look at recent comparable sales of your make and model, what similar yachts are currently listed for, and your specific boat's condition, equipment, hours, and history. That produces a realistic, market-based number, not an algorithm's guess. It is the same analysis we would use to price your yacht for sale.

**Q: Is a yacht valuation free?**
A: A broker market valuation from Haley Yachts is complimentary and carries no obligation to list. Whether you are deciding to sell, refinance, insure, or simply want to know where you stand, we will give you an honest, comparables-based figure. A formal appraisal for legal or insurance purposes is a separate, paid service.

**Q: What is the difference between a valuation and a survey?**
A: A valuation is about price: what your yacht is worth in today's market, based on comparable sales and condition. A survey is about condition and safety: a qualified marine surveyor's detailed inspection of the hull, systems, and equipment. Buyers order a survey during a purchase; owners request a valuation to understand or set value.

**Q: What affects my yacht's value the most?**
A: Make, model, and year set the baseline, then condition, engine hours, and maintenance history move it up or down. Upgrades, electronics, and how well the boat presents matter. So does the current market for that specific model. Two identical hulls can be worth meaningfully different numbers based on care and equipment.

**Q: How accurate are online yacht value estimates?**
A: Treat them as a rough starting point, not an answer. Automated tools do not see your yacht's actual condition, its equipment, its hours, or how its specific model is selling right now. A broker valuation built on real comparable sales and an informed read of the market is far more accurate, and it is what buyers and lenders trust.

---

## Guide: articles/how-to/how-to-choose-a-yacht-broker

**Q: What should I look for in a yacht broker?**
A: Look for a real track record, verifiable references, and someone who knows the market cold: pricing, recent comparable sales, and current inventory, without hesitation. Strong marketing, professional photography and video, and a broker who listens more than they sell. The right broker makes the process easier, the wrong one makes it expensive.

**Q: Are yacht brokers licensed in Florida?**
A: Yes. Florida is one of the few states that licenses and regulates yacht brokers and salespeople, including bonding requirements. Always confirm your broker is properly licensed. It is a baseline signal that they operate professionally and are accountable under state law, and it is an easy thing to verify before you commit.

**Q: What is the IYBA and why does it matter?**
A: The International Yacht Brokers Association is the professional body for yacht brokers. Membership signals a commitment to industry standards, ethics, and the standardized contracts that protect buyers and sellers. It is not legally required, but working with an IYBA member broker is a meaningful mark of professionalism worth looking for.

**Q: What questions should I ask a yacht broker before hiring them?**
A: Ask how many boats they have sold this year and what type, what their marketing plan is, whether they will personally inspect the vessel, and whether you can speak to a past client. Straight, confident answers are a good sign. Vague ones, or high-pressure tactics, tell you to keep looking.

**Q: What are red flags when choosing a yacht broker?**
A: High-pressure tactics, talking down competitors or specific brands, and vague answers about the boats they are selling. A broker who is trying to move a boat rather than find you the right one is working their interest, not yours. Trust the one who listens first and pushes least.

---

## Guide: articles/how-to/how-to-buy-a-yacht-the-step-by-step-timeline

**Q: What are the steps to buying a yacht?**
A: Define your use, budget, and target models, then search with a buyer's broker. Make a written offer, put down a deposit into escrow, and go to sea trial and survey. Accept or renegotiate based on findings, then close: final payment, title, and documentation. Your broker coordinates each step so nothing slips.

**Q: How long does the yacht buying process take?**
A: Once you find the right boat, expect roughly four to eight weeks to close a brokerage yacht. An accepted offer usually moves to sea trial and survey within about two weeks, then closing follows your acceptance of the vessel. The search itself is the variable part and can take anywhere from days to months.

**Q: What is a purchase and sale agreement?**
A: It is the written offer that starts the deal: the price you are offering, the deposit, and the contingencies that protect you, chiefly acceptance of the yacht after sea trial and survey. Standardized IYBA forms are the norm. Until you accept the vessel in writing, your deposit is refundable under those contingencies.

**Q: What happens at the sea trial and survey?**
A: The sea trial is your on-water test: the surveyor and you see how the yacht runs, handles, and performs under load. The survey is the detailed inspection of hull, systems, and equipment, often with a separate engine survey. Together they confirm condition and value before you commit, and their findings can reopen price.

**Q: What closing costs should I budget for when buying a yacht?**
A: Beyond the purchase price, budget for the survey and sea trial, any engine survey or haul-out, sales or use tax where applicable, documentation or titling and registration, and insurance. Financing, if used, has its own costs. Your broker will walk you through the specific numbers for your boat before you are committed.

---

## Guide: articles/how-to/what-a-survey-really-covers

**Q: What does a yacht survey cover?**
A: A full pre-purchase survey inspects the hull and structure, through-hulls and steering, electrical and plumbing systems, safety equipment, and general condition, usually with the boat hauled out. Engines are often covered by a separate engine or mechanical survey. The result is a written report on condition, deficiencies, and market value.

**Q: Do I need a survey to buy a used yacht?**
A: Yes, we strongly recommend one on any brokerage purchase. It protects you before you commit, confirms the vessel is what it appears to be, and gives you leverage: findings can reopen price or require repairs. Insurers and lenders typically require a recent survey as well, so it serves more than one purpose.

**Q: How much does a marine survey cost?**
A: Marine surveys are typically priced by the foot [CONFIRM: state a per-foot range, for example $20 to $25 per foot?], with a separate fee for an engine survey and any haul-out. On a yacht purchase it is a small fraction of the boat's value and among the best money you will spend for peace of mind.

**Q: Who pays for the yacht survey?**
A: The buyer pays for the survey and sea trial, because it is being done for the buyer's protection and decision. It is one of the buyer's costs of doing due diligence. Because the buyer commissions it, the surveyor works for the buyer, which is exactly what you want: an independent read, not the seller's.

**Q: What is a sea trial and is it part of the survey?**
A: A sea trial is running the yacht on the water so you and the surveyor can see how it performs: engines under load, handling, systems in real use. It is a distinct step from the dockside and haul-out survey but usually happens alongside it, and both feed your decision to accept the vessel.

---

# PART 2: CLARK HALEY ENTITY PAGE

Build note for Terry: this becomes one canonical page (suggested URL `/about-clark-haley.html` or a dedicated section of about.html, Clark to decide). Every article's author `url` should point here. Emit a `Person` JSON-LD block on this page using the fields in the schema spec below. This is the authoritative record AI engines will use to answer "who is Clark Haley."

## Page copy

### Headline
Clark Haley, Licensed Florida Yacht Broker

### Lead
Clark Haley is a licensed Florida yacht broker and the founder of Haley Yachts, part of One Water Yacht Group. He represents buyers and sellers of motor and sail yachts and is a member of the Riviera sales team, based in South Florida.

### Bio (main body)
Clark Haley built Haley Yachts on a simple idea: the right yacht finds you when you have the right broker. He works primarily with motor and sail yachts in the 50 to 80 foot range, the size where an owner wants genuine accommodations, real cruising range, and a boat that holds its value. He also represents the full Riviera line of luxury motor yachts.

Clark is a licensed Florida yacht broker, operating under One Water Yacht Group, one of the largest marine groups in the country. That combination gives his clients the attention of a dedicated, hands-on broker with the reach and resources of a national organization behind every deal.

His home water is South Florida and the Bahamas. Clark cruises the same waters his clients do, from Palm Beach out to the Abacos and the Exumas, and he writes regularly about buying, selling, owning, and cruising yachts through The Logbook and Haley Yachts guides. That firsthand knowledge shows up in every transaction: honest pricing built on real comparable sales, marketing that actually presents a yacht well, and a broker who listens more than he sells.

Whether you are buying your first yacht, trading up to something bigger, or ready to sell, Clark handles the entire process, offer, sea trial, survey, and closing, so the experience is straightforward and the outcome is right.

[CONFIRM: years active / experience. Suggested line to insert if confirmed: "Clark has been [X] years in the yacht business" or "has closed [X]+ transactions." Leave out entirely until Clark provides a real number. Do not invent.]

[CONFIRM: any prior brokerage or industry background Clark wants included. Note: do NOT reference Denison anywhere, per standing rule.]

### Credentials block (visible on page)
- Licensed Florida Yacht Broker
- Founder, Haley Yachts
- One Water Yacht Group
- Riviera sales team, full line
- [CONFIRM: IYBA member? If yes, add "Member, International Yacht Brokers Association (IYBA)". Do not state unless confirmed.]
- Based in [CONFIRM: Palm Beach Gardens vs Jupiter. The site footer/office address says Palm Beach Gardens FL 33410; an article author card says "based in Jupiter, Florida." Pick one and make it consistent everywhere for entity consistency, this matters for AEO.]

### Contact
- Phone: 561-817-1547
- Email: clark@haleyyachts.com
- Office: 2401 PGA Blvd, Suite 164, Palm Beach Gardens, FL 33410 [CONFIRM against the Jupiter reference above]

---

## Person schema spec (for Terry to build the JSON-LD)

Use these values in the `Person` block on the entity page. Keep them consistent with the sitewide business schema (`@id` https://haleyyachts.com/#business).

- `@type`: Person
- `@id`: https://haleyyachts.com/#clark-haley  (stable identifier, reference this same @id from article author blocks)
- `name`: Clark Haley
- `jobTitle`: Licensed Florida Yacht Broker
- `worksFor`: Organization, One Water Yacht Group
- `affiliation` / `memberOf`: Haley Yachts (link to `#business`); [CONFIRM: add IYBA as a memberOf Organization if member]
- `url`: the canonical entity page URL
- `image`: /images/people/clark-haley-headshot-square.jpg
- `telephone`: +1-561-817-1547
- `email`: clark@haleyyachts.com
- `knowsAbout` (array, this is the AEO expertise signal, include all that are true):
  - Yacht brokerage
  - Buying a yacht
  - Selling a yacht
  - Motor yachts
  - Sail yachts
  - Riviera Yachts
  - Luxury yachts (50 to 80 feet)
  - Yacht valuation
  - Marine surveys
  - Co-Brokerage
  - Yacht ownership
  - Bahamas cruising
  - South Florida yachting market
  - Palm Beach yacht market
- `knowsLanguage`: English
- `hasCredential`: [CONFIRM: Florida yacht broker license number, if Clark wants it public. If yes, model as an EducationalOccupationalCredential of type "license" issued by the State of Florida. If he does not want the number public, omit the number but keep "Licensed Florida Yacht Broker" as jobTitle.]
- `alumniOf`: [CONFIRM: only if Clark wants education included, otherwise omit]

---

## sameAs list (for both the Person schema and the sitewide business schema)

Goal: corroborate the entity across independent sources. Include a URL only if the profile actually exists and is live. Known-live profiles are marked; the rest need Clark to confirm the profile exists and provide the exact URL.

- Facebook: https://facebook.com/clarkhaleyyachtbroker  (KNOWN, already in site)
- Instagram: https://instagram.com/capnclark  (KNOWN, already in site)
- LinkedIn: [CONFIRM: does Clark have a LinkedIn profile / company page? Provide URL. High value for a personal-brand entity, add if it exists.]
- Google Business Profile: [CONFIRM: is the GBP live? There is a setup doc in docs/marketing/google-business-profile-setup.md. Once live, add the maps/GBP URL, this is a strong local + entity signal.]
- YouTube: [CONFIRM: is there a Haley Yachts / Clark Haley YouTube channel? Provide URL if it exists.]
- IYBA member directory: [CONFIRM: if IYBA member, add the public directory listing URL]
- One Water Yacht Group team/bio page: [CONFIRM: does OWYG list Clark on their site? If so, add the URL, an independent corroborating source is exactly what AEO rewards.]

---

# APPENDIX: build checklist for Terry (do not start until Clark approves)
1. Wrap each page's Q&A group in one FAQPage JSON-LD block; render the Q&A visibly on-page.
2. Build the canonical Clark Haley entity page; emit the Person JSON-LD per the spec.
3. Repoint every article author `url`/`@id` to the entity page `@id`.
4. Update `sameAs` in BOTH the sitewide business schema and the new Person schema with the confirmed profile URLs.
5. Resolve the Palm Beach Gardens vs Jupiter location inconsistency site-wide.
