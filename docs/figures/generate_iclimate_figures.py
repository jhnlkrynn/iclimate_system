from __future__ import annotations

from dataclasses import dataclass
from html import escape
from pathlib import Path
import textwrap


OUT_DIR = Path(__file__).resolve().parent
WIDTH = 1600


@dataclass
class Box:
    id: str
    x: int
    y: int
    w: int
    h: int
    title: str
    lines: list[str]
    kind: str = "process"


PALETTE = {
    "input": ("#e8f5e9", "#2e7d32"),
    "process": ("#e3f2fd", "#1565c0"),
    "output": ("#fff8e1", "#ef6c00"),
    "data": ("#f3e5f5", "#6a1b9a"),
    "actor": ("#fce4ec", "#ad1457"),
    "external": ("#eceff1", "#455a64"),
    "warning": ("#ffebee", "#c62828"),
}


def wrap_lines(text: str, width: int = 26) -> list[str]:
    return textwrap.wrap(text, width=width, break_long_words=False) or [text]


def box_center(box: Box) -> tuple[int, int]:
    return box.x + box.w // 2, box.y + box.h // 2


def edge_point(box: Box, toward: tuple[int, int]) -> tuple[int, int]:
    cx, cy = box_center(box)
    tx, ty = toward
    dx, dy = tx - cx, ty - cy
    if dx == 0 and dy == 0:
        return cx, cy
    scale_x = (box.w / 2) / abs(dx) if dx else float("inf")
    scale_y = (box.h / 2) / abs(dy) if dy else float("inf")
    scale = min(scale_x, scale_y)
    return int(cx + dx * scale), int(cy + dy * scale)


