from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import letter
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    PageTemplate,
    Paragraph,
    Spacer,
    PageBreak,
    Table,
    TableStyle,
    KeepTogether,
)
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfbase import pdfmetrics
from reportlab.lib.colors import HexColor
from pathlib import Path


ROOT = Path(r"D:\xampp\htdocs\MIS\uhlms")
OUTPUT = ROOT / "output" / "pdf" / "UHLMS_Project_Defense_Preparation_Guide.pdf"
OUTPUT.parent.mkdir(parents=True, exist_ok=True)


class NumberedCanvasMixin:
    def draw_header_footer(self, canvas, doc):
        canvas.saveState()
        width, height = letter
        canvas.setStrokeColor(HexColor("#D7DEE8"))
        canvas.setLineWidth(0.5)
        canvas.line(doc.leftMargin, height - 0.48 * inch, width - doc.rightMargin, height - 0.48 * inch)
        canvas.setFont("Helvetica", 8)
        canvas.setFillColor(HexColor("#64748B"))
        canvas.drawString(doc.leftMargin, height - 0.36 * inch, "UHLMS Project Defense Preparation")
        canvas.drawRightString(width - doc.rightMargin, 0.38 * inch, f"Page {doc.page}")
        canvas.restoreState()


class DefenseDocTemplate(BaseDocTemplate):
    def __init__(self, filename, **kwargs):
        super().__init__(filename, **kwargs)
        frame = Frame(
            self.leftMargin,
            self.bottomMargin,
            self.width,
            self.height,
            id="normal",
        )
        self.addPageTemplates([PageTemplate(id="main", frames=[frame], onPage=self.draw_header_footer)])

    draw_header_footer = NumberedCanvasMixin.draw_header_footer


styles = getSampleStyleSheet()
styles.add(ParagraphStyle(
    name="CoverTitle",
    parent=styles["Title"],
    fontName="Helvetica-Bold",
    fontSize=25,
    leading=30,
    alignment=TA_CENTER,
    textColor=HexColor("#12355B"),
    spaceAfter=14,
))
styles.add(ParagraphStyle(
    name="CoverSub",
    parent=styles["Normal"],
    fontName="Helvetica",
    fontSize=13,
    leading=18,
    alignment=TA_CENTER,
    textColor=HexColor("#475569"),
    spaceAfter=24,
))
styles.add(ParagraphStyle(
    name="H1Custom",
    parent=styles["Heading1"],
    fontName="Helvetica-Bold",
    fontSize=17,
    leading=21,
    textColor=HexColor("#12355B"),
    spaceBefore=8,
    spaceAfter=10,
))
styles.add(ParagraphStyle(
    name="H2Custom",
    parent=styles["Heading2"],
    fontName="Helvetica-Bold",
    fontSize=12.5,
    leading=16,
    textColor=HexColor("#0F766E"),
    spaceBefore=9,
    spaceAfter=5,
))
styles.add(ParagraphStyle(
    name="BodyCustom",
    parent=styles["BodyText"],
    fontName="Helvetica",
    fontSize=9.4,
    leading=13.5,
    textColor=HexColor("#1F2937"),
    spaceAfter=6,
))
styles.add(ParagraphStyle(
    name="SmallCustom",
    parent=styles["BodyText"],
    fontName="Helvetica",
    fontSize=8.4,
    leading=11.5,
    textColor=HexColor("#475569"),
    spaceAfter=4,
))
styles.add(ParagraphStyle(
    name="Question",
    parent=styles["BodyText"],
    fontName="Helvetica-Bold",
    fontSize=9.8,
    leading=13.5,
    textColor=HexColor("#12355B"),
    spaceBefore=5,
    spaceAfter=2,
))
styles.add(ParagraphStyle(
    name="Answer",
    parent=styles["BodyText"],
    fontName="Helvetica",
    fontSize=9.1,
    leading=13,
    leftIndent=12,
    borderPadding=6,
    borderColor=HexColor("#D7DEE8"),
    borderWidth=0.5,
    borderRadius=3,
    backColor=HexColor("#F8FAFC"),
    textColor=HexColor("#1F2937"),
    spaceAfter=6,
))
styles.add(ParagraphStyle(
    name="Script",
    parent=styles["BodyText"],
    fontName="Helvetica",
    fontSize=8.9,
    leading=12.1,
    textColor=HexColor("#1F2937"),
    spaceAfter=3,
))
styles.add(ParagraphStyle(
    name="Callout",
    parent=styles["BodyText"],
    fontName="Helvetica-Bold",
    fontSize=9.2,
    leading=13,
    textColor=HexColor("#0F4C5C"),
    backColor=HexColor("#E6F4F1"),
    borderColor=HexColor("#8AC6BC"),
    borderWidth=0.7,
    borderPadding=8,
    borderRadius=4,
    spaceBefore=5,
    spaceAfter=9,
))


