"""Build a contact-free dashboard dataset from explicit experiment summaries."""

import json
import math
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

# Counts are server totals, not the number entered in the simulator UI.
RUNS = [
    ("original", "Исходный лендинг", "Исходный", "85cd5a4", "2026-10-20", 503, 15, 4900, "4 обязательных", "Исходная страница до экспериментов. Счётчики из лидерборда; клиентской телеметрии нет.", None),
    ("compact", "Компактная форма", "Форма", "75b1615", "2026-10-22", 201, 19, 4900, "4 обязательных", "Исходные тексты; меньше отступы и высота формы на десктопе.", None),
    ("clarity", "Ясные условия", "Условия", "91d7aca", "2026-10-22", 201, 9, 4900, "4 обязательных", "Высокая форма, актуальная дата и пояснение заявки без оплаты.", None),
    ("bundle", "CTA и компактная форма", "CTA", "0fdeb4c", "2026-10-22", 202, 27, 4900, "4 обязательных", "Кнопка в первом экране, компактная форма, выбранный тариф и подсказка цели.", "behavior-bundle-2026-10-22.json"),
    ("content", "Короткая программа", "Программа", "07ba6c5", "2026-10-22", 201, 56, 4900, "4 обязательных", "Короткая программа, состав тарифа у формы, снятие ложной кликабельности карточек.", "behavior-content-2026-10-22.json"),
    ("position", "Форма под первым экраном", "Позиция", "868fd58", "2026-10-22", 202, 57, 4900, "4 обязательных", "Перенос секции регистрации под первый экран.", "behavior-form-position-2026-10-22.json"),
    ("optional", "Два поля необязательны", "4 поля*", "b808758", "2026-10-22", 201, 51, 4900, "4 видимых / 2 обязательных", "Email и цель курса видимы, но необязательны.", "behavior-optional-fields-2026-10-22.json"),
    ("combined", "Форма из двух полей", "2 поля", "7526ce2", "2026-10-22", 201, 52, 4900, "2 обязательных", "Имя и телефон, уточнение предложения, раскрываемое сравнение тарифов.", "behavior-combined-full-2026-10-22.json"),
    ("visual", "Новое оформление", "Дизайн", "bf593c4", "2026-10-22", 201, 47, 4900, "2 обязательных", "Новый первый экран, оформление, сокращённые тексты и SEO.", "behavior-visual-combined-full-2026-10-22.json"),
    ("restore", "Возврат текста программы", "Текст", "98a330d", "2026-10-22", 201, 36, 4900, "2 обязательных", "Программа из версии с 28,2% в новом оформлении.", "behavior-program-restore-full-2026-10-22.json"),
    ("repeat", "Повтор лучшей версии", "Контроль", "ccd6ce0", "2026-10-22", 201, 59, 4900, "4 обязательных", "Точный повтор конфигурации 868fd58: прежние тексты, оформление и четыре поля.", "behavior-best-repeat-full-2026-10-22.json"),
    ("price", "Цена 3 900 ₽", "Цена", "3858c95", "2026-10-23", 501, 137, 3900, "4 обязательных", "Снижение базовой цены с 4 900 до 3 900 ₽ и выделение базовой карточки вместо расширенной.", "behavior-price-highlight-full-2026-10-23.json"),
]


def wilson(success, total):
    if total <= 0 or not 0 <= success <= total:
        raise ValueError("Invalid conversion counts")
    z = 1.95996398454
    p = success / total
    d = 1 + z * z / total
    center = (p + z * z / (2 * total)) / d
    half = z * math.sqrt(p * (1 - p) / total + z * z / (4 * total * total)) / d
    return [max(0, center - half), min(1, center + half)]


def fisher(a, n1, b, n2):
    k = a + b
    def logcomb(n, r):
        return math.lgamma(n + 1) - math.lgamma(r + 1) - math.lgamma(n - r + 1)
    def probability(x):
        return logcomb(n1, x) + logcomb(n2, k - x) - logcomb(n1 + n2, k)
    threshold = probability(a)
    values = [probability(x) for x in range(max(0, k - n2), min(n1, k) + 1)]
    return min(1, sum(math.exp(v) for v in values if v <= threshold + 1e-9))


