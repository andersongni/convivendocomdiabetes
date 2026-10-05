#!/usr/bin/env python3
from pathlib import Path
import sys

p = Path(sys.argv[1] if len(sys.argv) > 1 else "dump-filtered.sql")
creates = []
inserts = set()
with p.open(encoding="utf-8", errors="replace") as f:
    for line in f:
        if line.startswith("CREATE TABLE"):
            if "`" in line:
                creates.append(line.split("`", 2)[1])
        if line.startswith("INSERT INTO"):
            if "`" in line:
                inserts.add(line.split("`", 2)[1])

print("file_mb", round(p.stat().st_size / 1e6, 2))
print("creates", len(creates))
print("insert_tables", len(inserts))
for t in [
    "wp_posts",
    "wp_postmeta",
    "wp_users",
    "wp_options",
    "wp_terms",
    "wp_wfknownfilelist",
    "wp_statistics_visitor",
]:
    print(
        t,
        "CREATE" if t in creates else "no-create",
        "INSERT" if t in inserts else "no-insert",
    )
print("all creates:")
for t in creates:
    print(" ", t)
