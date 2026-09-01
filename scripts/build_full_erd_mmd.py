import subprocess
from collections import defaultdict
from pathlib import Path


ROOT = Path(r"D:\xampp\htdocs\MIS\uhlms")
MYSQL = Path(r"D:\xampp\mysql\bin\mysql.exe")
OUTPUT = ROOT / "tmp" / "pdfs" / "uhlms-operational-full-fields-erd.mmd"
OUTPUT.parent.mkdir(parents=True, exist_ok=True)


def mysql_rows(sql):
    result = subprocess.run(
        [
            str(MYSQL),
            "--host=127.0.0.1",
            "--port=3306",
            "--user=root",
            "--database=uhlms",
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
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, ORDINAL_POSITION
"""
foreign_keys_sql = """
SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, COLUMN_NAME
"""

column_rows = mysql_rows(columns_sql)
foreign_key_rows = mysql_rows(foreign_keys_sql)
tables = defaultdict(list)
for table, column, column_type, column_key in column_rows:
    base_type = column_type.split("(", 1)[0].split()[0].lower()
    if base_type == "tinyint" and "(1)" in column_type:
        base_type = "boolean"
    tables[table].append((column, base_type, column_key))

fk_columns = {(row[0], row[1]) for row in foreign_key_rows}

preferred_order = [
    "users", "guest_accounts", "floors", "room_types", "rooms", "amenities", "amenity_room_type", "services",
    "reservations", "guests", "reservation_room_requests", "reservation_alternative_offers", "room_holds",
    "room_assignments", "check_in_snapshots", "reservation_charges", "reservation_payments",
    "reservation_feedback", "reservation_logs", "support_inquiries", "support_inquiry_replies",
    "tour_waypoints", "tour_hotspots", "settings", "reservation_sequences", "payment_webhook_events",
    "force_deletion_logs", "notifications", "sessions", "cache", "cache_locks", "jobs", "job_batches",
    "failed_jobs", "migrations", "password_reset_tokens", "guest_password_reset_tokens",
]
operational_tables = set(preferred_order[:19])
order_index = {name: index for index, name in enumerate(preferred_order)}
table_names = sorted(
    (name for name in tables if name in operational_tables),
    key=lambda name: (order_index.get(name, 999), name),
)

lines = [
    '%%{init: {',
    '  "theme": "base",',
    '  "themeVariables": {',
    '    "primaryColor": "#eef6ff",',
    '    "primaryTextColor": "#102a43",',
    '    "primaryBorderColor": "#2f6f9f",',
    '    "lineColor": "#486581",',
    '    "secondaryColor": "#f4f7fa",',
    '    "tertiaryColor": "#ffffff",',
    '    "fontFamily": "Arial, sans-serif",',
    '    "fontSize": "11px"',
    '  },',
    '  "htmlLabels": false,',
    '  "er": {',
    '    "useMaxWidth": false,',
    '    "minEntityWidth": 240,',
    '    "minEntityHeight": 50,',
    '    "entityPadding": 10,',
    '    "fontSize": 11',
    '  },',
    '  "layout": "elk"',
    '}}%%',
    'erDiagram',
]

for table in table_names:
    lines.append(f"    {table.upper()} {{")
    for column, base_type, column_key in tables[table]:
        # Mermaid ER attributes accept one key marker. Foreign-key lines
        # below preserve the complete relationship information.
        if column_key == "PRI":
            marker = " PK"
        elif column_key == "UNI":
            marker = " UK"
        elif (table, column) in fk_columns:
            marker = " FK"
        else:
            marker = ""
        suffix = marker
        lines.append(f"        {base_type} {column}{suffix}")
    lines.append("    }")
    lines.append("")

relation_columns = defaultdict(list)
for child_table, child_column, parent_table, parent_column in foreign_key_rows:
    if child_table not in operational_tables or parent_table not in operational_tables:
        continue
    relation_columns[(parent_table.upper(), child_table.upper())].append(f"{child_column} -> {parent_column}")

for (parent, child), labels in sorted(relation_columns.items()):
    label = ", ".join(labels).replace('"', "'")
    lines.append(f'    {parent} ||--o{{ {child} : "{label}"')

OUTPUT.write_text("\n".join(lines) + "\n", encoding="utf-8")
print(OUTPUT)
included_column_count = sum(len(tables[name]) for name in table_names)
print(f"tables={len(table_names)} columns={included_column_count} relationships={len(relation_columns)}")
