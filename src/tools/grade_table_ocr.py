#!/usr/bin/env python3
"""Grid-aware cell OCR for portal grade tables. JSON on stdout; non-zero exit plus {"error": ...} on failure."""
import json, re, subprocess, sys, tempfile, os
import numpy as np
from PIL import Image

NUM = "0123456789.INCDRPW"
WHITELIST = {
    "no": "0123456789",
    "code": "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 ",
    "description": None,
    "units": "0123456789.",
    "midterm": NUM, "final": NUM, "final_grade": NUM,
    "remarks": "ABCDEFGHIJKLMNOPQRSTUVWXYZ ",
}
COLS10 = ["no", "code", "description", "faculty", "units", "section", "midterm", "final", "final_grade", "remarks"]
# Detected so neighboring cells keep their bounds. Never read or stored.
IGNORE = {"faculty", "instructor", "professor", "section", "year", "schedule", "room", "no"}
HEADER_MAP = {
    "no": "no", "#": "no", "code": "code", "subject": "code",
    "description": "description", "descript": "description",
    "faculty": "faculty", "instructor": "instructor", "professor": "professor",
    "units": "units", "unit": "units",
    "section": "section", "year": "year", "schedule": "schedule", "room": "room",
    "midterm": "midterm", "final": "final",
    "grade": "final_grade", "remarks": "remarks", "remark": "remarks",
}


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


def prep(cell, threshold=150):
    c = cell.convert("L")
    c = c.resize((max(1, c.width * 3), max(1, c.height * 3)), Image.LANCZOS)
    c = Image.fromarray(np.where(np.array(c) < threshold, 0, 255).astype("uint8"))
    return Image.fromarray(np.pad(np.array(c), 14, constant_values=255))


def find_grid(im):
    a = np.array(im).astype(int)
    H, W = a.shape[0], a.shape[1]
    orange = (a[:, :, 0] > 210) & (a[:, :, 1] > 80) & (a[:, :, 1] < 180) & (a[:, :, 2] < 90)
    rows = np.where(orange.sum(1) > W * 0.25)[0]
    if len(rows) == 0:
        raise ValueError("header band not found")
    hy0, hy1 = int(rows.min()), int(rows.max())
    if hy1 - hy0 < 4:
        raise ValueError("header band too thin")
    band = orange[hy0 + 2:hy1 - 1, :]
    sep_cols = band.mean(0) < 0.08
    xs = np.where(band.any(0))[0]
    if len(xs) == 0:
        raise ValueError("header columns not found")
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
    if len(segs) < 5:
        raise ValueError(f"expected at least 5 columns, found {len(segs)}")
    sample_x0, sample_x1 = segs[min(1, len(segs) - 1)]
    bg = np.array(im.getpixel((sample_x0 + 2, min(H - 1, hy1 + 8))))
    sep = [y for y in range(hy1 + 2, H)
           if (np.abs(a[y, sample_x0:min(W, segs[min(3, len(segs) - 1)][1])] - bg).sum(1) > 25).mean() > 0.85]
    groups = []
    for y in sep:
        if groups and y - groups[-1][-1] <= 2:
            groups[-1].append(y)
        else:
            groups.append([y])
    b = [(g[0], g[-1]) for g in groups]
    cells = [(b[i][1] + 1, b[i + 1][0]) for i in range(len(b) - 1) if b[i + 1][0] - b[i][1] > 10]
    return hy0, hy1, segs, cells, H


def map_columns(im, hy0, hy1, segs):
    if len(segs) == 10:
        return COLS10
    names = []
    for (x0, x1) in segs:
        header = prep(im.crop((x0 + 1, hy0, x1 - 1, hy1)), threshold=180)
        text = tess(header, psm=7).lower()
        mapped = "description"
        for needle, key in HEADER_MAP.items():
            if needle in text:
                mapped = key
                break
        names.append(mapped)
    return names


def read_meta(im, hy0, last_row_bottom, H):
    meta = {"program": None, "school_year": None, "semester": None, "gpa_shown": None}
    top = im.crop((0, 0, im.width, hy0)).convert("L")
    top = top.resize((top.width * 2, top.height * 2), Image.LANCZOS)
    t = tess(top, psm=6)
    m = re.search(r"School\s*Year\s*and\s*Semester:\s*(\d{4})\s*-\s*(\d{4})\s*\|\s*(\w+)", t, re.I)
    if m:
        meta["school_year"] = f"{m.group(1)}-{m.group(2)}"
        meta["semester"] = m.group(3).capitalize()
    m = re.search(r"Program:\s*(.+?)\s{2,}|Program:\s*(.+?)\s+School", t, re.I)
    if m:
        meta["program"] = (m.group(1) or m.group(2)).strip()
    bottom = im.crop((int(im.width * 0.55), last_row_bottom + 1, im.width, H)).convert("L")
    bottom = bottom.resize((bottom.width * 3, bottom.height * 3), Image.LANCZOS)
    bottom = Image.fromarray(np.where(np.array(bottom) < 120, 0, 255).astype("uint8"))
    bottom = Image.fromarray(np.pad(np.array(bottom), 20, constant_values=255))
    g = re.search(r"(\d\.\d{2})", tess(bottom, "GPA0123456789. ", psm=6))
    if g:
        meta["gpa_shown"] = float(g.group(1))
    return meta


def main(path):
    im = Image.open(path).convert("RGB")
    hy0, hy1, segs, cells, H = find_grid(im)
    col_names = map_columns(im, hy0, hy1, segs)
    rows = []
    for (y0, y1) in cells:
        r = {}
        for name, (x0, x1) in zip(col_names, segs):
            if name in IGNORE:
                continue
            pad_x, pad_y = 2, 1
            crop = im.crop((x0 + pad_x, y0 + pad_y, max(x0 + pad_x + 1, x1 - pad_x), max(y0 + pad_y + 1, y1 - pad_y)))
            r[name] = tess(prep(crop), WHITELIST.get(name))
        rows.append(r)
    meta = read_meta(im, hy0, cells[-1][1] if cells else hy1, H)
    print(json.dumps({"meta": meta, "rows": rows}, indent=2))


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "usage: grade_table_ocr.py <image>"}))
        sys.exit(1)
    try:
        main(sys.argv[1])
    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)
