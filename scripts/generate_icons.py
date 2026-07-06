#!/usr/bin/env python3
"""Build Ticketin favicon/PWA icons from the source artwork."""

from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "assets" / "icons" / "ticketin-favicon-source.png"
OUTPUT_DIRS = [
    ROOT / "assets" / "icons",
    ROOT / "admin" / "icons",
]
SIZES = [32, 192, 512]


def square_crop(image: Image.Image) -> Image.Image:
    width, height = image.size
    side = min(width, height)
    left = (width - side) // 2
    top = (height - side) // 2
    return image.crop((left, top, left + side, top + side))


def main() -> None:
    if not SOURCE.is_file():
        raise SystemExit(f"Source icon not found: {SOURCE}")

    source = square_crop(Image.open(SOURCE).convert("RGBA"))

    for output_dir in OUTPUT_DIRS:
        output_dir.mkdir(parents=True, exist_ok=True)

    for size in SIZES:
        icon = source.resize((size, size), Image.Resampling.LANCZOS)
        for output_dir in OUTPUT_DIRS:
            filename = (
                "favicon-32.png"
                if size == 32 and output_dir.name == "icons" and "assets" in str(output_dir)
                else f"icon-{size}.png"
            )
            target = output_dir / filename
            icon.save(target, "PNG")
            print(f"wrote {target}")


if __name__ == "__main__":
    main()
