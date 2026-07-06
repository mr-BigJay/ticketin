#!/usr/bin/env python3
"""Generate square Ticketin favicon/PWA icons (32, 192, 512)."""

from __future__ import annotations

import math
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
OUTPUT_DIRS = [
    ROOT / "assets" / "icons",
    ROOT / "admin" / "icons",
]
SIZES = [32, 192, 512]


def lerp(a: float, b: float, t: float) -> float:
    return a + (b - a) * t


def gradient_bg(size: int) -> Image.Image:
    img = Image.new("RGB", (size, size))
    px = img.load()
    c1 = (6, 182, 212)
    c2 = (2, 132, 199)
    for y in range(size):
        t = y / max(size - 1, 1)
        r = int(lerp(c1[0], c2[0], t))
        g = int(lerp(c1[1], c2[1], t))
        b = int(lerp(c1[2], c2[2], t))
        for x in range(size):
            px[x, y] = (r, g, b)
    return img


def draw_icon(size: int) -> Image.Image:
    img = gradient_bg(size)
    draw = ImageDraw.Draw(img)
    white = (255, 255, 255)

    cx = size / 2
    cy = size / 2 + size * 0.02
    sw = size * 0.50
    sh = size * 0.56
    top = cy - sh / 2
    bottom = cy + sh / 2
    left = cx - sw / 2
    right = cx + sw / 2
    mid = cy + sh * 0.05

    shield = [
        (cx, top),
        (right, top + sh * 0.18),
        (right, mid),
        (cx, bottom),
        (left, mid),
        (left, top + sh * 0.18),
    ]
    stroke = max(2, int(size * 0.038))
    draw.polygon(shield, outline=white, width=stroke)

    inner_l = left + sw * 0.22
    inner_r = right - sw * 0.22
    inner_t = top + sh * 0.28
    inner_b = mid - sh * 0.05
    cols = 4
    for i in range(cols):
        x = inner_l + (inner_r - inner_l) * i / (cols - 1)
        h = inner_b - inner_t
        line_top = inner_t + h * (0.15 + 0.1 * i)
        draw.line((x, line_top, x, inner_b), fill=white, width=max(1, int(size * 0.022)))
        r = max(2, int(size * 0.026))
        draw.ellipse((x - r, inner_b - r, x + r, inner_b + r), fill=white)

    wx = cx + sw * 0.18
    wy = cy + sh * 0.18
    wlen = size * 0.15
    w = max(2, int(size * 0.032))
    ang = math.radians(-40)
    x2 = wx + wlen * math.cos(ang)
    y2 = wy + wlen * math.sin(ang)
    draw.line((wx, wy, x2, y2), fill=white, width=w)
    br = max(3, int(size * 0.042))
    draw.ellipse((wx - br, wy - br, wx + br, wy + br), outline=white, width=max(1, w - 1))
    hr = max(2, int(size * 0.032))
    draw.ellipse((x2 - hr, y2 - hr, x2 + hr, y2 + hr), fill=white)

    return img


def main() -> None:
    for output_dir in OUTPUT_DIRS:
        output_dir.mkdir(parents=True, exist_ok=True)

    for size in SIZES:
        icon = draw_icon(size)
        for output_dir in OUTPUT_DIRS:
            filename = "favicon-32.png" if size == 32 and output_dir.name == "icons" and "assets" in str(output_dir) else f"icon-{size}.png"
            target = output_dir / filename
            icon.save(target, "PNG")
            print(f"wrote {target}")


if __name__ == "__main__":
    main()
