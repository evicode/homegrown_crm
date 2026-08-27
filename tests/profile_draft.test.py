import importlib.util
import json
import unittest
from pathlib import Path


MODULE_PATH = Path(__file__).resolve().parents[1] / "agents" / "campaign-operator" / "profile_draft.py"
SPEC = importlib.util.spec_from_file_location("profile_draft", MODULE_PATH)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
SPEC.loader.exec_module(MODULE)


class ProfileDraftTests(unittest.TestCase):
    def test_prompt_is_bounded_and_requires_source_text(self):
        with self.assertRaises(ValueError):
            MODULE.profile_prompt("  ")
        prompt = MODULE.profile_prompt("x" * 50000)
        self.assertLessEqual(len(prompt), 41000)

    def test_valid_draft_has_expected_profile_shapes(self):
        draft = {
            "description": "Custom software for manufacturers",
            "required_any": ["manufacturing"],
            "positive_keywords": {"legacy systems": 8},
            "negative_keywords": ["consumer retail"],
            "preferred_locations": ["Oregon"],
            "minimum_score": 3,
            "strong_fit_score": 8,
        }
        self.assertEqual(MODULE.validate_draft(json.dumps(draft)), draft)

    def test_invalid_weight_is_rejected_before_apply(self):
        with self.assertRaises(ValueError):
            MODULE.validate_draft('{"positive_keywords":{"custom software":21}}')


if __name__ == "__main__":
    unittest.main()
