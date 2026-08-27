"""Windows control panel for the interactive Campaign Operator.

It deliberately exposes a fixed command set rather than a general terminal.
"""
from __future__ import annotations

import json
import os
import re
import subprocess
import sys
import threading
import tkinter as tk
import webbrowser
from pathlib import Path
from tkinter import filedialog, messagebox, scrolledtext
from tkinter import ttk

ROOT = Path(__file__).resolve().parents[2]
AGENT_DIR = Path(__file__).resolve().parent
if str(AGENT_DIR) not in sys.path:
    sys.path.insert(0, str(AGENT_DIR))
from profile_draft import extract_text, profile_prompt
RUNNER = AGENT_DIR / "run.php"
LAUNCHER = AGENT_DIR / "launch-campaign-agent.cmd"
ENV_FILE = AGENT_DIR / ".env"
CONNECTION_KEYS = ("CAMPAIGN_OPERATOR_MCP_URL", "CAMPAIGN_OPERATOR_TOKEN", "GOOGLE_PLACES_API_KEY", "FOURSQUARE_PLACES_API_KEY", "MAPBOX_ACCESS_TOKEN", "OSM_OVERPASS_URL", "CAMPAIGN_OPERATOR_SOURCES")
LOCAL_SETTING_KEYS = CONNECTION_KEYS + ("CAMPAIGN_OPERATOR_CAMPAIGN_ID",)


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


def save_local_environment(values: dict[str, str], path: Path = ENV_FILE) -> None:
    """Save dashboard connection settings while retaining unrelated local options."""
    for key, value in values.items():
        if key not in LOCAL_SETTING_KEYS or "\r" in value or "\n" in value:
            raise ValueError("Connection settings must be single-line values.")
    existing = path.read_text(encoding="utf-8").splitlines() if path.is_file() else []
    retained = [line for line in existing if not any(line.strip().startswith(key + "=") for key in values)]
    while retained and not retained[-1].strip():
        retained.pop()
    retained.extend(f"{key}={value}" for key, value in values.items())
    path.write_text("\n".join(retained) + "\n", encoding="utf-8")


def safe_query(query: str) -> str:
    value = query.strip()
    if not 3 <= len(value) <= 300:
        raise ValueError("Lead-search query must be 3 to 300 characters.")
    if any(character in value for character in "\r\n\0"):
        raise ValueError("Lead-search query cannot contain control characters.")
    return value


def command_for(action: str, query: str = "", save: bool = False, campaign_id: int | None = None, sources: list[str] | None = None) -> list[str]:
    base = [PHP, str(RUNNER)]
    if action in {"brief", "discover", "plan"}:
        command = base + [action]
        return command + ([f"--campaign-id={campaign_id}"] if campaign_id is not None and action == "brief" else [])
    if action == "campaigns":
        return base + [action, "--json"]
    if action == "find":
        command = base + ["find", safe_query(query)]
        if campaign_id is not None:
            command.append(f"--campaign-id={campaign_id}")
        if sources:
            command.append("--sources=" + ",".join(sources))
        return command + (["--save"] if save else [])
    raise ValueError("Unsupported campaign action.")


def required_environment() -> list[str]:
    required = ["CAMPAIGN_OPERATOR_MCP_URL", "CAMPAIGN_OPERATOR_TOKEN"]
    return [name for name in required if not os.environ.get(name)]


