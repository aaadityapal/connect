import os

with open('/Applications/XAMPP/xamppfiles/htdocs/dashboard-/calendar.js', 'r') as f:
    cal_lines = f.readlines()

# find // --- Add Task Logic ---
start_idx = -1
for i, line in enumerate(cal_lines):
    if '// --- Add Task Logic ---' in line:
        start_idx = i
        break

if start_idx != -1:
    lost_script_js_content = cal_lines[start_idx:-1] # excluding the last '});'
    new_cal_lines = cal_lines[:start_idx] + ['});\n']
    
    with open('/Applications/XAMPP/xamppfiles/htdocs/dashboard-/calendar.js', 'w') as f:
        f.writelines(new_cal_lines)
    
    # Now put it back into script.js
    with open('/Applications/XAMPP/xamppfiles/htdocs/dashboard-/script.js', 'r') as f:
        script_lines = f.readlines()
        
    # We need to find where to put it back. The first line is `            let parts = timeVal.split(':').map(Number);`
    # Let's search for this exact line to insert before it
    insert_idx = -1
    for i, line in enumerate(script_lines):
        if "let parts = timeVal.split(':').map(Number);" in line:
            # The line `if (timeVal) {` was the last one before it. We should insert where the first piece of function addNewTask() went missing.
            # `if (timeVal) {` is missing from script.js, wait 
            # In calendar.js, it ends at
            #             }
            #         }
            # Actually, let's just insert it right at the missing break.
            break

# Wait, we need to inspect exactly where to paste it back in script.js.