def p(text, style="BodyCustom"):
    return Paragraph(text, styles[style])


def bullets(items):
    return [p(f"&#8226; {item}", "BodyCustom") for item in items]


def section(title, new_page=True):
    return ([PageBreak()] if new_page else []) + [p(title, "H1Custom")]


def qna(question, answer):
    return KeepTogether([p(question, "Question"), p(answer, "Answer")])


story = []

# Cover
story += [Spacer(1, 1.0 * inch), p("UH Lodging Management System", "CoverTitle")]
story += [p("Project Defense Preparation Guide", "CoverSub")]
story += [Spacer(1, 0.25 * inch)]
cover_box = Table(
    [[p("Master of Information Systems Capstone Defense", "BodyCustom")],
     [p("Use this guide as a speaking aid. Adapt the title, institution, defense duration, and evaluation terminology to your approved manuscript.", "SmallCustom")]],
    colWidths=[5.8 * inch],
)
cover_box.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), HexColor("#F1F5F9")),
    ("BOX", (0, 0), (-1, -1), 0.8, HexColor("#CBD5E1")),
    ("LEFTPADDING", (0, 0), (-1, -1), 14),
    ("RIGHTPADDING", (0, 0), (-1, -1), 14),
    ("TOPPADDING", (0, 0), (-1, -1), 10),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
]))
story += [cover_box, Spacer(1, 0.5 * inch)]
story += [p("Core message", "H2Custom")]
story += [p("UHLMS integrates guest reservations and staff lodging operations into one secure workflow, connecting availability, room allocation, payments, check-in, checkout, reports, notifications, and virtual tours.", "Callout")]
story += [p("Project basis", "H2Custom"), p("This guide reflects the current UHLMS implementation: Laravel 11, Filament, MySQL, PayMongo integration, a server-rendered guest website, and a specialized JavaScript virtual-tour subsystem.", "SmallCustom")]

# 1 Presentation structure
story += section("1. Defense Presentation Structure and Speaker Script")
story += [p("Recommended flow for a 10- to 15-minute defense. Adjust the timing to your panel's instructions.", "BodyCustom")]

slides = [
    ("1. Title and introduction", "Good day. Our project is the UH Lodging Management System, or UHLMS. It is an integrated information system designed to support guest reservations and lodging operations."),
    ("2. Background and problem", "Lodging operations can become difficult to manage when reservations, room availability, payments, guest records, and reports are handled through disconnected or manual processes. These conditions can cause delays, duplicate work, and inconsistent information."),
    ("3. Project objectives", "The project aims to centralize reservation management, improve room availability monitoring, support online and manual payments, assist check-in and checkout, provide administrative reports, and improve the guest experience through account, tracking, support, feedback, and virtual-tour features."),
    ("4. Scope and users", "The system serves guests, lodging staff, and administrators. Guest functions include room browsing, reservation submission, payment, tracking, support, feedback, and virtual tours. Staff functions include room management, reservation approval, room assignment, payment recording, check-in, checkout, notifications, and reports."),
    ("5. System architecture", "UHLMS uses a layered Laravel architecture. Routes receive requests, controllers or Filament resources coordinate actions, services apply business rules, Eloquent models manage data, and MySQL stores persistent records. Policies and middleware protect access, while jobs and scheduled commands handle background work."),
    ("6. Database design", "The database separates master data, reservation transactions, occupancy, financial records, audit logs, support, and virtual-tour data. In particular, room holds are separated from room assignments, and charges are separated from payments to preserve operational and financial clarity."),
    ("7. Main workflow demonstration", "The main workflow begins when a guest searches for a room and submits a reservation. Staff review the request, approve it, optionally place room holds, process payment, assign rooms during check-in, record charges, and complete the stay during checkout."),
    ("8. Security, testing, and reliability", "The system uses validation, authorization policies, rate limiting, signed links, security headers, protected payment processing, audit logs, queued work, backups, and automated verification. The current improvement area is database-test portability because one migration uses MySQL-specific syntax."),
    ("9. Results and contribution", "The result is a unified lodging platform that connects guest-facing and staff-facing workflows. Its contribution is not only digitizing reservations, but also connecting availability, room operations, payments, occupancy, communication, reporting, and virtual tours."),
    ("10. Limitations and conclusion", "The main limitations are duplicated pricing logic, additional concurrency testing needs, and the need to improve test portability. Future work will centralize pricing, strengthen room-allocation controls, improve analytics, and expand monitoring. UHLMS provides a strong foundation for more efficient and accountable lodging operations."),
]
for title, script in slides:
    story += [p(title, "H2Custom"), p(f"<b>Speaker script:</b> {script}", "Script")]

