import importlib.util
import os
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


if __name__ == "__main__":
    unittest.main()