def telemetry(source):
    if "current" in source:
        current = source["current"]
        events = current["events"]
        cta = events["cta_click"]
        started = cta - current["cta_without_input"]
        return {
            "views": events["page_view"], "cta": cta,
            "field": current.get("cta_and_first_field", cta),
            "starts": started, "success": events["form_success"],
            "errors": events.get("form_error", 0),
            "invalid": events.get("form_invalid", 0),
            "median_input": current.get("median_seconds", {}).get("input_to_success"),
            "viewports": current.get("viewports", {}),
        }
    events = {e["event_name"]: e["sessions"] for e in source["events"]}
    cohorts = source["cohorts"]
    return {
        "views": cohorts["page_view"], "cta": cohorts["page_and_cta"],
        "field": cohorts["cta_and_first_field"], "starts": cohorts["cta_and_form_start"],
        "success": cohorts["started_and_succeeded"], "errors": events.get("form_error", 0),
        "invalid": events.get("form_invalid", 0), "median_input": None,
        "viewports": {d["detail"]: d["sessions"] for d in source.get("details", []) if d["event_name"] == "page_view"},
    }


def build_data():
    runs = []
    for ident, label, short, commit, date, views, orders, price, fields, change, source in RUNS:
        client = None
        if source:
            payload = json.loads((ROOT / "reports" / source).read_text())
            if "server" in payload:
                actual = payload["server"]
                if (actual["visits_count"], actual["orders_count"]) != (views, orders):
                    raise ValueError(f"Server counts differ from catalog: {ident}")
            client = telemetry(payload)
        # These early runs predate the unified summary format; counts are from
        # reports/compact-form-2026-10-22/hypotheses.md and clarity-2026-10-22.md.
        if ident in ("compact", "clarity"):
            cta, starts, success = (180, 19, 19) if ident == "compact" else (175, 10, 9)
            client = dict(views=201, cta=cta, field=cta, starts=starts, success=success,
                          errors=0, invalid=0, median_input=None, viewports={"desktop": 201})
        notes = []
        if client is None:
            notes.append("Нет клиентской телеметрии. Доступен только исторический серверный результат.")
        else:
            if client["views"] != views:
                notes.append(f"Сервер: {views} визитов; трекер: {client['views']}. Причина расхождения не установлена.")
            if client["success"] != orders:
                notes.append(f"Сервер: {orders} заявок; трекер: {client['success']}. Общую конверсию считаем по серверу.")
            if not (0 <= client["success"] <= client["starts"] <= client["field"] <= client["cta"] <= client["views"]):
                raise ValueError(f"Non-nested funnel: {ident}")
        runs.append(dict(id=ident, label=label, short=short, commit=commit, date=date,
                         visits=views, orders=orders, price=price, fields=fields, change=change,
                         rate=orders / views, interval=wilson(orders, views), client=client,
                         notes=notes, source=source or "Архивный отчёт"))
    comparisons = {}
    for current in runs:
        for control in runs:
            if current["id"] == control["id"]:
                continue
            delta = current["rate"] - control["rate"]
            low = delta - math.hypot(current["rate"] - current["interval"][0], control["interval"][1] - control["rate"])
            high = delta + math.hypot(current["interval"][1] - current["rate"], control["rate"] - control["interval"][0])
            comparisons[f"{current['id']}:{control['id']}"] = dict(
                delta=delta, interval=[low, high],
                p=fisher(current["orders"], current["visits"], control["orders"], control["visits"]))
    return {"generated_at": datetime.now(timezone.utc).isoformat(),
            "default_run": "price", "default_control": "repeat", "runs": runs, "comparisons": comparisons}


if __name__ == "__main__":
    print(json.dumps(build_data(), ensure_ascii=False, indent=2))
