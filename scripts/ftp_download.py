#!/usr/bin/env python3
"""Mirror a remote FTP directory to a local folder."""

from __future__ import annotations

import ftplib
import os
import sys
import time
from pathlib import Path

HOST = os.environ["FTP_HOST"]
USER = os.environ["FTP_USER"]
PASSWORD = os.environ["FTP_PASSWORD"]
REMOTE_ROOT = os.environ.get("FTP_REMOTE", "/public_html")
LOCAL_ROOT = Path(os.environ.get("FTP_LOCAL", "wordpress"))

SKIP_PREFIXES = (
    "wp-content/ai1wm-backups/",
)
SKIP_SUFFIXES = (
    ".wpress",
)
SKIP_NAMES = {
    "www.convivendocomdiabetes.com-20220712-121132-b7llsk.wpress",
}


def should_skip(rel: str) -> bool:
    rel = rel.replace("\\", "/")
    if any(rel.startswith(p) for p in SKIP_PREFIXES):
        return True
    if any(rel.endswith(s) for s in SKIP_SUFFIXES):
        return True
    name = rel.rsplit("/", 1)[-1]
    return name in SKIP_NAMES


def connect() -> ftplib.FTP:
    ftp = ftplib.FTP()
    ftp.connect(HOST, 21, timeout=60)
    ftp.login(USER, PASSWORD)
    ftp.set_pasv(True)
    return ftp


def list_dir(ftp: ftplib.FTP, path: str) -> list[tuple[str, bool, int]]:
    """Return list of (name, is_dir, size)."""
    entries: list[tuple[str, bool, int]] = []
    try:
        for name, facts in ftp.mlsd(path):
            if name in (".", ".."):
                continue
            ftype = facts.get("type", "file")
            size = int(facts.get("size", "0") or 0)
            entries.append((name, ftype == "dir", size))
        return entries
    except (ftplib.error_perm, AttributeError):
        pass

    lines: list[str] = []
    ftp.retrlines(f"LIST {path}", lines.append)
    for line in lines:
        parts = line.split(maxsplit=8)
        if len(parts) < 9:
            continue
        name = parts[8]
        if name in (".", ".."):
            continue
        is_dir = parts[0].startswith("d")
        try:
            size = int(parts[4])
        except ValueError:
            size = 0
        entries.append((name, is_dir, size))
    return entries


def download_file(ftp: ftplib.FTP, remote: str, local: Path, size: int) -> None:
    local.parent.mkdir(parents=True, exist_ok=True)
    if local.exists() and size > 0 and local.stat().st_size == size:
        return

    tmp = local.with_suffix(local.suffix + ".partial")
    attempts = 0
    while True:
        attempts += 1
        try:
            with open(tmp, "wb") as fh:
                ftp.retrbinary(f"RETR {remote}", fh.write, blocksize=1024 * 256)
            tmp.replace(local)
            return
        except Exception:
            if attempts >= 5:
                raise
            time.sleep(2 * attempts)
            try:
                ftp.voidcmd("NOOP")
            except Exception:
                try:
                    ftp.close()
                except Exception:
                    pass
                ftp = connect()


def walk(ftp: ftplib.FTP, remote_dir: str, rel: str = "") -> tuple[int, int, int]:
    files = 0
    bytes_dl = 0
    skipped = 0
    remote_dir = remote_dir.rstrip("/") or "/"

    for name, is_dir, size in list_dir(ftp, remote_dir):
        child_rel = f"{rel}/{name}".lstrip("/")
        remote_path = f"{remote_dir}/{name}"

        if should_skip(child_rel):
            skipped += 1
            print(f"SKIP  {child_rel}", flush=True)
            continue

        if is_dir:
            print(f"DIR   {child_rel}", flush=True)
            f, b, s = walk(ftp, remote_path, child_rel)
            files += f
            bytes_dl += b
            skipped += s
        else:
            local_path = LOCAL_ROOT / child_rel
            print(f"FILE  {child_rel} ({size / 1024 / 1024:.1f} MB)", flush=True)
            download_file(ftp, remote_path, local_path, size)
            files += 1
            bytes_dl += size if size else local_path.stat().st_size
            if files % 25 == 0:
                print(
                    f"--- progress: {files} files, {bytes_dl / 1024 / 1024:.1f} MB ---",
                    flush=True,
                )
    return files, bytes_dl, skipped


def main() -> int:
    LOCAL_ROOT.mkdir(parents=True, exist_ok=True)
    print(f"Connecting to {HOST} as {USER}", flush=True)
    print(f"Remote: {REMOTE_ROOT} -> {LOCAL_ROOT.resolve()}", flush=True)
    ftp = connect()
    try:
        files, bytes_dl, skipped = walk(ftp, REMOTE_ROOT)
    finally:
        try:
            ftp.quit()
        except Exception:
            ftp.close()
    print(
        f"DONE files={files} size_mb={bytes_dl / 1024 / 1024:.1f} skipped={skipped}",
        flush=True,
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
