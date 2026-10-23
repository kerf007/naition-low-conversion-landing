"""Classify observable CTA attempts; never infer motives from missing events."""
import json
import statistics
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def summarize(export):
    if not isinstance(export, dict) or not isinstance(export.get('visits'), list):
        raise ValueError('Expected a diagnostic visits export')
    counts = Counter()
    reasons = Counter()
    delays = {"return_to_focus": [], "return_to_exit": []}
    visits = export.get("visits", [])
    sequences_with_gaps = 0
    for visit in visits:
        events = sorted(visit["events"], key=lambda e: e["seq"])
        seq = [e["seq"] for e in events]
        gap = not seq or seq != list(range(1, max(seq) + 1))
        sequences_with_gaps += gap
        limited = any(e["event"] == "capture_limited" for e in events)
        for attempt in [e for e in events if e["event"] == "cta"]:
            flow = attempt["flowId"]
            rows = [e for e in events if e["flowId"] == flow and e["seq"] >= attempt["seq"]]
            types = {e["event"] for e in rows}
            ended = "exit" in types or any(e["event"] == "cta" and e["flowId"] > flow for e in events)
            counts["attempts"] += 1
            for name in ["scroll_requested", "form_arrival", "focus", "input", "success"]:
                counts[name] += name in types
            counts["pointer_attempt"] += bool(types & {"field_pointerdown", "field_region_pointerdown", "label_pointerdown"})
            counts["returned"] += any(e["event"] == "form_arrival" and e["detail"] == "return" for e in rows)
            arrivals = [e for e in rows if e["event"] == "form_arrival"]
            focus = next((e for e in rows if e["event"] == "focus"), None)
            end = next((e for e in rows if e["event"] == "exit"), None)
            cutoff = focus["seq"] if focus else (end["seq"] if end else float('inf'))
            arrivals = [e for e in arrivals if e["seq"] < cutoff]
            if arrivals and not gap:
                target = focus or end
                if target:
                    delays["return_to_focus" if focus else "return_to_exit"].append((target["tMs"]-arrivals[-1]["tMs"])/1000)
            if "success" in types:
                counts["success_branch"] += 1
            elif gap or limited or not ended:
                counts["unknown"] += 1
            elif "input" in types:
                counts["input_without_success"] += 1
            elif "focus" in types:
                counts["focus_without_input"] += 1
            elif types & {"field_pointerdown", "field_region_pointerdown", "label_pointerdown"}:
                counts["attempt_without_focus"] += 1
            else:
                snapshots = [e for e in rows if e["event"] == "geometry" and e["detail"] in ('settled','exit')]
                if not snapshots:
                    counts["unknown"] += 1
                    continue
                state = snapshots[-1]["state"]
                node = next((n for n in state["nodes"] if n["key"] == "name"), None)
                if not state["tab_visible"]:
                    counts["hidden_without_attempt"] += 1
                elif node is None:
                    counts["unknown"] += 1
                else:
                    blocked = []
                    if node['visible_permille'] < 500: blocked.append('outside_view')
                    if not node['enabled'] or node['readonly'] or not node['focusable']: blocked.append('not_editable')
                    if node['samples'] and node['hits'] < node['samples']: blocked.append('partly_covered')
                    if not node['pointer_events']: blocked.append('pointer_disabled')
                    if blocked:
                        counts["snapshot_obstacle"] += 1
                        reasons.update(blocked)
                    elif not node['samples']:
                        counts["unknown"] += 1
                    else:
                        counts["available_without_attempt"] += 1
    return {"visits":len(visits), "counts":dict(counts), "obstacles":dict(reasons),
            "sequence_gaps":sequences_with_gaps,
            "median_seconds":{k:round(statistics.median(v),3) if v else None for k,v in delays.items()},
            "versions":sorted({v.get('trackerVersion','unknown') for v in visits}),
            "ui_versions":sorted({v.get('uiVersion','unknown') for v in visits})}


def version_summaries(export, ident):
    # Old tabs can send delayed batches after a redeploy resets the database.
    groups = {}
    for visit in export['visits']:
        key = (visit.get('trackerVersion', 'unknown'),
               visit.get('uiVersion', 'unknown'), visit.get('experimentId', 'unknown'))
        groups.setdefault(key, []).append(visit)
    result = []
    for (tracker, ui, experiment), visits in sorted(groups.items()):
        label = ident if len(groups) == 1 else f'{ident} / {ui} / {experiment} / {tracker}'
        result.append({'id': label, 'captured_at': export.get('generated_at'),
                       'experiment_id': experiment, **summarize({'visits': visits})})
    return result


def saved_diagnostics():
    result = []
    root = ROOT / 'data'
    for folder in sorted(root.glob('visits-*')):
        path = folder / 'diagnostics.json'
        if folder.is_symlink() or path.is_symlink() or not path.is_file():
            continue
        try:
            export = json.loads(path.read_text())
            result.extend(version_summaries(export, folder.name))
        except (OSError, ValueError, KeyError, TypeError):
            result.append({"id":folder.name, "error":"Не удалось прочитать диагностический экспорт"})
    return {"exports":result}


if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('export', type=Path)
    args = parser.parse_args()
    print(json.dumps(summarize(json.loads(args.export.read_text())),ensure_ascii=False,indent=2))
