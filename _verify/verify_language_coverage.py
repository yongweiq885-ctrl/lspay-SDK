"""Verify that every SDK exposes the shared endpoint, request, response and way-code contract."""

from __future__ import print_function

import json
import os
import re
import sys


ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
LANGUAGES = {
    "php": ("php/LsPaySdk", {".php"}),
    "java": ("java/src/main/java", {".java"}),
    "python": ("python/lspay", {".py"}),
    "csharp": ("csharp/src", {".cs"}),
    "uniapp": ("uniapp/lspay", {".js", ".vue"}),
    "go": ("go", {".go"}),
}


def load_json(relative_path):
    with open(os.path.join(ROOT, relative_path), "r", encoding="utf-8") as handle:
        return json.load(handle)


def source_text(relative_root, extensions):
    root = os.path.join(ROOT, relative_root)
    if not os.path.isdir(root):
        raise RuntimeError("source directory does not exist: " + relative_root)

    chunks = []
    for current_root, directories, files in os.walk(root):
        directories[:] = [
            directory for directory in directories
            if directory not in {"bin", "obj", "vendor", "__pycache__", "examples"}
        ]
        for filename in files:
            if os.path.splitext(filename)[1].lower() not in extensions:
                continue
            if filename.endswith("_test.go"):
                continue
            path = os.path.join(current_root, filename)
            with open(path, "r", encoding="utf-8") as handle:
                chunks.append(handle.read())
    if not chunks:
        raise RuntimeError("no source files found under: " + relative_root)
    return "\n".join(chunks)


def contains_symbol(text, symbol):
    return re.search(r"\b" + re.escape(symbol) + r"\b", text) is not None


def verify_language(language, endpoint_contract, way_code_contract):
    relative_root, extensions = LANGUAGES[language]
    text = source_text(relative_root, extensions)
    missing = []

    for endpoint in endpoint_contract["endpoints"]:
        if endpoint["path"] not in text:
            missing.append("endpoint path " + endpoint["path"])
        for key in ("requestClass", "responseClass"):
            symbol = endpoint[key]
            if not contains_symbol(text, symbol):
                missing.append(key + " " + symbol)

    for request in endpoint_contract["additionalRequests"]:
        for key in ("requestClass", "responseClass"):
            symbol = request[key]
            if not contains_symbol(text, symbol):
                missing.append(key + " " + symbol)

    for notification in endpoint_contract["notifications"]:
        symbol = notification["class"]
        if not contains_symbol(text, symbol):
            missing.append("notification " + symbol)

    for code in way_code_contract["codes"]:
        if not contains_symbol(text, code):
            missing.append("way_code " + code)

    if missing:
        print("[FAIL] {0}: missing {1} contract items".format(language, len(missing)))
        for item in missing:
            print("  - " + item)
        return False

    print(
        "[PASS] {0}: {1} endpoints, {2} requests, {3} notifications, {4} way codes".format(
            language,
            len(endpoint_contract["endpoints"]),
            len(endpoint_contract["endpoints"]) + len(endpoint_contract["additionalRequests"]),
            len(endpoint_contract["notifications"]),
            len(way_code_contract["codes"]),
        )
    )
    return True


def main():
    selected = sys.argv[1:] or list(LANGUAGES)
    unknown = [language for language in selected if language not in LANGUAGES]
    if unknown:
        print("Unknown language(s): " + ", ".join(unknown), file=sys.stderr)
        return 2

    endpoint_contract = load_json("contracts/endpoints.json")
    way_code_contract = load_json("contracts/way-codes.json")
    passed = True
    for language in selected:
        try:
            passed = verify_language(language, endpoint_contract, way_code_contract) and passed
        except (OSError, RuntimeError) as error:
            passed = False
            print("[FAIL] {0}: {1}".format(language, error))
    return 0 if passed else 1


if __name__ == "__main__":
    sys.exit(main())
