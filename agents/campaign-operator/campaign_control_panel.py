"""Windows control panel for the interactive Campaign Operator.

It deliberately exposes a fixed command set rather than a general terminal.
"""
from __future__ import annotations

import os
import re
import subprocess
import threading
import tkinter as tk
from pathlib import Path
from tkinter import messagebox, scrolledtext

ROOT = Path(__file__).resolve().parents[2]
AGENT_DIR = Path(__file__).resolve().parent
RUNNER = AGENT_DIR / "run.php"
LAUNCHER = AGENT_DIR / "launch-campaign-agent.cmd"


def load_local_environment(path: Path = AGENT_DIR / ".env") -> None:
    """Load simple local key=value settings without replacing real environment values."""
    if not path.is_file():
        return
    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip()
        if not key.replace("_", "").isalnum() or not key or key[0].isdigit():
            continue
        if len(value) >= 2 and value[0] == value[-1] and value[0] in {"'", '"'}:
            value = value[1:-1]
        os.environ.setdefault(key, value)


load_local_environment()
PHP = os.environ.get("CAMPAIGN_OPERATOR_PHP", "php")


def safe_query(query: str) -> str:
    value = query.strip()
    if not 3 <= len(value) <= 300:
        raise ValueError("Lead-search query must be 3 to 300 characters.")
    if any(character in value for character in "\r\n\0"):
        raise ValueError("Lead-search query cannot contain control characters.")
    return value


def command_for(action: str, query: str = "", save: bool = False) -> list[str]:
    base = [PHP, str(RUNNER)]
    if action in {"brief", "discover", "plan"}:
        return base + [action]
    if action == "find":
        command = base + ["find", safe_query(query)]
        return command + (["--save"] if save else [])
    raise ValueError("Unsupported campaign action.")


def required_environment() -> list[str]:
    required = ["CAMPAIGN_OPERATOR_MCP_URL", "CAMPAIGN_OPERATOR_TOKEN"]
    return [name for name in required if not os.environ.get(name)]


def redact(text: str) -> str:
    token = os.environ.get("CAMPAIGN_OPERATOR_TOKEN", "")
    return text.replace(token, "[redacted]") if token else text


class CampaignControlPanel(tk.Tk):
    def __init__(self) -> None:
        super().__init__()
        self.title("Campaign Operator")
        self.minsize(780, 520)
        self.query = tk.StringVar()
        self.allow_save = tk.BooleanVar(value=False)
        self._build()

    def _build(self) -> None:
        frame = tk.Frame(self, padx=14, pady=14)
        frame.pack(fill="both", expand=True)
        tk.Label(frame, text="Campaign Operator", font=("Segoe UI", 16, "bold")).pack(anchor="w")
        tk.Label(frame, text="Discovery is dry-run by default. Saving requires explicit review and confirmation.").pack(anchor="w", pady=(0, 10))
        actions = tk.Frame(frame)
        actions.pack(fill="x")
        tk.Button(actions, text="Open interactive Codex", command=self.open_codex).pack(side="left", padx=(0, 8))
        tk.Button(actions, text="Daily brief", command=lambda: self.run("brief")).pack(side="left", padx=(0, 8))
        tk.Button(actions, text="Check MCP tools", command=lambda: self.run("discover")).pack(side="left")
        finder = tk.LabelFrame(frame, text="Lead Finder", padx=10, pady=10)
        finder.pack(fill="x", pady=12)
        tk.Label(finder, text="Ideal-customer search").grid(row=0, column=0, sticky="w")
        tk.Entry(finder, textvariable=self.query, width=75).grid(row=1, column=0, columnspan=3, sticky="ew", pady=(2, 8))
        tk.Button(finder, text="Suggest searches from profile", command=lambda: self.run("plan")).grid(row=2, column=0, sticky="w", padx=(0, 8))
        tk.Button(finder, text="Find (dry run)", command=lambda: self.run("find")).grid(row=2, column=1, sticky="w")
        tk.Checkbutton(finder, text="I reviewed the profile and want to submit qualifying candidates to the Lead Finder queue", variable=self.allow_save).grid(row=3, column=0, columnspan=3, sticky="w", pady=(8, 2))
        tk.Button(finder, text="Find and save reviewed candidates", command=self.save_find).grid(row=4, column=0, sticky="w")
        self.output = scrolledtext.ScrolledText(frame, height=18, wrap="word", state="disabled")
        self.output.pack(fill="both", expand=True, pady=(12, 0))

    def append(self, text: str) -> None:
        self.output.configure(state="normal")
        self.output.insert("end", redact(text) + "\n")
        self.output.see("end")
        self.output.configure(state="disabled")

    def open_codex(self) -> None:
        if not LAUNCHER.is_file():
            messagebox.showerror("Campaign Operator", "The Codex launcher is missing.")
            return
        subprocess.Popen(["cmd.exe", "/d", "/c", str(LAUNCHER)], cwd=AGENT_DIR, creationflags=getattr(subprocess, "CREATE_NEW_CONSOLE", 0))

    def save_find(self) -> None:
        if not self.allow_save.get():
            messagebox.showwarning("Review required", "Check the review box before saving candidates.")
            return
        if not messagebox.askyesno("Save candidates", "Save qualifying, non-duplicate candidates to the Lead Finder queue? This cannot create prospects."):
            return
        self.run("find", save=True)

    def run(self, action: str, save: bool = False) -> None:
        missing = required_environment()
        if missing:
            messagebox.showerror("Campaign Operator", "Copy .env.example to .env, fill in these values, then open this dashboard again:\n" + "\n".join(missing))
            return
        try:
            command = command_for(action, self.query.get(), save)
        except ValueError as error:
            messagebox.showerror("Campaign Operator", str(error))
            return
        self.append("> " + " ".join(command[:3]) + (" [lead query supplied]" if action == "find" else ""))
        threading.Thread(target=self._run_process, args=(command, action), daemon=True).start()

    def _run_process(self, command: list[str], action: str) -> None:
        try:
            result = subprocess.run(command, cwd=ROOT, env=os.environ.copy(), text=True, capture_output=True, timeout=120, shell=False)
            output = (result.stdout + result.stderr).strip() or "Command completed without output."
            if action == "plan":
                first_suggestion = re.search(r"^1\. (.+)$", result.stdout, re.MULTILINE)
                if first_suggestion:
                    self.after(0, self.query.set, first_suggestion.group(1))
                    output += "\n\nThe first suggestion is now in the search box. You can edit it before running the search."
            self.after(0, self.append, output)
        except subprocess.TimeoutExpired:
            self.after(0, self.append, "Command stopped after the 120-second safety timeout.")
        except OSError as error:
            self.after(0, self.append, f"Could not start command: {error}")


if __name__ == "__main__":
    CampaignControlPanel().mainloop()
