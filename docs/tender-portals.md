# Tender portals — scraping status & supplier access

Research date: **November 2026**. Estimates are planning figures, not audited counts; portals change often — re-verify before relying on any number. “Free” below refers to browsing notices; a few portals charge for supplier subscriptions, documents or bid security (called out where known).

## Coverage numbers used on the site

| Item | Value |
| --- | --- |
| Public portals represented | **18** |
| Countries | **13** (Saudi Arabia, UAE, Qatar, South Africa, Kenya, Nigeria, Ghana, Tanzania, Uganda, Rwanda, Ethiopia, Egypt, Morocco) + 2 pan-African/global institutions (AfDB, World Bank) |
| Estimated notices per year | **~192,000** → marketed as **“~190,000 notices a year”** (~3,700/week) |
| Notes | Sum of per-portal estimates below. Some notices appear on more than one portal, so the unique/deduplicated count is lower. Use “190,000+” only with the “estimated” framing. |

Per-portal estimates used: Etimad 25k · Aramco 5k · Abu Dhabi 8k · Dubai 6k · UAE federal 4k · Qatar 3k · SA 50k · Kenya 20k · Nigeria 15k · Ghana 8k · Tanzania 6k · Uganda 5k · Rwanda 3k · Ethiopia 7k · Egypt 12k · Morocco 10k · AfDB 2k · World Bank 3k.

## Scraping status

Status key: **A** = official API / OCDS open data · **B** = public HTML scrape · **C** = registration or JS-gated · **D** = private/blocked.

| Country / Region | Portal | Language(s) | Est. notices / yr | Access model | Scrape status | Best route |
| --- | --- | --- | --- | --- | --- | --- |
| Saudi Arabia | Etimad — etimad.sa | AR / EN | ~25,000 | Public tender list; supplier account (Nafath) to bid; paid subscription to participate | **C** | Scrape public listing (check ToS/rate limits); supplier account for depth |
| Saudi Arabia | Saudi Aramco — e-Marketplace (SAP Ariba) | EN / AR | ~5,000 | Registered suppliers only | **D** | No public feed; supplier registration/partnership only |
| UAE (Abu Dhabi) | Abu Dhabi Government Procurement Gate — supplier.adgpg.gov.ae | EN / AR | ~8,000 | Public “View Public Tenders”; registration to bid | **B** | Public tender-list HTML scrape |
| UAE (Dubai) | Dubai eSupply — esupply.dubai.gov.ae (JAGGAER) | EN / AR | ~6,000 | Public “Current Opportunities”; registration to bid | **B/C** | Public search scrape; ask JAGGAER/entity for a feed |
| UAE (Federal) | MoF Federal Supplier Register / Digital Procurement Platform — mof.gov.ae | AR / EN | ~4,000 | Registration via UAE PASS; opportunities largely behind login | **C** | Registration-gated; request API/data access |
| Qatar | Monaqasat — monaqasat.mof.gov.qa | AR / EN | ~3,000 | Public tender list; supplier registration/classification to bid | **B** | HTML scrape of public notices |
| South Africa | eTenders — etenders.gov.za | EN | ~50,000 | Fully public | **A** | Official OCDS REST API + monthly JSON/CSV/XLSX (PDDL licence) |
| Kenya | PPIP — tenders.go.ke | EN / SW | ~20,000 | Public | **A** | Unofficial JSON API `/api/active-tenders` (~60 req/min) + OCDS downloads |
| Nigeria | NOCOPO — nocopo.bpp.gov.ng | EN | ~15,000 | Public portal; some dashboards require login | **B/C** | Public pages where open; BPP P-COMS notices as fallback |
| Ghana | GHANEPS — ghaneps.gov.gh (+ PPA tenders.ppa.gov.gh) | EN | ~8,000 | Public tender search; registration to bid | **A/B** | GHANEPS OCDS dataset + HTML |
| Tanzania | NeST — nest.go.tz (replaced TANePS) | EN / SW | ~6,000 | Public current tenders; registration to bid | **A** | data.nest.go.tz OCDS API & downloads |
| Uganda | GPP — gpp.ppda.go.ug | EN | ~5,000 | Public bid invitations; registration to bid | **A/B** | GPP OCDS datasets + HTML listings |
| Rwanda | Umucyo — umucyo.gov.rw | EN / FR / RW | ~3,000 | Public List of Advertising; digital certificate to bid | **A/B** | ocds.umucyo.gov.rw open data + API docs — **licence CC BY-NC-SA (non-commercial), legal review needed** |
| Ethiopia | eGP — egp.gov.et | EN / AM | ~7,000 | Public tender list; registration to bid | **B** | Server-rendered HTML listing |
| Egypt | eTenders — etenders.gov.eg (GAGS) | AR / EN | ~12,000 | Public portal; often unreachable/geo-blocked outside Egypt | **D** (geo) | Needs in-region access or manual data partnership |
| Morocco | Marchés Publics — marchespublics.gov.ma | FR / AR | ~10,000 | Public consultations; registration to bid | **B** | HTML scrape; WAF/403 and portal hours (07:30–midnight) |
| Pan-African | AfDB procurement notices — afdb.org | EN / FR | ~2,000 | Public | **B** | HTML listing + RSS; Cloudflare challenge |
| Global | World Bank procurement notices | EN / FR | ~3,000 (Africa) | Public | **A** | Official API `search.worldbank.org/api/v2/procnotices` (CC BY 4.0) |

