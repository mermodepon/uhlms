import subprocess
from collections import defaultdict
from datetime import date
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import landscape, letter
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch
from reportlab.platypus import BaseDocTemplate, Frame, PageTemplate, Paragraph, Spacer, PageBreak, Table, TableStyle, LongTable
from reportlab.lib.colors import HexColor


ROOT = Path(r"D:\xampp\htdocs\MIS\uhlms")
OUTPUT = ROOT / "output" / "pdf" / "uhlms-database-erd-full-fields.pdf"
MYSQL = Path(r"D:\xampp\mysql\bin\mysql.exe")
DATABASE = "uhlms"
OUTPUT.parent.mkdir(parents=True, exist_ok=True)


def mysql_rows(sql):
    result = subprocess.run(
        [
            str(MYSQL),
            "--host=127.0.0.1",
            "--port=3306",
            "--user=root",
            f"--database={DATABASE}",
            "--batch",
            "--raw",
            "--skip-column-names",
            f"--execute={sql}",
        ],
        capture_output=True,
        text=True,
        check=True,
    )
    return [line.split("\t") for line in result.stdout.splitlines() if line.strip()]


columns_sql = """
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
       COALESCE(COLUMN_DEFAULT, '\\N'), COLUMN_KEY, EXTRA, ORDINAL_POSITION
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, ORDINAL_POSITION
"""
tables_sql = """
SELECT TABLE_NAME, TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME
"""
foreign_keys_sql = """
SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME,
       REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME
"""


column_rows = mysql_rows(columns_sql)
table_rows = mysql_rows(tables_sql)
foreign_key_rows = mysql_rows(foreign_keys_sql)

tables = defaultdict(list)
for row in column_rows:
    table, name, column_type, nullable, default, key, extra, ordinal = row
    tables[table].append({
        "name": name,
        "type": column_type,
        "nullable": nullable,
        "default": "-" if default in {r"\N", "N"} else default,
        "key": key or "-",
        "extra": extra or "-",
        "ordinal": ordinal,
    })

comments = {row[0]: row[1] for row in table_rows}
fk_by_column = defaultdict(list)
for table, column, constraint, ref_table, ref_column in foreign_key_rows:
    fk_by_column[(table, column)].append(f"{ref_table}.{ref_column}")

preferred_order = [
    "users", "guest_accounts", "floors", "room_types", "rooms", "amenities", "amenity_room_type", "services",
    "reservations", "guests", "reservation_room_requests", "reservation_alternative_offers", "room_holds",
    "room_assignments", "check_in_snapshots", "reservation_charges", "reservation_payments",
    "reservation_feedback", "reservation_logs", "support_inquiries", "support_inquiry_replies",
    "tour_waypoints", "tour_hotspots", "settings", "reservation_sequences", "payment_webhook_events",
    "force_deletion_logs", "notifications", "sessions", "cache", "cache_locks", "jobs", "job_batches",
    "failed_jobs", "migrations", "password_reset_tokens", "guest_password_reset_tokens",
]
order_index = {name: index for index, name in enumerate(preferred_order)}
table_names = sorted(tables, key=lambda name: (order_index.get(name, 999), name))

operational = set(preferred_order[:19])
support = {"support_inquiries", "support_inquiry_replies", "tour_waypoints", "tour_hotspots", "settings", "reservation_sequences", "payment_webhook_events", "force_deletion_logs"}
framework = set(table_names) - operational - support


class SchemaDocTemplate(BaseDocTemplate):
    def __init__(self, filename, **kwargs):
        super().__init__(filename, **kwargs)
        frame = Frame(self.leftMargin, self.bottomMargin, self.width, self.height, id="schema")
        self.addPageTemplates([PageTemplate(id="schema", frames=[frame], onPage=self.header_footer)])

    def header_footer(self, canvas, doc):
        canvas.saveState()
        width, height = landscape(letter)
        canvas.setStrokeColor(HexColor("#D7DEE8"))
        canvas.setLineWidth(0.5)
        canvas.line(doc.leftMargin, height - 0.42 * inch, width - doc.rightMargin, height - 0.42 * inch)
        canvas.setFont("Helvetica", 7.5)
        canvas.setFillColor(HexColor("#64748B"))
        canvas.drawString(doc.leftMargin, height - 0.31 * inch, "UHLMS Full-Field Database Schema")
        canvas.drawRightString(width - doc.rightMargin, 0.30 * inch, f"Page {doc.page}")
        canvas.restoreState()


styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name="CoverTitle", parent=styles["Title"], fontName="Helvetica-Bold", fontSize=24, leading=29, alignment=TA_CENTER, textColor=HexColor("#12355B"), spaceAfter=12))
styles.add(ParagraphStyle(name="CoverSub", parent=styles["Normal"], fontName="Helvetica", fontSize=12, leading=16, alignment=TA_CENTER, textColor=HexColor("#475569"), spaceAfter=20))
styles.add(ParagraphStyle(name="H1", parent=styles["Heading1"], fontName="Helvetica-Bold", fontSize=16, leading=19, textColor=HexColor("#12355B"), spaceBefore=4, spaceAfter=8))
styles.add(ParagraphStyle(name="H2", parent=styles["Heading2"], fontName="Helvetica-Bold", fontSize=11.5, leading=14, textColor=HexColor("#0F766E"), spaceBefore=6, spaceAfter=4))
styles.add(ParagraphStyle(name="Body", parent=styles["BodyText"], fontName="Helvetica", fontSize=8.6, leading=11.5, textColor=HexColor("#1F2937"), spaceAfter=5))
styles.add(ParagraphStyle(name="Small", parent=styles["BodyText"], fontName="Helvetica", fontSize=7.3, leading=9, textColor=HexColor("#475569"), spaceAfter=2))
styles.add(ParagraphStyle(name="TableName", parent=styles["Heading2"], fontName="Helvetica-Bold", fontSize=11, leading=13, textColor=HexColor("#12355B"), spaceBefore=5, spaceAfter=3))
styles.add(ParagraphStyle(name="Cell", parent=styles["BodyText"], fontName="Helvetica", fontSize=7.0, leading=8.4, textColor=HexColor("#1F2937"), spaceAfter=0))
styles.add(ParagraphStyle(name="CellHead", parent=styles["BodyText"], fontName="Helvetica-Bold", fontSize=7.0, leading=8.4, textColor=colors.white, spaceAfter=0))
styles.add(ParagraphStyle(name="Callout", parent=styles["BodyText"], fontName="Helvetica-Bold", fontSize=8.6, leading=11.5, textColor=HexColor("#0F4C5C"), backColor=HexColor("#E6F4F1"), borderColor=HexColor("#8AC6BC"), borderWidth=0.7, borderPadding=8, borderRadius=4, spaceBefore=5, spaceAfter=8))


def para(text, style="Body"):
    safe = str(text).replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
    return Paragraph(safe, styles[style])


def header_para(text):
    safe = str(text).replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
    return Paragraph(safe, styles["CellHead"])


def field_para(text):
    safe = str(text).replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
    return Paragraph(safe, styles["Cell"])


story = []
story += [Spacer(1, 0.85 * inch), para("UH Lodging Management System", "CoverTitle"), para("Full-Field Database ERD and Schema Reference", "CoverSub")]
summary = Table([
    [para("Purpose", "Small"), para("This version shows every current column in the live MySQL database, including primary keys, foreign keys, nullable fields, defaults, auto-increment details, JSON fields, timestamps, and Laravel infrastructure fields.", "Small")],
    [para("Source", "Small"), para(f"Database: {DATABASE}; generated: {date.today().isoformat()}; schema read from information_schema.", "Small")],
    [para("How to use", "Small"), para("Use this document for field-level defense questions. Use the compact ERD for a high-level relationship explanation.", "Small")],
], colWidths=[1.0 * inch, 8.8 * inch])
summary.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), HexColor("#F1F5F9")),
    ("BOX", (0, 0), (-1, -1), 0.7, HexColor("#CBD5E1")),
    ("INNERGRID", (0, 0), (-1, -1), 0.3, HexColor("#CBD5E1")),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 8),
    ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ("TOPPADDING", (0, 0), (-1, -1), 7),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
]))
story += [summary, Spacer(1, 12), para(f"The live schema contains {len(table_names)} tables and {len(column_rows)} columns.", "Callout")]

