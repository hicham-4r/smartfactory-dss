#!/usr/bin/env python3
from __future__ import annotations

import sys
from pathlib import Path


def main() -> int:
    if len(sys.argv) != 2:
        print("usage: validate_asset_source.py <configmaps.yaml>", file=sys.stderr)
        return 2
    text = Path(sys.argv[1]).read_text(encoding="utf-8")
    expected = "  ASSET_URL: https://localhost:8443"
    if text.count(expected) != 1:
        print("ASSET_URL source contract failed.", file=sys.stderr)
        return 1
    forbidden = [
        "ASSET_URL: http://localhost:8443",
        "ASSET_URL: http://smartfactory.local",
    ]
    for value in forbidden:
        if value in text:
            print(f"Forbidden asset origin found: {value}", file=sys.stderr)
            return 1
    print("Laravel HTTPS asset source contract: passed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