### Notes and corrections

- **“Qatar Daman (QGPC)”** is not a tender portal — Daman is Qatar’s national health insurer. The government portal is **Monaqasat** (Ministry of Finance). QatarEnergy uses its own **Mushtaryat** supplier portal; Ashghal prequalifies separately.
- **Tanzania**: TANePS was retired in favour of **NeST** (operational since July 2023). Data is at `data.nest.go.tz`.
- **Ghana**: the PPA’s tender registry (`tenders.ppa.gov.gh`) and **GHANEPS** are separate; GHANEPS publishes OCDS data.
- **Kenya**: PPIP is the notices/awards portal; actual bidding runs on **e-GP** (`egpkenya.go.ke`).
- **Rwanda’s** open-data licence is **non-commercial**; using it in a paid product needs permission or a separate agreement.
- **Egypt** frequently blocks or is unreachable from outside the country; plan for local access.
- **Aramco** tenders are only visible to registered suppliers inside SAP Ariba; no public listing.
- “All are free”: browsing notices is free on every portal above. **Registration is usually free**, but Etimad requires a paid subscription to participate, Tanzania charges foreign suppliers an annual registration fee, and individual UAE entities may charge prequalification fees. Bid documents and bid security can also cost money.

## How to apply for a tender (all portals)

1. **Pick the buyer’s portal** from the table above (many buyers use more than one).
2. **Register as a supplier/bidder** — company registration number, tax ID (VAT/TIN), bank letter, authorised signatory, licences. Approval can take hours to weeks.
3. **Complete the profile**: business categories/UNSPSC codes, experience, financials. Incomplete profiles are the most common cause of missed invitations.
4. **Find the notice** and download the tender/bidding document; read every addendum.
5. **Check eligibility & prepare the bid**: technical + financial proposal, bid security, required certificates; note the submission method and deadline (usually the portal’s clock).
6. **Submit online before the deadline** and keep the acknowledgement; clarifications and appeals go through the portal.

### Portal-specific registration

| Portal | How to register | Cost notes |
| --- | --- | --- |
| Etimad (SA) | `login.etimad.sa` → Business (Nafath / Saudi Business Center); foreign suppliers use “No CR” option | Subscription fee to participate/buy documents |
| Aramco | Aramco e-Marketplace (SAP Ariba) self-registration + questionnaire + Cybersecurity Compliance Certificate (CCC) | Registration free; qualification effort high |
| Abu Dhabi Gate | `supplier.adgpg.gov.ae` → register by chatbot or Oracle; AD-DED licence self-registration, others via entity request | Free |
| Dubai eSupply | `esupply.dubai.gov.ae` → New Supplier → organisation/user details + trade licence; activation via `newsuppliers@jaggaer.com` | Entity prequalification fees may apply |
| UAE Federal | `mof.gov.ae` → Register Supplier in Federal Supplier Register (UAE PASS) | Free; ~30 working days |
| Monaqasat (QA) | Register/classify suppliers with Ministry of Finance; buy tender documents online | Documents/bonds typically paid |
| SA eTenders | Browse freely; register on the Central Supplier Database (CSD) to bid | Free |
| Kenya PPIP / e-GP | Browse PPIP freely; register on `egpkenya.go.ke` to bid; AGPO lists reserved tenders | Free registration |
| Nigeria NOCOPO / BPP | BPP contractor database (`federalcontractors.bpp.gov.ng`) and P-COMS vendor registration (CAC + TIN) | Free initial registration |
| Ghana GHANEPS / PPA | PPA supplier database (`suppliers.ppa.gov.gh`) + GHANEPS supplier account | Annual renewal required |
| Tanzania NeST | Register as tenderer on `nest.go.tz`; BRELA + TRA TIN/tax clearance | Foreign suppliers may pay an annual fee |
| Uganda GPP / eGP | Register as provider on eGP/GPP; PPDA fees via URA payment reference number | Fees per PPDA schedule |
| Rwanda Umucyo | Register with RDB, then Umucyo supplier registration using a GovCA digital certificate | Certificate may carry a cost |
| Ethiopia eGP | Sign up on `egp.gov.et`, then submit supplier registration for FPPA approval | Free to browse |
| Egypt eTenders | “Register as supplier” on `etenders.gov.eg`; commercial register + tax card | Free registration per MoF guide |
| Morocco PMMP | Create an account on `marchespublics.gov.ma` (RC/ICE); local and non-established forms | Viewing free; documents may be paid |
| AfDB / World Bank | No registration to view; follow each notice’s submission instructions | Free |
