<?php
// modals/stats_detail_modal.php
?>
<style>
    /* ── Stats Detail Modal ─────────────────────────────── */
    #statsDetailModal .modal-content {
        max-width: 660px;
        width: 95%;
        border-radius: 16px;
        box-shadow: 0 20px 60px -10px rgba(0,0,0,0.18);
        border: 1px solid #f1f5f9;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 90vh;
    }

    /* Header */
    .sdm-header {
        padding: 1.25rem 1.5rem 1rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #ffffff;
        border-bottom: 1px solid #f1f5f9;
        flex-shrink: 0;
    }
    .sdm-header-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .sdm-icon-wrap {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .sdm-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        line-height: 1.2;
    }
    .sdm-subtitle {
        font-size: 0.72rem;
        color: #94a3b8;
        margin-top: 2px;
        font-weight: 500;
    }

    /* Search bar */
    .sdm-search-bar {
        padding: 0.75rem 1.25rem;
        background: #f8fafc;
        border-bottom: 1px solid #f1f5f9;
        flex-shrink: 0;
    }
    .sdm-search-input {
        width: 100%;
        padding: 0.5rem 0.75rem 0.5rem 2.1rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-family: inherit;
        font-size: 0.84rem;
        color: #1e293b;
        background: #fff;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .sdm-search-input:focus {
        border-color: #818cf8;
        box-shadow: 0 0 0 3px rgba(129,140,248,0.12);
    }
    .sdm-search-wrap {
        position: relative;
    }
    .sdm-search-wrap svg {
        position: absolute;
        left: 9px;
        top: 50%;
        transform: translateY(-50%);
        width: 14px;
        height: 14px;
        color: #94a3b8;
        pointer-events: none;
    }

    /* List body */
    .sdm-body {
        flex: 1;
        overflow-y: auto;
        background: #fff;
    }

    /* Individual employee row */
    .sdm-row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.85rem 1.25rem;
        border-bottom: 1px solid #f8fafc;
        transition: background 0.15s;
    }
    .sdm-row:last-child { border-bottom: none; }
    .sdm-row:hover { background: #f8fafc; }

    .sdm-avatar {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        background: #e0e7ff;
        color: #4f46e5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.9rem;
        flex-shrink: 0;
        border: 1px solid #c7d2fe;
    }
    .sdm-user-info {
        flex: 1;
        min-width: 0;
    }
    .sdm-user-name {
        font-size: 0.88rem;
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sdm-user-meta {
        font-size: 0.72rem;
        color: #64748b;
        margin-top: 1px;
    }
    .sdm-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        flex-shrink: 0;
    }
    .sdm-time {
        font-size: 0.82rem;
        font-weight: 600;
        color: #334155;
        font-variant-numeric: tabular-nums;
    }

    /* Geo tag */
    .sdm-geo-wrap {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 3px;
    }
    .sdm-geo-tag {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.18rem 0.45rem;
        border-radius: 4px;
    }
    .sdm-geo-out  { background: #fef2f2; color: #dc2626; }
    .sdm-geo-in   { background: #f0fdf4; color: #16a34a; }
    .sdm-geo-rej  { background: #fef2f2; color: #dc2626; }

    /* Empty state */
    .sdm-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem 1.5rem;
        color: #94a3b8;
        gap: 0.5rem;
        font-size: 0.85rem;
        font-weight: 500;
    }

    /* Footer */
    .sdm-footer {
        padding: 0.65rem 1.25rem;
        background: #f8fafc;
        border-top: 1px solid #f1f5f9;
        font-size: 0.75rem;
        color: #94a3b8;
        text-align: center;
        flex-shrink: 0;
    }
</style>

<div id="statsDetailModal" class="modal-overlay" style="display:none; align-items:center; justify-content:center;">
    <div class="modal-content">

        <!-- Header -->
        <div class="sdm-header">
            <div class="sdm-header-left">
                <div class="sdm-icon-wrap" id="sdmIconWrap">
                    <i id="sdmIcon" data-lucide="users" style="width:18px;height:18px;color:#fff;"></i>
                </div>
                <div>
                    <h3 class="sdm-title" id="sdmTitle">Employee Details</h3>
                    <div class="sdm-subtitle" id="sdmSubtitle"></div>
                </div>
            </div>
            <button class="modal-close" onclick="closeStatsModal()"
                    style="background:none;border:none;cursor:pointer;color:#94a3b8;transition:0.2s;padding:0.25rem;border-radius:6px;"
                    onmouseover="this.style.background='#fef2f2';this.style.color='#ef4444'"
                    onmouseout="this.style.background='none';this.style.color='#94a3b8'">
                <i data-lucide="x" style="width:18px;height:18px;"></i>
            </button>
        </div>

        <!-- Search bar -->
        <div class="sdm-search-bar">
            <div class="sdm-search-wrap">
                <i data-lucide="search"></i>
                <input type="text" id="sdmSearchInput" class="sdm-search-input"
                       placeholder="Search by name or date..."
                       oninput="filterStatsModalList()">
            </div>
        </div>

        <!-- List -->
        <div class="sdm-body" id="sdmList">
            <!-- Populated by JS -->
        </div>

        <!-- Footer -->
        <div class="sdm-footer" id="sdmFooter"></div>

    </div>
</div>