def missing_source_settings(sources: list[str]) -> list[str]:
    requirements = {
        "google_places": ("GOOGLE_PLACES_API_KEY",),
        "foursquare": ("FOURSQUARE_PLACES_API_KEY",),
        "osm": ("MAPBOX_ACCESS_TOKEN", "OSM_OVERPASS_URL"),
    }
    labels = {
        "google_places": "Google Places key",
        "foursquare": "Foursquare key",
        "osm": "Mapbox token and OSM Overpass URL",
    }
    return [labels[source] for source in sources if any(not os.environ.get(key) for key in requirements[source])]


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
        available_height = max(1, self.winfo_screenheight() - 80)
        self.minsize(480, min(480, available_height))
        self.geometry(f"840x{min(760, available_height)}")
        self.query = tk.StringVar()
        self.allow_save = tk.BooleanVar(value=False)
        self.endpoint = tk.StringVar(value=os.environ.get("CAMPAIGN_OPERATOR_MCP_URL", "http://127.0.0.1/conversions/mcp"))
        self.token = tk.StringVar(value=os.environ.get("CAMPAIGN_OPERATOR_TOKEN", ""))
        self.places_key = tk.StringVar(value=os.environ.get("GOOGLE_PLACES_API_KEY", ""))
        self.foursquare_key = tk.StringVar(value=os.environ.get("FOURSQUARE_PLACES_API_KEY", ""))
        self.mapbox_key = tk.StringVar(value=os.environ.get("MAPBOX_ACCESS_TOKEN", ""))
        self.overpass_url = tk.StringVar(value=os.environ.get("OSM_OVERPASS_URL", ""))
        self.use_google = tk.BooleanVar(value="google_places" in os.environ.get("CAMPAIGN_OPERATOR_SOURCES", "google_places").split(","))
        self.use_foursquare = tk.BooleanVar(value="foursquare" in os.environ.get("CAMPAIGN_OPERATOR_SOURCES", "").split(","))
        self.use_osm = tk.BooleanVar(value="osm" in os.environ.get("CAMPAIGN_OPERATOR_SOURCES", "").split(","))
        self.connection_status = tk.StringVar()
        self.campaign_selection = tk.StringVar()
        self.campaign_ids: dict[str, int] = {}
        self.campaign_context: dict[str, dict[str, object]] = {}
        self.profile_source_text = ""
        self._dashboard_width = 0
        self._scrollregion_update_pending = False
        self._build()

    def _build(self) -> None:
        viewport = tk.Frame(self)
        viewport.pack(fill="both", expand=True)
        canvas = tk.Canvas(viewport, highlightthickness=0)
        scrollbar = tk.Scrollbar(viewport, orient="vertical", command=canvas.yview)
        canvas.configure(yscrollcommand=scrollbar.set)
        scrollbar.pack(side="right", fill="y")
        canvas.pack(side="left", fill="both", expand=True)
        frame = tk.Frame(canvas, padx=20, pady=18)
        frame_window = canvas.create_window((0, 0), window=frame, anchor="nw")
        frame.bind("<Configure>", lambda _event: self._schedule_scrollregion_update(canvas))
        canvas.bind("<Configure>", lambda event: self._resize_dashboard_content(canvas, frame_window, event.width))
        canvas.bind_all("<MouseWheel>", lambda event: self._scroll_dashboard(canvas, event))
        tk.Label(frame, text="Find companies worth talking to", font=("Segoe UI", 20, "bold")).pack(anchor="w")
        tk.Label(frame, text="Start at step 1 if this is your first time. The dashboard will not create prospects or contact anyone without your confirmation.", wraplength=760, justify="left").pack(anchor="w", pady=(3, 14))

        setup_ready = not required_environment()
        self.connection_step(frame, setup_ready)
        self.campaign_step(frame, setup_ready)
        self.step(frame, "3", "Build your ideal customer profile", "Describe your company or import a PDF, Word, or text file. The agent will turn it into an editable profile draft.", "Build profile draft", self.open_profile_assistant)
        self.step(frame, "4", "Get search ideas", "The dashboard turns your profile into suggested company searches and puts the first one below for you to edit.", "Suggest company searches", lambda: self.run("plan"))
        self.suggestions = tk.LabelFrame(frame, text="Suggested company searches", padx=12, pady=9, font=("Segoe UI", 10, "bold"))
        self.suggestions.pack(fill="x", pady=(0, 10))
        self.suggestion_message = tk.StringVar(value="Click “Suggest company searches” in step 4. Your choices will appear here.")
        tk.Label(self.suggestions, textvariable=self.suggestion_message, wraplength=740, justify="left").pack(anchor="w")

        finder = tk.LabelFrame(frame, text="5. Search for potential companies", padx=12, pady=10, font=("Segoe UI", 10, "bold"))
        finder.pack(fill="x", pady=(10, 8))
        tk.Label(finder, text="Search phrase", font=("Segoe UI", 10, "bold")).grid(row=0, column=0, sticky="w")
        tk.Label(finder, text="Use a suggestion above or write your own. You can change it before searching.").grid(row=1, column=0, columnspan=3, sticky="w", pady=(0, 4))
        self.search_entry = tk.Entry(finder, textvariable=self.query, width=76)
        self.search_entry.grid(row=2, column=0, columnspan=3, sticky="ew", pady=(0, 8))
        tk.Button(finder, text="Find companies (nothing saved)", command=lambda: self.run("find"), font=("Segoe UI", 10, "bold")).grid(row=3, column=0, sticky="w")
        finder.columnconfigure(0, weight=1)

        review = tk.LabelFrame(frame, text="6. After reviewing the results", padx=12, pady=10, font=("Segoe UI", 10, "bold"))
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

    def _resize_dashboard_content(self, canvas: tk.Canvas, frame_window: int, width: int) -> None:
        """Avoid a full canvas relayout for repeated configure events while the window moves."""
        width = max(1, width)
        if width == self._dashboard_width:
            return
        self._dashboard_width = width
        canvas.itemconfigure(frame_window, width=width)
        self._schedule_scrollregion_update(canvas)

    def _schedule_scrollregion_update(self, canvas: tk.Canvas) -> None:
        if self._scrollregion_update_pending:
            return
        self._scrollregion_update_pending = True
        self.after_idle(lambda: self._refresh_scrollregion(canvas))

    def _refresh_scrollregion(self, canvas: tk.Canvas) -> None:
        self._scrollregion_update_pending = False
        bounds = canvas.bbox("all")
        if bounds is not None:
            canvas.configure(scrollregion=bounds)

    def _scroll_dashboard(self, canvas: tk.Canvas, event: tk.Event) -> None:
        if event.widget.winfo_toplevel() is not self:
            return
        if isinstance(event.widget, (tk.Entry, tk.Text, tk.Spinbox)):
            return
        if event.delta:
            canvas.yview_scroll(int(-event.delta / 120), "units")

    def show_suggested_searches(self, queries: list[str], explanation: str = "") -> None:
        for child in self.suggestions.winfo_children():
            child.destroy()
        if not queries:
            message = explanation or "Add at least one required or scored characteristic in the Ideal Customer Profile, then try again."
            tk.Label(self.suggestions, text="No search ideas yet. " + message, wraplength=740, justify="left").pack(anchor="w")
            self.suggestion_message.set("No search ideas yet.")
            return
        tk.Label(self.suggestions, text="Choose one to copy it into the search box. You can edit it before looking for companies.", wraplength=740, justify="left").pack(anchor="w", pady=(0, 6))
        for query in queries:
            row = tk.Frame(self.suggestions)
            row.pack(fill="x", pady=2)
            tk.Label(row, text=query, wraplength=570, justify="left").pack(side="left", fill="x", expand=True)
            tk.Button(row, text="Use this search", command=lambda value=query: self.use_suggested_search(value)).pack(side="right", padx=(10, 0))
        self.suggestion_message.set(f"{len(queries)} suggested searches ready.")

    def use_suggested_search(self, query: str) -> None:
        self.query.set(query)
        self.search_entry.focus_set()
        self.append("Search selected. Review or edit it in step 4, then choose “Find companies (nothing saved)”.")

    def step(self, parent: tk.Widget, number: str, title: str, detail: str, button: str, command: object, status: str | None = None) -> None:
        row = tk.Frame(parent, padx=10, pady=8, highlightthickness=1, highlightbackground="#d7dce5")
        row.pack(fill="x", pady=(0, 7))
        tk.Label(row, text=number, font=("Segoe UI", 13, "bold"), width=3).grid(row=0, column=0, rowspan=2, sticky="n")
        title_text = title if status is None else f"{title} — {status}"
        tk.Label(row, text=title_text, font=("Segoe UI", 10, "bold")).grid(row=0, column=1, sticky="w")
        tk.Label(row, text=detail, wraplength=535, justify="left").grid(row=1, column=1, sticky="w")
        tk.Button(row, text=button, command=command).grid(row=0, column=2, rowspan=2, padx=(12, 0))
        row.columnconfigure(1, weight=1)

    def connection_step(self, parent: tk.Widget, setup_ready: bool) -> None:
        box = tk.LabelFrame(parent, text="1. Connect this dashboard", padx=12, pady=10, font=("Segoe UI", 10, "bold"))
        box.pack(fill="x", pady=(0, 7))
        self.connection_status.set("Connection ready — you can edit these settings here." if setup_ready else "Required once before the dashboard can use your CRM.")
        tk.Label(box, textvariable=self.connection_status, wraplength=740, justify="left").grid(row=0, column=0, columnspan=2, sticky="w", pady=(0, 7))
        tk.Label(box, text="CRM address", font=("Segoe UI", 9, "bold")).grid(row=1, column=0, sticky="w")
        tk.Entry(box, textvariable=self.endpoint, width=68).grid(row=1, column=1, sticky="ew", pady=2)
        tk.Label(box, text="Use the address ending in /mcp.").grid(row=2, column=1, sticky="w", pady=(0, 5))
        tk.Label(box, text="CRM token", font=("Segoe UI", 9, "bold")).grid(row=3, column=0, sticky="w")
        tk.Entry(box, textvariable=self.token, show="*", width=68).grid(row=3, column=1, sticky="ew", pady=2)
        tk.Label(box, text="Create this in CRM → Integrations. It stays only in this computer's ignored .env file.", wraplength=620, justify="left").grid(row=4, column=1, sticky="w", pady=(0, 5))
        tk.Label(box, text="Google Places key", font=("Segoe UI", 9, "bold")).grid(row=5, column=0, sticky="w")
        tk.Entry(box, textvariable=self.places_key, show="*", width=68).grid(row=5, column=1, sticky="ew", pady=2)
        tk.Label(box, text="Optional: needed only when Google Places is selected below. You can save the CRM connection without it.").grid(row=6, column=1, sticky="w", pady=(0, 7))
        tk.Label(box, text="Foursquare key", font=("Segoe UI", 9, "bold")).grid(row=7, column=0, sticky="w")
        tk.Entry(box, textvariable=self.foursquare_key, show="*", width=68).grid(row=7, column=1, sticky="ew", pady=2)
        tk.Label(box, text="Mapbox token", font=("Segoe UI", 9, "bold")).grid(row=8, column=0, sticky="w")
        tk.Entry(box, textvariable=self.mapbox_key, show="*", width=68).grid(row=8, column=1, sticky="ew", pady=2)
        tk.Label(box, text="Your own Mapbox Search token. It will be used only to locate OSM search areas; it is never copied from another project.", wraplength=620, justify="left").grid(row=9, column=1, sticky="w", pady=(0, 4))
        tk.Label(box, text="OSM Overpass URL", font=("Segoe UI", 9, "bold")).grid(row=10, column=0, sticky="w")
        tk.Entry(box, textvariable=self.overpass_url, width=68).grid(row=10, column=1, sticky="ew", pady=2)
        tk.Label(box, text="Optional: your managed or self-hosted OpenStreetMap query endpoint. Required only when OpenStreetMap is selected.", wraplength=620, justify="left").grid(row=11, column=1, sticky="w", pady=(0, 4))
        sources = tk.Frame(box); sources.grid(row=12, column=1, sticky="w", pady=4)
        tk.Checkbutton(sources, text="Google Places", variable=self.use_google).pack(side="left")
        tk.Checkbutton(sources, text="Foursquare", variable=self.use_foursquare).pack(side="left", padx=10)
        tk.Checkbutton(sources, text="OpenStreetMap", variable=self.use_osm).pack(side="left", padx=10)
        tk.Button(box, text="Save connection", command=self.save_connection, font=("Segoe UI", 9, "bold")).grid(row=13, column=1, sticky="w")
        box.columnconfigure(1, weight=1)

    def campaign_step(self, parent: tk.Widget, setup_ready: bool) -> None:
        box = tk.LabelFrame(parent, text="2. Choose the campaign to work on", padx=12, pady=10, font=("Segoe UI", 10, "bold"))
        box.pack(fill="x", pady=(0, 7))
        tk.Label(box, text="Load the campaigns from your CRM, then choose one. Its existing prospects and targets become the agent’s working context; this does not change the CRM’s active campaign.", wraplength=740, justify="left").grid(row=0, column=0, columnspan=2, sticky="w", pady=(0, 7))
        self.campaign_box = ttk.Combobox(box, textvariable=self.campaign_selection, state="readonly", width=62)
        self.campaign_box.grid(row=1, column=0, sticky="ew", padx=(0, 8))
        self.campaign_box.bind("<<ComboboxSelected>>", lambda _event: self.select_campaign())
        tk.Button(box, text="Load campaigns", command=lambda: self.run("campaigns"), state="normal" if setup_ready else "disabled").grid(row=1, column=1, sticky="e")
        self.campaign_detail = tk.StringVar(value="Connect the dashboard first, then load your campaigns." if not setup_ready else "Click “Load campaigns” to choose one.")
        tk.Label(box, textvariable=self.campaign_detail, wraplength=740, justify="left").grid(row=2, column=0, columnspan=2, sticky="w", pady=(6, 0))
        box.columnconfigure(0, weight=1)

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

    def save_connection(self) -> None:
        endpoint = self.endpoint.get().strip().rstrip("/")
        token = self.token.get().strip()
        places_key = self.places_key.get().strip()
        foursquare_key = self.foursquare_key.get().strip()
        mapbox_key = self.mapbox_key.get().strip()
        overpass_url = self.overpass_url.get().strip()
        sources = [name for name, enabled in (("google_places", self.use_google.get()), ("foursquare", self.use_foursquare.get()), ("osm", self.use_osm.get())) if enabled]
        if not endpoint.endswith("/mcp"):
            messagebox.showerror("Campaign Operator", "The CRM address must end with /mcp.")
            return
        if not token:
            messagebox.showerror("Campaign Operator", "Enter the CRM token from CRM → Integrations.")
            return
        try:
            save_local_environment({"CAMPAIGN_OPERATOR_MCP_URL": endpoint, "CAMPAIGN_OPERATOR_TOKEN": token, "GOOGLE_PLACES_API_KEY": places_key, "FOURSQUARE_PLACES_API_KEY": foursquare_key, "MAPBOX_ACCESS_TOKEN": mapbox_key, "OSM_OVERPASS_URL": overpass_url, "CAMPAIGN_OPERATOR_SOURCES": ",".join(sources)})
        except (OSError, ValueError) as error:
            messagebox.showerror("Campaign Operator", f"Could not save the connection: {error}")
            return
        os.environ.update({"CAMPAIGN_OPERATOR_MCP_URL": endpoint, "CAMPAIGN_OPERATOR_TOKEN": token, "GOOGLE_PLACES_API_KEY": places_key, "FOURSQUARE_PLACES_API_KEY": foursquare_key, "MAPBOX_ACCESS_TOKEN": mapbox_key, "OSM_OVERPASS_URL": overpass_url, "CAMPAIGN_OPERATOR_SOURCES": ",".join(sources)})
        self.connection_status.set("Connection saved on this computer. You can continue to step 2.")
        self.run("campaigns")
        messagebox.showinfo("Campaign Operator", "Connection saved. Your campaigns are loading now; choose one in step 2.")

    def selected_campaign_id(self) -> int | None:
        return self.campaign_ids.get(self.campaign_selection.get())

    def show_campaigns(self, campaigns: list[dict[str, object]]) -> None:
        self.campaign_ids = {}
        self.campaign_context = {}
        labels: list[str] = []
        active_label = ""
        for campaign in campaigns:
            if not campaign.get("available"):
                continue
            campaign_id = int(campaign["id"])
            label = f"{campaign['name']} ({campaign['start_date']} to {campaign['end_date']})" + (" — active" if campaign.get("is_active") else "")
            self.campaign_ids[label] = campaign_id
            self.campaign_context[label] = campaign
            labels.append(label)
            if campaign.get("is_active"):
                active_label = label
        self.campaign_box["values"] = labels
        if labels:
            saved_id = int(os.environ.get("CAMPAIGN_OPERATOR_CAMPAIGN_ID", "0") or 0)
            saved_label = next((label for label, campaign_id in self.campaign_ids.items() if campaign_id == saved_id), "")
            self.campaign_selection.set(saved_label or active_label or labels[0])
            self.select_campaign(save=False)
        else:
            self.campaign_selection.set("")
            self.campaign_detail.set("No campaigns were found. Create one in the CRM, then load campaigns again.")

    def select_campaign(self, save: bool = True) -> None:
        label = self.campaign_selection.get()
        campaign = self.campaign_context.get(label)
        campaign_id = self.campaign_ids.get(label)
        if campaign is None or campaign_id is None:
            return
        targets = campaign.get("targets", {})
        target_text = ""
        if isinstance(targets, dict) and targets:
            summary = ", ".join(f"{value} {str(key).replace('_', ' ')}" for key, value in list(targets.items())[:3])
            target_text = " Targets include " + summary + "."
        self.campaign_detail.set("Selected: " + label + ". The agent will use this campaign’s prospects and targets without changing the CRM’s active campaign." + target_text)
        if save:
            try:
                save_local_environment({"CAMPAIGN_OPERATOR_CAMPAIGN_ID": str(campaign_id)})
                os.environ["CAMPAIGN_OPERATOR_CAMPAIGN_ID"] = str(campaign_id)
            except OSError:
                self.append("Campaign selected for this session, but the dashboard could not remember it for next time.")

    def open_ideal_customer_profile(self) -> None:
        url = ideal_customer_profile_url()
        if url is None:
            messagebox.showwarning("Campaign Operator", "Set the CRM address in .env first, then reopen this dashboard.")
            return
        webbrowser.open(url)

    def open_profile_assistant(self) -> None:
        window = tk.Toplevel(self)
        window.title("Build ideal customer profile")
        window.geometry("760x600")
        tk.Label(window, text="Describe what your company does and who it helps", font=("Segoe UI", 13, "bold")).pack(anchor="w", padx=16, pady=(16, 4))
        tk.Label(window, text="Paste text below or import a PDF, Word, or text file. The source stays on this computer until you explicitly create a draft.", wraplength=710, justify="left").pack(anchor="w", padx=16)
        source = scrolledtext.ScrolledText(window, height=16, wrap="word")
        source.pack(fill="both", expand=True, padx=16, pady=12)
        source.insert("1.0", self.profile_source_text)
        status = tk.StringVar(value="")
        tk.Label(window, textvariable=status, wraplength=710, justify="left").pack(anchor="w", padx=16)
        actions = tk.Frame(window); actions.pack(fill="x", padx=16, pady=12)
        def choose_file() -> None:
            filename = filedialog.askopenfilename(parent=window, filetypes=[("Supported files", "*.txt *.pdf *.doc *.docx"), ("All files", "*.*")])
            if not filename:
                return
            try:
                text = extract_text(Path(filename))
            except Exception as error:
                messagebox.showerror("Could not read file", str(error), parent=window)
                return
            source.delete("1.0", "end"); source.insert("1.0", text); status.set(f"Imported {Path(filename).name}. Review or edit the text before drafting.")
        def draft() -> None:
            self.profile_source_text = source.get("1.0", "end").strip()
            try:
                prompt = profile_prompt(self.profile_source_text)
            except ValueError as error:
                messagebox.showwarning("Need a description", str(error), parent=window); return
            self.clipboard_clear(); self.clipboard_append(prompt)
            status.set("The structured drafting request is copied. Open the Campaign Operator ChatGPT session, paste it, then review the returned JSON before applying it.")
        tk.Button(actions, text="Import file", command=choose_file).pack(side="left")
        tk.Button(actions, text="Create ChatGPT draft", command=draft, font=("Segoe UI", 10, "bold")).pack(side="left", padx=8)
        tk.Button(actions, text="Open ChatGPT agent", command=self.open_codex).pack(side="left")

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
        sources = [name for name, enabled in (("google_places", self.use_google.get()), ("foursquare", self.use_foursquare.get()), ("osm", self.use_osm.get())) if enabled]
        if action == "find":
            if not sources:
                messagebox.showerror("Campaign Operator", "Select at least one discovery source before searching.")
                return
            missing_settings = missing_source_settings(sources)
            if missing_settings:
                messagebox.showerror("Campaign Operator", "Add the required setting for every selected source before searching:\n" + "\n".join(missing_settings))
                return
        try:
            command = command_for(action, self.query.get(), save, self.selected_campaign_id(), sources)
        except ValueError as error:
            messagebox.showerror("Campaign Operator", str(error))
            return
        self.append("> " + " ".join(command[:3]) + (" [lead query supplied]" if action == "find" else ""))
        threading.Thread(target=self._run_process, args=(command, action), daemon=True).start()

    def _run_process(self, command: list[str], action: str) -> None:
        try:
            result = subprocess.run(command, cwd=ROOT, env=os.environ.copy(), text=True, capture_output=True, timeout=120, shell=False, creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0))
            output = (result.stdout + result.stderr).strip() or "Command completed without output."
            if action == "campaigns" and result.returncode == 0:
                try:
                    campaigns = json.loads(result.stdout).get("items", [])
                    if isinstance(campaigns, list):
                        self.after(0, self.show_campaigns, campaigns)
                except (json.JSONDecodeError, AttributeError):
                    output += "\n\nCould not read the campaign list. Check the connection and try again."
            if action == "plan":
                suggestions = re.findall(r"^\d+\. (.+)$", result.stdout, re.MULTILINE)
                plan_lines = [line.strip() for line in result.stdout.splitlines() if line.strip() and line.strip() != "SUGGESTED COMPANY SEARCHES"]
                explanation = " ".join(line for line in plan_lines if not re.match(r"^\d+\. ", line))
                self.after(0, self.show_suggested_searches, suggestions, explanation)
                if suggestions:
                    self.after(0, self.query.set, suggestions[0])
                    output += "\n\nSuggested searches are now shown above step 4. The first is in the search box."
            self.after(0, self.append, output)
        except subprocess.TimeoutExpired:
            self.after(0, self.append, "Command stopped after the 120-second safety timeout.")
        except OSError as error:
            self.after(0, self.append, f"Could not start command: {error}")


if __name__ == "__main__":
    CampaignControlPanel().mainloop()
