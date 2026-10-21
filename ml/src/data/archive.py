"""List the Pianda RAR5 archive and compare it with the loose source files."""

from __future__ import annotations

import zlib
from pathlib import Path

from src.common import RAW_EMPLOYABILITY


def _read_vint(buf: bytes, index: int) -> tuple[int, int]:
    value = 0
    shift = 0
    while True:
        if index >= len(buf):
            raise EOFError("Truncated RAR5 vint.")
        byte = buf[index]
        index += 1
        value |= (byte & 0x7F) << shift
        if byte < 0x80:
            return value, index
        shift += 7
        if shift > 63:
            raise ValueError("RAR5 vint is too long.")


def list_rar5(path: Path) -> list[dict]:
    data = path.read_bytes()
    if data[:8] != b"Rar!\x1a\x07\x01\x00":
        raise ValueError("Dataset.rar is not a RAR5 archive.")
    index = 8
    entries = []
    while index < len(data):
        _crc, index = int.from_bytes(data[index : index + 4], "little"), index + 4
        header_size, index = _read_vint(data, index)
        header_start = index
        header_end = header_start + header_size
        header_type, index = _read_vint(data, index)
        header_flags, index = _read_vint(data, index)
        extra_size = 0
        data_size = 0
        if header_flags & 0x0001:
            extra_size, index = _read_vint(data, index)
        if header_flags & 0x0002:
            data_size, index = _read_vint(data, index)
        entry = {
            "header_type": header_type,
            "packed_bytes": data_size,
            "extra_bytes": extra_size,
        }
        if header_type == 2:
            file_flags, index = _read_vint(data, index)
            unpacked, index = _read_vint(data, index)
            _attributes, index = _read_vint(data, index)
            if file_flags & 0x0002:
                index += 4
            data_crc = None
            if file_flags & 0x0004:
                data_crc = int.from_bytes(data[index : index + 4], "little")
                index += 4
            _compression, index = _read_vint(data, index)
            _host, index = _read_vint(data, index)
            name_length, index = _read_vint(data, index)
            name = data[index : index + name_length].decode("utf-8", errors="replace")
            entry.update(
                {
                    "name": name,
                    "unpacked_bytes": unpacked,
                    "crc32": data_crc,
                    "is_directory": bool(file_flags & 0x0001),
                }
            )
        entries.append(entry)
        index = header_end + data_size
    return entries


def audit_employability_archive() -> dict:
    archive = RAW_EMPLOYABILITY / "Dataset.rar"
    entries = [entry for entry in list_rar5(archive) if entry.get("header_type") == 2]
    loose = {
        "Raw Dataset.xlsx": RAW_EMPLOYABILITY / "Raw Dataset.xlsx",
        "Survey questionnaire.docx": RAW_EMPLOYABILITY / "Survey questionnaire.docx",
    }
    comparisons = []
    for entry in entries:
        name = entry.get("name", "")
        match = None
        for loose_name, loose_path in loose.items():
            if name.endswith(loose_name) or loose_name in name or (
                loose_name.endswith(".xlsx") and name.lower().endswith(".xlsx")
            ) or (loose_name.endswith(".docx") and name.lower().endswith(".docx")):
                payload = loose_path.read_bytes()
                match = {
                    "loose_name": loose_name,
                    "loose_bytes": len(payload),
                    "loose_crc32": zlib.crc32(payload) & 0xFFFFFFFF,
                    "same_size": len(payload) == entry.get("unpacked_bytes"),
                    "same_crc32": (zlib.crc32(payload) & 0xFFFFFFFF) == entry.get("crc32"),
                }
                break
        comparisons.append({**entry, "loose_comparison": match})
    return {
        "archive": str(archive.name),
        "files": comparisons,
        "combined_into_training": False,
        "note": (
            "Archive members were listed and checksum-compared. "
            "They were not extracted into the training table."
        ),
    }