def render_box(box: Box) -> str:
    fill, stroke = PALETTE.get(box.kind, PALETTE["process"])
    parts = [
        f'<rect x="{box.x}" y="{box.y}" width="{box.w}" height="{box.h}" '
        f'rx="12" fill="{fill}" stroke="{stroke}" stroke-width="3"/>'
    ]
    parts.append(
        f'<text x="{box.x + box.w / 2}" y="{box.y + 34}" text-anchor="middle" '
        f'class="box-title">{escape(box.title)}</text>'
    )
    y = box.y + 68
    for line in box.lines:
        for wrapped in wrap_lines(line, max(18, box.w // 16)):
            parts.append(
                f'<text x="{box.x + box.w / 2}" y="{y}" text-anchor="middle" '
                f'class="box-line">{escape(wrapped)}</text>'
            )
            y += 24
        y += 5
    return "\n".join(parts)


def arrow(boxes: dict[str, Box], src: str, dst: str, label: str = "") -> str:
    a = boxes[src]
    b = boxes[dst]
    bx, by = box_center(b)
    ax, ay = edge_point(a, (bx, by))
    ex, ey = edge_point(b, box_center(a))
    midx, midy = (ax + ex) // 2, (ay + ey) // 2
    line = (
        f'<line x1="{ax}" y1="{ay}" x2="{ex}" y2="{ey}" '
        f'stroke="#37474f" stroke-width="3" marker-end="url(#arrow)"/>'
    )
    if not label:
        return line
    return (
        line
        + f'\n<rect x="{midx - 115}" y="{midy - 24}" width="230" height="30" '
        + 'rx="8" fill="#ffffff" stroke="#cfd8dc"/>'
        + f'\n<text x="{midx}" y="{midy - 4}" text-anchor="middle" '
        + f'class="edge-label">{escape(label)}</text>'
    )


def svg(title: str, height: int, boxes: list[Box], edges: list[tuple[str, str, str]], note: str = "") -> str:
    box_map = {box.id: box for box in boxes}
    rendered_edges = "\n".join(arrow(box_map, *edge) for edge in edges)
    rendered_boxes = "\n".join(render_box(box) for box in boxes)
    note_text = (
        f'<text x="{WIDTH / 2}" y="{height - 34}" text-anchor="middle" class="note">{escape(note)}</text>'
        if note
        else ""
    )
    return f'''<svg xmlns="http://www.w3.org/2000/svg" width="{WIDTH}" height="{height}" viewBox="0 0 {WIDTH} {height}" role="img" aria-labelledby="title desc">
  <title id="title">{escape(title)}</title>
  <desc id="desc">Diagram for the iClimate web-based decision-support platform.</desc>
  <defs>
    <marker id="arrow" markerWidth="14" markerHeight="10" refX="12" refY="5" orient="auto" markerUnits="strokeWidth">
      <path d="M 0 0 L 14 5 L 0 10 z" fill="#37474f"/>
    </marker>
    <style>
      .title {{ font-family: Arial, Helvetica, sans-serif; font-size: 34px; font-weight: 700; fill: #1b1b1b; }}
      .subtitle {{ font-family: Arial, Helvetica, sans-serif; font-size: 18px; fill: #455a64; }}
      .box-title {{ font-family: Arial, Helvetica, sans-serif; font-size: 22px; font-weight: 700; fill: #1b1b1b; }}
      .box-line {{ font-family: Arial, Helvetica, sans-serif; font-size: 18px; fill: #263238; }}
      .edge-label {{ font-family: Arial, Helvetica, sans-serif; font-size: 15px; fill: #263238; }}
      .note {{ font-family: Arial, Helvetica, sans-serif; font-size: 17px; fill: #455a64; }}
      .phase {{ font-family: Arial, Helvetica, sans-serif; font-size: 19px; font-weight: 700; fill: #1b1b1b; }}
    </style>
  </defs>
  <rect width="{WIDTH}" height="{height}" fill="#ffffff"/>
  <text x="{WIDTH / 2}" y="54" text-anchor="middle" class="title">{escape(title)}</text>
  {rendered_edges}
  {rendered_boxes}
  {note_text}
</svg>
'''


def write(name: str, content: str) -> None:
    (OUT_DIR / name).write_text(content, encoding="utf-8")


def figure_1() -> None:
    boxes = [
        Box("inputs", 80, 155, 360, 270, "Climate and Farm Inputs", ["Rainfall, temperature, humidity, wind speed", "Barangay, season, farm area, farm type", "Rice production and user requests"], "input"),
        Box("theory", 570, 145, 460, 290, "Theoretical Basis", ["Decision Support System", "Climate-smart agriculture", "Predictive modeling", "Web-based agricultural information system"], "process"),
        Box("system", 1160, 155, 360, 270, "iClimate Processes", ["Weather analysis", "Rice yield prediction", "Advisory generation", "Risk mapping", "Reports and notifications"], "process"),
        Box("outcome", 570, 540, 460, 180, "Decision-Support Outcome", ["Climate-informed rice farming decisions", "Farmer and MAO planning support"], "output"),
    ]
    edges = [("inputs", "theory", "feeds"), ("theory", "system", "guides"), ("system", "outcome", "produces"), ("inputs", "outcome", "supports")]
    write("fig01-theoretical-framework.svg", svg("Figure 1. Theoretical Framework", 800, boxes, edges))


def figure_2() -> None:
    boxes = [
        Box("input", 70, 160, 320, 410, "Input", ["PAGASA climate records", "Open-Meteo forecasts", "MAO rice records", "Farm area and type", "Barangay and season", "User role/request"], "input"),
        Box("process", 470, 160, 420, 410, "Process", ["Collect and validate data", "Store in MySQL", "Analyze weather variables", "Run prediction model", "Generate advisories", "Score agricultural risk", "Prepare dashboards/reports"], "process"),
        Box("output", 970, 160, 320, 410, "Output", ["Weather forecast", "Rice yield estimate", "Planting advisory", "Irrigation recommendation", "Risk heat map", "Reports and notifications", "AI response"], "output"),
        Box("outcome", 480, 660, 640, 130, "Outcome", ["Web-based climate-informed decision support for rice farming in Lian, Batangas"], "data"),
    ]
    edges = [("input", "process", ""), ("process", "output", ""), ("output", "outcome", "")]
    write("fig02-conceptual-framework.svg", svg("Figure 2. Conceptual Framework", 860, boxes, edges))


def figure_3() -> None:
    labels = [
        ("req", 620, 125, "Requirements", ["Users, data needs, advisories"]),
        ("plan", 970, 265, "Planning", ["Modules, roles, stack"]),
        ("design", 1060, 545, "Design", ["Schema, UI, diagrams"]),
        ("dev", 790, 735, "Development", ["Laravel, Python, APIs"]),
        ("test", 430, 735, "Testing", ["Feature and unit tests"]),
        ("review", 160, 545, "Review", ["Gap fixes and revisions"]),
        ("deploy", 250, 265, "Deployment", ["XAMPP/Laravel/MySQL"]),
    ]
    boxes = [Box(i, x, y, 260, 125, title, lines, "process") for i, x, y, title, lines in labels]
    boxes.append(Box("maintain", 590, 455, 360, 125, "Iteration and Maintenance", ["Refresh forecasts, advisories, logs, models"], "output"))
    edges = [("req", "plan", ""), ("plan", "design", ""), ("design", "dev", ""), ("dev", "test", ""), ("test", "review", ""), ("review", "deploy", ""), ("deploy", "req", ""), ("maintain", "req", "feedback")]
    write("fig03-agile-methodology.svg", svg("Figure 3. Agile Methodology", 930, boxes, edges, "Agile cycle applied to iClimate system development."))


def figure_4() -> None:
    boxes = [
        Box("users", 70, 160, 300, 170, "User Layer", ["Farmer", "MAO Personnel", "IT Expert/Admin"], "actor"),
        Box("ui", 470, 130, 300, 230, "Presentation Layer", ["Blade views", "Dashboards", "Forms", "Heat map", "AI chat"], "process"),
        Box("app", 870, 120, 320, 250, "Application Layer", ["Routes and controllers", "Middleware", "Validation", "Role access"], "process"),
        Box("services", 1230, 120, 300, 250, "Service Layer", ["Prediction services", "Advisory engine", "Risk scorer", "AI orchestrator"], "process"),
        Box("db", 300, 520, 360, 210, "Data Layer", ["MySQL database", "Laravel models", "Model files in storage"], "data"),
        Box("python", 760, 520, 320, 210, "ML/Python Layer", ["predict.py", "Farming AI API", "Random Forest model", "Fallback estimate"], "external"),
        Box("external", 1180, 520, 320, 210, "External Services", ["PAGASA pages", "Open-Meteo API", "Groq API"], "external"),
    ]
    edges = [("users", "ui", ""), ("ui", "app", ""), ("app", "services", ""), ("app", "db", ""), ("services", "python", ""), ("services", "external", ""), ("services", "db", "read/write")]
    write("fig04-system-architecture.svg", svg("Figure 4. System Architecture", 820, boxes, edges))


def figure_5() -> None:
    boxes = [
        Box("stake", 70, 160, 330, 230, "Stakeholder Needs", ["Forecasts", "Yield estimates", "Advisories", "Risk visibility", "Reports"], "actor"),
        Box("functional", 500, 130, 360, 320, "Functional Requirements", ["Authentication", "Record management", "Prediction", "Advisory management", "Heat map", "Reports", "Notifications"], "process"),
        Box("nonfunc", 500, 520, 360, 250, "Non-Functional Requirements", ["Security", "Validation", "Responsiveness", "Usability", "Maintainability", "Performance"], "warning"),
        Box("modules", 980, 160, 440, 420, "System Modules", ["Dashboards", "Climate records", "Rice production", "Weather/yield prediction", "Advisories", "Heat map", "AI assistant", "Messaging/community", "User and log management"], "output"),
    ]
    edges = [("stake", "functional", ""), ("stake", "nonfunc", ""), ("functional", "modules", ""), ("nonfunc", "modules", "quality constraints")]
    write("fig05-requirement-analysis.svg", svg("Figure 5. Requirement Analysis", 850, boxes, edges))


def figure_6() -> None:
    boxes = [
        Box("farmer", 70, 170, 250, 250, "Farmer", ["View dashboard", "View forecasts", "Request prediction", "Read advisories", "Use AI assistant"], "actor"),
        Box("mao", 70, 510, 250, 250, "MAO Personnel", ["Manage records", "Approve advisories", "Generate reports", "Post updates"], "actor"),
        Box("admin", 1280, 170, 250, 250, "IT Expert/Admin", ["Manage users", "View logs", "Evaluate model", "Monitor system"], "actor"),
        Box("external", 1280, 510, 250, 250, "External Services", ["Weather data", "Advisory data", "AI response"], "external"),
        Box("system", 520, 120, 560, 690, "iClimate Use Cases", ["Authenticate user", "Manage farmer profiles", "Manage climate/rice records", "Forecast weather", "Predict rice yield", "Generate planting advisories", "Display risk heat map", "Generate reports", "Send notifications", "Support messages/feed"], "process"),
    ]
    edges = [("farmer", "system", ""), ("mao", "system", ""), ("admin", "system", ""), ("external", "system", "supplies data")]
    write("fig06-use-case-diagram.svg", svg("Figure 6. Use Case Diagram", 900, boxes, edges))


def figure_7() -> None:
    boxes = [
        Box("system", 560, 290, 480, 240, "iClimate Platform", ["Web-based decision-support system", "Forecasting, prediction, advisories, reports"], "process"),
        Box("farmer", 80, 150, 300, 150, "Farmer", ["Farm data", "Prediction request"], "actor"),
        Box("mao", 80, 560, 300, 150, "MAO Personnel", ["Climate/rice records", "Advisory decisions"], "actor"),
        Box("admin", 1220, 150, 300, 150, "IT Expert/Admin", ["Users, logs, models"], "actor"),
        Box("weather", 1220, 560, 300, 150, "Weather/AI Services", ["PAGASA, Open-Meteo, Groq"], "external"),
        Box("db", 650, 690, 300, 130, "MySQL Database", ["System records"], "data"),
    ]
    edges = [("farmer", "system", "requests/data"), ("system", "farmer", "outputs"), ("mao", "system", "records/review"), ("system", "mao", "reports"), ("admin", "system", "admin actions"), ("system", "admin", "logs/status"), ("weather", "system", "external data"), ("system", "db", "store"), ("db", "system", "retrieve")]
    write("fig07-context-diagram.svg", svg("Figure 7. Context Diagram", 900, boxes, edges))


def figure_8() -> None:
    # Figure 8 is custom-rendered so the DFD remains readable in print:
    # no arrow labels overlap process boxes, and every flow is numbered.
    height = 1280
    boxes = [
        Box("user", 70, 125, 300, 165, "E1 System Users", ["Farmer", "MAO Personnel", "IT Expert/Admin"], "actor"),
        Box("pagasa", 70, 405, 300, 135, "E2 PAGASA", ["Advisories", "historical climate source label"], "external"),
        Box("openmeteo", 70, 620, 300, 135, "E3 Open-Meteo API", ["Forecast weather variables"], "external"),
        Box("python", 70, 840, 300, 135, "E4 Python / AI Services", ["Rice yield model", "AI assistant provider"], "external"),
        Box("user_out", 70, 1030, 300, 135, "E1 System Users", ["Receive forecasts", "advisories, reports, alerts"], "actor"),
        Box("p1", 520, 115, 370, 150, "1.0 User Access", ["Validate credentials", "Apply role permissions"], "process"),
        Box("p2", 520, 315, 370, 160, "2.0 Profile and Record Management", ["Save farmer profiles", "Save climate and rice records"], "process"),
        Box("p3", 520, 535, 370, 160, "3.0 Weather Data Processing", ["Fetch and normalize forecast data", "Store weather observations"], "process"),
        Box("p4", 520, 755, 370, 170, "4.0 Prediction Processing", ["Prepare farm and weather inputs", "Generate yield and weather results"], "process"),
        Box("p5", 520, 990, 370, 180, "5.0 Decision Output Generation", ["Create advisories", "Update heat map", "Generate reports", "Send notifications"], "output"),
        Box("d1", 1130, 115, 360, 150, "D1 Users and Profiles", ["users", "farmer_profiles"], "data"),
        Box("d2", 1130, 315, 360, 160, "D2 Farm and Climate Records", ["climate_records", "rice_productions"], "data"),
        Box("d3", 1130, 535, 360, 160, "D3 Weather and AI Data", ["external_weather_data", "a_i_chats", "knowledge_base"], "data"),
        Box("d4", 1130, 990, 360, 180, "D4 Decision-Support Outputs", ["planting_advisories", "heatmap_areas", "reports", "notifications"], "data"),
    ]
    box_map = {box.id: box for box in boxes}

    def direct(src: str, dst: str) -> str:
        return arrow(box_map, src, dst, "")

    def number(x: int, y: int, text: str) -> str:
        return (
            f'<circle cx="{x}" cy="{y}" r="18" fill="#ffffff" stroke="#263238" stroke-width="2"/>'
            f'<text x="{x}" y="{y + 6}" text-anchor="middle" class="edge-label" '
            f'font-weight="700">{escape(text)}</text>'
        )

    def vertical_flow(x: int, y1: int, y2: int, n: str) -> str:
        mid = (y1 + y2) // 2
        return (
            f'<line x1="{x}" y1="{y1}" x2="{x}" y2="{y2}" stroke="#37474f" '
            f'stroke-width="3" marker-end="url(#arrow)"/>'
            + number(x + 32, mid, n)
        )

    lines = [
        direct("user", "p1") + number(440, 190, "1"),
        direct("p1", "d1") + number(1010, 190, "2"),
        vertical_flow(705, 265, 315, "3"),
        direct("user", "p2") + number(440, 360, "4"),
        direct("p2", "d2") + number(1010, 395, "5"),
        direct("pagasa", "p3") + number(440, 475, "6"),
        direct("openmeteo", "p3") + number(440, 650, "7"),
        direct("p3", "d3") + number(1010, 615, "8"),
        vertical_flow(705, 695, 755, "9"),
        direct("python", "p4") + number(440, 860, "10"),
        vertical_flow(705, 925, 990, "11"),
        direct("p5", "d4") + number(1010, 1080, "12"),
        direct("p5", "user_out") + number(440, 1080, "13"),
    ]
    rendered_boxes = "\n".join(render_box(box) for box in boxes)
    rendered_lines = "\n".join(lines)
    legend_items = [
        "1 Login, registration, or role request",
        "2 Verified user account and role access",
        "3 Authorized session continues to record modules",
        "4 Farmer profile, climate record, or rice production entry",
        "5 Validated local records saved to database",
        "6 PAGASA advisory or historical-climate reference data",
        "7 Forecast variables: rainfall, temperature, humidity, wind",
        "8 Normalized weather data stored for later use",
        "9 Weather and farm records sent to prediction processing",
        "10 Model or AI response returned to iClimate",
        "11 Prediction result sent to decision-output generation",
        "12 Advisories, heat map, reports, and notifications saved",
        "13 Decision-support outputs displayed to users",
    ]
    legend = []
    y = 1208
    for i, item in enumerate(legend_items[:7]):
        legend.append(f'<text x="85" y="{y + i * 23}" class="edge-label">{escape(item)}</text>')
    for i, item in enumerate(legend_items[7:]):
        legend.append(f'<text x="820" y="{y + i * 23}" class="edge-label">{escape(item)}</text>')
    legend_text = "\n".join(legend)
    content = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{WIDTH}" height="{height}" viewBox="0 0 {WIDTH} {height}" role="img" aria-labelledby="title desc">
  <title id="title">Figure 8. Data Flow Diagram - Level 1</title>
  <desc id="desc">Specific, numbered data flow diagram for the iClimate web-based decision-support platform.</desc>
  <defs>
    <marker id="arrow" markerWidth="14" markerHeight="10" refX="12" refY="5" orient="auto" markerUnits="strokeWidth">
      <path d="M 0 0 L 14 5 L 0 10 z" fill="#37474f"/>
    </marker>
    <style>
      .title {{ font-family: Arial, Helvetica, sans-serif; font-size: 34px; font-weight: 700; fill: #1b1b1b; }}
      .box-title {{ font-family: Arial, Helvetica, sans-serif; font-size: 22px; font-weight: 700; fill: #1b1b1b; }}
      .box-line {{ font-family: Arial, Helvetica, sans-serif; font-size: 18px; fill: #263238; }}
      .edge-label {{ font-family: Arial, Helvetica, sans-serif; font-size: 16px; fill: #263238; }}
      .legend-title {{ font-family: Arial, Helvetica, sans-serif; font-size: 21px; font-weight: 700; fill: #1b1b1b; }}
      .note {{ font-family: Arial, Helvetica, sans-serif; font-size: 17px; fill: #455a64; }}
    </style>
  </defs>
  <rect width="{WIDTH}" height="{height}" fill="#ffffff"/>
  <text x="{WIDTH / 2}" y="54" text-anchor="middle" class="title">Figure 8. Data Flow Diagram - Level 1</text>
  <text x="220" y="92" text-anchor="middle" class="note">External entities</text>
  <text x="705" y="92" text-anchor="middle" class="note">iClimate system processes</text>
  <text x="1310" y="92" text-anchor="middle" class="note">Database / data stores</text>
  {rendered_lines}
  {rendered_boxes}
  <line x1="60" y1="1185" x2="1540" y2="1185" stroke="#cfd8dc" stroke-width="2"/>
  <text x="85" y="1170" class="legend-title">Numbered Data Flows</text>
  {legend_text}
</svg>
'''
    write("fig08-data-flow-diagram.svg", content)


def figure_9() -> None:
    boxes = [
        Box("users", 80, 130, 290, 230, "users", ["id, name, email", "role, barangay", "status, last_active_at"], "data"),
        Box("profiles", 460, 130, 290, 190, "farmer_profiles", ["user_id", "barangay", "farm_area", "farm_type"], "data"),
        Box("advisories", 830, 110, 310, 270, "planting_advisories", ["posted_by", "weather_data_id", "advisory_rule_id", "status, severity", "recommended_action"], "data"),
        Box("rules", 1240, 110, 290, 200, "advisory_rules", ["created_by", "conditions", "recommendation", "severity"], "data"),
        Box("weather", 1240, 390, 290, 240, "external_weather_data", ["source, location", "forecast_date", "rainfall, temp", "humidity, wind"], "data"),
        Box("reports", 80, 470, 290, 170, "reports", ["generated_by", "report_type", "title, payload"], "data"),
        Box("notifications", 460, 470, 290, 170, "notifications", ["user_id", "title, message", "type, is_read"], "data"),
        Box("social", 830, 470, 310, 220, "feed/conversations", ["feed_posts", "feed_comments", "feed_reactions", "conversations", "conversation_messages"], "data"),
        Box("logs", 80, 720, 290, 140, "system_logs", ["user_id", "action", "details"], "data"),
        Box("risk", 460, 720, 290, 140, "heatmap_areas", ["barangay", "risk_level", "risk_score"], "data"),
        Box("production", 830, 760, 310, 150, "rice_productions", ["barangay, season", "irrigation_type", "yield_per_hectare", "total_production"], "data"),
        Box("climate", 1240, 760, 290, 150, "climate_records", ["record_date", "rainfall, temp", "humidity, wind", "season"], "data"),
    ]
    edges = [("users", "profiles", "1:1"), ("users", "advisories", "1:M"), ("rules", "advisories", "1:M"), ("weather", "advisories", "1:M"), ("users", "reports", "1:M"), ("users", "notifications", "1:M"), ("users", "social", "1:M"), ("users", "logs", "1:M"), ("climate", "production", "analysis input"), ("production", "risk", "risk input")]
    write("fig09-entity-relationship-diagram.svg", svg("Figure 9. Entity Relationship Diagram", 980, boxes, edges))


def figure_10() -> None:
    boxes = [
        Box("req", 80, 230, 230, 150, "Requirements", ["Users, objectives, data needs"], "input"),
        Box("design", 370, 230, 230, 150, "System Design", ["Architecture, UI, ERD, DFD"], "process"),
        Box("db", 660, 230, 230, 150, "Database Design", ["Tables, models, relationships"], "data"),
        Box("dev", 950, 230, 230, 150, "Development", ["Laravel modules and services"], "process"),
        Box("ml", 1240, 230, 230, 150, "ML/API Integration", ["Python, weather APIs, AI"], "external"),
        Box("test", 370, 540, 230, 150, "Testing", ["Feature, unit, security tests"], "warning"),
        Box("deploy", 660, 540, 230, 150, "Deployment", ["XAMPP, Laravel, MySQL"], "output"),
        Box("maint", 950, 540, 230, 150, "Maintenance", ["Forecast refresh, advisories, logs"], "output"),
    ]
    edges = [("req", "design", ""), ("design", "db", ""), ("db", "dev", ""), ("dev", "ml", ""), ("ml", "test", ""), ("test", "deploy", ""), ("deploy", "maint", ""), ("maint", "req", "iteration")]
    write("fig10-development-flow.svg", svg("Figure 10. Development Flow of iClimate", 790, boxes, edges))


def main() -> None:
    figure_1()
    figure_2()
    figure_3()
    figure_4()
    figure_5()
    figure_6()
    figure_7()
    figure_8()
    figure_9()
    figure_10()


if __name__ == "__main__":
    main()
