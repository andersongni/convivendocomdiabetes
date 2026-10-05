#!/usr/bin/env python3
"""One-shot: download /uploads/sites/* referenced in old-host-upload-links.txt into local uploads."""
from pathlib import Path
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
LINKS = ROOT / "old-host-upload-links.txt"
DEST = ROOT / "wordpress" / "wp-content" / "uploads"
BASE = "https://www.convivendocomdiabetes.com/wp-content/uploads/"

urls = [
    line.strip()
    for line in LINKS.read_text(encoding="utf-8").splitlines()
    if "/uploads/sites/" in line
]
ok = fail = 0
for url in urls:
    rel = url.split("/uploads/", 1)[1]
    out = DEST / rel.replace("/", "\\")
    out.parent.mkdir(parents=True, exist_ok=True)
    if out.is_file() and out.stat().st_size > 0:
        ok += 1
        continue
    try:
        urllib.request.urlretrieve(url, out)
        ok += 1
        print("OK", rel)
    except Exception as e:
        fail += 1
        print("FAIL", rel, e)

print(f"done ok={ok} fail={fail}")
