from docx import Document
from docx.shared import Pt
from docx.enum.text import WD_PARAGRAPH_ALIGNMENT
from docx.oxml.ns import qn
from docx.oxml import OxmlElement

# Create Word Document with updated details
doc = Document()
doc.add_heading('Minutes of Meeting (MoM) – Construction Site Visit', 0)

# Meeting Details
doc.add_paragraph('Date: 25/05/2025')
doc.add_paragraph('Time: Afternoon (2:30 PM – 4:00 PM)')
doc.add_paragraph('Location: Flat Number 4041, Jasola, New Delhi')
doc.add_paragraph('Project Name: [Project Title or Name]')
doc.add_paragraph('Meeting Type: Progress Review and Coordination')
doc.add_paragraph('Meeting Conducted By: Mr. Khwaja (Client), Nikesh (Site Coordinator), Rachana Pal & Prabhat Arya (Architects)')

# Attendees
doc.add_heading('Attendees', level=1)
table = doc.add_table(rows=1, cols=4)
table.style = 'Table Grid'  # Add visible grid lines

# Make header row bold
hdr_cells = table.rows[0].cells
for cell in hdr_cells:
    for paragraph in cell.paragraphs:
        for run in paragraph.runs:
            run.bold = True
    
hdr_cells[0].text = 'Name'
hdr_cells[1].text = 'Designation'
hdr_cells[2].text = 'Organization/Company'
hdr_cells[3].text = 'Contact Info'

# Make all header text bold again (since setting text removes formatting)
for cell in hdr_cells:
    for paragraph in cell.paragraphs:
        for run in paragraph.runs:
            run.bold = True

attendees = [
    ('Mr. Khwaja', 'Client', '-', '-'),
    ('Mr. ABC', 'Client', '-', '-'),
    ('Nikesh', 'Site Coordinator', '-', '-'),
    ('Rachana Pal', 'Architect', '-', '-'),
    ('Prabhat Arya', 'Architect', '-', '-')
]

for name, desig, org, contact in attendees:
    row_cells = table.add_row().cells
    row_cells[0].text = name
    row_cells[1].text = desig
    row_cells[2].text = org
    row_cells[3].text = contact

# Agenda
doc.add_heading('Agenda', level=1)
agenda_items = [
    'Drawings discussion and confirmation of accuracy',
    'Completion of pending works at the earliest',
    'Strategies to accelerate site progress',
    'Final approvals and clarifications'
]
for item in agenda_items:
    doc.add_paragraph(item, style='List Bullet')

# Key Discussions & Decisions
doc.add_heading('Key Discussions & Decisions', level=1)
discussions = [
    "All drawings reviewed and confirmed as accurate by the architects and client.",
    "Site coordinator to prioritize and finish the pending works on high priority.",
    "Team discussed measures to improve workflow and manpower for faster site completion.",
    "Final approvals were given on minor layout changes and clarifications addressed."
]
for point in discussions:
    doc.add_paragraph(f'- {point}')

# Site Observations
doc.add_heading('Site Observations', level=1)
observations = [
    'Overall construction progress is satisfactory.',
    'Electrical works and carpentry are ongoing.',
    'Site is maintained well with adequate safety protocols.'
]
for item in observations:
    doc.add_paragraph(item, style='List Bullet')

# Client Approvals/Instructions
doc.add_heading('Client Approvals/Instructions', level=1)
approvals = [
    'Approved layout as per latest drawing revision.',
    'Requested completion of A.C. line works and revised electrical point markings.'
]
for item in approvals:
    doc.add_paragraph(item, style='List Bullet')

# Concerns Raised
doc.add_heading('Concerns Raised', level=1)
concerns = [
    "Changes in the electrical points between site and drawings.",
    "A.C. pipe cutting and wall coordination to be completed by Tuesday.",
    "Final selection of lighting fixtures required.",
    "One-week work schedule to be submitted to the client.",
    "Updated revised drawings to be shared as per today's discussion.",
    "Plumbing work to be initiated this week.",
    "Bathroom waterproofing details and execution plan needed."
]
for point in concerns:
    doc.add_paragraph(f'- {point}')

# Next Steps
doc.add_heading('Next Steps', level=1)
next_steps = [
    'Submit revised electrical drawings within 2 days.',
    'Complete pending wall cuttings for A.C. pipes by Tuesday.',
    'Prepare and submit one-week work schedule.',
    'Initiate plumbing and waterproofing works as planned.'
]
for step in next_steps:
    doc.add_paragraph(step, style='List Bullet')

# Next Meeting Schedule
doc.add_heading('Next Meeting Schedule', level=1)
doc.add_paragraph('Date: [Insert Date]')
doc.add_paragraph('Time: [Insert Time]')
doc.add_paragraph('Location: [Site / Office / Online]')

# Signatures - Updated to match the provided format
doc.add_heading('Signatures', level=1)
doc.add_paragraph('Name | Designation | Signature | Date')

# Add signature lines for each attendee
signatures = [
    ('Mr. Khwaja', 'Client', '25/05/2025'),
    ('Nikesh', 'Site Supervisor', '25/05/2025'),
    ('Rachana Pal', 'Architect', '25/05/2025'),
    ('Prabhat Arya', 'Architect', '25/05/2025')
]

for name, designation, date in signatures:
    p = doc.add_paragraph()
    p.add_run(f"{name} | {designation} | ").bold = False
    p.add_run("____________").bold = False
    p.add_run(f" | {date}").bold = False
    doc.add_paragraph()  # Add empty line between signatures

# Save the Word document
formatted_word_path = "MoM_Construction_Site_Formatted_v2.docx"
doc.save(formatted_word_path)

print(f"Word document saved as: {formatted_word_path}")