# 2 Architecture/database
story += section("2. Architecture and Database Design Explanation", new_page=False)
story += [p("UHLMS is a server-rendered Laravel application with a specialized JavaScript subsystem for panoramic tours. Its architecture separates interface concerns from business rules and data persistence.", "BodyCustom")]
story += [p("Request flow", "H2Custom")]
story += [p("User request -> route -> controller or Filament resource -> service layer -> Eloquent model -> MySQL -> response, notification, email, or queued job.", "Callout")]
story += [p("Main architectural layers", "H2Custom")]
story += bullets([
    "Routes map URLs and HTTP methods to application actions.",
    "Controllers handle request validation, normalization, and responses.",
    "Services contain reusable workflows such as reservation approval, room holds, check-in, and payments.",
    "Models define records, relationships, casts, and focused domain behavior.",
    "Policies and middleware enforce authentication, permissions, and security controls.",
    "Observers, notifications, mail, jobs, and scheduled commands support cross-cutting operations.",
])
story += [p("Database organization", "H2Custom")]
story += bullets([
    "Master data: users, guest accounts, floors, room types, rooms, amenities, and services.",
    "Reservation data: reservations, guests, room requests, alternative offers, holds, assignments, and check-in snapshots.",
    "Finance and audit: reservation charges, payments, feedback, and reservation logs.",
    "Guest support: inquiries and replies.",
    "Virtual tours: waypoints and hotspots.",
    "System support: settings, yearly reservation sequences, webhook events, notifications, queues, and cache tables.",
])
story += [p("Key design decisions", "H2Custom")]
story += bullets([
    "Room holds represent future inventory; room assignments represent actual occupancy.",
    "Charges represent amounts owed; payments represent amounts received.",
    "One reservation can contain multiple guests and room assignments.",
    "Date overlap logic treats stays as [check-in, check-out), so same-day checkout and check-in are compatible.",
    "The server recalculates availability during submission and approval rather than trusting browser data.",
])
story += [p("Defense explanation", "H2Custom"), p("The architecture is appropriate because the project is workflow-oriented and data-intensive. Laravel provides the application foundation, Filament supports staff operations, MySQL maintains structured records, and services keep important business rules reusable. The database design mirrors the actual lodging lifecycle, which makes the system easier to audit and extend.", "Callout")]

