#!/usr/bin/env python3
"""Comprime imagens em wordpress/wp-content/uploads mantendo nome/URL.

- Redimensiona para no maximo MAX_EDGE (lado maior)
- JPEG/WebP quality QUALITY
- PNG: otimiza; se nao tiver alpha e for grande, converte para JPEG
  (mantendo .png so quando ha transparencia)
- So substitui o arquivo se o novo for menor
"""

from __future__ import annotations

import argparse
import io
import os
import sys
import time
from pathlib import Path

from PIL import Image, ImageOps, UnidentifiedImageError

# Evita erro com imagens muito grandes
Image.MAX_IMAGE_PIXELS = 200_000_000

DEFAULT_ROOT = Path(__file__).resolve().parents[1] / "wordpress" / "wp-content" / "uploads"
EXTS = {".jpg", ".jpeg", ".png", ".webp"}


def human(n: int) -> str:
    for unit in ("B", "KB", "MB", "GB"):
        if n < 1024:
            return f"{n:.0f}{unit}" if unit == "B" else f"{n:.1f}{unit}"
        n /= 1024
    return f"{n:.1f}TB"


def optimize_one(
    path: Path,
    *,
    max_edge: int,
    quality: int,
    min_bytes: int,
    dry_run: bool,
) -> tuple[int, int] | None:
    """Returns (old_size, new_size) if changed/would-change, else None."""
    try:
        old_size = path.stat().st_size
    except OSError:
        return None
    if old_size < min_bytes:
        return None

    try:
        with Image.open(path) as im:
            im = ImageOps.exif_transpose(im)
            fmt = (im.format or path.suffix.lstrip(".")).upper()
            has_alpha = im.mode in ("RGBA", "LA") or (
                im.mode == "P" and "transparency" in im.info
            )

            w, h = im.size
            scale = min(1.0, max_edge / float(max(w, h)))
            if scale < 1.0:
                new_size = (max(1, int(w * scale)), max(1, int(h * scale)))
                im = im.resize(new_size, Image.Resampling.LANCZOS)

            out = io.BytesIO()
            suffix = path.suffix.lower()
            save_path_suffix = suffix

            if suffix in {".jpg", ".jpeg"}:
                if im.mode not in ("RGB", "L"):
                    im = im.convert("RGB")
                im.save(
                    out,
                    format="JPEG",
                    quality=quality,
                    optimize=True,
                    progressive=True,
                )
            elif suffix == ".png":
                if has_alpha:
                    if im.mode != "RGBA":
                        im = im.convert("RGBA")
                    im.save(out, format="PNG", optimize=True, compress_level=9)
                else:
                    # Foto salva como PNG: reencode RGB+PNG costuma encolher bem apos resize
                    if im.mode not in ("RGB", "L"):
                        im = im.convert("RGB")
                    im.save(out, format="PNG", optimize=True, compress_level=9)
            elif suffix == ".webp":
                if has_alpha:
                    if im.mode != "RGBA":
                        im = im.convert("RGBA")
                    im.save(out, format="WEBP", quality=quality, method=6)
                else:
                    if im.mode not in ("RGB", "L"):
                        im = im.convert("RGB")
                    im.save(out, format="WEBP", quality=quality, method=6)
            else:
                return None

            data = out.getvalue()
            new_size = len(data)
            if new_size >= old_size * 0.98:
                return None

            if dry_run:
                return old_size, new_size

            tmp = path.with_suffix(path.suffix + ".ccdtmp")
            tmp.write_bytes(data)
            os.replace(tmp, path)
            return old_size, new_size
    except (UnidentifiedImageError, OSError, ValueError, SyntaxError) as exc:
        print(f"SKIP {path}: {exc}", file=sys.stderr)
        return None


def main() -> int:
    parser = argparse.ArgumentParser(description="Otimiza uploads do WordPress")
    parser.add_argument("--root", type=Path, default=DEFAULT_ROOT)
    parser.add_argument("--max-edge", type=int, default=1920)
    parser.add_argument("--quality", type=int, default=82)
    parser.add_argument("--min-bytes", type=int, default=200_000)
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--limit", type=int, default=0, help="0 = todos")
    args = parser.parse_args()

    root: Path = args.root
    if not root.is_dir():
        print(f"Pasta nao encontrada: {root}", file=sys.stderr)
        return 1

    files = [
        p
        for p in root.rglob("*")
        if p.is_file() and p.suffix.lower() in EXTS and ".ccdtmp" not in p.name
    ]
    files.sort(key=lambda p: p.stat().st_size, reverse=True)
    if args.limit > 0:
        files = files[: args.limit]

    print(
        f"Otimizando em {root} | candidatos={len(files)} | "
        f"max_edge={args.max_edge} quality={args.quality} min={human(args.min_bytes)}"
        f"{' | DRY-RUN' if args.dry_run else ''}"
    )

    t0 = time.time()
    changed = 0
    saved = 0
    scanned = 0
    for path in files:
        scanned += 1
        result = optimize_one(
            path,
            max_edge=args.max_edge,
            quality=args.quality,
            min_bytes=args.min_bytes,
            dry_run=args.dry_run,
        )
        if not result:
            continue
        old_size, new_size = result
        delta = old_size - new_size
        saved += delta
        changed += 1
        if changed <= 40 or changed % 50 == 0:
            print(
                f"[{changed}] -{human(delta)}  {human(old_size)} -> {human(new_size)}  "
                f"{path.relative_to(root)}"
            )

    elapsed = time.time() - t0
    print(
        f"\nFeito: scanned={scanned} changed={changed} "
        f"saved={human(saved)} in {elapsed:.1f}s"
        f"{' (dry-run)' if args.dry_run else ''}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
