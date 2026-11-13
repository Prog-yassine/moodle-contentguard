#!/usr/bin/env python3
"""Package source and test artifacts, never local credentials or runtime data."""
from pathlib import Path
import hashlib
import zipfile

root = Path(__file__).resolve().parent.parent
artifacts = root / 'artifacts'
artifacts.mkdir(exist_ok=True)
plugin = root / 'plugin' / 'local' / 'contentguard'
with zipfile.ZipFile(artifacts / 'contentguard-plugin.zip', 'w', zipfile.ZIP_DEFLATED) as bundle:
    for item in sorted(plugin.rglob('*')):
        if item.is_file():
            bundle.write(item, Path('contentguard') / item.relative_to(plugin))

excluded = {'.env', 'runtime', '.git', 'node_modules', '__pycache__'}
with zipfile.ZipFile(artifacts / 'contentguard-moodle-docker.zip', 'w', zipfile.ZIP_DEFLATED) as bundle:
    for item in sorted(root.rglob('*')):
        relative = item.relative_to(root)
        if not item.is_file() or excluded.intersection(relative.parts):
            continue
        if relative.parts[0] == 'artifacts' and item.suffix not in {'.png', '.json'}:
            continue
        bundle.write(item, Path('contentguard-moodle') / relative)

# Verify that no current laboratory credential entered either downloadable archive.
settings = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines()
                if line and not line.startswith('#') and '=' in line)
secrets = [value.encode() for key, value in settings.items() if key.endswith('PASSWORD')]
checksums = []
for archive in sorted(artifacts.glob('contentguard-*.zip')):
    with zipfile.ZipFile(archive) as bundle:
        assert bundle.testzip() is None, f'Corrupted archive: {archive.name}'
        for name in bundle.namelist():
            assert '.env' not in Path(name).parts and 'runtime' not in Path(name).parts
            content = bundle.read(name)
            assert not any(secret in content for secret in secrets), f'Credential found in {name}'
    checksums.append(hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name)
(artifacts / 'SHA256SUMS').write_text('\n'.join(checksums) + '\n')
print('\n'.join(checksums))