# 3 Demo
story += section("3. Live-Demo Sequence and Backup Steps")
story += [p("The demo should tell one complete story rather than showing unrelated screens. Use a prepared test reservation and avoid creating data for the first time during the defense.", "BodyCustom")]
demo_steps = [
    ("1. Guest landing page", "Show the lodging brand, room discovery, navigation, and available guest functions.", "If the landing page fails, open the prepared room catalog screenshot."),
    ("2. Room details and availability", "Select a room type and show pricing, amenities, capacity, and date-based availability.", "Use a screenshot of the room details page and explain the overlap rule verbally."),
    ("3. Reservation submission", "Submit a prepared guest reservation with valid dates and occupants. Point out validation and the generated reference number.", "Use a pre-created reservation and show the tracking screen."),
    ("4. Staff reservation review", "Open the Filament panel, locate the pending reservation, and show the review details and status controls.", "Use the admin reservation screenshot or a previously approved record."),
    ("5. Approval and room hold", "Approve the reservation and demonstrate room selection or explain that room assignment may occur at check-in.", "Explain the workflow using the database relationship: reservation -> room hold -> room assignment."),
    ("6. Payment", "Show the payment status or payment link and explain that the gateway webhook is authoritative.", "Do not depend on a live gateway. Use a test payment record or screenshot."),
    ("7. Check-in", "Assign a room, record guest details, create the check-in snapshot, and show the financial summary.", "Use a prepared checked-in reservation if the live flow is too risky."),
    ("8. Checkout and reports", "Show checkout, balance handling, reservation logs, dashboard statistics, or report export.", "Open a prepared report or exported file if the live export is unavailable."),
    ("9. Virtual tour", "Show a waypoint, hotspot, navigation, and room-type availability link.", "Use the saved virtual-tour screenshot if media or browser permissions fail."),
]
demo_table = [[p("Stage", "SmallCustom"), p("What to show", "SmallCustom"), p("Backup", "SmallCustom")]]
for a, b, c in demo_steps:
    demo_table.append([p(a, "SmallCustom"), p(b, "SmallCustom"), p(c, "SmallCustom")])
tbl = Table(demo_table, colWidths=[1.35 * inch, 2.75 * inch, 1.7 * inch], repeatRows=1)
tbl.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), HexColor("#12355B")),
    ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
    ("GRID", (0, 0), (-1, -1), 0.35, HexColor("#CBD5E1")),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("BACKGROUND", (0, 1), (-1, -1), HexColor("#F8FAFC")),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [HexColor("#F8FAFC"), colors.white]),
    ("LEFTPADDING", (0, 0), (-1, -1), 6),
    ("RIGHTPADDING", (0, 0), (-1, -1), 6),
    ("TOPPADDING", (0, 0), (-1, -1), 5),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
]))
story += [tbl, Spacer(1, 8)]
story += [p("Demo safety checklist", "H2Custom")]
story += bullets([
    "Prepare one pending, one approved, and one checked-in reservation.",
    "Use test accounts and non-sensitive payment data.",
    "Keep screenshots of every critical screen in a single folder.",
    "Have the local URL ready and confirm the Cloudflare URL before the defense if it will be used.",
    "Do not depend on live email delivery, real payment, or an untested database restore.",
    "If a screen fails, explain the intended workflow and continue using the prepared evidence.",
])

# 4 Q&A
story += section("4. Likely Panel Questions with Strong Answers")
qna_items = [
    ("What problem does your system address?", "UHLMS addresses the difficulty of managing lodging reservations, room availability, payments, guest records, check-in, checkout, and reports through disconnected or manual processes. It centralizes these activities into one system to improve accuracy, visibility, and operational efficiency."),
    ("Why did you develop this system?", "The system provides a structured and integrated solution for lodging operations. Instead of relying on separate records, messages, and manual computations, staff can manage the complete reservation lifecycle from one platform."),
    ("Who are the intended users?", "The primary users are lodging staff and administrators. Guests are also users because they can browse rooms, submit reservations, make payments, track bookings, use virtual tours, and provide feedback."),
    ("What is the main contribution of the project?", "The contribution is the integration of guest-facing reservation features with staff-facing operational management. The system connects availability, room allocation, payments, check-in, checkout, notifications, reports, and virtual tours in one workflow."),
    ("Why did you choose Laravel?", "Laravel provides routing, validation, authentication, authorization, migrations, queues, scheduling, notifications, and mail support. These capabilities fit a transaction-based information system such as lodging management."),
    ("Why use Filament?", "Filament supports staff forms, tables, filters, dashboards, and actions while integrating with Laravel models and authorization policies. It reduces repetitive administration code and allows more attention to the core business workflows."),
    ("Why not build the entire system as a single-page application?", "The system is primarily form- and workflow-oriented, so server-rendered Laravel pages are appropriate and easier to maintain. JavaScript is used where it provides clear value, especially in the interactive virtual-tour subsystem."),
    ("How does the system prevent double booking?", "Before creating a room hold, the server checks existing holds and active room assignments using date-overlap logic. The check is repeated during submission and approval, so the system does not depend only on browser information."),
    ("Why separate room holds and room assignments?", "A room hold protects future inventory, while a room assignment records actual guest occupancy. Separating them makes future availability different from current occupancy and improves auditability."),
    ("Why separate charges and payments?", "Charges represent what the guest owes, while payments represent what the guest has paid. This supports deposits, partial payments, multiple payment methods, and clearer financial auditing."),
    ("How is online payment verified?", "The browser initiates payment, but the authoritative result comes from the payment gateway webhook. The system processes and verifies the gateway event instead of trusting only the browser redirect."),
    ("How do you protect administrative functions?", "Administrative features use authentication, role and permission checks, authorization policies, middleware, security headers, and multi-factor authentication controls for sensitive staff operations."),
    ("What is the biggest limitation?", "Some pricing and discount rules are duplicated in different areas of the application, which creates a risk of inconsistent calculations. The recommended improvement is to centralize these rules in one pricing service and expand focused tests."),
    ("Can the system scale?", "The architecture can support growth through indexing, queue workers, scheduled jobs, caching, and separation of guest and administrative functions. Larger deployments would still require load testing, stronger concurrency controls, and database optimization."),
    ("What would you improve with more time?", "I would improve automated test portability, centralize pricing and discount calculations, add stronger concurrency protection for room holds, improve reporting analytics, and enhance monitoring and backup verification."),
    ("Did you use AI during development?", "AI assistance was used during parts of development, documentation, and code exploration. The output was reviewed against the running application, requirements, database structure, and tests. AI was treated as an aid, not as the final authority, and I remain responsible for understanding and validating the system."),
]
for question, answer in qna_items:
    story += [qna(question, answer)]

