"""Local file extraction and a bounded prompt for the Campaign Operator profile assistant."""
from __future__ import annotations

import re
import subprocess
import tempfile
import zipfile
from pathlib import Path


def extract_text(path: Path) -> str:
    suffix = path.suffix.lower()
    if suffix == ".txt":
        for encoding in ("utf-8-sig", "utf-16", "cp1252"):
            try:
                return path.read_text(encoding=encoding).strip()
            except UnicodeError:
                continue
    if suffix == ".docx":
        with zipfile.ZipFile(path) as document:
            xml = document.read("word/document.xml").decode("utf-8", "replace")
        return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", xml)).strip()
    if suffix == ".pdf":
        from PyPDF2 import PdfReader
        return "\n".join(page.extract_text() or "" for page in PdfReader(str(path)).pages).strip()
    if suffix == ".doc":
        result = subprocess.run(["antiword", str(path)], text=True, capture_output=True, timeout=30, shell=False)
        if result.returncode == 0:
            return result.stdout.strip()
    raise ValueError("Use a readable .txt, .pdf, .doc, or .docx file.")


def profile_prompt(source: str) -> str:
    source = source.strip()[:40000]
    if not source:
        raise ValueError("Describe your company or choose a file first.")
    return """Turn this company description into an editable Ideal Customer Profile draft. Do not invent facts. Return JSON only with: description (string), required_any (array of strings), positive_keywords (object mapping characteristic to importance 1-20), negative_keywords (array), preferred_locations (array), minimum_score (integer), strong_fit_score (integer). Use concise, searchable business characteristics.\n\nSOURCE:\n""" + source


def draft_with_codex(source: str) -> str:
    prompt = profile_prompt(source)
    with tempfile.NamedTemporaryFile(prefix="campaign-profile-draft-", suffix=".json", delete=False) as output:
        output_path = Path(output.name)
    try:
        result = subprocess.run(["cmd.exe", "/d", "/c", "codex.cmd", "exec", "--skip-git-repo-check", "--output-last-message", str(output_path), prompt], text=True, capture_output=True, timeout=180, shell=False)
        if result.returncode != 0:
            raise RuntimeError((result.stderr or result.stdout or "ChatGPT could not create a draft.").strip())
        draft = output_path.read_text(encoding="utf-8").strip()
        if not draft:
            raise RuntimeError("ChatGPT returned an empty draft.")
        return draft
    finally:
        output_path.unlink(missing_ok=True)
