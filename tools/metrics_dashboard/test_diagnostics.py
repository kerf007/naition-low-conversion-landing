import copy
import unittest
from diagnostics import summarize


def event(seq, name, detail='', state=None, flow=1):
    return dict(seq=seq, flowId=flow, event=name, detail=detail, tMs=seq*100, state=state or {})


class ClassificationTests(unittest.TestCase):
    def summarize(self, events):
        return summarize({'visits':[{'events':events,'trackerVersion':'preinput-v1'}]})['counts']

    def test_missing_visibility_is_unknown_not_refusal(self):
        counts=self.summarize([event(1,'cta'),event(2,'exit')])
        self.assertEqual(counts['unknown'],1)
        self.assertNotIn('available_without_attempt',counts)

    def test_gap_overrides_negative_classification(self):
        counts=self.summarize([event(1,'cta'),event(3,'focus','name'),event(4,'exit')])
        self.assertEqual(counts['unknown'],1)

    def test_focus_does_not_require_pointer(self):
        counts=self.summarize([event(1,'cta'),event(2,'focus','name'),event(3,'exit')])
        self.assertEqual(counts['focus_without_input'],1)
        self.assertEqual(counts['pointer_attempt'],0)

    def test_retries_are_separate_attempts(self):
        counts=self.summarize([event(1,'cta'),event(2,'focus','name'),event(3,'cta',flow=2),event(4,'success',flow=2),event(5,'exit',flow=2)])
        self.assertEqual(counts['attempts'],2)
        self.assertEqual(counts['focus_without_input'],1)
        self.assertEqual(counts['success_branch'],1)

    def test_snapshot_obstacle_and_accessibility(self):
        node=dict(key='name',visible_permille=1000,enabled=True,readonly=False,focusable=True,pointer_events=True,samples=5,hits=5)
        state=dict(tab_visible=True,nodes=[node])
        events=[event(1,'cta'),event(2,'geometry','exit',state),event(3,'exit')]
        self.assertEqual(self.summarize(events)['available_without_attempt'],1)
        covered=copy.deepcopy(events);covered[1]['state']['nodes'][0]['hits']=0
        self.assertEqual(self.summarize(covered)['snapshot_obstacle'],1)
        hidden=copy.deepcopy(events);hidden[1]['state']['tab_visible']=False
        self.assertEqual(self.summarize(hidden)['hidden_without_attempt'],1)

    def test_capture_limit_is_not_interpreted_as_no_input(self):
        counts=self.summarize([event(1,'cta'),event(2,'focus','name'),event(3,'capture_limited','events'),event(4,'exit')])
        self.assertEqual(counts['unknown'],1)


if __name__=='__main__':
    unittest.main()
