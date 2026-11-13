#!/usr/bin/env python3
"""Show only the credentials generated for this disposable, synthetic lab."""
from pathlib import Path
import sys

project = Path(__file__).resolve().parent.parent
settings = dict(line.split('=', 1) for line in (project / '.env').read_text().splitlines()
                if line and not line.startswith('#') and '=' in line)
print('Adresse Moodle : ' + settings.get('MOODLE_WWWROOT', 'http://localhost:8080'))
print('Étudiant : etudiant / ' + settings['CONTENTGUARD_STUDENT_PASSWORD'])
if '--admin' in sys.argv:
    print('Administrateur : admin / ' + settings['MOODLE_ADMIN_PASSWORD'])
