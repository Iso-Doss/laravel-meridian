"""Add the notes of a GitHub release to CHANGELOG.md, verbatim.

Usage: RELEASE_NAME=v1.2.3 RELEASE_BODY="..." RELEASE_DATE=2026-01-31 python3 update-changelog.py [CHANGELOG.md]

The new "## <name> - <date>" section is inserted above the latest version,
an "## Unreleased" section is replaced, and nothing happens when the version
is already listed.
"""

import os
import re
import sys
from datetime import date

path = sys.argv[1] if len(sys.argv) > 1 else "CHANGELOG.md"
name = os.environ["RELEASE_NAME"].strip()
body = os.environ.get("RELEASE_BODY", "").replace("\r\n", "\n").strip()
day = os.environ.get("RELEASE_DATE") or date.today().isoformat()

with open(path, encoding="utf-8") as file:
    changelog = file.read().replace("\r\n", "\n")

if re.search(rf"^## {re.escape(name)}( |$)", changelog, re.MULTILINE):
    print(f"{name} is already in {path}, nothing to do.")
    sys.exit(0)

# Drop an "Unreleased" section: the release notes supersede it.
changelog = re.sub(r"^## Unreleased\n.*?(?=^## |\Z)", "", changelog, flags=re.MULTILINE | re.DOTALL)

section = f"## {name} - {day}\n\n{body}\n\n" if body else f"## {name} - {day}\n\n"
first_version = re.search(r"^## ", changelog, re.MULTILINE)

if first_version:
    changelog = changelog[: first_version.start()] + section + changelog[first_version.start():]
else:
    changelog = changelog.rstrip("\n") + "\n\n" + section

with open(path, "w", encoding="utf-8") as file:
    file.write(changelog.rstrip("\n") + "\n")

print(f"Added {name} to {path}.")
