#!/usr/bin/env python3
"""
REFERENCE IMPLEMENTATION (tested) - grid-aware, cell-by-cell OCR for the UCC portal grade table.

Why this exists: plain whole-image Tesseract misreads grade digits badly on these screenshots
("1.25" -> "HE25)", "1.75" -> "75", "3" -> "3}", "PR 002" -> "PROO2"). Cropping each table cell, upscaling,
binarizing and OCR-ing it with a per-column character whitelist read 23/23 rows of 3 samples correctly.

Usage:  python3 grade_table_ocr.py <image.png>      -> prints JSON to stdout
Needs:  python3, Pillow, numpy, the `tesseract` CLI (all installed in the app Docker image).

Cursor: productionize this at src/tools/grade_table_ocr.py (keep the CLI contract: JSON on stdout, non-zero exit +
{"error": "..."} on failure). Laravel calls it with Symfony Process. Make header/grid detection tolerant, add
unit tests, and fall back to the generic OCR pipeline when layout detection fails (see spec section 6).
Known limitation: tuned to this portal's look (orange header, light grid lines). Other layouts -> fallback path.
"""
import json, re, subprocess, sys, tempfile, os
import numpy as np
from PIL import Image

NUM = "0123456789.INCDRPW"
WHITELIST = {
    "no": "0123456789",
    "code": "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ",
    "units": "0123456789.",
    "midterm": NUM, "final": NUM, "final_grade": NUM,
    "remarks": "ABCDEFGHIJKLMNOPQRSTUVWXYZ ",
}
COLS = ["no", "code", "description", "faculty", "units", "section", "midterm", "final", "final_grade", "remarks"]
IGNORE = {"faculty", "section"}  # never read or store faculty names / sections (privacy)


def tess(img, whitelist=None, psm=7):
    with tempfile.NamedTemporaryFile(suffix=".png", delete=False) as f:
        path = f.name
    img.save(path)
    cmd = ["tesseract", path, "-", "--psm", str(psm)]
    if whitelist:
        cmd += ["-c", "tessedit_char_whitelist=" + whitelist]
    out = subprocess.run(cmd, capture_output=True, text=True).stdout.strip()
    os.unlink(path)
    return out


def prep(cell):
    c = cell.convert("L")
    c = c.resize((c.width * 3, c.height * 3), Image.LANCZOS)
    c = Image.fromarray(np.where(np.array(c) < 150, 0, 255).astype("uint8"))
    return Image.fromarray(np.pad(np.array(c), 14, constant_values=255))


def find_grid(im):
    a = np.array(im).astype(int)
    H, W, _ = a.shape
    orange = (a[:, :, 0] > 230) & (a[:, :, 1] > 100) & (a[:, :, 1] < 160) & (a[:, :, 2] < 60)
    rows = np.where(orange.sum(1) > W * 0.4)[0]
    if len(rows) == 0:
        raise ValueError("header band not found")
    hy0, hy1 = int(rows.min()), int(rows.max())
    band = orange[hy0 + 2:hy1 - 1, :]
    sep_cols = band.mean(0) < 0.02
    xs = np.where(band.any(0))[0]
    x_min, x_max = int(xs.min()), int(xs.max())
    segs, start = [], None
    for x in range(x_min, x_max + 1):
        if not sep_cols[x] and start is None:
            start = x
        if sep_cols[x] and start is not None:
            segs.append((start, x)); start = None
    if start is not None:
        segs.append((start, x_max + 1))
    segs = [s for s in segs if s[1] - s[0] > 6]
    if len(segs) != 10:
        raise ValueError(f"expected 10 columns, found {len(segs)}")
    bg = np.array(im.getpixel((segs[1][0] + 2, hy1 + 8)))
    sep = [y for y in range(hy1 + 2, H)
           if (np.abs(a[y, segs[1][0]:segs[3][1]] - bg).sum(1) > 25).mean() > 0.9]
    groups = []
    for y in sep:
        if groups and y - groups[-1][-1] <= 2: groups[-1].append(y)
        else: groups.append([y])
    b = [(g[0], g[-1]) for g in groups]
    cells = [(b[i][1] + 1, b[i + 1][0]) for i in range(len(b) - 1) if b[i + 1][0] - b[i][1] > 12]
    return hy0, hy1, segs, cells, H


def read_meta(im, hy0, last_row_bottom, H):
    meta = {"program": None, "school_year": None, "semester": None, "gpa_shown": None}
    top = im.crop((0, 0, im.width, hy0)).convert("L")
    top = top.resize((top.width * 2, top.height * 2), Image.LANCZOS)
    t = tess(top, psm=6)
    m = re.search(r"School\s*Year\s*and\s*Semester:\s*(\d{4})\s*-\s*(\d{4})\s*\|\s*(\w+)", t, re.I)
    if m:
        meta["school_year"] = f"{m.group(1)}-{m.group(2)}"; meta["semester"] = m.group(3).capitalize()
    m = re.search(r"Program:\s*(.+?)\s{2,}|Program:\s*(.+?)\s+School", t, re.I)
    if m:
        meta["program"] = (m.group(1) or m.group(2)).strip()
    bottom = im.crop((int(im.width * 0.6), last_row_bottom + 1, im.width, H)).convert("L")
    bottom = bottom.resize((bottom.width * 3, bottom.height * 3), Image.LANCZOS)
    # footer text is dark green on mid green: MUST binarize or Tesseract returns nothing
    bottom = Image.fromarray(np.where(np.array(bottom) < 120, 0, 255).astype("uint8"))
    bottom = Image.fromarray(np.pad(np.array(bottom), 20, constant_values=255))
    g = re.search(r"(\d\.\d{2})", tess(bottom, "GPA0123456789. ", psm=6))
    if g:
        meta["gpa_shown"] = float(g.group(1))
    return meta


def main(path):
    im = Image.open(path).convert("RGB")
    hy0, hy1, segs, cells, H = find_grid(im)
    rows = []
    for (y0, y1) in cells:
        r = {}
        for name, (x0, x1) in zip(COLS, segs):
            if name in IGNORE:
                continue
            c = prep(im.crop((x0 + 2, y0 + 1, x1 - 2, y1 - 1)))
            r[name] = tess(c, WHITELIST.get(name))
        rows.append(r)
    meta = read_meta(im, hy0, cells[-1][1] if cells else hy1, H)
    print(json.dumps({"meta": meta, "rows": rows}, indent=2))


if __name__ == "__main__":
    try:
        main(sys.argv[1])
    except Exception as e:
        print(json.dumps({"error": str(e)})); sys.exit(1)
