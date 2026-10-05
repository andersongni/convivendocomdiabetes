#!/usr/bin/env python3
"""Remove heavy Wordfence log table DATA from a MySQL dump (keep empty schemas)."""
from pathlib import Path
import sys

SKIP_TABLES = {
    "wp_wfknownfilelist",
    "wp_wfhits",
    "wp_wffilemods",
    "wp_wfloctype",
    "wp_wflogins",
    "wp_wftrafficrates",
    "wp_wfcrawlers",
    "wp_wfblockediplog",
    "wp_wfissues",
    "wp_wfauditevents",
    "wp_wfnotifications",
    "wp_wfpendingissues",
    "wp_wfreversecache",
    "wp_wfsnipcache",
    "wp_wfstatus",
    "wp_statistics_visitor",
    "wp_statistics_pages",
    "wp_statistics_search",
    "wp_statistics_visit",
    "wp_wpmailsmtp_debug_events",
}

src = Path(sys.argv[1] if len(sys.argv) > 1 else "dump-database.sql")
dst = Path(sys.argv[2] if len(sys.argv) > 2 else "dump-filtered.sql")

skip_insert = False
n_in = n_out = n_skip = 0
skipped_bytes = 0

with src.open("r", encoding="utf-8", errors="replace") as fin, dst.open(
    "w", encoding="utf-8", newline="\n"
) as fout:
    for line in fin:
        n_in += 1

        if not skip_insert and line.startswith("INSERT INTO `"):
            table = line.split("`", 2)[1]
            if table in SKIP_TABLES:
                skip_insert = True

        if skip_insert:
            n_skip += 1
            skipped_bytes += len(line.encode("utf-8", errors="replace"))
            # mysqldump multi-line inserts end with a line that ends in );
            if line.rstrip().endswith(";"):
                skip_insert = False
            continue

        fout.write(line)
        n_out += 1

print(f"lines in={n_in} out={n_out} skipped_lines={n_skip}")
print(f"skipped_MB={skipped_bytes / 1e6:.1f}")
print(f"sizeMB={dst.stat().st_size / 1e6:.1f} -> {dst}")