story += [para("Table groups", "H2")]
story += [para("Operational tables contain lodging, reservation, occupancy, finance, and audit records. Supporting application tables contain support, tour, settings, webhook, and deletion records. Framework tables support Laravel sessions, queues, cache, migrations, and password resets.", "Body")]
story += [para("Key legend: PRI = primary key, UNI = unique key, MUL = indexed/non-unique key, FK target = referenced table and column shown in the final column.", "Small")]
story += [PageBreak(), para("Complete Field Reference", "H1")]

def add_table(table_name):
    group = "Operational" if table_name in operational else ("Supporting application" if table_name in support else "Laravel/framework")
    heading = f"{table_name}  [{group}]"
    story.append(para(heading, "TableName"))
    if comments.get(table_name):
        story.append(para(comments[table_name], "Small"))
    rows = [[header_para("#"), header_para("Field"), header_para("Type"), header_para("Null"), header_para("Default"), header_para("Key"), header_para("Extra"), header_para("Foreign-key target")]]
    for index, field in enumerate(tables[table_name], start=1):
        targets = ", ".join(fk_by_column.get((table_name, field["name"]), [])) or "-"
        rows.append([
            field_para(index), field_para(field["name"]), field_para(field["type"]), field_para(field["nullable"]),
            field_para(field["default"]), field_para(field["key"]), field_para(field["extra"]), field_para(targets),
        ])
    table = LongTable(rows, colWidths=[0.30 * inch, 1.75 * inch, 1.35 * inch, 0.45 * inch, 1.35 * inch, 0.42 * inch, 1.05 * inch, 2.03 * inch], repeatRows=1, hAlign="LEFT")
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), HexColor("#12355B")),
        ("GRID", (0, 0), (-1, -1), 0.3, HexColor("#CBD5E1")),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [HexColor("#F8FAFC"), colors.white]),
        ("LEFTPADDING", (0, 0), (-1, -1), 4),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4),
        ("TOPPADDING", (0, 0), (-1, -1), 3),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
    ]))
    story.append(table)
    story.append(Spacer(1, 7))


for name in table_names:
    add_table(name)

story.append(PageBreak())
story.append(para("Foreign-Key Relationship Appendix", "H1"))
story.append(para("These relationships are read directly from information_schema.KEY_COLUMN_USAGE. Polymorphic and application-managed references may not appear here because they are not enforced by MySQL foreign keys.", "Body"))
relationship_rows = [[header_para("Child table.field"), header_para("Constraint"), header_para("Referenced table.field")]]
for table, column, constraint, ref_table, ref_column in foreign_key_rows:
    relationship_rows.append([field_para(f"{table}.{column}"), field_para(constraint), field_para(f"{ref_table}.{ref_column}")])
relationship_table = LongTable(relationship_rows, colWidths=[3.2 * inch, 2.2 * inch, 3.3 * inch], repeatRows=1)
relationship_table.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), HexColor("#12355B")),
    ("GRID", (0, 0), (-1, -1), 0.3, HexColor("#CBD5E1")),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [HexColor("#F8FAFC"), colors.white]),
    ("LEFTPADDING", (0, 0), (-1, -1), 5),
    ("RIGHTPADDING", (0, 0), (-1, -1), 5),
    ("TOPPADDING", (0, 0), (-1, -1), 3),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
]))
story.append(relationship_table)

doc = SchemaDocTemplate(
    str(OUTPUT),
    pagesize=landscape(letter),
    leftMargin=0.42 * inch,
    rightMargin=0.42 * inch,
    topMargin=0.56 * inch,
    bottomMargin=0.48 * inch,
    title="UHLMS Full-Field Database ERD and Schema Reference",
    author="OpenAI Codex",
)
doc.build(story)
print(OUTPUT)
