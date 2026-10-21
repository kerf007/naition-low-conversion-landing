document.addEventListener('DOMContentLoaded', () => {
    const registrationSection = document.getElementById('registration');
    const form = document.getElementById('registration-form');
    const message = document.getElementById('form-message');
    const registerButtons = document.querySelectorAll('.btn-register');
    const sessionId = window.crypto?.randomUUID?.() ?? null;
    const sentEvents = new Set();
    const startedAt = performance.now();
    let formStarted = false;
    let formSucceeded = false;
    let exitTracked = false;
    let maxScrollY = Math.max(0, Math.round(window.scrollY));
    let activeSection = '';

    const track = (event, detail = '') => {
        if (!sessionId) {
            return;
        }

        try {
            const scrollY = Math.max(0, Math.round(window.scrollY));
            maxScrollY = Math.max(maxScrollY, scrollY);
            const pageHeight = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight);
            const payload = new Blob([JSON.stringify({
                session_id: sessionId,
                event,
                detail,
                elapsed_ms: Math.max(0, Math.round(performance.now() - startedAt)),
                scroll_y: scrollY,
                max_scroll_y: maxScrollY,
                page_height: pageHeight,
                viewport_height: window.innerHeight,
            })], {
                type: 'application/json',
            });

            if (navigator.sendBeacon?.('behavior.php', payload)) {
                return;
            }

            fetch('behavior.php', {
                method: 'POST',
                body: payload,
                keepalive: true,
            }).catch(() => {});
        } catch (_) {
            // Analytics must not interrupt navigation or form submission.
        }
    };

    const trackOnce = (event, detail = '') => {
        const key = `${event}:${detail}`;
        if (sentEvents.has(key)) {
            return;
        }

        sentEvents.add(key);
        track(event, detail);
    };

    const viewport = window.innerWidth < 720 ? 'mobile' : window.innerWidth < 1024 ? 'tablet' : 'desktop';
    trackOnce('page_view', viewport);

    const setupVisibilityTracking = () => {
        if (!('IntersectionObserver' in window)) {
            return;
        }

        const observed = new Map();
        const pending = new Map();
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const timer = pending.get(entry.target);
                if (!entry.isIntersecting || entry.intersectionRatio < 0.5) {
                    clearTimeout(timer);
                    pending.delete(entry.target);
                    return;
                }

                if (timer) {
                    return;
                }

                pending.set(entry.target, setTimeout(() => {
                    const item = observed.get(entry.target);
                    if (!item) {
                        return;
                    }

                    const [event, detail] = item;
                    trackOnce(event, detail);
                    observer.unobserve(entry.target);
                    pending.delete(entry.target);
                }, 500));
            });
        }, { threshold: [0, 0.5] });

        const observe = (element, event, detail = '') => {
            if (!element) {
                return;
            }

            observed.set(element, [event, detail]);
            observer.observe(element);
        };

        observe(document.querySelector('.hero-register'), 'cta_view', 'hero');
        observe(document.querySelector('.program-list')?.closest('section')?.querySelector('.section-title'), 'program_view');
        observe(document.querySelector('.pricing-section .section-title'), 'pricing_view');
        observe(document.querySelector('#registration .section-title'), 'form_view');
        observe(document.querySelector('#registration-form input[name="name"]'), 'form_field_view');
        observe(document.querySelector('#registration-form button[type="submit"]'), 'form_submit_view');

        document.querySelectorAll('.pricing-card .btn-register').forEach((button, index) => {
            observe(button, 'cta_view', `pricing_${index + 1}`);
        });
    };

    try {
        setupVisibilityTracking();
    } catch (_) {
        // Missing visibility APIs must not affect the registration path.
    }

    const trackScrollDepth = () => {
        maxScrollY = Math.max(maxScrollY, Math.max(0, Math.round(window.scrollY)));
        const scrollableHeight = document.documentElement.scrollHeight - window.innerHeight;
        const percent = scrollableHeight > 0 ? (window.scrollY / scrollableHeight) * 100 : 100;
        [25, 50, 75].forEach((milestone) => {
            if (percent >= milestone) {
                trackOnce('scroll_depth', String(milestone));
            }
        });
        [10, 25, 50, 75, 90, 100].forEach((milestone) => {
            if (percent >= milestone) {
                trackOnce('scroll_checkpoint', String(milestone));
            }
        });

        const sections = document.querySelectorAll('header.hero, main > section');
        const marker = window.scrollY + window.innerHeight * 0.35;
        const sectionIndex = Array.from(sections).findIndex((section) =>
            section.offsetTop <= marker && marker < section.offsetTop + section.offsetHeight);
        const sectionId = sectionIndex < 0 ? '' : `s${sectionIndex + 1}`;
        if (sectionId && sectionId !== activeSection) {
            activeSection = sectionId;
            track('section_enter', sectionId);
        }
    };

    window.addEventListener('scroll', trackScrollDepth, { passive: true });
    window.addEventListener('resize', trackScrollDepth, { passive: true });
    trackScrollDepth();

    const contentSelectors = [
        '.meta-card', '.feature-card', '.program-module', '.injury-card',
        '.legal-block', '.instructor-card', '.pricing-card',
    ];
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || event.target.closest('a, button, input, textarea, select')) {
            return;
        }

        const selector = contentSelectors.find((item) => event.target.closest(item));
        if (selector) {
            track('content_click', selector);
        }
    });

    window.addEventListener('pagehide', () => {
        if (exitTracked) {
            return;
        }

        exitTracked = true;
        const seconds = (performance.now() - startedAt) / 1000;
        const duration = seconds < 10 ? 'under_10s' : seconds < 30 ? '10_to_30s' : seconds < 60 ? '30_to_60s' : 'over_60s';
        trackOnce('page_exit', duration);
        if (formStarted && !formSucceeded) {
            trackOnce('form_abandon');
        }
    });

    document.querySelector('.hero-secondary')?.addEventListener('click', () => {
        track('pricing_link_click');
    });

    registerButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const pricingCard = button.closest('.pricing-card');
            const pricingIndex = pricingCard
                ? Array.from(document.querySelectorAll('.pricing-card')).indexOf(pricingCard) + 1
                : 0;
            track('cta_click', pricingIndex ? `pricing_${pricingIndex}` : 'hero');

            if (!registrationSection) {
                return;
            }

            registrationSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    if (!form) {
        return;
    }

    form.addEventListener('focusin', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)) {
            return;
        }

        trackOnce('field_focus', field.name);
    });

    form.addEventListener('input', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement)) {
            return;
        }

        formStarted = true;
        trackOnce('form_start');
        trackOnce('field_input', field.name);
    });

    form.addEventListener('invalid', (event) => {
        track('form_invalid', event.target.name);
    }, true);

    form.querySelector('button[type="submit"]')?.addEventListener('click', () => {
        track('form_submit_click');
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        formStarted = true;
        trackOnce('form_start');
        track('form_submit');

        const submitButton = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);

        if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = true;
        }

        if (message) {
            message.textContent = '';
            message.className = 'form-message';
        }

        let errorKind = 'network';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
            });

            errorKind = 'response';
            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.error || 'Не удалось отправить заявку.');
            }

            if (message) {
                message.textContent = 'Заявка успешно отправлена. Мы свяжемся с вами в ближайшее время.';
                message.className = 'form-message success';
            }

            track('form_success');
            formSucceeded = true;
            form.reset();
        } catch (error) {
            track('form_error', errorKind);
            if (message) {
                message.textContent = error instanceof Error ? error.message : 'Не удалось отправить заявку.';
                message.className = 'form-message error';
            }
        } finally {
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = false;
            }
        }
    });
});
