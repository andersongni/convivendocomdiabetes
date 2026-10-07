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
import json
import os
import sys
import time
from pathlib import Path

from PIL import Image, ImageFile, ImageOps, UnidentifiedImageError

# Evita erro com imagens muito grandes / PNG truncado legado
Image.MAX_IMAGE_PIXELS = 200_000_000
ImageFile.LOAD_TRUNCATED_IMAGES = True

DEFAULT_ROOT = Path(__file__).resolve().parents[1] / "wordpress" / "wp-content" / "uploads"
EXTS = {".jpg", ".jpeg", ".png", ".webp"}

# PNG opaco grande: regrava como .jpg (rename) para Content-Type correto.
PNG_TO_JPEG_MIN_BYTES = 150_000


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
) -> tuple[int, int, str | None] | None:
    """Returns (old_size, new_size, renamed_to_relpath|None) if changed."""
    try:
        old_size = path.stat().st_size
    except OSError:
        return None
    if old_size < min_bytes:
        return None

    try:
        with Image.open(path) as im:
            im = ImageOps.exif_transpose(im)
            im.load()
            has_alpha = im.mode in ("RGBA", "LA") or (
                im.mode == "P" and "transparency" in im.info
            )
            if has_alpha and im.mode == "P":
                im = im.convert("RGBA")
                has_alpha = True
            elif im.mode == "P":
                im = im.convert("RGB")
                has_alpha = False
            if has_alpha and im.mode in ("RGBA", "LA"):
                alpha = im.getchannel("A")
                extrema = alpha.getextrema()
                # Opaco ou quase opaco (foto com borda suave): achata em branco → JPEG
                hist = alpha.histogram()
                total_px = max(1, im.size[0] * im.size[1])
                opaque_px = hist[255] if len(hist) > 255 else 0
                if extrema == (255, 255) or (opaque_px / total_px) >= 0.995:
                    bg = Image.new("RGB", im.size, (255, 255, 255))
                    rgba = im if im.mode == "RGBA" else im.convert("RGBA")
                    bg.paste(rgba, mask=rgba.split()[-1])
                    im = bg
                    has_alpha = False

            w, h = im.size
            scale = min(1.0, max_edge / float(max(w, h)))
            if scale < 1.0:
                new_size = (max(1, int(w * scale)), max(1, int(h * scale)))
                im = im.resize(new_size, Image.Resampling.LANCZOS)

            out = io.BytesIO()
            suffix = path.suffix.lower()
            dest_path = path
            rename_from_png = False

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
                    if im.mode not in ("RGB", "L"):
                        im = im.convert("RGB")
                    jpeg_out = io.BytesIO()
                    im.save(
                        jpeg_out,
                        format="JPEG",
                        quality=quality,
                        optimize=True,
                        progressive=True,
                    )
                    png_out = io.BytesIO()
                    im.save(png_out, format="PNG", optimize=True, compress_level=9)
                    if (
                        old_size >= PNG_TO_JPEG_MIN_BYTES
                        and len(jpeg_out.getvalue()) + 1024 < len(png_out.getvalue())
                    ):
                        out = jpeg_out
                        dest_path = path.with_suffix(".jpg")
                        rename_from_png = True
                    else:
                        out = png_out
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
                return old_size, new_size, dest_path.name if rename_from_png else None

            if rename_from_png:
                if dest_path.exists() and dest_path.resolve() != path.resolve():
                    # Evita sobrescrever JPEG existente
                    return None
                dest_path.write_bytes(data)
                path.unlink(missing_ok=True)
                return old_size, new_size, dest_path.name

            tmp = path.with_suffix(path.suffix + ".ccdtmp")
            tmp.write_bytes(data)
            os.replace(tmp, path)
            return old_size, new_size, None
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
    renames: list[dict[str, str]] = []
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
        old_size, new_size, new_name = result
        delta = old_size - new_size
        saved += delta
        changed += 1
        rel = path.relative_to(root).as_posix()
        label = rel
        if new_name:
            new_rel = (path.parent / new_name).relative_to(root).as_posix()
            label = f"{rel} -> {new_rel}"
            if not args.dry_run:
                renames.append({"from": rel, "to": new_rel})
        if changed <= 40 or changed % 50 == 0:
            print(
                f"[{changed}] -{human(delta)}  {human(old_size)} -> {human(new_size)}  "
                f"{label}"
            )

    elapsed = time.time() - t0
    print(
        f"\nFeito: scanned={scanned} changed={changed} "
        f"saved={human(saved)} in {elapsed:.1f}s"
        f"{' (dry-run)' if args.dry_run else ''}"
    )
    if renames:
        map_path = root / "ccd-optimize-renames.json"
        map_path.write_text(json.dumps(renames, indent=2), encoding="utf-8")
        print(f"renames={len(renames)} map={map_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
