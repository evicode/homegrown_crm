import importlib.util
import os
import tempfile
import unittest
from pathlib import Path

MODULE_PATH = Path(__file__).resolve().parents[1] / "agents" / "campaign-operator" / "campaign_control_panel.py"
SPEC = importlib.util.spec_from_file_location("campaign_control_panel", MODULE_PATH)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
SPEC.loader.exec_module(MODULE)


class CampaignOperatorGuardrailTests(unittest.TestCase):
    def test_only_whitelisted_commands_are_built(self):
        self.assertEqual(MODULE.command_for("brief")[-1], "brief")
        self.assertEqual(MODULE.command_for("plan")[-1], "plan")
        self.assertIn("--save", MODULE.command_for("find", "software firms in Portland", True))
        with self.assertRaises(ValueError):
            MODULE.command_for("shell", "whoami")

    def test_queries_are_bounded_and_single_line(self):
        with self.assertRaises(ValueError):
            MODULE.safe_query("x")
        with self.assertRaises(ValueError):
            MODULE.safe_query("companies\n--save")
        self.assertEqual(MODULE.safe_query("  firms in Oregon  "), "firms in Oregon")

    def test_token_is_redacted_from_output(self):
        previous = os.environ.get("CAMPAIGN_OPERATOR_TOKEN")
        os.environ["CAMPAIGN_OPERATOR_TOKEN"] = "secret-token"
        self.assertEqual(MODULE.redact("Bearer secret-token"), "Bearer [redacted]")
        if previous is None:
            del os.environ["CAMPAIGN_OPERATOR_TOKEN"]
        else:
            os.environ["CAMPAIGN_OPERATOR_TOKEN"] = previous

    def test_local_environment_file_is_loaded_without_overwriting_existing_values(self):
        key = "CAMPAIGN_OPERATOR_TEST_SETTING"
        previous = os.environ.pop(key, None)
        try:
            with tempfile.NamedTemporaryFile(mode="w", encoding="utf-8", delete=False) as file:
                file.write("# comment\nCAMPAIGN_OPERATOR_TEST_SETTING=from-file\n")
                path = Path(file.name)
            MODULE.load_local_environment(path)
            self.assertEqual(os.environ[key], "from-file")
            os.environ[key] = "from-process"
            MODULE.load_local_environment(path)
            self.assertEqual(os.environ[key], "from-process")
        finally:
            if "path" in locals():
                path.unlink(missing_ok=True)
            if previous is None:
                os.environ.pop(key, None)
            else:
                os.environ[key] = previous

    def test_profile_url_comes_from_the_mcp_address(self):
        key = "CAMPAIGN_OPERATOR_MCP_URL"
        previous = os.environ.get(key)
        try:
            os.environ[key] = "https://crm.example.test/workspace/mcp"
            self.assertEqual(MODULE.ideal_customer_profile_url(), "https://crm.example.test/workspace/lead-finder/profile")
            os.environ[key] = "https://crm.example.test/not-an-mcp-endpoint"
            self.assertIsNone(MODULE.ideal_customer_profile_url())
        finally:
            if previous is None:
                os.environ.pop(key, None)
            else:
                os.environ[key] = previous


if __name__ == "__main__":
    unittest.main()