# 5 Technical explanations
story += section("5. Technical Explanations for an MSIS Defense")
technical = [
    ("Availability calculation", "The system calculates availability using room type, date range, capacity, room status, existing room holds, and active room assignments. Private rooms are generally counted as rooms, while shared accommodations are evaluated using available bed or slot capacity."),
    ("Date-overlap rule", "Two stays conflict when the existing check-in is earlier than the requested checkout and the existing checkout is later than the requested check-in. This treats reservations as half-open intervals: [check-in, check-out)."),
    ("Reservation lifecycle", "A reservation normally moves from pending to approved or declined, then to confirmed when a room hold or payment establishes a stronger commitment, followed by checked-in and checked-out states."),
    ("Payment model", "The system separates payment initiation from payment confirmation. The browser begins the transaction, while the gateway webhook provides the authoritative asynchronous result. Webhook events are processed as server-side business events."),
    ("Auditability", "Reservation logs, payment records, charge records, reviewer fields, and check-in snapshots preserve important business events and financial changes. This makes the system easier to investigate than a single mutable reservation record."),
    ("Request-aware URLs", "Relative or request-aware links help the same application operate under localhost and the Cloudflare-hosted domain. Absolute URLs remain necessary for external callbacks, email links, QR codes, and some signed URLs."),
    ("Layered security", "Security is implemented at several layers: input validation, rate limiting, authentication, authorization policies, signed links, CSRF protection where applicable, security headers, webhook verification, logging, and restricted administrative actions."),
]
for title, body in technical:
    story += [p(title, "H2Custom"), p(body, "BodyCustom")]

# 6 limitations etc.
story += section("6. Limitations, Security, Testing, Scalability, and Future Work")
story += [p("Limitations", "H2Custom")]
story += bullets([
    "Some pricing and discount rules are repeated across services and administrative pages.",
    "Room allocation needs additional concurrency-focused testing for simultaneous approvals.",
    "The current automated PHP suite is blocked by a SQLite-incompatible migration using MySQL-specific syntax.",
    "External payment, email, and Cloudflare features depend on network and service availability.",
])
story += [p("Security position", "H2Custom")]
story += [p("The system protects administrative routes through authentication, permissions, policies, middleware, security headers, and MFA-related controls. Guest tracking uses controlled identifiers and signed links. Public reservation submission uses validation, throttling, and anti-spam protection. Payment results are confirmed through the gateway webhook rather than trusted solely from the browser.", "BodyCustom")]
story += [p("Testing position", "H2Custom")]
story += [p("Verification includes application boot checks, route checks, PHP syntax checks, frontend build checks, and automated tests for models, services, policies, controllers, payments, and administrative behavior. The honest qualification is that the suite currently needs a portable migration fix before its aggregate results can be treated as a complete quality measure.", "BodyCustom")]
story += [p("Scalability position", "H2Custom")]
story += [p("The system can grow through database indexing, caching, queue workers, scheduled processing, optimized queries, and separation of concerns. At higher scale, it would need load testing, stronger room-allocation serialization, centralized monitoring, and possibly independent workers or horizontally scaled application instances.", "BodyCustom")]
story += [p("Future improvements", "H2Custom")]
story += bullets([
    "Create one authoritative reservation pricing service.",
    "Make migrations and automated tests portable across supported databases.",
    "Add concurrency tests and stronger locking or allocation controls.",
    "Expand management dashboards and trend-based analytics.",
    "Improve observability, backup verification, and operational alerts.",
    "Continue improving accessibility, mobile behavior, and guest self-service.",
])

