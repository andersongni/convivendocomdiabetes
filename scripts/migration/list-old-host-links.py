#!/usr/bin/env python3
"""List upload URLs from the dump that are not present locally under synced years
(and thus would 302 to the old host on Railway)."""
from __future__ import annotations

import re
import sys
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DUMP = ROOT / (sys.argv[1] if len(sys.argv) > 1 else "dump-filtered.sql")
UPLOADS = ROOT / "wordpress" / "wp-content" / "uploads"

# Years/dirs present on Railway wp-uploads volume (as of last sync)
ON_RAILWAY = {
    "2015",
    "2018",
    "2019",
    "2020",
    "2021",
    "2022",
    "2026",
    "elementor",
    "groovy",
    "revslider",
    "sucuri",
    "wp-file-manager-pro",
    "wpforms",
}

# Capture relative path after /wp-content/uploads/
PAT = re.compile(
    r"(?:https?://(?:www\.)?convivendocomdiabetes\.com)?"
    r"/wp-content/uploads/([^\"'\\?\s<>]+)",
    re.I,
)

paths: Counter[str] = Counter()
with DUMP.open("r", encoding="utf-8", errors="replace") as f:
    for line in f:
        for m in PAT.finditer(line):
            rel = m.group(1).rstrip("/")
            # strip size suffixes query already excluded; unescape common junk
            rel = rel.split("#", 1)[0]
            paths[rel] += 1

old_host: list[tuple[str, int]] = []
missing_local: list[tuple[str, int]] = []
on_volume: list[tuple[str, int]] = []

for rel, n in paths.most_common():
    top = rel.split("/", 1)[0]
    local = UPLOADS / rel.replace("/", "\\")
    exists_local = local.is_file()
    if top in ON_RAILWAY and exists_local:
        on_volume.append((rel, n))
    elif top not in ON_RAILWAY:
        old_host.append((rel, n))
    else:
        # year synced but this specific file missing locally (or only on remote)
        missing_local.append((rel, n))

print(f"dump={DUMP.name}")
print(f"unique_upload_paths={len(paths)}")
print(f"would_hit_old_host_by_year={len(old_host)}")
print(f"synced_year_but_file_missing_local={len(missing_local)}")
print(f"likely_served_from_railway={len(on_volume)}")
print()

by_year: Counter[str] = Counter()
for rel, n in old_host:
    by_year[rel.split("/", 1)[0]] += n
print("=== refs by missing top-level dir (go to old host) ===")
for y, n in by_year.most_common():
    print(f"  {n:6d}  {y}/")
print()

print("=== unique links that redirect to old host ===")
base = "https://www.convivendocomdiabetes.com/wp-content/uploads/"
for rel, n in sorted(old_host, key=lambda x: x[0]):
    print(f"{base}{rel}  (refs={n})")
