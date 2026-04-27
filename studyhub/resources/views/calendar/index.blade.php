{{-- resources/views/calendar/index.blade.php --}}
@extends('layouts.app')   {{-- swap for your actual layout --}}

@section('title', 'Calendar – StudyHub')

@push('styles')
<style>
    :root {
        --primary:#1a5f7a; --primary-dark:#144d61;
        --secondary:#f59e42; --accent:#ff6b6b;
        --success:#2a9d8f; --purple:#7c4dca;
        --bg-main:#fafbfc; --bg-sidebar:#ffffff; --bg-card:#ffffff;
        --text-primary:#1a1a1a; --text-secondary:#6b7280; --text-light:#9ca3af;
        --border:#e5e7eb;
        --shadow-sm:rgba(0,0,0,.03); --shadow-md:rgba(0,0,0,.08); --shadow-lg:rgba(0,0,0,.14);
        --sidebar-col:80px; --sidebar-full:280px; --right-w:300px;
    }
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'DM Sans',sans-serif;background:var(--bg-main);color:var(--text-primary);overflow-x:hidden}

    /* ── SIDEBAR ─────────────────────────────────────────────── */
    .sidebar{position:fixed;left:0;top:0;width:var(--sidebar-col);height:100vh;background:var(--bg-sidebar);border-right:1px solid var(--border);display:flex;flex-direction:column;transition:width .3s cubic-bezier(.4,0,.2,1);z-index:1000}
    .sidebar:hover{width:var(--sidebar-full)}
    .sidebar-header{padding:24px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:center}
    .logo{display:flex;align-items:center;gap:12px}
    .logo-icon{width:40px;height:40px;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);border-radius:12px;display:flex;align-items:center;justify-content:center;font-family:'Crimson Pro',serif;font-weight:700;font-size:20px;color:#fff;flex-shrink:0}
    .logo-text{font-family:'Crimson Pro',serif;font-size:24px;font-weight:700;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;opacity:0;width:0;overflow:hidden;white-space:nowrap;transition:all .3s ease}
    .sidebar:hover .logo-text{opacity:1;width:auto}
    .sidebar-nav{flex:1;padding:16px 12px;overflow-y:auto}
    .nav-item{display:flex;align-items:center;gap:16px;padding:14px 16px;margin-bottom:4px;border-radius:12px;color:var(--text-secondary);text-decoration:none;font-weight:500;font-size:15px;transition:all .3s ease;cursor:pointer;position:relative}
    .nav-item:hover{background:var(--bg-main);color:var(--primary)}
    .nav-item.active{background:linear-gradient(135deg,rgba(26,95,122,.08) 0%,rgba(245,158,66,.08) 100%);color:var(--primary);font-weight:600}
    .nav-item.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:4px;height:24px;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);border-radius:0 4px 4px 0}
    .nav-icon{width:24px;height:24px;flex-shrink:0}
    .nav-text{opacity:0;width:0;overflow:hidden;white-space:nowrap;transition:all .3s ease}
    .sidebar:hover .nav-text{opacity:1;width:auto}
    .nav-badge{margin-left:auto;background:var(--accent);color:#fff;font-size:11px;font-weight:700;padding:3px 8px;border-radius:12px;opacity:0;width:0;overflow:hidden;transition:all .3s ease}
    .sidebar:hover .nav-badge{opacity:1;width:auto}
    .sidebar-footer{padding:16px;border-top:1px solid var(--border)}
    .user-profile{display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;transition:all .3s ease;cursor:pointer;text-decoration:none}
    .user-profile:hover{background:var(--bg-main)}
    .user-avatar{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:16px;flex-shrink:0;overflow:hidden}
    .user-info{flex:1;opacity:0;width:0;overflow:hidden;transition:all .3s ease}
    .sidebar:hover .user-info{opacity:1;width:auto}
    .user-name{font-weight:600;font-size:14px;color:var(--text-primary);white-space:nowrap}
    .user-status{font-size:12px;color:var(--text-light)}

    /* ── TOP BAR ──────────────────────────────────────────────── */
    .top-bar{position:fixed;top:0;right:0;left:var(--sidebar-col);height:64px;background:var(--bg-sidebar);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 24px;gap:12px;z-index:900}
    .top-bar-left{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .top-bar-right{display:flex;align-items:center;gap:10px}
    .top-bar-btn{position:relative;width:40px;height:40px;border-radius:10px;border:1px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;color:var(--text-secondary);text-decoration:none}
    .top-bar-btn:hover{background:var(--bg-main);color:var(--primary);border-color:var(--primary)}
    .top-bar-btn svg{width:20px;height:20px}
    .notif-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;background:var(--accent);border-radius:50%;border:2px solid #fff}
    .top-bar-avatar{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;cursor:pointer;border:none;overflow:hidden;transition:all .2s}
    .top-bar-avatar:hover{opacity:.85;transform:scale(.97)}
    .btn-add-event{display:flex;align-items:center;gap:8px;padding:9px 20px;background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dark) 100%);color:#fff;border:none;border-radius:10px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:14px;cursor:pointer;transition:all .3s}
    .btn-add-event:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(26,95,122,.35)}
    .btn-add-event svg{width:16px;height:16px}
    .btn-select-mode{display:flex;align-items:center;gap:6px;padding:8px 14px;border:1px solid var(--border);background:#fff;border-radius:10px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:13px;color:var(--text-secondary);cursor:pointer;transition:all .2s;white-space:nowrap}
    .btn-select-mode:hover{border-color:var(--accent);color:var(--accent)}
    .btn-select-mode.active{border-color:var(--accent);color:var(--accent);background:#fff5f5}
    .btn-select-mode svg{width:15px;height:15px;flex-shrink:0}
    .bulk-bar{display:none;align-items:center;gap:10px;padding:8px 14px;background:#fff3f3;border:1.5px solid #fecaca;border-radius:10px;font-size:13px;font-weight:600;color:#dc2626;white-space:nowrap}
    .bulk-bar.visible{display:flex}
    .bulk-bar-count{min-width:90px}
    .btn-bulk-delete{padding:7px 16px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:12px;cursor:pointer;transition:all .2s;white-space:nowrap}
    .btn-bulk-delete:hover{background:#b91c1c}
    .btn-bulk-cancel{padding:7px 12px;background:transparent;border:1px solid #fecaca;color:#dc2626;border-radius:8px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:12px;cursor:pointer;white-space:nowrap}
    .btn-bulk-cancel:hover{background:#fee2e2}

    /* ── LAYOUT ──────────────────────────────────────────────── */
    .main-content{margin-left:var(--sidebar-col);margin-top:64px;min-height:calc(100vh - 64px);padding:28px 24px;display:flex;gap:24px;align-items:flex-start}
    .center-column{flex:1;min-width:0;display:flex;flex-direction:column;gap:20px}

    /* ── MONTH CALENDAR ─────────────────────────────────────── */
    .calendar-card{background:var(--bg-card);border-radius:20px;border:1px solid var(--border);padding:28px;box-shadow:0 4px 16px var(--shadow-sm)}
    .cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:10px}
    .cal-nav-group{display:flex;align-items:center;gap:10px}
    .cal-nav-btn{width:36px;height:36px;border-radius:10px;border:1px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;color:var(--text-secondary)}
    .cal-nav-btn:hover{background:var(--bg-main);color:var(--primary);border-color:var(--primary)}
    .cal-nav-btn svg{width:16px;height:16px}
    .cal-month-title{font-family:'Crimson Pro',serif;font-size:26px;font-weight:700;color:var(--text-primary)}
    .cal-today-btn{padding:7px 16px;border:1px solid var(--border);background:#fff;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;color:var(--text-secondary);transition:all .2s}
    .cal-today-btn:hover{border-color:var(--primary);color:var(--primary)}
    .cal-view-toggle{display:flex;background:var(--bg-main);border-radius:10px;padding:4px;gap:2px}
    .view-btn{padding:7px 14px;border-radius:8px;border:none;background:transparent;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all .2s}
    .view-btn.active{background:#fff;color:var(--primary);box-shadow:0 2px 8px var(--shadow-md)}

    /* Month grid */
    .cal-weekdays{display:grid;grid-template-columns:repeat(7,1fr);margin-bottom:6px}
    .cal-weekday{text-align:center;font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.06em;padding:8px 0}
    .cal-days{display:grid;grid-template-columns:repeat(7,1fr);gap:3px}
    .cal-day{min-height:84px;border-radius:10px;padding:7px;cursor:pointer;transition:all .15s;position:relative;border:1.5px solid transparent;user-select:none}
    .cal-day:hover{background:var(--bg-main)}
    .cal-day.other-month{opacity:.38}
    .cal-day.today{background:linear-gradient(135deg,rgba(26,95,122,.06) 0%,rgba(245,158,66,.06) 100%);border-color:var(--primary)}
    .day-check{display:none;position:absolute;top:5px;right:5px;width:18px;height:18px;border-radius:50%;border:2px solid var(--border);background:#fff;z-index:2;align-items:center;justify-content:center;cursor:pointer;transition:all .15px;pointer-events:auto}
    body.select-mode .cal-day:not(.other-month) .day-check{display:flex}
    .cal-day.day-sel .day-check{border-color:var(--accent);background:var(--accent)}
    .cal-day.day-sel .day-check::after{content:'';width:8px;height:5px;border-left:2px solid #fff;border-bottom:2px solid #fff;transform:rotate(-45deg) translateY(-1px)}
    .cal-day.day-sel{background:#fff5f5 !important;border-color:var(--accent) !important}
    .day-num{font-size:13px;font-weight:600;color:var(--text-primary);width:26px;height:26px;display:flex;align-items:center;justify-content:center;border-radius:50%;margin-bottom:3px}
    .cal-day.today .day-num{background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);color:#fff}
    .day-events{display:flex;flex-direction:column;gap:2px}
    .day-event-chip{font-size:10px;font-weight:600;padding:2px 5px;border-radius:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .chip-deadline{background:rgba(255,107,107,.14);color:#dc2626}
    .chip-class{background:rgba(42,157,143,.14);color:#0f766e}
    .chip-group{background:rgba(124,77,202,.14);color:#7c3aed}
    .chip-event{background:rgba(26,95,122,.12);color:var(--primary)}

    /* ── WEEK VIEW ──────────────────────────────────────────── */
    #weekView{display:none}
    .week-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
    .week-col{border-radius:12px;border:1px solid var(--border);overflow:hidden;min-height:160px}
    .week-col-header{padding:10px 8px;text-align:center;background:var(--bg-main);border-bottom:1px solid var(--border)}
    .week-col-day{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-light)}
    .week-col-num{font-size:22px;font-weight:700;color:var(--text-primary);margin-top:2px}
    .week-col.today .week-col-num{color:var(--primary)}
    .week-col.today .week-col-header{background:linear-gradient(135deg,rgba(26,95,122,.07),rgba(245,158,66,.07))}
    .week-events{padding:6px;display:flex;flex-direction:column;gap:4px}
    .week-event-chip{font-size:11px;font-weight:600;padding:5px 7px;border-radius:7px;cursor:pointer;line-height:1.3;transition:opacity .15s}
    .week-event-chip:hover{opacity:.8}
    .week-no-events{font-size:11px;color:var(--text-light);text-align:center;padding:14px 6px}

    /* ── UPCOMING ────────────────────────────────────────────── */
    .upcoming-card{background:var(--bg-card);border-radius:20px;border:1px solid var(--border);padding:24px;box-shadow:0 4px 16px var(--shadow-sm)}
    .section-title{font-family:'Crimson Pro',serif;font-size:20px;font-weight:700;color:var(--text-primary);margin-bottom:16px;display:flex;align-items:center;gap:8px}
    .upcoming-list{display:flex;flex-direction:column;gap:8px}
    .upcoming-item{display:flex;align-items:center;gap:12px;padding:13px 16px;border-radius:12px;border:1px solid var(--border);transition:all .2s;cursor:pointer;position:relative}
    .upcoming-item:hover{border-color:var(--primary);box-shadow:0 4px 12px var(--shadow-sm);transform:translateY(-1px)}
    .upcoming-item.item-sel{background:#fff5f5;border-color:var(--accent)}
    .upcoming-check{display:none;width:18px;height:18px;border-radius:50%;border:2px solid var(--border);flex-shrink:0;align-items:center;justify-content:center;cursor:pointer;transition:all .15s;background:#fff}
    body.select-mode .upcoming-check{display:flex}
    .upcoming-item.item-sel .upcoming-check{border-color:var(--accent);background:var(--accent)}
    .upcoming-item.item-sel .upcoming-check::after{content:'';width:8px;height:5px;border-left:2px solid #fff;border-bottom:2px solid #fff;transform:rotate(-45deg) translateY(-1px)}
    .upcoming-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
    .upcoming-info{flex:1;min-width:0}
    .upcoming-title{font-size:14px;font-weight:600;color:var(--text-primary)}
    .upcoming-sub{font-size:12px;color:var(--text-secondary);margin-top:2px}
    .upcoming-tag{font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;white-space:nowrap}

    /* ── RIGHT SIDEBAR ──────────────────────────────────────── */
    .right-sidebar{width:var(--right-w);flex-shrink:0;display:flex;flex-direction:column;gap:20px;position:sticky;top:calc(64px + 28px);max-height:calc(100vh - 64px - 56px);overflow-y:auto}
    .right-sidebar::-webkit-scrollbar{width:4px}
    .right-sidebar::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
    @media(max-width:1100px){.right-sidebar{display:none}}
    .widget-card{background:var(--bg-card);border-radius:16px;border:1px solid var(--border);padding:20px}
    .widget-title{font-family:'Crimson Pro',serif;font-size:18px;font-weight:700;color:var(--text-primary);margin-bottom:16px}
    .deadline-item{display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)}
    .deadline-item:last-child{border-bottom:none;padding-bottom:0}
    .deadline-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
    .deadline-info{flex:1;min-width:0}
    .deadline-title{font-size:13px;font-weight:600;color:var(--text-primary)}
    .deadline-subject{font-size:11px;color:var(--text-secondary);margin-top:2px}
    .deadline-due{font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px;white-space:nowrap;flex-shrink:0}
    .due-urgent{background:rgba(255,107,107,.12);color:#dc2626}
    .due-soon{background:rgba(245,158,66,.12);color:#d97706}
    .due-normal{background:rgba(26,95,122,.1);color:var(--primary)}
    .cal-category{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;cursor:pointer;transition:all .2s;margin-bottom:6px;border:1.5px solid transparent}
    .cal-category:last-child{margin-bottom:0}
    .cal-category:hover{background:var(--bg-main)}
    .cal-category-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0}
    .cal-category-info{flex:1;min-width:0}
    .cal-category-name{font-size:13px;font-weight:600;color:var(--text-primary)}
    .cal-category-meta{font-size:11px;color:var(--text-secondary);margin-top:1px}
    .cal-category-toggle{width:16px;height:16px;border-radius:4px;border:2px solid currentColor;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:all .2s}
    .cal-category.active .cal-category-toggle{background:currentColor}
    .cal-category.active .cal-category-toggle::after{content:'';width:8px;height:5px;border-left:2px solid #fff;border-bottom:2px solid #fff;transform:rotate(-45deg) translateY(-1px)}

    /* ── DAY POPOVER ────────────────────────────────────────── */
    /* FIX: use visibility+opacity instead of display so closePopover works cleanly */
    .day-popover{
        position:fixed;z-index:1500;background:#fff;border-radius:16px;
        border:1px solid var(--border);box-shadow:0 16px 40px var(--shadow-lg);
        padding:20px;width:284px;
        visibility:hidden;opacity:0;pointer-events:none;
        transition:opacity .18s,visibility .18s;
        transform:translateY(6px);
    }
    .day-popover.open{
        visibility:visible;opacity:1;pointer-events:all;
        transform:translateY(0);transition:opacity .18s,visibility .18s,transform .18s;
    }
    .popover-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    .popover-date{font-family:'Crimson Pro',serif;font-size:17px;font-weight:700;color:var(--text-primary)}
    .popover-close-btn{width:26px;height:26px;border:none;border-radius:6px;background:var(--bg-main);cursor:pointer;color:var(--text-secondary);font-size:16px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .popover-close-btn:hover{background:#fee2e2;color:var(--accent)}
    .popover-events{display:flex;flex-direction:column;gap:6px;max-height:230px;overflow-y:auto}
    .popover-event{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:8px;background:var(--bg-main);cursor:pointer;transition:all .15s}
    .popover-event:hover{background:#f0f4f8}
    .popover-event-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
    .popover-event-info{flex:1;min-width:0}
    .popover-event-title{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .popover-event-time{font-size:11px;color:var(--text-light);margin-top:1px}
    .popover-ev-del{width:22px;height:22px;border:none;border-radius:5px;background:transparent;cursor:pointer;color:var(--text-light);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s;opacity:0}
    .popover-event:hover .popover-ev-del{opacity:1}
    .popover-ev-del:hover{background:#fee2e2;color:var(--accent)}
    .popover-ev-del svg{width:13px;height:13px}
    .popover-empty{font-size:13px;color:var(--text-light);text-align:center;padding:12px 0}
    .popover-add-btn{width:100%;margin-top:12px;padding:9px;border:1.5px dashed var(--border);background:none;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all .2s}
    .popover-add-btn:hover{border-color:var(--primary);color:var(--primary);background:rgba(26,95,122,.04)}

    /* ── MODALS ─────────────────────────────────────────────── */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:2000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s}
    .modal-overlay.open{opacity:1;pointer-events:all}
    .modal{background:#fff;border-radius:20px;width:90%;max-width:500px;padding:28px;transform:scale(.95);transition:transform .2s;box-shadow:0 20px 60px rgba(0,0,0,.18)}
    .modal-overlay.open .modal{transform:scale(1)}
    .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
    .modal-title{font-family:'Crimson Pro',serif;font-size:22px;font-weight:700}
    .modal-close{width:32px;height:32px;border-radius:8px;border:none;background:var(--bg-main);cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;color:var(--text-secondary)}
    .modal-close:hover{background:#fee2e2;color:var(--accent)}
    .form-group{margin-bottom:14px}
    .form-label{display:block;font-size:13px;font-weight:600;color:var(--text-secondary);margin-bottom:6px}
    .form-input,.form-select,.form-textarea{width:100%;padding:10px 14px;border:2px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;transition:border-color .2s;background:#fff;color:var(--text-primary)}
    .form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--primary)}
    .form-textarea{min-height:72px;resize:vertical}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .modal-actions{display:flex;gap:10px;justify-content:space-between;margin-top:20px;align-items:center;flex-wrap:wrap}
    .modal-right{display:flex;gap:10px}
    .btn-cancel{padding:10px 22px;border-radius:10px;border:1px solid var(--border);background:#fff;font-family:'DM Sans',sans-serif;font-weight:600;cursor:pointer;color:var(--text-secondary)}
    .btn-cancel:hover{background:var(--bg-main)}
    .btn-save{padding:10px 26px;border-radius:10px;border:none;background:linear-gradient(135deg,var(--primary) 0%,var(--primary-dark) 100%);color:#fff;font-family:'DM Sans',sans-serif;font-weight:600;cursor:pointer}
    .btn-save:hover{opacity:.9}
    .btn-delete-ev{padding:10px 20px;border-radius:10px;border:1.5px solid var(--accent);background:#fff;color:var(--accent);font-family:'DM Sans',sans-serif;font-weight:600;cursor:pointer;transition:all .2s;display:none}
    .btn-delete-ev:hover{background:#fff0f0}
    .recur-toggle{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:500;color:var(--text-secondary)}
    .recur-toggle input{width:16px;height:16px;accent-color:var(--primary);cursor:pointer}
    .recur-days-group{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
    .rday{padding:5px 10px;border:1.5px solid var(--border);border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;background:#fff;color:var(--text-secondary);transition:all .15s;font-family:'DM Sans',sans-serif}
    .rday.sel{border-color:var(--primary);background:rgba(26,95,122,.08);color:var(--primary)}
    .confirm-modal{max-width:400px}
    .confirm-body{font-size:15px;color:var(--text-secondary);margin-bottom:20px;line-height:1.6}
    .confirm-count{font-weight:700;color:var(--text-primary)}
    .btn-confirm-del{padding:10px 26px;border-radius:10px;border:none;background:var(--accent);color:#fff;font-family:'DM Sans',sans-serif;font-weight:600;cursor:pointer}
    .btn-confirm-del:hover{background:#e05555}
    .state-box{text-align:center;padding:28px;color:var(--text-light);font-size:14px}
    .state-box.err{color:#dc2626;background:#fff5f5;border-radius:12px;border:1px solid #fecaca}
    .spinner{width:22px;height:22px;border:3px solid var(--border);border-top-color:var(--primary);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 8px}
    @keyframes spin{to{transform:rotate(360deg)}}
</style>
@endpush

@section('content')

{{-- ── SIDEBAR ─────────────────────────────────────────────────────────────── --}}
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">S</div>
            <span class="logo-text">StudyHub</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        @php
        $navItems = [
            ['route' => 'dashboard',    'label' => 'Newsfeed',     'icon' => '<path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>'],
            ['route' => 'calendar',     'label' => 'Calendar',     'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
            ['route' => 'study-groups','label' => 'Study Groups',  'icon' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>'],
            ['route' => 'resources',    'label' => 'Resources',    'icon' => '<path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>'],
            ['route' => 'notifications','label' => 'Notifications','icon' => '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>', 'badge' => 5],
            ['route' => 'messages',     'label' => 'Messages',     'icon' => '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>'],
            ['route' => 'focus-mode',   'label' => 'Focus Mode',   'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>'],
            ['route' => 'profile',      'label' => 'Profile',      'icon' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
            ['route' => 'settings',     'label' => 'Settings',     'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
        ];
        @endphp

        @foreach($navItems as $item)
        <a href="{{ route($item['route']) }}"
           class="nav-item {{ request()->routeIs($item['route']) ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                {!! $item['icon'] !!}
            </svg>
            <span class="nav-text">{{ $item['label'] }}</span>
            @isset($item['badge'])
                <span class="nav-badge">{{ $item['badge'] }}</span>
            @endisset
        </a>
        @endforeach
    </nav>
    <div class="sidebar-footer">
        <a href="{{ route('profile') }}" class="user-profile">
            <div class="user-avatar">
                {{ strtoupper(substr(session('user_first_name', 'U'), 0, 1) . substr(session('user_last_name', 'U'), 0, 1)) }}
            </div>
            <div class="user-info">
                <div class="user-name">
                    {{ trim((session('user_first_name') ?? '') . ' ' . (session('user_last_name') ?? '')) ?: (session('user_username') ?? 'You') }}
                </div>
                <div class="user-status">Online</div>
            </div>
        </a>
    </div>
</aside>

{{-- ── TOP BAR ─────────────────────────────────────────────────────────────── --}}
<div class="top-bar">
    <div class="top-bar-left">
        <button class="btn-add-event" id="btnAdd">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Event
        </button>
        <button class="btn-select-mode" id="btnSelectMode">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            Select
        </button>
        <div class="bulk-bar" id="bulkBar">
            <span class="bulk-bar-count" id="bulkCount">0 selected</span>
            <button class="btn-bulk-cancel" id="btnBulkCancel">Cancel</button>
            <button class="btn-bulk-delete" id="btnBulkDelete">🗑 Delete Selected</button>
        </div>
    </div>
    <div class="top-bar-right">
        <a href="{{ route('notifications') }}" class="top-bar-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            <span class="notif-dot"></span>
        </a>
        <button class="top-bar-avatar" onclick="window.location='{{ route('profile') }}'">
            {{ strtoupper(substr(session('user_first_name', 'U'), 0, 1) . substr(session('user_last_name', 'U'), 0, 1)) }}
        </button>
    </div>
</div>

{{-- ── MAIN ─────────────────────────────────────────────────────────────────── --}}
<main class="main-content">
    <div class="center-column">

        {{-- Calendar card (month + week in same card) --}}
        <div class="calendar-card">
            <div class="cal-header">
                <div class="cal-nav-group">
                    <button class="cal-nav-btn" id="btnPrev">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <h2 class="cal-month-title" id="calTitle"></h2>
                    <button class="cal-nav-btn" id="btnNext">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                    <button class="cal-today-btn" id="btnToday">Today</button>
                </div>
                <div class="cal-view-toggle">
                    <button class="view-btn active" data-view="month">Month</button>
                    <button class="view-btn"        data-view="week">Week</button>
                </div>
            </div>

            {{-- Month view --}}
            <div id="monthView">
                <div class="cal-weekdays">
                    <div class="cal-weekday">Sun</div><div class="cal-weekday">Mon</div>
                    <div class="cal-weekday">Tue</div><div class="cal-weekday">Wed</div>
                    <div class="cal-weekday">Thu</div><div class="cal-weekday">Fri</div>
                    <div class="cal-weekday">Sat</div>
                </div>
                <div class="cal-days" id="calDays">
                    <div class="state-box" style="grid-column:1/-1"><div class="spinner"></div>Loading…</div>
                </div>
            </div>

            {{-- Week view --}}
            <div id="weekView">
                <div id="weekSummaryBar" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;"></div>
                <div style="border:1px solid var(--border);border-radius:16px;overflow:hidden;">
                    <div id="weekGrid"></div>
                </div>
            </div>

        </div> {{-- ✅ closes .calendar-card --}}

        {{-- Upcoming --}}
        <div class="upcoming-card">
            <div class="section-title">📅 Upcoming This Week</div>
            <div class="upcoming-list" id="upcomingList">
                <div class="state-box"><div class="spinner"></div>Loading…</div>
            </div>
        </div>

    </div> {{-- ✅ closes .center-column --}}

    {{-- Right sidebar --}}
    <aside class="right-sidebar">
        <div class="widget-card">
            <div class="widget-title">⏰ Deadlines</div>
            <div id="deadlinesList"><div class="state-box"><div class="spinner"></div></div></div>
        </div>
        <div class="widget-card">
            <div class="widget-title">📁 My Calendars</div>
            <div id="myCalendars"></div>
        </div>
    </aside>
</main>

{{-- ── DAY POPOVER ─────────────────────────────────────────────────────────── --}}
<div class="day-popover" id="dayPopover">
    <div class="popover-header">
        <div class="popover-date" id="popDate"></div>
        {{-- FIX: stopPropagation so the document click-outside handler doesn't fight it --}}
        <button class="popover-close-btn" id="btnPopClose">✕</button>
    </div>
    <div class="popover-events" id="popEvents"></div>
    <button class="popover-add-btn" id="btnPopAdd">+ Add event on this day</button>
</div>

{{-- ── EVENT MODAL ─────────────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="eventModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Add Event</span>
            <button class="modal-close" id="btnModalClose">✕</button>
        </div>
        <div class="form-group">
            <label class="form-label">Event Title *</label>
            <input type="text" class="form-input" id="evTitle" placeholder="e.g. Math Assignment Due" maxlength="120">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Date *</label>
                <input type="date" class="form-input" id="evDate">
            </div>
            <div class="form-group">
                <label class="form-label">Time</label>
                <input type="time" class="form-input" id="evTime">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Category</label>
            <select class="form-select" id="evCat">
                <option value="deadline">📌 Deadline</option>
                <option value="class">📗 Class Schedule</option>
                <option value="group">👥 Study Group</option>
                <option value="event">📅 Event</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea class="form-textarea" id="evDesc" placeholder="Room, link, notes…"></textarea>
        </div>
        <div class="form-group">
            <label class="recur-toggle">
                <input type="checkbox" id="evRecur"> Repeating event (weekly)
            </label>
            <div id="recurOpts" style="display:none;margin-top:10px">
                <div class="form-label" style="margin-bottom:6px">Repeat on</div>
                <div class="recur-days-group">
                    @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                        <button type="button" class="rday" data-d="{{ $day }}">{{ $day }}</button>
                    @endforeach
                </div>
                <div style="margin-top:10px">
                    <label class="form-label">Repeat until</label>
                    <input type="date" class="form-input" id="evRecurEnd">
                </div>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-delete-ev" id="btnDelEv">Delete Event</button>
            <div class="modal-right">
                <button class="btn-cancel" id="btnModalCancel">Cancel</button>
                <button class="btn-save" id="btnSaveEv">Save Event</button>
            </div>
        </div>
    </div>
</div>

{{-- ── CONFIRM DELETE MODAL ─────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="confirmModal">
    <div class="modal confirm-modal">
        <div class="modal-header">
            <span class="modal-title" id="confirmTitle">Delete</span>
            <button class="modal-close" id="btnConfirmClose">✕</button>
        </div>
        <p class="confirm-body" id="confirmBody"></p>
        <div class="modal-actions" style="justify-content:flex-end">
            <div class="modal-right">
                <button class="btn-cancel" id="btnConfirmCancel">Cancel</button>
                <button class="btn-confirm-del" id="btnConfirmDel">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ═══════════════════════════════════════════════════════════════════
// CONFIG  (injected safely from the controller, not from .env directly)
// ═══════════════════════════════════════════════════════════════════
const SB_URL  = @json($supabaseUrl);
const SB_ANON = @json($supabaseAnonKey);
const SB_SVC  = @json($supabaseSvcKey);
const TABLE   = 'calendar_events';
const UID     = @json($userId);

// ═══════════════════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════════════════
let curDate    = new Date();
let allEvents  = [];
let expanded   = [];
let filters    = { deadline:true, class:true, group:true, event:true };
let selectMode = false;
let selIds     = new Set();
let editId     = null;
let pendDel    = null;
let curView    = 'month';   // 'month' | 'week'

// ═══════════════════════════════════════════════════════════════════
// SUPABASE HELPERS
// ═══════════════════════════════════════════════════════════════════
const hdrs = (write = false) => ({
    'apikey'       : write ? SB_SVC : SB_ANON,
    'Authorization': `Bearer ${write ? SB_SVC : SB_ANON}`,
    'Content-Type' : 'application/json',
    // FIX: tell PostgREST which schema to use — prevents "column not in schema cache"
    'Accept-Profile': 'public',
    'Content-Profile': 'public',
});

async function sbReq(path, opts = {}) {
    const r = await fetch(`${SB_URL}/rest/v1/${path}`, opts);
    if (!r.ok) {
        const e = await r.json().catch(() => ({}));
        throw new Error(e.message || e.error || `HTTP ${r.status}`);
    }
    return r.status === 204 ? null : r.json();
}

async function dbLoad() {
    allEvents = await sbReq(
        `${TABLE}?user_id=eq.${UID}&order=event_date.asc,event_time.asc`,
        { headers: hdrs() }
    );
}
async function dbInsert(data) {
    const [row] = await sbReq(TABLE, {
        method : 'POST',
        headers: { ...hdrs(true), 'Prefer': 'return=representation' },
        body   : JSON.stringify({ ...data, user_id: UID }),
    });
    return row;
}
async function dbUpdate(id, data) {
    const [row] = await sbReq(`${TABLE}?id=eq.${id}`, {
        method : 'PATCH',
        headers: { ...hdrs(true), 'Prefer': 'return=representation' },
        body   : JSON.stringify(data),
    });
    return row;
}
async function dbDelete(ids) {
    const list = ids.map(i => `"${i}"`).join(',');
    await sbReq(`${TABLE}?id=in.(${list})`, { method: 'DELETE', headers: hdrs(true) });
}

// ═══════════════════════════════════════════════════════════════════
// RECURRING EXPANSION
// ═══════════════════════════════════════════════════════════════════
const DN = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

function expandAll() {
    const vs = new Date(curDate.getFullYear(), curDate.getMonth() - 1, 1);
    const ve = new Date(curDate.getFullYear(), curDate.getMonth() + 2, 0);
    const rows = [];
    for (const ev of allEvents) {
        if (!ev.is_recurring || !ev.recur_days?.length) {
            rows.push({ ...ev, idate: ev.event_date });
            continue;
        }
        const start   = new Date(ev.event_date + 'T00:00:00');
        const rEnd    = ev.recur_end ? new Date(ev.recur_end + 'T00:00:00') : ve;
        const walkEnd = rEnd < ve ? rEnd : ve;
        let c = start < vs ? new Date(vs) : new Date(start);
        while (c <= walkEnd) {
            if (ev.recur_days.includes(DN[c.getDay()]))
                rows.push({ ...ev, idate: fd(c) });
            c.setDate(c.getDate() + 1);
        }
    }
    expanded = rows;
}

// ═══════════════════════════════════════════════════════════════════
// CONSTANTS
// ═══════════════════════════════════════════════════════════════════
const CC = { deadline:'#dc2626', class:'#0f766e', group:'#7c3aed', event:'#1a5f7a' };
const CB = { deadline:'rgba(255,107,107,.13)', class:'rgba(42,157,143,.13)', group:'rgba(124,77,202,.13)', event:'rgba(26,95,122,.11)' };
const CI = { deadline:'📌', class:'📗', group:'👥', event:'📅' };
const CL = { deadline:'Deadline', class:'Class', group:'Study Group', event:'Event' };

// ═══════════════════════════════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', async () => {
    wireUI();
    try {
        await dbLoad();
        expandAll();
    } catch (err) {
        document.getElementById('calDays').innerHTML =
            `<div class="state-box err" style="grid-column:1/-1">⚠️ ${esc(err.message)}</div>`;
        return;
    }
    redraw();
});

function wireUI() {
    // Navigation
    document.getElementById('btnPrev').onclick  = () => { navigate(-1); };
    document.getElementById('btnNext').onclick  = () => { navigate(+1); };
    document.getElementById('btnToday').onclick = () => { curDate = new Date(); expandAll(); redraw(); };

    // View toggle — FIX: actually switches between month and week
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            curView = btn.dataset.view;
            redraw();
        });
    });

    // Add event
    document.getElementById('btnAdd').onclick = () => openEvModal();

    // Popover — FIX: stopPropagation on close so document handler can't interfere
    document.getElementById('btnPopClose').addEventListener('click', e => {
        e.stopPropagation();
        closePopover();
    });
    document.getElementById('btnPopAdd').onclick = () => {
        const d = document.getElementById('btnPopAdd').dataset.date;
        closePopover();
        openEvModal(null, d);
    };

    // Select mode
    document.getElementById('btnSelectMode').onclick = toggleSelectMode;
    document.getElementById('btnBulkCancel').onclick = exitSelectMode;
    document.getElementById('btnBulkDelete').onclick = promptBulkDelete;

    // Event modal
    document.getElementById('btnModalClose').onclick  = closeEvModal;
    document.getElementById('btnModalCancel').onclick = closeEvModal;
    document.getElementById('btnSaveEv').onclick      = saveEv;
    document.getElementById('btnDelEv').onclick       = () => promptSingleDelete(editId);
    document.getElementById('evRecur').onchange       = e => {
        document.getElementById('recurOpts').style.display = e.target.checked ? 'block' : 'none';
    };
    document.querySelectorAll('.rday').forEach(b => b.onclick = () => b.classList.toggle('sel'));

    // Confirm modal
    document.getElementById('btnConfirmClose').onclick  = closeConfirm;
    document.getElementById('btnConfirmCancel').onclick = closeConfirm;
    document.getElementById('btnConfirmDel').onclick    = execDelete;

    // Close popover on outside click
    // FIX: check the popover is actually open before closing, preventing false triggers
    document.addEventListener('click', e => {
        if (!document.getElementById('dayPopover').classList.contains('open')) return;
        if (!e.target.closest('.cal-day') && !e.target.closest('#dayPopover')) {
            closePopover();
        }
    });
}

// Navigate: month view → shift month; week view → shift week
function navigate(dir) {
    if (curView === 'month') {
        curDate.setMonth(curDate.getMonth() + dir);
    } else {
        curDate.setDate(curDate.getDate() + dir * 7);
    }
    expandAll();
    redraw();
}

function redraw() {
    updateTitle();
    if (curView === 'month') {
        document.getElementById('monthView').style.display = '';
        document.getElementById('weekView').style.display  = 'none';
        renderCal();
    } else {
        document.getElementById('monthView').style.display = 'none';
        document.getElementById('weekView').style.display  = 'block';
        renderWeek();
    }
    renderDeadlines();
    renderMyCalendars();
    renderUpcoming();
}

function updateTitle() {
    if (curView === 'month') {
        document.getElementById('calTitle').textContent =
            new Date(curDate.getFullYear(), curDate.getMonth(), 1)
                .toLocaleDateString('en-US', { month:'long', year:'numeric' });
    } else {
        const { start, end } = weekRange(curDate);
        const opts = { month:'short', day:'numeric' };
        document.getElementById('calTitle').textContent =
            `${start.toLocaleDateString('en-US', opts)} – ${end.toLocaleDateString('en-US', { ...opts, year:'numeric' })}`;
    }
}

// ═══════════════════════════════════════════════════════════════════
// MONTH CALENDAR
// ═══════════════════════════════════════════════════════════════════
function renderCal() {
    const y = curDate.getFullYear(), m = curDate.getMonth(), today = new Date();
    const first = new Date(y, m, 1).getDay();
    const dim   = new Date(y, m + 1, 0).getDate();
    const prev  = new Date(y, m, 0).getDate();
    const total = Math.ceil((first + dim) / 7) * 7;
    const grid  = document.getElementById('calDays');
    grid.innerHTML = '';

    for (let i = 0; i < total; i++) {
        let dn, mo = 0;
        if      (i < first)       { dn = prev - first + i + 1; mo = -1; }
        else if (i >= first + dim) { dn = i - first - dim + 1;  mo = 1;  }
        else                       { dn = i - first + 1; }

        const cell = new Date(y, m + mo, dn), ds = fd(cell);
        const isToday = sameDay(cell, today), isOther = mo !== 0;
        const devs    = evForDate(ds);

        const div = document.createElement('div');
        div.className = 'cal-day'
            + (isOther  ? ' other-month' : '')
            + (isToday  ? ' today'       : '')
            + (isDaySel(ds) ? ' day-sel' : '');
        div.dataset.date = ds;

        const vis   = devs.filter(e => filters[e.category]);
        const chips = vis.slice(0, 3).map(e =>
            `<div class="day-event-chip chip-${e.category}">${esc(e.title)}</div>`).join('');
        const more = vis.length > 3
            ? `<div class="day-event-chip chip-event">+${vis.length - 3} more</div>` : '';

        div.innerHTML =
            `<div class="day-check"></div>
             <div class="day-num">${dn}</div>
             <div class="day-events">${chips}${more}</div>`;

        if (!isOther) {
            div.addEventListener('click', e => {
                if (selectMode) { toggleDaySel(ds, div); return; }
                e.stopPropagation();
                openPopover(div, ds, cell);
            });
            div.querySelector('.day-check').addEventListener('click', e => {
                e.stopPropagation();
                toggleDaySel(ds, div);
            });
        }
        grid.appendChild(div);
    }
}

function isDaySel(ds) { return evForDate(ds).some(e => selIds.has(e.id)); }

// ═══════════════════════════════════════════════════════════════════
// WEEK VIEW  (new — shows Mon–Sun columns with event chips)
// ═══════════════════════════════════════════════════════════════════
function weekRange(ref) {
    const d    = new Date(ref);
    const day  = d.getDay();                    // 0=Sun
    const start = new Date(d);
    start.setDate(d.getDate() - day);           // back to Sunday
    start.setHours(0,0,0,0);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    return { start, end };
}

function renderWeek() {
    const today = new Date();
    const { start } = weekRange(curDate);
    const dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const HOUR_HEIGHT = 60; // px per hour
    const START_HOUR  = 0;  // start at midnight
    const END_HOUR    = 24;
    const TOTAL_H     = (END_HOUR - START_HOUR) * HOUR_HEIGHT;

    // Build days array
    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        days.push(d);
    }

    // Summary bar
    const counts = { deadline:0, class:0, group:0, event:0 };
    days.forEach(d => {
        evForDate(fd(d)).filter(e => filters[e.category])
            .forEach(e => counts[e.category]++);
    });
    const sumColors = { deadline:'#dc2626', class:'#0f766e', group:'#7c3aed', event:'#1a5f7a' };
    const sumLabels = { deadline:'deadline', class:'class', group:'study group', event:'event' };
    const summaryHTML = Object.entries(counts)
        .filter(([,n]) => n > 0)
        .map(([cat, n]) => `
            <div style="display:flex;align-items:center;gap:5px;padding:5px 12px;
                        border-radius:20px;font-size:13px;font-weight:500;
                        border:1px solid var(--border);background:white;color:var(--text-secondary)">
                <div style="width:8px;height:8px;border-radius:50%;background:${sumColors[cat]}"></div>
                ${n} ${sumLabels[cat]}${n !== 1 ? 's' : ''}
            </div>`).join('');
    document.getElementById('weekSummaryBar').innerHTML = summaryHTML;

    // Scroll to current time or 7am on load
    const now = new Date();
    const scrollToHour = sameDay(now, today) ? Math.max(0, now.getHours() - 1) : 7;

    // Build the grid
    let html = `
    <div style="display:flex;flex-direction:column;border:1px solid var(--border);border-radius:16px;overflow:hidden;">

        {{-- Day header row --}}
        <div style="display:grid;grid-template-columns:52px repeat(7,1fr);
                    border-bottom:1px solid var(--border);background:var(--bg-main);
                    position:sticky;top:0;z-index:10;">
            <div style="border-right:1px solid var(--border);"></div>`;

    days.forEach(d => {
        const isTod = sameDay(d, today);
        html += `
            <div style="padding:10px 6px;text-align:center;
                        border-right:1px solid var(--border);
                        background:${isTod ? 'rgba(26,95,122,0.06)' : 'transparent'};">
                <div style="font-size:11px;font-weight:600;color:var(--text-light);
                            text-transform:uppercase;letter-spacing:.04em;">
                    ${dayNames[d.getDay()]}
                </div>
                <div style="font-size:20px;font-weight:600;margin-top:2px;
                            color:${isTod ? 'var(--primary)' : 'var(--text-primary)'};">
                    ${d.getDate()}
                </div>
            </div>`;
    });
    html += `</div>`;

    // Scrollable body
    html += `
        <div id="weekScrollBody" style="overflow-y:auto;max-height:600px;">
            <div style="display:grid;grid-template-columns:52px repeat(7,1fr);position:relative;">`;

    // Time gutter + hour lines
    html += `<div style="position:relative;height:${TOTAL_H}px;border-right:1px solid var(--border);background:var(--bg-main);">`;
    for (let h = START_HOUR; h < END_HOUR; h++) {
        const top = (h - START_HOUR) * HOUR_HEIGHT;
        const label = h === 0 ? '12 AM'
            : h < 12 ? `${h} AM`
            : h === 12 ? '12 PM'
            : `${h - 12} PM`;
        html += `
            <div style="position:absolute;top:${top}px;right:6px;
                        font-size:10px;color:var(--text-light);
                        transform:translateY(-50%);white-space:nowrap;
                        ${h === 0 ? 'display:none;' : ''}">
                ${label}
            </div>`;
    }
    html += `</div>`;

    // Day columns
    days.forEach(d => {
        const ds    = fd(d);
        const isTod = sameDay(d, today);
        const evs   = evForDate(ds).filter(e => filters[e.category]);

        html += `
            <div style="position:relative;height:${TOTAL_H}px;
                        border-right:1px solid var(--border);
                        background:${isTod ? 'rgba(26,95,122,0.015)' : 'white'};"
                 onclick="openEvModal(null,'${ds}')">`;

        // Hour gridlines
        for (let h = START_HOUR; h < END_HOUR; h++) {
            const top = (h - START_HOUR) * HOUR_HEIGHT;
            html += `
                <div style="position:absolute;top:${top}px;left:0;right:0;
                            border-top:1px solid ${h === START_HOUR ? 'transparent' : 'var(--border)'};
                            pointer-events:none;">
                </div>`;
            // Half-hour line
            html += `
                <div style="position:absolute;top:${top + HOUR_HEIGHT/2}px;left:0;right:0;
                            border-top:1px dashed rgba(0,0,0,0.06);
                            pointer-events:none;">
                </div>`;
        }

        // Current time indicator
        if (isTod) {
            const nowMins = now.getHours() * 60 + now.getMinutes();
            const topPx   = (nowMins / 60) * HOUR_HEIGHT;
            html += `
                <div style="position:absolute;top:${topPx}px;left:0;right:0;
                            border-top:2px solid var(--accent);z-index:3;pointer-events:none;">
                    <div style="position:absolute;left:-1px;top:-5px;
                                width:8px;height:8px;border-radius:50%;
                                background:var(--accent);"></div>
                </div>`;
        }

        // Events — positioned by exact time
        // Group overlapping events into columns
        const timedEvs   = evs.filter(e => e.event_time);
        const untimedEvs = evs.filter(e => !e.event_time);

        // Untimed events stacked at top with a subtle bg
        untimedEvs.forEach((ev, idx) => {
            html += `
                <div class="day-event-chip chip-${ev.category}"
                     style="position:absolute;top:${4 + idx * 22}px;left:2px;right:2px;
                            font-size:10px;cursor:pointer;z-index:2;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                     onclick="event.stopPropagation();openEvModal('${ev.id}')"
                     title="${esc(ev.title)}">
                    ${esc(ev.title)}
                </div>`;
        });

        // Timed events — detect overlaps and split into side-by-side columns
        const placed = [];
        timedEvs.forEach(ev => {
            const [h, m] = ev.event_time.split(':').map(Number);
            const startMin = h * 60 + m;
            const topPx    = ((startMin) / 60) * HOUR_HEIGHT;
            const evHeight = Math.max(24, HOUR_HEIGHT * 0.85); // ~51px default

            // Find which column this event fits in (no overlap)
            let col = 0;
            while (placed.some(p => p.col === col &&
                Math.abs(p.startMin - startMin) < 55)) {
                col++;
            }
            const totalCols = Math.max(1, col + 1);
            placed.push({ col, startMin });

            // Recalculate width based on overlaps at same time
            const overlapping = placed.filter(p => Math.abs(p.startMin - startMin) < 55);
            const cols        = overlapping.length;
            const width       = `calc((100% - 4px) / ${cols})`;
            const left        = `calc(2px + (100% - 4px) / ${cols} * ${col})`;

            const timeLabel = fmt12(ev.event_time);
            html += `
                <div class="day-event-chip chip-${ev.category}"
                     style="position:absolute;
                            top:${topPx}px;
                            left:${left};
                            width:${width};
                            height:${evHeight}px;
                            font-size:10px;cursor:pointer;z-index:2;
                            overflow:hidden;display:flex;flex-direction:column;
                            justify-content:flex-start;padding:3px 5px;
                            box-sizing:border-box;border-radius:5px;"
                     onclick="event.stopPropagation();openEvModal('${ev.id}')"
                     title="${esc(ev.title)} · ${timeLabel}">
                    <div style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        ${esc(ev.title)}
                    </div>
                    <div style="opacity:.75;font-size:9px;margin-top:1px;">${timeLabel}</div>
                </div>`;
        });

        html += `</div>`; // end day column
    });

    html += `</div>`; // end grid
    html += `</div>`; // end scroll body
    html += `</div>`; // end outer wrapper

    document.getElementById('weekGrid').innerHTML = html;

    // Scroll to the right hour
    const scrollBody = document.getElementById('weekScrollBody');
    if (scrollBody) {
        scrollBody.scrollTop = scrollToHour * HOUR_HEIGHT;
    }
}

// ═══════════════════════════════════════════════════════════════════
// DAY POPOVER  — FIX: use visibility/opacity via CSS class only
// ═══════════════════════════════════════════════════════════════════
function openPopover(dayEl, ds, cell) {
    closePopover();
    const pop = document.getElementById('dayPopover');
    document.getElementById('popDate').textContent =
        cell.toLocaleDateString('en-US', { weekday:'long', month:'long', day:'numeric' });
    document.getElementById('btnPopAdd').dataset.date = ds;

    const evs = evForDate(ds).filter(e => filters[e.category]);
    const el  = document.getElementById('popEvents');
    el.innerHTML = evs.length
        ? evs.map(ev => `
            <div class="popover-event" onclick="openEvModal('${ev.id}')">
                <div class="popover-event-dot" style="background:${CC[ev.category]}"></div>
                <div class="popover-event-info">
                    <div class="popover-event-title">${esc(ev.title)}</div>
                    ${ev.event_time ? `<div class="popover-event-time">${fmt12(ev.event_time)}</div>` : ''}
                </div>
                <button class="popover-ev-del" title="Delete"
                        onclick="event.stopPropagation();promptSingleDelete('${ev.id}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6M14 11v6"/>
                        <path d="M9 6V4h6v2"/>
                    </svg>
                </button>
            </div>`).join('')
        : '<div class="popover-empty">No events on this day</div>';

    // Position
    const r = dayEl.getBoundingClientRect();
    let l   = r.left + r.width / 2 - 142;
    let t   = r.bottom + 8;
    l = Math.max(16, Math.min(l, window.innerWidth - 300));
    if (t + 320 > window.innerHeight - 16) t = r.top - 330;
    pop.style.left = l + 'px';
    pop.style.top  = t + 'px';

    // FIX: only toggle the class — CSS handles show/hide
    pop.classList.add('open');
}

// FIX: only toggle the class — no more inline style fighting CSS
function closePopover() {
    document.getElementById('dayPopover').classList.remove('open');
}

// ═══════════════════════════════════════════════════════════════════
// SELECT MODE
// ═══════════════════════════════════════════════════════════════════
function toggleSelectMode() {
    selectMode = !selectMode;
    const btn  = document.getElementById('btnSelectMode');
    if (selectMode) {
        document.body.classList.add('select-mode');
        btn.classList.add('active');
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><polyline points="20 6 9 17 4 12"/></svg> Selecting…`;
    } else {
        exitSelectMode();
    }
}

function exitSelectMode() {
    selectMode = false; selIds.clear();
    document.body.classList.remove('select-mode');
    const btn = document.getElementById('btnSelectMode');
    btn.classList.remove('active');
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg> Select`;
    document.querySelectorAll('.cal-day.day-sel').forEach(d => d.classList.remove('day-sel'));
    document.querySelectorAll('.upcoming-item.item-sel').forEach(i => i.classList.remove('item-sel'));
    updateBulkBar();
}

function toggleDaySel(ds, div) {
    const ids  = evForDate(ds).map(e => e.id);
    const allIn = ids.every(id => selIds.has(id));
    ids.forEach(id => allIn ? selIds.delete(id) : selIds.add(id));
    div.classList.toggle('day-sel', !allIn);
    syncUpcomingSel();
    updateBulkBar();
}

function toggleItemSel(id, el) {
    selIds.has(id) ? selIds.delete(id) : selIds.add(id);
    el.classList.toggle('item-sel', selIds.has(id));
    renderCal();
    updateBulkBar();
}

function syncUpcomingSel() {
    document.querySelectorAll('.upcoming-item[data-id]').forEach(el => {
        el.classList.toggle('item-sel', selIds.has(el.dataset.id));
    });
}

function updateBulkBar() {
    const n = selIds.size;
    document.getElementById('bulkCount').textContent = `${n} event${n !== 1 ? 's' : ''} selected`;
    document.getElementById('bulkBar').classList.toggle('visible', n > 0 && selectMode);
}

// ═══════════════════════════════════════════════════════════════════
// UPCOMING LIST
// ═══════════════════════════════════════════════════════════════════
function renderUpcoming() {
    const today = new Date(); today.setHours(0,0,0,0);
    const wEnd  = new Date(today); wEnd.setDate(today.getDate() + 7);
    const list  = expanded
        .filter(e => filters[e.category])
        .filter(e => { const d = new Date(e.idate + 'T00:00:00'); return d >= today && d <= wEnd; })
        .sort((a, b) => (a.idate + (a.event_time||'')) > (b.idate + (b.event_time||'')) ? 1 : -1);

    const el = document.getElementById('upcomingList');
    if (!list.length) { el.innerHTML = '<div class="state-box">No upcoming events this week 🎉</div>'; return; }
    el.innerHTML = list.map(ev => {
        const d  = new Date(ev.idate + 'T00:00:00');
        const dl = d.toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric' });
        const sel = selIds.has(ev.id);
        return `<div class="upcoming-item ${sel ? 'item-sel' : ''}" data-id="${ev.id}"
                     onclick="handleItemClick(event,'${ev.id}',this)">
            <div class="upcoming-check"></div>
            <div class="upcoming-dot" style="background:${CC[ev.category]}"></div>
            <div class="upcoming-info">
                <div class="upcoming-title">${esc(ev.title)}</div>
                <div class="upcoming-sub">${dl}${ev.event_time ? ' · ' + fmt12(ev.event_time) : ''}</div>
            </div>
            <span class="upcoming-tag" style="background:${CB[ev.category]};color:${CC[ev.category]}">
                ${CI[ev.category]} ${CL[ev.category]}
            </span>
        </div>`;
    }).join('');
}

function handleItemClick(e, id, el) {
    if (selectMode) { toggleItemSel(id, el); return; }
    openEvModal(id);
}

// ═══════════════════════════════════════════════════════════════════
// RIGHT SIDEBAR
// ═══════════════════════════════════════════════════════════════════
function renderDeadlines() {
    const today = new Date(); today.setHours(0,0,0,0);
    const items = allEvents.filter(e => e.category === 'deadline')
        .sort((a, b) => a.event_date > b.event_date ? 1 : -1).slice(0, 7);
    if (!items.length) {
        document.getElementById('deadlinesList').innerHTML = '<div class="state-box">No deadlines 🎉</div>';
        return;
    }
    document.getElementById('deadlinesList').innerHTML = items.map(e => {
        const due  = new Date(e.event_date + 'T00:00:00');
        const diff = Math.ceil((due - today) / 86400000);
        let cls = 'due-normal', lbl = `${diff}d left`;
        if (diff <= 0)       { cls = 'due-urgent'; lbl = 'Overdue!'; }
        else if (diff === 1) { cls = 'due-urgent'; lbl = 'Tomorrow'; }
        else if (diff <= 3)  { cls = 'due-soon'; }
        return `<div class="deadline-item">
            <div class="deadline-icon" style="background:${CB.deadline}">📌</div>
            <div class="deadline-info">
                <div class="deadline-title">${esc(e.title)}</div>
                <div class="deadline-subject">${due.toLocaleDateString('en-US',{month:'short',day:'numeric'})}${e.event_time?' · '+fmt12(e.event_time):''}</div>
            </div>
            <div class="deadline-due ${cls}">${lbl}</div>
        </div>`;
    }).join('');
}

function renderMyCalendars() {
    const today = new Date(); today.setHours(0,0,0,0);
    const wEnd  = new Date(today); wEnd.setDate(today.getDate() + 7);
    const cntW  = cat => expanded.filter(e =>
        e.category === cat &&
        new Date(e.idate + 'T00:00:00') >= today &&
        new Date(e.idate + 'T00:00:00') <= wEnd).length;

    const cats = [
        { key:'class',    label:'Class Schedule', color:'#0f766e', meta:'Your enrolled classes' },
        { key:'group',    label:'Study Groups',    color:'#7c3aed', meta:`${cntW('group')} events this week` },
        { key:'deadline', label:'Deadlines',       color:'#dc2626', meta:`${cntW('deadline')} due this week` },
        { key:'event',    label:'Events',          color:'#1a5f7a', meta:'School & personal events' },
    ];
    document.getElementById('myCalendars').innerHTML = cats.map(c => `
        <div class="cal-category ${filters[c.key] ? 'active' : ''}" style="color:${c.color}"
             onclick="toggleFilter('${c.key}')">
            <div class="cal-category-dot" style="background:${c.color}"></div>
            <div class="cal-category-info">
                <div class="cal-category-name">${c.label}</div>
                <div class="cal-category-meta">${c.meta}</div>
            </div>
            <div class="cal-category-toggle"></div>
        </div>`).join('');
}

function toggleFilter(key) {
    filters[key] = !filters[key];
    renderCal(); renderMyCalendars(); renderUpcoming();
}

// ═══════════════════════════════════════════════════════════════════
// EVENT MODAL
// ═══════════════════════════════════════════════════════════════════
function openEvModal(id = null, prefill = null) {
    closePopover();
    editId = id;
    resetForm();
    if (prefill) document.getElementById('evDate').value = prefill;
    if (id) {
        const ev = allEvents.find(e => e.id === id);
        if (!ev) return;
        document.getElementById('modalTitle').textContent = 'Edit Event';
        document.getElementById('evTitle').value   = ev.title;
        document.getElementById('evDate').value    = ev.event_date;
        document.getElementById('evTime').value    = ev.event_time ? ev.event_time.slice(0, 5) : '';
        document.getElementById('evCat').value     = ev.category;
        document.getElementById('evDesc').value    = ev.description || '';
        document.getElementById('evRecur').checked = !!ev.is_recurring;
        if (ev.is_recurring) {
            document.getElementById('recurOpts').style.display = 'block';
            ev.recur_days?.forEach(d => {
                const b = document.querySelector(`.rday[data-d="${d}"]`);
                if (b) b.classList.add('sel');
            });
            document.getElementById('evRecurEnd').value = ev.recur_end || '';
        }
        document.getElementById('btnDelEv').style.display = 'block';
    } else {
        document.getElementById('modalTitle').textContent = 'Add Event';
        if (!prefill) document.getElementById('evDate').value = fd(new Date());
    }
    document.getElementById('eventModal').classList.add('open');
}

function closeEvModal() {
    document.getElementById('eventModal').classList.remove('open');
    editId = null;
}

function resetForm() {
    ['evTitle','evDesc','evTime','evRecurEnd'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('evDate').value    = '';
    document.getElementById('evCat').value     = 'deadline';
    document.getElementById('evRecur').checked = false;
    document.getElementById('recurOpts').style.display = 'none';
    document.querySelectorAll('.rday').forEach(b => b.classList.remove('sel'));
    document.getElementById('btnDelEv').style.display = 'none';
}

async function saveEv() {
    const title = document.getElementById('evTitle').value.trim();
    const date  = document.getElementById('evDate').value;
    const time  = document.getElementById('evTime').value || null;
    const cat   = document.getElementById('evCat').value;
    const desc  = document.getElementById('evDesc').value.trim() || null;
    const recur = document.getElementById('evRecur').checked;
    const rDays = recur ? [...document.querySelectorAll('.rday.sel')].map(b => b.dataset.d) : null;
    const rEnd  = recur ? (document.getElementById('evRecurEnd').value || null) : null;

    if (!title)                         return alert('Please enter a title.');
    if (!date)                          return alert('Please select a date.');
    if (recur && rDays && !rDays.length) return alert('Select at least one repeat day.');

    const data = {
        title, event_date:date, event_time:time, category:cat,
        description:desc, is_recurring:recur, recur_days:rDays, recur_end:rEnd,
    };
    const btn = document.getElementById('btnSaveEv');
    btn.textContent = 'Saving…'; btn.disabled = true;
    try {
        if (editId) {
            const updated = await dbUpdate(editId, data);
            const i = allEvents.findIndex(e => e.id === editId);
            if (i !== -1) allEvents[i] = { ...allEvents[i], ...data, ...(updated || {}) };
        } else {
            const row = await dbInsert(data);
            allEvents.push(row);
        }
        expandAll();
        closeEvModal();
        redraw();
    } catch (err) {
        alert('Save failed: ' + err.message);
    } finally {
        btn.textContent = 'Save Event'; btn.disabled = false;
    }
}

// ═══════════════════════════════════════════════════════════════════
// DELETE
// ═══════════════════════════════════════════════════════════════════
function promptSingleDelete(id) {
    closeEvModal(); closePopover();
    const ev = allEvents.find(e => e.id === id);
    pendDel  = { mode:'single', ids:[id] };
    document.getElementById('confirmTitle').textContent = 'Delete Event';
    document.getElementById('confirmBody').innerHTML    =
        `Are you sure you want to delete <span class="confirm-count">"${esc(ev?.title || 'this event')}"</span>? This cannot be undone.`;
    document.getElementById('confirmModal').classList.add('open');
}

function promptBulkDelete() {
    if (!selIds.size) return;
    const n = selIds.size;
    pendDel  = { mode:'bulk', ids:[...selIds] };
    document.getElementById('confirmTitle').textContent = `Delete ${n} Event${n !== 1 ? 's' : ''}`;
    document.getElementById('confirmBody').innerHTML    =
        `Are you sure you want to permanently delete <span class="confirm-count">${n} event${n !== 1 ? 's' : ''}</span>? This cannot be undone.`;
    document.getElementById('confirmModal').classList.add('open');
}

function closeConfirm() {
    document.getElementById('confirmModal').classList.remove('open');
    pendDel = null;
}

async function execDelete() {
    if (!pendDel) return;
    const btn = document.getElementById('btnConfirmDel');
    btn.textContent = 'Deleting…'; btn.disabled = true;
    try {
        await dbDelete(pendDel.ids);
        const set = new Set(pendDel.ids);
        allEvents = allEvents.filter(e => !set.has(e.id));
        if (pendDel.mode === 'bulk') exitSelectMode();
        expandAll();
        closeConfirm();
        redraw();
    } catch (err) {
        alert('Delete failed: ' + err.message);
    } finally {
        btn.textContent = 'Yes, Delete'; btn.disabled = false;
    }
}

// ═══════════════════════════════════════════════════════════════════
// UTILS
// ═══════════════════════════════════════════════════════════════════
function evForDate(ds) { return expanded.filter(e => e.idate === ds); }
function fd(d) {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}
function sameDay(a, b) {
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}
function fmt12(t) {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    return `${h === 0 ? 12 : h > 12 ? h - 12 : h}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`;
}
function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
</script>
@endpush
