#!/usr/bin/env python3
from collections import defaultdict

sizes = defaultdict(int)
with open("dump-database.sql", "r", encoding="utf-8", errors="replace") as f:
    for line in f:
        if line.startswith("INSERT INTO `"):
            table = line.split("`", 2)[1]
            sizes[table] += len(line)

for table, size in sorted(sizes.items(), key=lambda x: -x[1])[:30]:
    print(f"{size / 1e6:8.1f} MB  {table}")
print(f"TOTAL inserts {sum(sizes.values()) / 1e6:.1f} MB")