# 7 cheat sheet
story += section("7. Concise Reviewer and Memorization Cheat Sheet")
story += [p("One-sentence project summary", "H2Custom"), p("UHLMS is an integrated lodging information system that connects guest reservations with room availability, payments, check-in, checkout, staff operations, reports, notifications, and virtual tours.", "Callout")]
story += [p("Five points to remember", "H2Custom")]
story += bullets([
    "Architecture: Laravel layered application with Filament administration and a JavaScript virtual-tour subsystem.",
    "Core workflow: browse -> reserve -> review -> approve -> hold or pay -> check in -> charge -> check out.",
    "Database principle: separate reservation, occupancy, finance, audit, support, and tour concerns.",
    "Security principle: validate and authorize on the server; use webhook confirmation for payments.",
    "Improvement principle: centralize repeated rules, restore trustworthy tests, and strengthen concurrency controls.",
])
story += [p("Key terms", "H2Custom")]
terms = [
    [p("Term", "SmallCustom"), p("Meaning", "SmallCustom")],
    [p("Room hold", "SmallCustom"), p("Future inventory protection for an approved reservation.", "SmallCustom")],
    [p("Room assignment", "SmallCustom"), p("Actual room occupancy recorded during check-in.", "SmallCustom")],
    [p("Reservation charge", "SmallCustom"), p("An amount owed by the guest.", "SmallCustom")],
    [p("Reservation payment", "SmallCustom"), p("An amount received from the guest or gateway.", "SmallCustom")],
    [p("Webhook", "SmallCustom"), p("A server-to-server event sent by the payment gateway.", "SmallCustom")],
    [p("Policy", "SmallCustom"), p("An authorization rule determining whether a user may perform an action.", "SmallCustom")],
]
term_table = Table(terms, colWidths=[1.55 * inch, 4.25 * inch], repeatRows=1)
term_table.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), HexColor("#12355B")),
    ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
    ("GRID", (0, 0), (-1, -1), 0.35, HexColor("#CBD5E1")),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [HexColor("#F8FAFC"), colors.white]),
    ("LEFTPADDING", (0, 0), (-1, -1), 7),
    ("RIGHTPADDING", (0, 0), (-1, -1), 7),
    ("TOPPADDING", (0, 0), (-1, -1), 5),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
]))
story += [term_table, Spacer(1, 10)]
story += [p("Answer formula", "H2Custom"), p("Direct answer -> design or evidence -> limitation -> improvement.", "Callout")]
story += [p("Example: How secure is the system?", "Question"), p("The system uses layered security including validation, authentication, authorization policies, rate limiting, signed links, security headers, and webhook verification. Its improvement area is continued security testing and operational monitoring as deployment scale increases.", "Answer")]
story += [p("Final reminder", "H2Custom"), p("Defend the actual system honestly. A strong defense does not claim that the system is perfect; it explains why the design is appropriate, what evidence supports it, what limitations remain, and how the system can be improved.", "Callout")]


doc = DefenseDocTemplate(
    str(OUTPUT),
    pagesize=letter,
    rightMargin=0.55 * inch,
    leftMargin=0.55 * inch,
    topMargin=0.68 * inch,
    bottomMargin=0.62 * inch,
    title="UHLMS Project Defense Preparation Guide",
    author="OpenAI Codex",
)
doc.build(story)
print(OUTPUT)
