/* Passive, bounded diagnostics. Never reads field values or changes focus/scroll. */
window.createFormDiagnostics = (sessionId) => {
    if (!sessionId) return null;
    const form = document.getElementById('registration-form');
    if (!form) return null;
    const started = performance.now();
    const fields = ['name', 'phone', 'email', 'purpose'];
    const nodes = new Map([['form', form], ['summary', document.getElementById('registration-summary')],
        ['submit', form.querySelector('[type="submit"]')]]);
    fields.forEach((key) => {
        const field = form.elements.namedItem(key);
        if (field) { nodes.set(key, field); nodes.set(`label_${key}`, field.closest('label')); }
    });
    const exposureKeys = ['form', 'summary', 'name', 'phone', 'email', 'purpose', 'submit'];
    const visibleSince = new Map();
    let seq = 0, flow = 0, eventCount = 0, geometryCount = 0;
    let inputStarted = false, leftForm = false, arrived = false, ended = false, limited = false;
    let scrollTimer, exposureTimer, flushTimer;
    let queue = [];
    const now = () => Math.min(3600000, Math.round(performance.now() - started));
    const integer = (value) => Math.max(-200000, Math.min(200000, Math.round(value || 0)));
    const fieldKey = (target) => fields.includes(target?.name) && form.contains(target) ? target.name : '';
    const safely = (fn) => (...args) => { try { return fn(...args); } catch (_) { return undefined; } };

    const state = () => ({
        scroll_y: Math.max(0, integer(scrollY)), viewport_w: integer(innerWidth), viewport_h: integer(innerHeight),
        visual_w: integer(window.visualViewport?.width ?? innerWidth),
        visual_h: integer(window.visualViewport?.height ?? innerHeight),
        visual_x: integer(window.visualViewport?.offsetLeft ?? 0),
        visual_y: integer(window.visualViewport?.offsetTop ?? 0),
        scale_milli: Math.round((window.visualViewport?.scale ?? 1) * 1000),
        tab_visible: document.visibilityState === 'visible',
        active_field: fieldKey(document.activeElement) || 'other', input_started: inputStarted,
    });

    const measure = (key, element, hitTest = false) => {
        const r = element.getBoundingClientRect(), style = getComputedStyle(element);
        const vv = window.visualViewport;
        const left = Math.max(r.left, vv?.offsetLeft ?? 0), top = Math.max(r.top, vv?.offsetTop ?? 0);
        const right = Math.min(r.right, (vv?.offsetLeft ?? 0) + (vv?.width ?? innerWidth));
        const bottom = Math.min(r.bottom, (vv?.offsetTop ?? 0) + (vv?.height ?? innerHeight));
        const styled = typeof element.checkVisibility === 'function'
            ? element.checkVisibility({checkOpacity:true, checkVisibilityCSS:true})
            : style.display !== 'none' && style.visibility === 'visible' && Number(style.opacity) > 0;
        const area = Math.max(0, right - left) * Math.max(0, bottom - top);
        const ratio = document.visibilityState === 'visible' && styled && r.width * r.height > 0
            ? Math.round(area / (r.width * r.height) * 1000) : 0;
        let hits = 0, samples = 0;
        if (hitTest && ratio > 0) {
            [[.5,.5],[.2,.2],[.8,.2],[.2,.8],[.8,.8]].forEach(([x,y]) => {
                const topElement = document.elementFromPoint(left + (right-left)*x, top + (bottom-top)*y);
                samples++;
                if (topElement && (topElement === element || element.contains(topElement))) hits++;
            });
        }
        return {key, x: integer(r.x), y: integer(r.y), w: integer(r.width), h: integer(r.height),
            visible_permille: ratio, hits, samples, styled_visible: styled,
            enabled: !element.disabled, readonly: Boolean(element.readOnly),
            focusable: element.tabIndex >= 0 && !element.disabled && styled && r.width > 0 && r.height > 0,
            pointer_events: style.pointerEvents !== 'none'};
    };

    const flush = safely(() => {
        clearTimeout(flushTimer);
        flushTimer = null;
        while (queue.length) {
            const events = queue.splice(0, 8);
            const body = new Blob([JSON.stringify({session_id: sessionId, tracker_version: 'preinput-v1',
                ui_version: '3858c95', experiment_id: 'price3900-diagnostics', events})], {type:'application/json'});
            // Batches stay below the browser's keepalive quota. No unbounded retries.
            if (!navigator.sendBeacon?.('diagnostics.php', body)) {
                fetch('diagnostics.php', {method:'POST', body, keepalive:true}).catch(() => {});
            }
        }
    });
    const emit = (event, detail = '', extra = {}) => {
        if (ended || eventCount >= 240) return;
        if (eventCount >= 238 && !['exit','capture_limited'].includes(event)) {
            if (!limited) { limited = true; emit('capture_limited', 'events'); }
            return;
        }
        eventCount++;
        queue.push({seq: ++seq, flow_id: flow, event, detail, elapsed_ms: now(), state: {...state(), ...extra}});
        if (queue.length >= 8) flush();
        else if (!flushTimer) flushTimer = setTimeout(() => { flushTimer = null; flush(); }, 1200);
    };
    const geometry = (reason, force = false) => {
        if (geometryCount >= 16 && !force) {
            if (geometryCount === 16) { geometryCount++; emit('capture_limited', 'geometry'); }
            return;
        }
        geometryCount++;
        emit('geometry', reason, {nodes: Array.from(nodes).filter(([,el]) => el).map(([key,el]) => measure(key,el,true))});
    };
    const closeExposures = () => {
        visibleSince.forEach((time,key) => emit('exposure_end', key, {visible_ms: now() - time}));
        visibleSince.clear();
    };
    const sampleExposures = safely(() => {
        exposureTimer = null;
        exposureKeys.forEach((key) => {
            const element = nodes.get(key);
            if (!element) return;
            // A tall form may never be 50% visible: use any visible area for the form,
            // and >=50% for individual controls/summary. These are geometric exposures.
            const ratio = measure(key,element).visible_permille;
            const visible = key === 'form' ? ratio > 0 : ratio >= 500;
            if (visible && !visibleSince.has(key)) { visibleSince.set(key,now()); emit('exposure_start',key); }
            if (!visible && visibleSince.has(key)) {
                emit('exposure_end',key,{visible_ms:now()-visibleSince.get(key)}); visibleSince.delete(key);
                if (key === 'form' && flow) leftForm = true;
            }
        });
    });
    const scheduleExposures = () => { if (!exposureTimer) exposureTimer = setTimeout(sampleExposures,100); };
    const settled = safely(() => {
        if (!flow || ended) return;
        sampleExposures();
        emit('scroll_settled');
        if (visibleSince.has('form')) {
            emit('form_arrival', arrived && leftForm ? 'return' : 'initial');
            arrived = true; leftForm = false;
            geometry('settled');
        }
    });
    const interaction = safely((event, detail) => {
        if (!detail) return;
        emit(event,detail);
        if (flow && ['field_pointerdown','field_region_pointerdown','label_pointerdown','focus'].includes(event)) geometry('interaction');
    });

    window.addEventListener('scroll', safely(() => {
        scheduleExposures(); clearTimeout(scrollTimer); scrollTimer = setTimeout(settled,400);
    }), {passive:true});
    window.addEventListener('resize', scheduleExposures, {passive:true});
    window.visualViewport?.addEventListener('resize', scheduleExposures, {passive:true});
    document.addEventListener('visibilitychange', safely(() => {
        emit('visibility', document.visibilityState === 'visible' ? 'visible' : 'hidden');
        if (document.visibilityState !== 'visible') { geometry('hidden'); closeExposures(); flush(); }
        else sampleExposures();
    }));
    form.addEventListener('pointerdown', safely((event) => {
        const key = fieldKey(event.target);
        if (key) interaction('field_pointerdown',key);
        else if (event.target instanceof Element) {
            const label = event.target.closest('label');
            interaction('label_pointerdown',fieldKey(label?.querySelector('input,textarea')));
        }
    }), {passive:true});
    document.addEventListener('pointerdown', safely((event) => {
        if (!flow || form.contains(event.target)) return;
        const key = fields.find((name) => {
            const r = nodes.get(name)?.getBoundingClientRect();
            return r && event.clientX >= r.left && event.clientX <= r.right
                && event.clientY >= r.top && event.clientY <= r.bottom;
        });
        // The pointer landed in a field's rectangle, but the actual target was outside
        // the form (for example, an overlay). This records position, not user intent.
        interaction('field_region_pointerdown',key);
    }), {passive:true, capture:true});
    form.addEventListener('focusin', safely(e => interaction('focus',fieldKey(e.target))));
    form.addEventListener('focusout', safely(e => interaction('blur',fieldKey(e.target))));
    const inputFields = new Set();
    form.addEventListener('input', safely((e) => {
        const key = fieldKey(e.target);
        if (!key || inputFields.has(`${flow}:${key}`)) return;
        inputFields.add(`${flow}:${key}`); inputStarted = true; emit('input',key);
    }));
    form.addEventListener('keydown', safely((e) => {
        if (e.key === 'Tab') emit('navigation_key', e.shiftKey ? 'shift_tab' : 'tab');
    }));
    let lastMovement = 0;
    ['wheel','touchmove'].forEach(type => window.addEventListener(type,safely(() => {
        if (flow && performance.now()-lastMovement > 1000) { lastMovement=performance.now(); emit('scroll_input',type); }
    }),{passive:true}));
    window.addEventListener('pagehide', safely(() => {
        if (ended) return;
        closeExposures(); geometry('exit',true); emit('exit'); ended=true; flush();
        clearTimeout(scrollTimer); clearTimeout(exposureTimer);
    }));
    emit('context'); sampleExposures();
    return {
        cta: safely((source) => {
            if (flow >= 12) { emit('capture_limited','flows'); return; }
            closeExposures(); flow++; arrived=false; leftForm=false;
            emit('cta',source); geometry('cta'); sampleExposures();
        }),
        scrollRequested: safely(() => { emit('scroll_requested'); clearTimeout(scrollTimer); scrollTimer=setTimeout(settled,400); }),
        success: safely(() => { emit('success'); flush(); }),
    };
};
