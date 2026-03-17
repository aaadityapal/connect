/**
 * =====================================================
 * SCHEDULE TIMELINE ENGINE — components/schedule-timeline.js
 * =====================================================
 * Supports three zoom levels for My Schedule:
 *   Day   → hour-by-hour (7AM–8PM)
 *   Week  → day-by-day (Mon–Sun of current week)
 *   Month → date-by-date (all days of current month)
 *
 * Team Schedule always renders in Day zoom.
 *
 * Public API:
 *   window.ScheduleTimeline.init()
 * =====================================================
 */

(function (global) {
    'use strict';

    // ─── CSS injected once ────────────────────────────────────
    function injectStyles() {
        if (document.getElementById('tl-anim-styles')) return;
        const s = document.createElement('style');
        s.id = 'tl-anim-styles';
        s.textContent = `
            @keyframes tlBlockIn {
                from { opacity: 0; transform: translateX(-10px); }
                to   { opacity: 1; transform: translateX(0); }
            }
            @keyframes tlFadeIn {
                from { opacity: 0; transform: translateY(4px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            @keyframes tlIndicatorPulse {
                0%,100% { opacity: 1; }
                50%     { opacity: 0.5; }
            }
            @keyframes tlDotRipple {
                0%   { box-shadow: 0 0 0 0   rgba(239,68,68,.55); }
                70%  { box-shadow: 0 0 0 8px rgba(239,68,68,0);   }
                100% { box-shadow: 0 0 0 0   rgba(239,68,68,0);   }
            }
            @keyframes tlRowIn {
                from { opacity:0; transform:translateY(6px); }
                to   { opacity:1; transform:translateY(0);   }
            }
            @keyframes tlTipIn {
                from { opacity:0; transform:translateY(4px) scale(0.97); }
                to   { opacity:1; transform:translateY(0)   scale(1);    }
            }
            .tl-legend-row  { display:flex; flex-wrap:wrap; gap:.65rem 1.25rem; padding:.75rem .25rem .35rem; animation:tlRowIn .4s ease both; }
            .tl-legend-item { display:flex; align-items:center; gap:.4rem; font-size:.72rem; font-weight:600; color:#475569; cursor:default; transition:opacity .2s; }
            .tl-legend-item:hover { opacity:.75; }
            .tl-legend-dot  { width:10px; height:10px; border-radius:50%; flex-shrink:0; box-shadow:0 1px 3px rgba(0,0,0,.15); }
            /* Zoom button hover */
            #myZoomOutBtn:hover, #myZoomInBtn:hover,
            #teamZoomOutBtn:hover, #teamZoomInBtn:hover {
                background: #e2e8f0 !important; color: #1e293b !important;
            }
            /* Period Navigation UI */
            .period-nav-btn {
                padding: 0.3rem 0.5rem; background: transparent; border: none; cursor: pointer; border-radius: 0.3rem; color: #64748b; transition: background 0.15s, color 0.15s;
            }
            .period-nav-btn:hover { background: #e2e8f0; color: #1e293b; }
            .period-today-btn {
                padding: 0.2rem 0.6rem; font-size: 0.75rem; font-weight: 700; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 0.3rem; color: #475569; cursor: pointer; margin: 0 0.2rem; transition: all 0.2s;
            }
            .period-today-btn:hover { background: #f8fafc; color: #1e293b; }
            .period-label {
                font-size: 0.8rem; font-weight: 600; width: 140px; display: inline-block; text-align: center; color: #1e293b; font-family: 'Outfit', sans-serif; white-space: nowrap; padding: 0 0.4rem;
            }
            /* Task tooltip */
            #tl-tooltip {
                position: fixed;
                z-index: 99999;
                pointer-events: none;
                background: #1e2433;
                color: #ffffff;
                border-radius: 10px;
                padding: 8px 13px;
                font-family: 'Outfit','Inter',sans-serif;
                font-size: 0.78rem;
                line-height: 1.5;
                box-shadow: 0 8px 24px rgba(0,0,0,0.22), 0 0 0 1px rgba(255,255,255,0.06);
                max-width: 240px;
                white-space: normal;
                word-break: break-word;
                animation: tlTipIn 0.18s cubic-bezier(.22,1,.36,1) both;
                display: none;
            }
            #tl-tooltip .tl-tip-title {
                font-weight: 700;
                font-size: 0.82rem;
                color: #ffffff;
                margin-bottom: 4px;
                display: block;
            }
            #tl-tooltip .tl-tip-meta {
                font-size: 0.72rem;
                color: #94a3b8;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            #tl-tooltip .tl-tip-dot {
                width: 7px; height: 7px;
                border-radius: 50%;
                display: inline-block;
                flex-shrink: 0;
            }
            #tl-tooltip::after {
                content: '';
                position: absolute;
                top: 100%;
                left: 18px;
                border: 6px solid transparent;
                border-top-color: #1e2433;
            }
        `;
        document.head.appendChild(s);

        // Singleton tooltip element
        if (!document.getElementById('tl-tooltip')) {
            const tip = document.createElement('div');
            tip.id = 'tl-tooltip';
            tip.innerHTML = '<span class="tl-tip-title"></span><div class="tl-tip-meta"><span class="tl-tip-dot"></span><span class="tl-tip-person"></span><span class="tl-tip-label"></span></div>';
            document.body.appendChild(tip);
        }
    }

    // ─── Tooltip helpers ──────────────────────────────────────
    let _tipTimer = null;

    function showTooltip(e, { title, person, label, dotColor }) {
        const tip = document.getElementById('tl-tooltip');
        if (!tip) return;
        tip.querySelector('.tl-tip-title').textContent  = title;
        tip.querySelector('.tl-tip-person').textContent = person || '';
        tip.querySelector('.tl-tip-label').textContent  = label ? ` · ${label}` : '';
        tip.querySelector('.tl-tip-dot').style.background = dotColor || '#94a3b8';

        clearTimeout(_tipTimer);
        _tipTimer = setTimeout(() => {
            tip.style.display = 'block';
            // Position: above the cursor
            const tw = tip.offsetWidth  || 220;
            const th = tip.offsetHeight || 60;
            let x = e.clientX - 14;
            let y = e.clientY - th - 14;
            // Keep inside viewport
            if (x + tw > window.innerWidth  - 8) x = window.innerWidth  - tw - 8;
            if (x < 8) x = 8;
            if (y < 8) y = e.clientY + 20; // flip below cursor if too close to top
            tip.style.left = x + 'px';
            tip.style.top  = y + 'px';
        }, 300);
    }

    function hideTooltip() {
        clearTimeout(_tipTimer);
        const tip = document.getElementById('tl-tooltip');
        if (tip) tip.style.display = 'none';
    }

    function movTooltip(e) {
        const tip = document.getElementById('tl-tooltip');
        if (!tip || tip.style.display === 'none') return;
        const tw = tip.offsetWidth  || 220;
        const th = tip.offsetHeight || 60;
        let x = e.clientX - 14;
        let y = e.clientY - th - 14;
        if (x + tw > window.innerWidth - 8) x = window.innerWidth - tw - 8;
        if (x < 8) x = 8;
        if (y < 8) y = e.clientY + 20;
        tip.style.left = x + 'px';
        tip.style.top  = y + 'px';
    }

    // ─── Person dot colors ────────────────────────────────────
    const PALETTE = [
        '#ef4444', '#f97316', '#f59e0b', '#84cc16', '#22c55e', '#10b981', '#14b8a6', '#06b6d4', 
        '#0ea5e9', '#3b82f6', '#6366f1', '#8b5cf6', '#d946ef', '#ec4899', '#f43f5e',
        '#dc2626', '#ea580c', '#d97706', '#65a30d', '#16a34a', '#059669', '#0d9488', '#0891b2',
        '#0284c7', '#2563eb', '#4f46e5', '#7c3aed', '#c026d3', '#db2777', '#e11d48'
    ];

    function getColorForPerson(name) {
        if (!name) return '#94a3b8';
        name = name.trim();
        
        // Single deterministic Session Cache
        if (!window._personColorCache) {
            window._personColorCache = {};
            window._colorIndex = 0;
        }
        
        if (window._personColorCache[name]) {
            return window._personColorCache[name];
        }
        
        const c = PALETTE[window._colorIndex % PALETTE.length];
        window._colorIndex++;
        
        window._personColorCache[name] = c;
        return c;
    }

    // ─── Shared helpers ───────────────────────────────────────
    function toMin(str) {
        const m = /([0-9]{1,2}):([0-9]{2})\s*(AM|PM)?/i.exec(str);
        if (!m) return 0;
        let h = +m[1], min = +m[2];
        const p = (m[3] || '').toUpperCase();
        if (p === 'PM' && h !== 12) h += 12;
        if (p === 'AM' && h === 12) h = 0;
        return h * 60 + min;
    }

    function fmtHourLabel(h) {
        if (h === 0 || h === 24) return '12:00 AM';
        return `${h % 12 || 12}:00 ${h < 12 ? 'AM' : 'PM'}`;
    }

    function fmtMinLabel(totalMin) {
        const h24 = Math.floor(totalMin / 60) % 24;
        const m   = totalMin % 60;
        return `${h24 % 12 || 12}:${String(m).padStart(2, '0')} ${h24 < 12 ? 'AM' : 'PM'}`;
    }

    // ─── Generic pill builder (shared across all views) ───────
    function makePill({ leftPx, topPx, height, label, title, dotColor, delay, person, persons, dateFrom, dateTo, assignedBy, rawEvent, hideAssignedTo }) {
        const block = document.createElement('div');
        Object.assign(block.style, {
            position:   'absolute',
            left:       (leftPx + 4) + 'px',
            top:        topPx + 'px',
            height:     height + 'px',
            display:    'flex',
            alignItems: 'center',
            gap:        '0',
            padding:    '0',
            background: '#ffffff',
            borderRadius: '16px',
            whiteSpace: 'nowrap',
            cursor:     'pointer',
            boxShadow:  '0 1px 6px rgba(0,0,0,.10), 0 0 0 1px rgba(0,0,0,.06)',
            fontFamily: "'Outfit','Inter',sans-serif",
            zIndex:     '2',
            overflow:   'hidden',
            width:      'max-content',
            transition: 'transform .18s ease, box-shadow .18s ease',
            animation:  `tlBlockIn .35s cubic-bezier(.22,1,.36,1) ${delay} both`,
            opacity:    '0',
        });

        // Dark time/label badge
        const badge = document.createElement('span');
        badge.textContent = label;
        Object.assign(badge.style, {
            display:       'inline-flex',
            alignItems:    'center',
            height:        '100%',
            padding:       '0 10px',
            background:    '#1e2433',
            color:         '#ffffff',
            fontSize:      '0.68rem',
            fontWeight:    '700',
            letterSpacing: '0.03em',
            flexShrink:    '0',
            borderRadius:  '16px 0 0 16px',
            whiteSpace:    'nowrap',
        });

        // White right section
        const right = document.createElement('span');
        Object.assign(right.style, {
            display:    'flex',
            alignItems: 'center',
            gap:        '6px',
            padding:    '0 10px',
            height:     '100%',
            flex:       '1 1 auto',
            minWidth:   '0',
            overflow:   'hidden',
        });

        // Assigned person dots container (left side of title)
        const dotsContainer = document.createElement('div');
        Object.assign(dotsContainer.style, {
            display: 'flex',
            alignItems: 'center',
            gap: '4px',
            flexShrink: '0',
            paddingRight: '2px' // small separation before text
        });

        if (!hideAssignedTo) {
            const assignees = (persons && persons.length > 0) ? persons : (person ? [person] : []);
            assignees.slice(0, 4).forEach((p) => {
                const c = getColorForPerson(p);
                const d = document.createElement('div');
                Object.assign(d.style, {
                    width: '10px', height: '10px',
                    borderRadius: '50%', background: c, flexShrink: '0',
                    boxShadow: '0 1px 3px rgba(0,0,0,.15)'
                });
                d.title = p;
                dotsContainer.appendChild(d);
            });
        } else {
            // My schedule single category dot
            const d = document.createElement('div');
            Object.assign(d.style, {
                width: '10px', height: '10px', borderRadius: '50%', background: dotColor || '#94a3b8', flexShrink: '0',
                boxShadow: '0 1px 3px rgba(0,0,0,.2)'
            });
            dotsContainer.appendChild(d);
        }
        right.appendChild(dotsContainer);

        const titleEl = document.createElement('span');
        titleEl.textContent = title;
        Object.assign(titleEl.style, {
            fontSize:     '0.74rem',
            fontWeight:   '500',
            color:        '#1e293b',
            overflow:     'hidden',
            textOverflow: 'ellipsis',
            whiteSpace:   'nowrap',
            flex:         '1 1 auto',
            minWidth:     '0',
        });

        right.appendChild(titleEl); // ← task title (middle)
        block.appendChild(badge);
        block.appendChild(right);

        // ── Tooltip on hover ───────────────────────────────────
        const tipData = { title, person: person || '', label, dotColor };

        block.addEventListener('click', () => {
            if (window.TaskModal && window.TaskModal.open) {
                window.TaskModal.open({
                    title, person, label, dotColor,
                    id: rawEvent ? rawEvent.id : null,
                    duration: rawEvent ? rawEvent.duration : null,
                    durationDays: rawEvent ? rawEvent.durationDays : null,
                    durationStr: rawEvent ? rawEvent.durationStr : null,
                    dateFrom, dateTo, assignedBy, hideAssignedTo,
                    projectStage: rawEvent ? rawEvent.projectStage : null,
                    status: rawEvent ? rawEvent.status : 'Pending',
                    due_date: rawEvent ? rawEvent.due_date : null,
                    due_time_24: rawEvent ? rawEvent.due_time_24 : null,
                    extension_count: rawEvent ? rawEvent.extension_count : 0,
                    previous_due_date: rawEvent ? rawEvent.previous_due_date : null,
                    previous_due_time: rawEvent ? rawEvent.previous_due_time : null,
                    completed_at: rawEvent ? rawEvent.completed_at : null,
                    assignee_statuses: rawEvent ? rawEvent.assignee_statuses : [],
                    extension_history: rawEvent ? (rawEvent.extension_history || []) : [],
                    desc: rawEvent ? rawEvent.desc : ''
                });
            }
        });

        block.addEventListener('mouseenter', (e) => {
            block.style.transform = 'translateY(-3px)';
            block.style.boxShadow = '0 6px 18px rgba(0,0,0,.14), 0 0 0 1px rgba(0,0,0,.08)';
            block.style.zIndex    = '10';
            showTooltip(e, tipData);
        });
        block.addEventListener('mousemove',  (e) => movTooltip(e));
        block.addEventListener('mouseleave', () => {
            block.style.transform = '';
            block.style.boxShadow = '0 1px 6px rgba(0,0,0,.10), 0 0 0 1px rgba(0,0,0,.06)';
            block.style.zIndex    = '2';
            hideTooltip();
        });

        return block;
    }

    // ─────────────────────────────────────────────────────────
    //  DAY VIEW
    // ─────────────────────────────────────────────────────────
    const DAY_START  = 0;
    const DAY_END    = 24;
    const DAY_COL_W  = 120;
    const DAY_HDR_H  = 44;
    const DAY_ROW_H  = 52;
    const DAY_ROWS   = 5;

    function buildDayView(cfg) {
        const { headersEl, gridEl, eventsEl, indicatorEl, events,
                scrollWrapper, legendTarget, baseDate, isMySchedule } = cfg;
        if (!headersEl || !eventsEl) return;

        const TOTAL_HOURS = DAY_END - DAY_START;
        const TOTAL_W     = TOTAL_HOURS * DAY_COL_W;
        const EVENTS_H    = DAY_ROW_H * DAY_ROWS;
        const TOTAL_H     = DAY_HDR_H + EVENTS_H;
        const startMin    = DAY_START * 60;
        const totalMin    = TOTAL_HOURS * 60;
        
        const now         = new Date();
        const nowMin      = now.getHours() * 60 + now.getMinutes();
        
        // Checking if baseDate is today
        const isTodayBase = baseDate.getFullYear() === now.getFullYear() && 
                            baseDate.getMonth() === now.getMonth() && 
                            baseDate.getDate() === now.getDate();
        
        const nowPct      = isTodayBase ? ((nowMin - startMin) / totalMin) * 100 : -1;

        // Container
        const container = headersEl.parentElement;
        if (container) Object.assign(container.style, {
            height: TOTAL_H + 'px', position: 'relative',
            minWidth: TOTAL_W + 'px', background: '#ffffff',
        });

        // Headers
        headersEl.innerHTML = '';
        Object.assign(headersEl.style, {
            position: 'absolute', top: '0', left: '0',
            width: TOTAL_W + 'px', height: DAY_HDR_H + 'px',
            background: '#ffffff', borderBottom: '1px solid #e9ecef', zIndex: '10',
        });
        for (let h = DAY_START; h <= DAY_END; h++) {
            const isCurrentHr = isTodayBase && (h === now.getHours());
            const lbl = document.createElement('span');
            lbl.textContent = fmtHourLabel(h);
            Object.assign(lbl.style, {
                position: 'absolute', left: ((h - DAY_START) * DAY_COL_W) + 'px',
                top: '50%', transform: 'translate(-50%,-50%)',
                fontSize: '0.72rem', fontWeight: isCurrentHr ? '700' : '500',
                color: isCurrentHr ? '#ef4444' : '#94a3b8',
                whiteSpace: 'nowrap', fontFamily: "'Outfit','Inter',sans-serif",
            });
            headersEl.appendChild(lbl);
        }

        // Grid
        if (gridEl) {
            gridEl.innerHTML = '';
            Object.assign(gridEl.style, {
                position: 'absolute', top: DAY_HDR_H + 'px', left: '0',
                width: TOTAL_W + 'px', height: EVENTS_H + 'px',
                zIndex: '1', pointerEvents: 'none',
            });
            for (let r = 0; r < DAY_ROWS; r++) {
                const band = document.createElement('div');
                Object.assign(band.style, {
                    position: 'absolute', top: (r * DAY_ROW_H) + 'px',
                    left: '0', right: '0', height: DAY_ROW_H + 'px',
                    background: '#ffffff', borderBottom: '1px solid #f1f5f9',
                });
                gridEl.appendChild(band);
            }
            for (let h = DAY_START; h <= DAY_END; h++) {
                const line = document.createElement('div');
                Object.assign(line.style, {
                    position: 'absolute', left: ((h - DAY_START) * DAY_COL_W) + 'px',
                    top: '0', bottom: '0', width: '1px', background: '#e9ecef',
                });
                gridEl.appendChild(line);
            }
        }

        // Events
        eventsEl.innerHTML = '';
        Object.assign(eventsEl.style, {
            position: 'absolute', top: DAY_HDR_H + 'px', left: '0',
            width: TOTAL_W + 'px', height: EVENTS_H + 'px', zIndex: '3',
        });

        let idx = 0;
        (events || []).forEach(ev => {
            const evMin = toMin(ev.time);
            const dur   = ev.duration || 60;
            if (evMin + dur < startMin || evMin > startMin + totalMin) return;

            const clampedStart = Math.max(evMin, startMin);
            const leftPx = ((clampedStart - startMin) / totalMin) * TOTAL_W;
            const topPx  = (ev.row || 0) * DAY_ROW_H + 6;
            const dotColor = getColorForPerson(ev.person);

            const startDateStr = baseDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const dateFrom = ev.modalDateFrom || `${startDateStr} - ${fmtMinLabel(evMin)}`;
            const dateTo   = ev.modalDateTo || `${startDateStr} - ${fmtMinLabel(evMin + dur)}`;

            const pill = makePill({
                leftPx, topPx, height: DAY_ROW_H - 14,
                label: fmtMinLabel(evMin), title: ev.title,
                dotColor, delay: (idx * 50) + 'ms', person: ev.person, persons: ev.persons,
                dateFrom, dateTo, assignedBy: ev.assignedBy || 'System Admin', rawEvent: ev,
                hideAssignedTo: isMySchedule
            });
            eventsEl.appendChild(pill);
            idx++;
        });

        // Current-time indicator
        _drawIndicator(indicatorEl, nowPct, TOTAL_H, DAY_HDR_H);

        // Legend
        _drawLegend(legendTarget, events, isMySchedule);

        // Scroll
        _wireDragScroll(scrollWrapper);
        if (isTodayBase) {
            _autoCentre(scrollWrapper, nowPct, TOTAL_W);
        } else {
            if (scrollWrapper) scrollWrapper.scrollLeft = 0;
        }
    }

    // ─────────────────────────────────────────────────────────
    //  WEEK VIEW
    // ─────────────────────────────────────────────────────────
    const WEEK_COL_W = 160;
    const WEEK_HDR_H = 56;
    const WEEK_ROW_H = 52;
    const WEEK_ROWS  = 5;
    const WEEK_DAYS  = 7;

    function buildWeekView(cfg) {
        const { headersEl, gridEl, eventsEl, indicatorEl, events, scrollWrapper, baseDate, isMySchedule } = cfg;
        if (!headersEl || !eventsEl) return;

        const TOTAL_W  = WEEK_DAYS * WEEK_COL_W;
        const EVENTS_H = WEEK_ROW_H * WEEK_ROWS;
        const TOTAL_H  = WEEK_HDR_H + EVENTS_H;

        const now = new Date();
        const monday = new Date(baseDate);
        monday.setDate(baseDate.getDate() - ((baseDate.getDay() + 6) % 7));
        monday.setHours(0, 0, 0, 0);

        // Check if `now` is currently inside this rendering week
        let todayDOW = -1;
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        sunday.setHours(23, 59, 59, 999);
        if (now >= monday && now <= sunday) {
            todayDOW = (now.getDay() + 6) % 7;
        }

        const dayLabels = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];

        // Container
        const container = headersEl.parentElement;
        if (container) Object.assign(container.style, {
            height: TOTAL_H + 'px', position: 'relative',
            minWidth: TOTAL_W + 'px', background: '#ffffff',
        });

        // Headers
        headersEl.innerHTML = '';
        Object.assign(headersEl.style, {
            position: 'absolute', top: '0', left: '0',
            width: TOTAL_W + 'px', height: WEEK_HDR_H + 'px',
            background: '#ffffff', borderBottom: '1px solid #e9ecef', zIndex: '10',
            display: 'flex',
        });

        for (let d = 0; d < WEEK_DAYS; d++) {
            const date = new Date(monday);
            date.setDate(monday.getDate() + d);
            const isToday = (d === todayDOW);

            const cell = document.createElement('div');
            Object.assign(cell.style, {
                width: WEEK_COL_W + 'px', flexShrink: '0',
                display: 'flex', flexDirection: 'column',
                alignItems: 'center', justifyContent: 'center',
                borderRight: '1px solid #e9ecef',
                background: isToday ? '#fff8f8' : 'transparent',
                fontFamily: "'Outfit','Inter',sans-serif",
                cursor: 'default',
            });

            const dayName = document.createElement('span');
            dayName.textContent = dayLabels[d];
            Object.assign(dayName.style, {
                fontSize: '0.65rem', fontWeight: '600',
                letterSpacing: '0.06em',
                color: isToday ? '#ef4444' : '#94a3b8',
                marginBottom: '3px',
            });

            const dateNum = document.createElement('span');
            dateNum.textContent = date.getDate();
            Object.assign(dateNum.style, {
                fontSize: isToday ? '1.1rem' : '0.95rem',
                fontWeight: isToday ? '800' : '600',
                color: isToday ? '#ef4444' : '#1e293b',
            });

            if (isToday) {
                Object.assign(dateNum.style, {
                    background:   '#ef4444',
                    color:        '#ffffff',
                    width:        '26px', height: '26px',
                    borderRadius: '50%',
                    display:      'flex',
                    alignItems:   'center', justifyContent: 'center',
                    fontSize:     '0.85rem',
                });
            }

            cell.appendChild(dayName);
            cell.appendChild(dateNum);
            headersEl.appendChild(cell);
        }

        // Grid
        if (gridEl) {
            gridEl.innerHTML = '';
            Object.assign(gridEl.style, {
                position: 'absolute', top: WEEK_HDR_H + 'px', left: '0',
                width: TOTAL_W + 'px', height: EVENTS_H + 'px',
                zIndex: '1', pointerEvents: 'none',
            });
            for (let r = 0; r < WEEK_ROWS; r++) {
                const band = document.createElement('div');
                Object.assign(band.style, {
                    position: 'absolute', top: (r * WEEK_ROW_H) + 'px',
                    left: '0', right: '0', height: WEEK_ROW_H + 'px',
                    background: '#ffffff', borderBottom: '1px solid #f1f5f9',
                });
                gridEl.appendChild(band);
            }
            for (let d = 0; d <= WEEK_DAYS; d++) {
                const line = document.createElement('div');
                Object.assign(line.style, {
                    position: 'absolute', left: (d * WEEK_COL_W) + 'px',
                    top: '0', bottom: '0', width: '1px', background: '#e9ecef',
                });
                gridEl.appendChild(line);
            }
            if (todayDOW >= 0 && todayDOW < 7) {
                const todayBg = document.createElement('div');
                Object.assign(todayBg.style, {
                    position: 'absolute', left: (todayDOW * WEEK_COL_W) + 'px',
                    top: '0', bottom: '0', width: WEEK_COL_W + 'px',
                    background: 'rgba(239,68,68,0.03)',
                });
                gridEl.appendChild(todayBg);
            }
        }

        // Events
        eventsEl.innerHTML = '';
        Object.assign(eventsEl.style, {
            position: 'absolute', top: WEEK_HDR_H + 'px', left: '0',
            width: TOTAL_W + 'px', height: EVENTS_H + 'px', zIndex: '3',
        });

        let idx = 0;
        (events || []).forEach(ev => {
            if (ev.dayIndex === undefined || ev.dayIndex >= WEEK_DAYS) return;
            const leftPx   = ev.dayIndex * WEEK_COL_W + 4;
            const widthPx  = Math.max((ev.durationDays || 1) * WEEK_COL_W - 8, 80);
            const topPx    = (ev.row || 0) * WEEK_ROW_H + 6;
            const dotColor = getColorForPerson(ev.person);

            const evDate = new Date(monday);
            evDate.setDate(monday.getDate() + ev.dayIndex);
            const mStr = evDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

            const evEndDate = new Date(evDate);
            evEndDate.setDate(evDate.getDate() + (ev.durationDays || 1) - 1);
            const toStr = evEndDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            
            const dateFrom = ev.modalDateFrom || evDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            const dateTo   = ev.modalDateTo || toStr;

            const pill = makePill({
                leftPx: leftPx - 4, topPx, height: WEEK_ROW_H - 14,
                label: mStr, title: ev.title,
                dotColor, delay: (idx * 40) + 'ms', person: ev.person, persons: ev.persons,
                dateFrom, dateTo, assignedBy: ev.assignedBy || 'System Admin', rawEvent: ev,
                hideAssignedTo: isMySchedule
            });
            pill.style.width = widthPx + 'px';
            eventsEl.appendChild(pill);
            idx++;
        });

        // Indicator
        if (indicatorEl) {
            indicatorEl.innerHTML = '';
            if (todayDOW >= 0) {
                const todayLeft = todayDOW * WEEK_COL_W + WEEK_COL_W / 2;
                Object.assign(indicatorEl.style, {
                    position: 'absolute', left: todayLeft + 'px',
                    top: '0', height: TOTAL_H + 'px', width: '2px',
                    background: '#ef4444', zIndex: '20', pointerEvents: 'none',
                    transform: 'translateX(-50%)', display: 'block',
                    animation: 'tlIndicatorPulse 2.5s ease-in-out infinite',
                });
                const dot = document.createElement('div');
                Object.assign(dot.style, {
                    position: 'absolute', top: (WEEK_HDR_H - 5) + 'px',
                    left: '50%', transform: 'translateX(-50%)',
                    width: '10px', height: '10px', borderRadius: '50%',
                    background: '#ef4444', animation: 'tlDotRipple 1.6s ease-out infinite',
                });
                indicatorEl.appendChild(dot);
            } else {
                indicatorEl.style.display = 'none';
            }
        }

        _wireDragScroll(scrollWrapper);
        if (todayDOW >= 0) {
            setTimeout(() => {
                if (scrollWrapper) {
                    const centre = todayDOW * WEEK_COL_W - scrollWrapper.clientWidth / 2 + WEEK_COL_W / 2;
                    scrollWrapper.scrollLeft = Math.max(0, centre);
                }
            }, 150);
        } else {
            if (scrollWrapper) scrollWrapper.scrollLeft = 0;
        }
    }

    // ─────────────────────────────────────────────────────────
    //  MONTH VIEW
    // ─────────────────────────────────────────────────────────
    const MONTH_COL_W = 55;
    const MONTH_HDR_H = 56;
    const MONTH_ROW_H = 52;
    const MONTH_ROWS  = 5;

    function buildMonthView(cfg) {
        const { headersEl, gridEl, eventsEl, indicatorEl, events, scrollWrapper, baseDate, isMySchedule } = cfg;
        if (!headersEl || !eventsEl) return;

        const now          = new Date();
        const year         = baseDate.getFullYear();
        const month        = baseDate.getMonth();
        const daysInMonth  = new Date(year, month + 1, 0).getDate();
        
        const isCurrentMonth = (now.getFullYear() === year && now.getMonth() === month);
        const today          = isCurrentMonth ? now.getDate() : -1;
        
        const TOTAL_W      = daysInMonth * MONTH_COL_W;
        const EVENTS_H     = MONTH_ROW_H * MONTH_ROWS;
        const TOTAL_H      = MONTH_HDR_H + EVENTS_H;

        const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const DOW_LABELS = ['Su','Mo','Tu','We','Th','Fr','Sa'];

        // Container
        const container = headersEl.parentElement;
        if (container) Object.assign(container.style, {
            height: TOTAL_H + 'px', position: 'relative',
            minWidth: TOTAL_W + 'px', background: '#ffffff',
        });

        // Headers
        headersEl.innerHTML = '';
        Object.assign(headersEl.style, {
            position: 'absolute', top: '0', left: '0',
            width: TOTAL_W + 'px', height: MONTH_HDR_H + 'px',
            background: '#ffffff', borderBottom: '1px solid #e9ecef',
            zIndex: '10', display: 'flex',
        });

        const firstDOW = new Date(year, month, 1).getDay(); // 0=Sun

        for (let d = 1; d <= daysInMonth; d++) {
            const isToday = (d === today);
            const dow = (firstDOW + d - 1) % 7;
            const isWeekend = (dow === 0 || dow === 6);

            const cell = document.createElement('div');
            Object.assign(cell.style, {
                width: MONTH_COL_W + 'px', flexShrink: '0',
                display: 'flex', flexDirection: 'column',
                alignItems: 'center', justifyContent: 'center',
                borderRight: '1px solid #e9ecef',
                background: isToday ? '#fff8f8' : isWeekend ? '#fafbfc' : 'transparent',
                fontFamily: "'Outfit','Inter',sans-serif",
                cursor: 'default',
            });

            const dowLbl = document.createElement('span');
            dowLbl.textContent = DOW_LABELS[dow];
            Object.assign(dowLbl.style, {
                fontSize: '0.6rem', fontWeight: '600',
                color: isWeekend ? '#cbd5e1' : '#94a3b8',
                letterSpacing: '0.04em', marginBottom: '3px',
            });

            const dateLbl = document.createElement('span');
            dateLbl.textContent = d;
            Object.assign(dateLbl.style, {
                fontSize:   '0.8rem',
                fontWeight: isToday ? '800' : '600',
                color:      isToday ? '#ffffff' : isWeekend ? '#94a3b8' : '#1e293b',
            });

            if (isToday) {
                Object.assign(dateLbl.style, {
                    background:   '#ef4444',
                    width:        '22px', height: '22px',
                    borderRadius: '50%',
                    display:      'flex',
                    alignItems:   'center', justifyContent: 'center',
                    fontSize:     '0.72rem',
                });
            }

            cell.appendChild(dowLbl);
            cell.appendChild(dateLbl);
            headersEl.appendChild(cell);
        }

        // Grid
        if (gridEl) {
            gridEl.innerHTML = '';
            Object.assign(gridEl.style, {
                position: 'absolute', top: MONTH_HDR_H + 'px', left: '0',
                width: TOTAL_W + 'px', height: EVENTS_H + 'px',
                zIndex: '1', pointerEvents: 'none',
            });
            for (let r = 0; r < MONTH_ROWS; r++) {
                const band = document.createElement('div');
                Object.assign(band.style, {
                    position: 'absolute', top: (r * MONTH_ROW_H) + 'px',
                    left: '0', right: '0', height: MONTH_ROW_H + 'px',
                    background: '#ffffff', borderBottom: '1px solid #f1f5f9',
                });
                gridEl.appendChild(band);
            }
            for (let d = 0; d <= daysInMonth; d++) {
                const dow = (firstDOW + d - 1) % 7;
                const isWeekend = (dow === 0 || dow === 6) && d > 0;
                const line = document.createElement('div');
                Object.assign(line.style, {
                    position: 'absolute', left: (d * MONTH_COL_W) + 'px',
                    top: '0', bottom: '0', width: '1px', background: '#e9ecef',
                });
                gridEl.appendChild(line);
                if (isWeekend) {
                    const shade = document.createElement('div');
                    Object.assign(shade.style, {
                        position: 'absolute', left: ((d - 1) * MONTH_COL_W) + 'px',
                        top: '0', bottom: '0', width: MONTH_COL_W + 'px',
                        background: 'rgba(0,0,0,0.015)',
                    });
                    gridEl.appendChild(shade);
                }
            }
            if (today >= 1 && today <= daysInMonth) {
                const todayBg = document.createElement('div');
                Object.assign(todayBg.style, {
                    position: 'absolute', left: ((today - 1) * MONTH_COL_W) + 'px',
                    top: '0', bottom: '0', width: MONTH_COL_W + 'px',
                    background: 'rgba(239,68,68,0.04)',
                });
                gridEl.appendChild(todayBg);
            }
        }

        // Events
        eventsEl.innerHTML = '';
        Object.assign(eventsEl.style, {
            position: 'absolute', top: MONTH_HDR_H + 'px', left: '0',
            width: TOTAL_W + 'px', height: EVENTS_H + 'px', zIndex: '3',
        });

        let idx = 0;
        (events || []).forEach(ev => {
            if (!ev.dateNum || ev.dateNum > daysInMonth) return;
            const startD   = ev.dateNum;
            const dur      = Math.min(ev.durationDays || 1, daysInMonth - startD + 1);
            const leftPx   = (startD - 1) * MONTH_COL_W + 4;
            const widthPx  = Math.max(dur * MONTH_COL_W - 8, 60);
            const topPx    = (ev.row || 0) * MONTH_ROW_H + 6;
            const dotColor = getColorForPerson(ev.person);

            const evDate = new Date(year, month, startD);
            const defaultDateFrom = evDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            
            const evEndDate = new Date(year, month, startD + (ev.durationDays || 1) - 1);
            const defaultDateTo = evEndDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

            const dateFrom = ev.modalDateFrom || defaultDateFrom;
            const dateTo   = ev.modalDateTo || defaultDateTo;

            const pill = makePill({
                leftPx: leftPx - 4, topPx, height: MONTH_ROW_H - 14,
                label: `${monthNames[month]} ${startD}`,
                title: ev.title, dotColor, delay: (idx * 30) + 'ms', person: ev.person, persons: ev.persons,
                dateFrom, dateTo, assignedBy: ev.assignedBy || 'System Admin', rawEvent: ev,
                hideAssignedTo: isMySchedule
            });
            pill.style.width = widthPx + 'px';
            eventsEl.appendChild(pill);
            idx++;
        });

        // Indicator
        if (indicatorEl) {
            indicatorEl.innerHTML = '';
            if (today >= 1) {
                const todayLeft = (today - 0.5) * MONTH_COL_W;
                Object.assign(indicatorEl.style, {
                    position: 'absolute', left: todayLeft + 'px',
                    top: '0', height: TOTAL_H + 'px', width: '2px',
                    background: '#ef4444', zIndex: '20', pointerEvents: 'none',
                    transform: 'translateX(-50%)', display: 'block',
                    animation: 'tlIndicatorPulse 2.5s ease-in-out infinite',
                });
                const dot = document.createElement('div');
                Object.assign(dot.style, {
                    position: 'absolute', top: (MONTH_HDR_H - 5) + 'px',
                    left: '50%', transform: 'translateX(-50%)',
                    width: '10px', height: '10px', borderRadius: '50%',
                    background: '#ef4444', animation: 'tlDotRipple 1.6s ease-out infinite',
                });
                indicatorEl.appendChild(dot);
            } else {
                indicatorEl.style.display = 'none';
            }
        }

        _wireDragScroll(scrollWrapper);
        if (today >= 1) {
            setTimeout(() => {
                if (scrollWrapper) {
                    const centre = (today - 1) * MONTH_COL_W - scrollWrapper.clientWidth / 2 + MONTH_COL_W / 2;
                    scrollWrapper.scrollLeft = Math.max(0, centre);
                }
            }, 150);
        } else {
            if (scrollWrapper) scrollWrapper.scrollLeft = 0;
        }
    }

    // ─── Shared rendering helpers ─────────────────────────────
    function _drawIndicator(el, pct, totalH, hdrH) {
        if (!el) return;
        el.innerHTML = '';
        if (pct >= 0 && pct <= 100) {
            Object.assign(el.style, {
                position: 'absolute', left: `calc(${pct}%)`,
                top: '0', height: totalH + 'px', width: '2px',
                background: '#ef4444', zIndex: '20', pointerEvents: 'none',
                transform: 'translateX(-50%)',
                animation: 'tlIndicatorPulse 2.5s ease-in-out infinite',
            });
            const dot = document.createElement('div');
            Object.assign(dot.style, {
                position: 'absolute', top: (hdrH - 5) + 'px',
                left: '50%', transform: 'translateX(-50%)',
                width: '10px', height: '10px', borderRadius: '50%',
                background: '#ef4444', animation: 'tlDotRipple 1.6s ease-out infinite',
            });
            el.appendChild(dot);
        } else { el.style.display = 'none'; }
    }

    function _drawLegend(target, events, isMySchedule) {
        if (!target) return;
        const old = target.querySelector('.tl-legend-row');
        if (old) old.remove();

        const row = document.createElement('div');
        row.className = 'tl-legend-row';
        Object.assign(row.style, { display: 'flex', gap: '1.5rem', alignItems: 'center', justifyContent: 'center', flexWrap: 'wrap' });

        if (isMySchedule) {
            // Personal legend items
            const myLegend = {
                'Deep Work': '#7c3aed',
                'Meetings': '#3b82f6',
                'Tasks': '#f59e0b',
                'Reviews': '#ec4899',
            };
            Object.entries(myLegend).forEach(([name, color]) => {
                const item = document.createElement('div');
                item.className = 'tl-legend-item';
                item.innerHTML = `<div class="tl-legend-dot" style="background:${color}"></div><span>${name}</span>`;
                row.appendChild(item);
            });
        } else {
            // Team legend items (dynamic from real hierarchy)
            let teamNames = [];
            
            function extractNames(node) {
                if (!node) return;
                teamNames.push({ name: node.name, dot: getColorForPerson(node.name) });
                if (node.children) node.children.forEach(extractNames);
            }
            
            if (_lastHierarchyData && (_lastHierarchyData.tree || _lastHierarchyData.manager)) {
                extractNames(_lastHierarchyData.tree || _lastHierarchyData.manager);
            }

            // Deduplicate to avoid multiple dots for same person
            const seen = new Set();
            const unique = [];
            for (let t of teamNames) {
                if (!seen.has(t.name)) {
                    seen.add(t.name);
                    unique.push(t);
                }
            }

            unique.forEach(p => {
                const item = document.createElement('div');
                item.className = 'tl-legend-item';
                item.innerHTML = `<div class="tl-legend-dot" style="background:${p.dot}"></div><span>${p.name}</span>`;
                row.appendChild(item);
            });
        }
        target.appendChild(row);
    }

    function _wireButtons(left, right, wrapper, step) {
        if (!left || !right || !wrapper) return;
        left.onclick  = () => wrapper.scrollBy({ left: -step * 2, behavior: 'smooth' });
        right.onclick = () => wrapper.scrollBy({ left:  step * 2, behavior: 'smooth' });
    }

    function _wireDragScroll(wrapper) {
        if (!wrapper || wrapper._dragBound) return;
        wrapper._dragBound = true;
        let isDown = false, startX, scrollLeftPos;
        wrapper.style.cursor = 'grab';
        wrapper.addEventListener('mousedown', e => {
            isDown = true; wrapper.style.cursor = 'grabbing';
            startX = e.pageX - wrapper.offsetLeft;
            scrollLeftPos = wrapper.scrollLeft;
        });
        wrapper.addEventListener('mouseleave', () => { isDown = false; wrapper.style.cursor = 'grab'; });
        wrapper.addEventListener('mouseup',    () => { isDown = false; wrapper.style.cursor = 'grab'; });
        wrapper.addEventListener('mousemove',  e => {
            if (!isDown) return; e.preventDefault();
            wrapper.scrollLeft = scrollLeftPos - (e.pageX - wrapper.offsetLeft - startX) * 1.5;
        });
        wrapper.addEventListener('wheel', e => {
            if (e.deltaY !== 0 && Math.abs(e.deltaX) < 5) {
                e.preventDefault();
                wrapper.scrollBy({ left: e.deltaY * 1.5, behavior: 'smooth' });
            }
        }, { passive: false });
    }

    function _autoCentre(wrapper, pct, totalW) {
        if (!wrapper || pct < 0 || pct > 100) return;
        setTimeout(() => {
            const centre = (pct / 100) * totalW - wrapper.clientWidth / 2;
            wrapper.scrollLeft = Math.max(0, centre);
        }, 150);
    }

    // ─── Event data ───────────────────────────────────────────

    /** Day-view: hour-based events */
    const scheduleEvents = [
        { time: '07:00', duration: 75,  title: 'Finalize Presentation Slides',  person: 'Alex Johnson',   row: 0 },
        { time: '07:20', duration: 55,  title: 'Respond to Client Emails',      person: 'Sarah Williams', row: 1 },
        { time: '09:00', duration: 80,  title: 'Update Social Media Profiles',  person: 'Mike Davis',     row: 2 },
        { time: '11:00', duration: 65,  title: 'Conduct Team Meeting',          person: 'Manager John',   row: 0 },
        { time: '12:10', duration: 60,  title: 'Complete Daily Report',         person: 'Anna Tailor',    row: 1 },
        { time: '13:30', duration: 45,  title: 'Lunch Break',                   person: 'All Team',       row: 2 },
    ];

    /** Week-view: day-indexed events (dayIndex 0=Mon … 6=Sun) */
    const weekEvents = [
        { dayIndex: 0, durationDays: 1, title: 'Sprint Planning',       person: 'Sarah Williams', row: 0 },
        { dayIndex: 0, durationDays: 2, title: 'UI Design Sprint',      person: 'Mike Davis',     row: 1 },
        { dayIndex: 1, durationDays: 1, title: 'Client Call',           person: 'Manager John',   row: 2 },
        { dayIndex: 2, durationDays: 1, title: 'Code Review',           person: 'Alex Johnson',   row: 0 },
        { dayIndex: 2, durationDays: 2, title: 'Project Alpha Dev',     person: 'Sarah Williams', row: 1 },
        { dayIndex: 3, durationDays: 1, title: 'Team Stand-up',         person: 'Manager John',   row: 0 },
        { dayIndex: 3, durationDays: 1, title: 'Report Writing',        person: 'Anna Tailor',    row: 2 },
        { dayIndex: 4, durationDays: 2, title: 'QA & Testing',          person: 'Anna Tailor',    row: 1 },
        { dayIndex: 5, durationDays: 1, title: 'UI Review',             person: 'Mike Davis',     row: 0 },
        { dayIndex: 6, durationDays: 1, title: 'Weekend Deployment',    person: 'Alex Johnson',   row: 1 },
    ];

    /** Month-view: date-number events (dateNum 1–31) */
    const monthEvents = [
        { dateNum: 1,  durationDays: 3, title: 'Sprint Kickoff',         person: 'Sarah Williams', row: 0 },
        { dateNum: 2,  durationDays: 4, title: 'UI Design Phase',        person: 'Mike Davis',     row: 1 },
        { dateNum: 5,  durationDays: 2, title: 'Client Review',          person: 'Alex Johnson',   row: 2 },
        { dateNum: 7,  durationDays: 5, title: 'Core Development',       person: 'Manager John',   row: 0 },
        { dateNum: 9,  durationDays: 3, title: 'API Integration',        person: 'Anna Tailor',    row: 1 },
        { dateNum: 11, durationDays: 2, title: 'Mid-Sprint Review',      person: 'Sarah Williams', row: 2 },
        { dateNum: 14, durationDays: 4, title: 'Feature Development',    person: 'Alex Johnson',   row: 0 },
        { dateNum: 15, durationDays: 2, title: 'UAT Phase',              person: 'Mike Davis',     row: 1 },
        { dateNum: 18, durationDays: 3, title: 'Bug Fixes',              person: 'Anna Tailor',    row: 2 },
        { dateNum: 20, durationDays: 2, title: 'Deployment Prep',        person: 'Manager John',   row: 0 },
        { dateNum: 22, durationDays: 4, title: 'Sprint Close & Docs',    person: 'Sarah Williams', row: 1 },
        { dateNum: 25, durationDays: 3, title: 'Next Sprint Planning',   person: 'Alex Johnson',   row: 2 },
        { dateNum: 28, durationDays: 3, title: 'Release & Monitoring',   person: 'Manager John',   row: 0 },
    ];

    /** Team Schedule day events */
    const teamEvents = [
        { time: '08:00', duration: 90,  title: 'Sprint Planning',     person: 'Sarah Williams', persons: ['Sarah Williams', 'Alex Johnson', 'Mike Davis'],              row: 0 },
        { time: '09:30', duration: 60,  title: 'Code Review',         person: 'Alex Johnson',   persons: ['Alex Johnson', 'Anna Tailor'],                              row: 1 },
        { time: '10:00', duration: 50,  title: 'Design Sync',         person: 'Mike Davis',     persons: ['Mike Davis', 'Sarah Williams', 'Manager John'],             row: 2 },
        { time: '11:00', duration: 35,  title: 'Stand-up Meeting',    person: 'Anna Tailor',    persons: ['Anna Tailor', 'Alex Johnson', 'Mike Davis', 'Manager John'], row: 0 },
        { time: '12:00', duration: 120, title: 'Client Presentation', person: 'Manager John',   persons: ['Manager John', 'Sarah Williams', 'Alex Johnson'],           row: 1 },
        { time: '14:00', duration: 60,  title: 'QA Testing',          person: 'Sarah Williams', persons: ['Sarah Williams', 'Anna Tailor'],                            row: 2 },
        { time: '15:30', duration: 90,  title: 'Backend Integration', person: 'Alex Johnson',   persons: ['Alex Johnson', 'Mike Davis', 'Anna Tailor'],                row: 0 },
    ];

    /** Team Schedule week events (dayIndex 0=Mon … 6=Sun) */
    const teamWeekEvents = [
        { dayIndex: 0, durationDays: 1, title: 'Sprint Planning',        person: 'Sarah Williams', persons: ['Sarah Williams', 'Alex Johnson', 'Mike Davis'],              row: 0 },
        { dayIndex: 0, durationDays: 3, title: 'Frontend Development',   person: 'Alex Johnson',   persons: ['Alex Johnson', 'Mike Davis'],                               row: 1 },
        { dayIndex: 0, durationDays: 2, title: 'Design Systems',         person: 'Mike Davis',     persons: ['Mike Davis', 'Anna Tailor', 'Sarah Williams'],               row: 2 },
        { dayIndex: 1, durationDays: 1, title: 'Backend API Build',      person: 'Anna Tailor',    persons: ['Anna Tailor', 'Alex Johnson'],                               row: 0 },
        { dayIndex: 1, durationDays: 2, title: 'Client Demo Prep',       person: 'Manager John',   persons: ['Manager John', 'Sarah Williams', 'Mike Davis'],             row: 1 },
        { dayIndex: 2, durationDays: 1, title: 'Code Review Session',    person: 'Alex Johnson',   persons: ['Alex Johnson', 'Anna Tailor', 'Manager John'],               row: 2 },
        { dayIndex: 3, durationDays: 1, title: 'Daily Stand-up',         person: 'Manager John',   persons: ['Manager John', 'Sarah Williams', 'Alex Johnson', 'Mike Davis'], row: 0 },
        { dayIndex: 3, durationDays: 2, title: 'QA & Bug Fixing',        person: 'Anna Tailor',    persons: ['Anna Tailor', 'Sarah Williams'],                              row: 1 },
        { dayIndex: 4, durationDays: 1, title: 'Performance Review',     person: 'Sarah Williams', persons: ['Sarah Williams', 'Manager John'],                            row: 2 },
        { dayIndex: 4, durationDays: 2, title: 'Release Notes Prep',     person: 'Mike Davis',     persons: ['Mike Davis', 'Alex Johnson', 'Anna Tailor'],                 row: 0 },
        { dayIndex: 5, durationDays: 1, title: 'Weekend Deployment',     person: 'Alex Johnson',   persons: ['Alex Johnson', 'Mike Davis'],                               row: 1 },
        { dayIndex: 6, durationDays: 1, title: 'On-Call Monitoring',     person: 'Anna Tailor',    persons: ['Anna Tailor', 'Alex Johnson'],                               row: 2 },
    ];

    /** Team Schedule month events (dateNum 1–31) */
    const teamMonthEvents = [
        { dateNum: 1,  durationDays: 2, title: 'Sprint Kickoff',             person: 'Manager John',   persons: ['Manager John', 'Sarah Williams', 'Alex Johnson'],           row: 0 },
        { dateNum: 1,  durationDays: 5, title: 'Frontend Feature Dev',       person: 'Alex Johnson',   persons: ['Alex Johnson', 'Mike Davis'],                               row: 1 },
        { dateNum: 2,  durationDays: 3, title: 'UI Component Library',       person: 'Mike Davis',     persons: ['Mike Davis', 'Anna Tailor', 'Sarah Williams'],               row: 2 },
        { dateNum: 5,  durationDays: 4, title: 'API Development',            person: 'Anna Tailor',    persons: ['Anna Tailor', 'Alex Johnson', 'Mike Davis'],                 row: 0 },
        { dateNum: 6,  durationDays: 2, title: 'Client Sync Meetings',       person: 'Sarah Williams', persons: ['Sarah Williams', 'Manager John'],                            row: 1 },
        { dateNum: 8,  durationDays: 6, title: 'Core System Build',          person: 'Alex Johnson',   persons: ['Alex Johnson', 'Anna Tailor', 'Mike Davis', 'Manager John'], row: 2 },
        { dateNum: 10, durationDays: 3, title: 'Database Optimisation',      person: 'Anna Tailor',    persons: ['Anna Tailor', 'Alex Johnson'],                               row: 0 },
        { dateNum: 12, durationDays: 2, title: 'Mid-Sprint Demo',            person: 'Manager John',   persons: ['Manager John', 'Sarah Williams', 'Mike Davis'],             row: 1 },
        { dateNum: 14, durationDays: 5, title: 'Integration Testing',        person: 'Sarah Williams', persons: ['Sarah Williams', 'Anna Tailor', 'Alex Johnson'],            row: 2 },
        { dateNum: 16, durationDays: 3, title: 'Security Audit',             person: 'Mike Davis',     persons: ['Mike Davis', 'Manager John', 'Anna Tailor'],                 row: 0 },
        { dateNum: 19, durationDays: 2, title: 'Performance Testing',        person: 'Alex Johnson',   persons: ['Alex Johnson', 'Mike Davis'],                               row: 1 },
        { dateNum: 20, durationDays: 4, title: 'User Acceptance Testing',    person: 'Anna Tailor',    persons: ['Anna Tailor', 'Sarah Williams', 'Alex Johnson', 'Mike Davis'], row: 2 },
        { dateNum: 23, durationDays: 2, title: 'Sprint Retrospective',       person: 'Manager John',   persons: ['Manager John', 'Sarah Williams', 'Alex Johnson'],           row: 0 },
        { dateNum: 24, durationDays: 3, title: 'Release Candidate Prep',     person: 'Sarah Williams', persons: ['Sarah Williams', 'Mike Davis', 'Anna Tailor'],               row: 1 },
        { dateNum: 26, durationDays: 4, title: 'Production Deployment',      person: 'Mike Davis',     persons: ['Mike Davis', 'Alex Johnson', 'Manager John'],               row: 2 },
        { dateNum: 28, durationDays: 3, title: 'Post-Release Monitoring',    person: 'Alex Johnson',   persons: ['Alex Johnson', 'Anna Tailor', 'Sarah Williams'],            row: 0 },
    ];

    // ─── My Schedule zoom & nav state ─────────────────────────
    const MY_ZOOM_LEVELS   = ['Day', 'Week', 'Month'];
    let myZoomIndex        = 0;
    let myBaseDate         = new Date();

    // ─── Team Schedule zoom & nav state ───────────────────────
    const TEAM_ZOOM_LEVELS = ['Day', 'Week', 'Month'];
    let teamZoomIndex      = 0;
    let teamBaseDate       = new Date();

    function formatPeriodLabel(zoom, d) {
        if (zoom === 'day') {
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        } else if (zoom === 'week') {
            const mon = new Date(d);
            mon.setDate(d.getDate() - ((d.getDay() + 6) % 7));
            const sun = new Date(mon);
            sun.setDate(mon.getDate() + 6);
            const mStr = mon.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            const sStr = sun.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            return `${mStr} - ${sStr}`;
        } else {
            return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        }
    }

    async function buildMyTimeline(zoom) {
        const ids = {
            headersEl:     document.getElementById('timelineHeaders'),
            gridEl:        document.getElementById('timelineGrid'),
            eventsEl:      document.getElementById('timelineEvents'),
            legendTarget:  zoom === 'day'
                             ? document.getElementById('myScheduleLegendContainer')
                             : null,
            indicatorEl:   document.getElementById('currentTimeIndicator'),
            scrollWrapper: document.getElementById('scheduleScrollWrapper'),
            baseDate:      myBaseDate,
            isMySchedule:  true,
        };
        const lbl = document.getElementById('myPeriodLabel');
        if (lbl) lbl.textContent = formatPeriodLabel(zoom, myBaseDate);

        // Fetch real tasks assigned to user
        let evts = [];
        try {
            const res = await fetch('api/fetch_my_schedule.php');
            if (res.ok) {
                const data = await res.json();
                if (data.success && data.events) {
                    evts = data.events;
                }
            }
        } catch (e) {
            console.error('[Timeline] Fetch error:', e);
        }

        // Filter events based on the current baseDate & zoom
        const year = myBaseDate.getFullYear();
        const month = myBaseDate.getMonth();
        const date = myBaseDate.getDate();

        let filtered = [];
        if (zoom === 'day') {
            filtered = evts.filter(e => {
                if (!e.due_date) return false;
                const d = new Date(e.due_date);
                return d.getFullYear() === year && d.getMonth() === month && d.getDate() === date;
            });
            buildDayView({ ...ids, events: filtered, legendTarget: null });
        } 
        else if (zoom === 'week') {
            const monday = new Date(myBaseDate);
            monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
            monday.setHours(0, 0, 0, 0);
            
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            sunday.setHours(23, 59, 59, 999);

            filtered = evts.filter(e => {
                if (!e.due_date) return false;
                const d = new Date(e.due_date);
                return d >= monday && d <= sunday;
            });
            buildWeekView({ ...ids, events: filtered });
        } 
        else if (zoom === 'month') {
            filtered = evts.filter(e => {
                if (!e.due_date) return false;
                const d = new Date(e.due_date);
                return d.getFullYear() === year && d.getMonth() === month;
            });
            buildMonthView({ ...ids, events: filtered });
        }
    }

    async function buildTeamTimeline(zoom) {
        const ids = {
            headersEl:     document.getElementById('teamTimelineHeaders'),
            gridEl:        document.getElementById('teamTimelineGrid'),
            eventsEl:      document.getElementById('teamTimelineEvents'),
            indicatorEl:   document.getElementById('teamCurrentTimeIndicator'),
            scrollWrapper: document.getElementById('teamScheduleScrollWrapper'),
            baseDate:      teamBaseDate,
            legendTarget:  zoom === 'day'
                             ? document.getElementById('teamScheduleLegendContainer')
                             : null,
            isMySchedule:  false,
        };
        const lbl = document.getElementById('teamPeriodLabel');
        if (lbl) lbl.textContent = formatPeriodLabel(zoom, teamBaseDate);

        let url = 'api/fetch_team_schedule.php';
        if (_selectedHierarchyUserId) {
            url += `?target_user_id=${_selectedHierarchyUserId}`;
        }

        let evts = [];
        try {
            const res = await fetch(url);
            if (res.ok) {
                const data = await res.json();
                if (data.success && data.events) {
                    evts = data.events;
                }
            }
        } catch (e) {
            console.error('[Timeline] Fetch error:', e);
        }

        const year = teamBaseDate.getFullYear();
        const month = teamBaseDate.getMonth();
        const date = teamBaseDate.getDate();

        let filtered = [];
        if (zoom === 'day') {
            filtered = evts.filter(e => {
                if (!e.due_date) return false;
                const d = new Date(e.due_date);
                return d.getFullYear() === year && d.getMonth() === month && d.getDate() === date;
            });
            buildDayView({ ...ids, events: filtered, legendTarget: ids.legendTarget });
        } 
        else if (zoom === 'week') {
            const monday = new Date(teamBaseDate);
            monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
            monday.setHours(0, 0, 0, 0);
            
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            sunday.setHours(23, 59, 59, 999);

            filtered = evts.filter(e => {
                if (!e.due_date) return false;
                const d = new Date(e.due_date);
                return d >= monday && d <= sunday;
            });
            buildWeekView({ ...ids, events: filtered });
        } 
        else if (zoom === 'month') {
            filtered = evts.filter(e => {
                if (!e.due_date) return false;
                const d = new Date(e.due_date);
                return d.getFullYear() === year && d.getMonth() === month;
            });
            buildMonthView({ ...ids, events: filtered });
        }
    }

    function initMyZoom() {
        const zoomOutBtn = document.getElementById('myZoomOutBtn');
        const zoomInBtn  = document.getElementById('myZoomInBtn');
        const zoomText   = document.getElementById('myZoomLevelText');
        if (!zoomOutBtn || !zoomInBtn || !zoomText) return;

        function refresh() {
            const level = MY_ZOOM_LEVELS[myZoomIndex];
            zoomText.textContent     = level;
            zoomOutBtn.style.opacity = myZoomIndex === 0 ? '0.35' : '1';
            zoomInBtn.style.opacity  = myZoomIndex === MY_ZOOM_LEVELS.length - 1 ? '0.35' : '1';
            myBaseDate = new Date(); // Reset to today on zoom switch
            buildMyTimeline(level.toLowerCase());
        }

        zoomOutBtn.addEventListener('click', () => { if (myZoomIndex > 0) { myZoomIndex--; refresh(); } });
        zoomInBtn.addEventListener('click',  () => { if (myZoomIndex < MY_ZOOM_LEVELS.length - 1) { myZoomIndex++; refresh(); } });
    }

    function initTeamZoom() {
        const zoomOutBtn = document.getElementById('teamZoomOutBtn');
        const zoomInBtn  = document.getElementById('teamZoomInBtn');
        const zoomText   = document.getElementById('teamZoomLevelText');
        if (!zoomOutBtn || !zoomInBtn || !zoomText) return;

        function refresh() {
            const level = TEAM_ZOOM_LEVELS[teamZoomIndex];
            zoomText.textContent     = level;
            zoomOutBtn.style.opacity = teamZoomIndex === 0 ? '0.35' : '1';
            zoomInBtn.style.opacity  = teamZoomIndex === TEAM_ZOOM_LEVELS.length - 1 ? '0.35' : '1';
            teamBaseDate = new Date(); // Reset to today on zoom switch
            buildTeamTimeline(level.toLowerCase());
        }

        zoomOutBtn.addEventListener('click', () => { if (teamZoomIndex > 0) { teamZoomIndex--; refresh(); } });
        zoomInBtn.addEventListener('click',  () => { if (teamZoomIndex < TEAM_ZOOM_LEVELS.length - 1) { teamZoomIndex++; refresh(); } });
    }

    function initMyNav() {
        const prev = document.getElementById('myPrevPeriodBtn');
        const next = document.getElementById('myNextPeriodBtn');
        const todayBtn = document.getElementById('myTodayBtn');
        if (!prev || !next || !todayBtn) return;

        function bump(dir) {
            const zoom = MY_ZOOM_LEVELS[myZoomIndex].toLowerCase();
            if (zoom === 'day')   myBaseDate.setDate(myBaseDate.getDate() + dir);
            if (zoom === 'week')  myBaseDate.setDate(myBaseDate.getDate() + dir * 7);
            if (zoom === 'month') myBaseDate.setMonth(myBaseDate.getMonth() + dir);
            buildMyTimeline(zoom);
        }

        prev.addEventListener('click', () => bump(-1));
        next.addEventListener('click', () => bump(1));
        todayBtn.addEventListener('click', () => {
            myBaseDate = new Date();
            buildMyTimeline(MY_ZOOM_LEVELS[myZoomIndex].toLowerCase());
        });
    }

    function initTeamNav() {
        const prev = document.getElementById('teamPrevPeriodBtn');
        const next = document.getElementById('teamNextPeriodBtn');
        const todayBtn = document.getElementById('teamTodayBtn');
        if (!prev || !next || !todayBtn) return;

        function bump(dir) {
            const zoom = TEAM_ZOOM_LEVELS[teamZoomIndex].toLowerCase();
            if (zoom === 'day')   teamBaseDate.setDate(teamBaseDate.getDate() + dir);
            if (zoom === 'week')  teamBaseDate.setDate(teamBaseDate.getDate() + dir * 7);
            if (zoom === 'month') teamBaseDate.setMonth(teamBaseDate.getMonth() + dir);
            buildTeamTimeline(zoom);
        }

        prev.addEventListener('click', () => bump(-1));
        next.addEventListener('click', () => bump(1));
        todayBtn.addEventListener('click', () => {
            teamBaseDate = new Date();
            buildTeamTimeline(TEAM_ZOOM_LEVELS[teamZoomIndex].toLowerCase());
        });
    }

    // ─────────────────────────────────────────────────────────
    //  TEAM HIERARCHY — loads real data from DB via API
    // ─────────────────────────────────────────────────────────

    /** Convert HSL string → hex (for inline CSS avatar backgrounds) */
    function hslToHex(hslStr) {
        const m = hslStr.match(/hsl\(\s*(\d+),\s*(\d+)%,\s*(\d+)%\s*\)/i);
        if (!m) return '#64748b';
        let h = parseInt(m[1]) / 360;
        const s = parseInt(m[2]) / 100;
        const l = parseInt(m[3]) / 100;
        let r, g, b;
        if (s === 0) {
            r = g = b = l;
        } else {
            const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
            const p = 2 * l - q;
            const hue2rgb = (p, q, t) => {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1/6) return p + (q - p) * 6 * t;
                if (t < 1/2) return q;
                if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                return p;
            };
            r = hue2rgb(p, q, h + 1/3);
            g = hue2rgb(p, q, h);
            b = hue2rgb(p, q, h - 1/3);
        }
        const toHex = x => Math.round(x * 255).toString(16).padStart(2, '0');
        return '#' + toHex(r) + toHex(g) + toHex(b);
    }

    let _lastHierarchyData = null;
    let _selectedHierarchyUserId = null;

    function handleHierarchyClick(userId) {
        _selectedHierarchyUserId = userId;
        
        // Re-render the side panel to update active state
        const content = document.getElementById('hierarchyContent');
        if (content && _lastHierarchyData) renderTreeToTarget(_lastHierarchyData, content, true);
        
        // Re-render the modal if it is open
        const modalBody = document.getElementById('hierModalBody');
        const modal = document.getElementById('hierFullModal');
        if (modal && modal.style.display !== 'none' && _lastHierarchyData) {
            renderTreeToTarget(_lastHierarchyData, modalBody, false);
        }

        // Fetch team timeline filtering by this user
        buildTeamTimeline(TEAM_ZOOM_LEVELS[teamZoomIndex].toLowerCase());
    }

    function renderHierarchy(data) {
        _lastHierarchyData = data;
        const loader = document.getElementById('hierarchyLoadingState');
        const content = document.getElementById('hierarchyContent');
        if (!content) return;
        if (loader) loader.style.display = 'none';
        content.style.display = 'block';

        renderTreeToTarget(data, content, true); // true = use max-height scroll wrap

        // Trigger a legend refresh now that hierarchy data is loaded
        const legendTarget = document.getElementById('teamScheduleLegendContainer');
        if (legendTarget) {
            _drawLegend(legendTarget, [], false);
        }
    }

    function renderTreeToTarget(data, targetEl, useScrollWrap) {
        targetEl.innerHTML = '';
        _hAnim = 0; // reset stagger

        const tree = data.tree || data.manager;
        if (!tree) {
            targetEl.innerHTML = '<div class="hier-empty">No hierarchy data.</div>';
            return;
        }

        // If nothing explicitly selected yet, YOU node is the active one structurally
        const isActiveNode = (_selectedHierarchyUserId == null) || (_selectedHierarchyUserId == tree.id);

        // ── YOU row ──────────────────────────────────────────────
        const youRow = document.createElement('div');
        youRow.className = 'hier-you-row';
        if (isActiveNode) {
            youRow.style.boxShadow = '0 0 0 2px rgba(99,102,241,0.2)';
            youRow.style.borderColor = '#6366f1';
        }
        youRow.style.cursor = 'pointer';
        youRow.innerHTML = `
            <div class="hier-you-avatar" style="background:${tree.color || '#6366f1'};">
                ${tree.initials}
            </div>
            <div class="hier-you-info">
                <span class="hier-you-name" title="${tree.name}">${tree.name}</span>
                <span class="hier-you-pos">${tree.position || tree.role || 'Manager'}</span>
            </div>
            <span class="hier-you-badge">YOU</span>`;
        
        youRow.addEventListener('click', () => handleHierarchyClick(tree.id));
        targetEl.appendChild(youRow);

        // ── Subordinate tree ──────────────────────────────────────
        if (tree.children && tree.children.length > 0) {
            const wrap = document.createElement('div');
            // If it's the sidebar wrapper, restrict height. If modal, let it flow naturally.
            if (useScrollWrap) {
                wrap.className = 'hier-scroll';
            } else {
                wrap.style.paddingLeft = '0.5rem';
                wrap.style.marginTop = '1rem';
            }
            wrap.appendChild(buildHierTree(tree.children));
            targetEl.appendChild(wrap);
        } else {
            const empty = document.createElement('div');
            empty.className = 'hier-empty';
            empty.innerHTML = `<i class="fa-solid fa-users-slash"></i><span>No direct reports found.</span>`;
            targetEl.appendChild(empty);
        }
    }

    let _hAnim = 0;
    function buildHierTree(nodes) {
        const ul = document.createElement('ul');
        ul.className = 'hier-tree';

        nodes.forEach(node => {
            _hAnim++;
            const li = document.createElement('li');
            li.className = 'hier-node';

            const avSize = 22;
            const color  = getColorForPerson(node.name);
            const hasKids = node.children && node.children.length > 0;

            const isActive = (node.id == _selectedHierarchyUserId);

            // Row
            const row = document.createElement('div');
            row.className = 'hier-node-row';
            row.style.animation = `tlFadeIn 0.28s ease ${_hAnim * 45}ms both`;
            row.style.opacity   = '0';
            row.style.cursor    = 'pointer';

            if (isActive) {
                row.style.background = '#e2e8f0';
                row.style.fontWeight = '700';
            }

            row.innerHTML = `
                <div class="hier-node-av"
                     style="width:${avSize}px;height:${avSize}px;background:${color};font-size:0.55rem;"
                     title="${node.name}">${node.initials}</div>
                <div class="hier-node-info">
                    <span class="hier-node-name" title="${node.name}">${node.name}</span>
                    <span class="hier-node-role">${node.position || node.role || 'Team Member'}</span>
                </div>`;

            row.addEventListener('click', (e) => {
                // if they click the chevron, don't trigger the row selection
                if (e.target.classList.contains('hier-chev')) return;
                handleHierarchyClick(node.id);
            });

            if (hasKids) {
                const chev = document.createElement('i');
                chev.className = 'fa-solid fa-chevron-down hier-chev';
                row.appendChild(chev);
                li.appendChild(row);

                const childUl = buildHierTree(node.children);
                li.appendChild(childUl);

                let open = true;
                chev.addEventListener('click', e => {
                    e.stopPropagation();
                    open = !open;
                    childUl.style.display = open ? '' : 'none';
                    chev.classList.toggle('hem-closed', !open);
                });
            } else {
                li.appendChild(row);
            }

            ul.appendChild(li);
        });

        return ul;
    }

    function showHierarchyError(msg) {
        const loader  = document.getElementById('hierarchyLoadingState');
        const content = document.getElementById('hierarchyContent');
        if (loader)  loader.style.display  = 'none';
        if (content) {
            content.style.display = 'block';
            content.innerHTML = `
                <div class="hier-empty">
                    <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;opacity:1;"></i>
                    <span style="color:#ef4444;">${msg || 'Failed to load hierarchy.'}</span>
                </div>`;
        }
    }

    function loadTeamHierarchy() {
        const loader  = document.getElementById('hierarchyLoadingState');
        const content = document.getElementById('hierarchyContent');
        if (!loader && !content) return;

        fetch('api/fetch_my_hierarchy.php')
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    _hAnim = 0;
                    renderHierarchy(data);
                } else {
                    showHierarchyError(data.error || 'Unknown error');
                }
            })
            .catch(err => {
                console.error('[TeamHierarchy] Fetch error:', err);
                showHierarchyError('Could not load hierarchy data.');
            });
    }

    function initHierarchyModal() {
        const openBtn = document.getElementById('openHierModalBtn');
        const closeBtn = document.getElementById('closeHierModalBtn');
        const modal = document.getElementById('hierFullModal');
        const modalBody = document.getElementById('hierModalBody');

        if (!openBtn || !closeBtn || !modal || !modalBody) return;

        openBtn.addEventListener('click', () => {
            modal.style.display = 'flex';
            // Render the tree inside the modal body without max-height scrolling (false)
            if (_lastHierarchyData) {
                renderTreeToTarget(_lastHierarchyData, modalBody, false);
            } else {
                modalBody.innerHTML = '<div class="hier-empty">Loading hierarchy...</div>';
            }
        });

        const closeModal = () => {
            modal.style.display = 'none';
        };

        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal(); // allow clicking the backdrop to close
        });
    }

    // ─── Public init ──────────────────────────────────────────
    function init() {
        injectStyles();

        // My Schedule (Day view default)
        buildMyTimeline('day');
        initMyZoom();
        initMyNav();

        // Team Schedule (Day view default)
        buildTeamTimeline('day');
        initTeamZoom();
        initTeamNav();

        // Load real Team Hierarchy from DB
        loadTeamHierarchy();
        initHierarchyModal();
    }

    global.ScheduleTimeline = { init };

})(window);
