"""Windows control panel for the interactive Campaign Operator.

It deliberately exposes a fixed command set rather than a general terminal.
"""
from __future__ import annotations

import os
import re
import shutil
import subprocess
import threading
import tkinter as tk
import webbrowser
from pathlib import Path
from tkinter import messagebox, scrolledtext

ROOT = Path(__file__).resolve().parents[2]
AGENT_DIR = Path(__file__).resolve().parent
RUNNER = AGENT_DIR / "run.php"
LAUNCHER = AGENT_DIR / "launch-campaign-agent.cmd"
ENV_FILE = AGENT_DIR / ".env"
ENV_EXAMPLE = AGENT_DIR / ".env.example"


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


def ideal_customer_profile_url() -> str | None:
    endpoint = os.environ.get("CAMPAIGN_OPERATOR_MCP_URL", "").strip().rstrip("/")
    if not endpoint or not endpoint.endswith("/mcp"):
        return None
    return endpoint[:-4] + "/lead-finder/profile"


def redact(text: str) -> str:
    token = os.environ.get("CAMPAIGN_OPERATOR_TOKEN", "")
    return text.replace(token, "[redacted]") if token else text


class CampaignControlPanel(tk.Tk):
    def __init__(self) -> None:
        super().__init__()
        self.title("Campaign Operator Dashboard")
        self.minsize(840, 700)
        self.query = tk.StringVar()
        self.allow_save = tk.BooleanVar(value=False)
        self._build()

    def _build(self) -> None:
        frame = tk.Frame(self, padx=20, pady=18)
        frame.pack(fill="both", expand=True)
        tk.Label(frame, text="Find companies worth talking to", font=("Segoe UI", 20, "bold")).pack(anchor="w")
        tk.Label(frame, text="Start at step 1 if this is your first time. The dashboard will not create prospects or contact anyone without your confirmation.", wraplength=760, justify="left").pack(anchor="w", pady=(3, 14))

        setup_ready = not required_environment()
        connection_text = "Connection ready" if setup_ready else "Connection needs setup"
        connection_detail = "Your local CRM connection is configured." if setup_ready else "Add the CRM address and token once. The dashboard keeps them in a local .env file."
        self.step(frame, "1", "Connect this dashboard", connection_detail, "Review connection settings" if setup_ready else "Set up connection", self.open_configuration, connection_text)
        self.step(frame, "2", "Describe the companies you want", "Choose the characteristics, importance, locations, and exclusions that define a good fit. This is where the agent gets its instructions.", "Open Ideal Customer Profile", self.open_ideal_customer_profile)
        self.step(frame, "3", "Get search ideas", "The dashboard turns your profile into suggested company searches and puts the first one below for you to edit.", "Suggest company searches", lambda: self.run("plan"))

        finder = tk.LabelFrame(frame, text="4. Search for potential companies", padx=12, pady=10, font=("Segoe UI", 10, "bold"))
        finder.pack(fill="x", pady=(10, 8))
        tk.Label(finder, text="Search phrase", font=("Segoe UI", 10, "bold")).grid(row=0, column=0, sticky="w")
        tk.Label(finder, text="Use a suggestion above or write your own. You can change it before searching.").grid(row=1, column=0, columnspan=3, sticky="w", pady=(0, 4))
        tk.Entry(finder, textvariable=self.query, width=76).grid(row=2, column=0, columnspan=3, sticky="ew", pady=(0, 8))
        tk.Button(finder, text="Find companies (nothing saved)", command=lambda: self.run("find"), font=("Segoe UI", 10, "bold")).grid(row=3, column=0, sticky="w")
        finder.columnconfigure(0, weight=1)

        review = tk.LabelFrame(frame, text="5. After reviewing the results", padx=12, pady=10, font=("Segoe UI", 10, "bold"))
        review.pack(fill="x", pady=(0, 10))
        tk.Label(review, text="Only use this after you have read the results below. It adds qualifying companies to the CRM review queue; it does not create prospects or send messages.", wraplength=740, justify="left").pack(anchor="w")
        tk.Checkbutton(review, text="I reviewed the results and want to add qualifying companies to the review queue", variable=self.allow_save).pack(anchor="w", pady=(7, 3))
        tk.Button(review, text="Add reviewed companies to the queue", command=self.save_find).pack(anchor="w")

        returning = tk.Frame(frame)
        returning.pack(fill="x", pady=(0, 8))
        tk.Label(returning, text="Returning to an active campaign?", font=("Segoe UI", 9, "bold")).pack(side="left")
        tk.Button(returning, text="See today’s campaign summary", command=lambda: self.run("brief")).pack(side="left", padx=(8, 5))
        tk.Button(returning, text="Advanced: check connection tools", command=lambda: self.run("discover")).pack(side="left")

        tk.Label(frame, text="Results", font=("Segoe UI", 11, "bold")).pack(anchor="w")
        self.output = scrolledtext.ScrolledText(frame, height=14, wrap="word", state="disabled")
        self.output.pack(fill="both", expand=True, pady=(3, 0))
        self.append("Welcome. " + ("Start with step 2 to describe the companies you want to find." if setup_ready else "Start with step 1 to connect this dashboard to your CRM."))

    def step(self, parent: tk.Widget, number: str, title: str, detail: str, button: str, command: object, status: str | None = None) -> None:
        row = tk.Frame(parent, padx=10, pady=8, highlightthickness=1, highlightbackground="#d7dce5")
        row.pack(fill="x", pady=(0, 7))
        tk.Label(row, text=number, font=("Segoe UI", 13, "bold"), width=3).grid(row=0, column=0, rowspan=2, sticky="n")
        title_text = title if status is None else f"{title} — {status}"
        tk.Label(row, text=title_text, font=("Segoe UI", 10, "bold")).grid(row=0, column=1, sticky="w")
        tk.Label(row, text=detail, wraplength=535, justify="left").grid(row=1, column=1, sticky="w")
        tk.Button(row, text=button, command=command).grid(row=0, column=2, rowspan=2, padx=(12, 0))
        row.columnconfigure(1, weight=1)

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

    def open_configuration(self) -> None:
        if not ENV_FILE.exists():
            if not ENV_EXAMPLE.exists():
                messagebox.showerror("Campaign Operator", "The configuration template is missing.")
                return
            shutil.copyfile(ENV_EXAMPLE, ENV_FILE)
        os.startfile(ENV_FILE)
        messagebox.showinfo("Campaign Operator", "Fill in the CRM address and token, save the file, then close and reopen this dashboard.")

    def open_ideal_customer_profile(self) -> None:
        url = ideal_customer_profile_url()
        if url is None:
            messagebox.showwarning("Campaign Operator", "Set the CRM address in .env first, then reopen this dashboard.")
            return
        webbrowser.open(url)

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
        if action == "find" and not os.environ.get("GOOGLE_PLACES_API_KEY"):
            messagebox.showerror("Campaign Operator", "Add GOOGLE_PLACES_API_KEY to .env before searching for companies. Search suggestions and profile setup work without it.")
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
