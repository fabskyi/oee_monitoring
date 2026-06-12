            </div>
            <!-- End of Main Content -->
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- ══════════════ CHAT WIDGET ══════════════ -->
    <style>
    :root { --cr:#8B1A1A; --cr2:#dc2626; --cblue:#2563eb; --cgreen:#16a34a; }

    /* ── FAB ─────────────────────────────────── */
    #cFab {
        position:fixed; bottom:68px; right:22px; z-index:1055;
        width:52px; height:52px; border-radius:50%;
        background:linear-gradient(135deg,var(--cr),var(--cr2));
        color:#fff; border:none; cursor:pointer;
        box-shadow:0 4px 18px rgba(139,26,26,.55);
        font-size:1.15rem; display:flex; align-items:center; justify-content:center;
        transition:transform .2s, box-shadow .2s;
    }
    #cFab:hover { transform:scale(1.1); }
    #cFab.pulse { animation:fabGlow 1.8s ease-in-out infinite; }
    @keyframes fabGlow {
        0%,100%{ box-shadow:0 4px 18px rgba(139,26,26,.55); }
        50%{ box-shadow:0 4px 28px rgba(220,38,38,.85), 0 0 0 9px rgba(220,38,38,.12); }
    }
    #cFabBadge {
        position:absolute; top:-4px; right:-4px;
        background:#ef4444; color:#fff; border-radius:10px;
        min-width:18px; height:18px; padding:0 4px;
        font-size:.58rem; font-weight:800;
        display:none; align-items:center; justify-content:center;
        border:2px solid #fff; line-height:1;
    }

    /* ── Panel ───────────────────────────────── */
    #cPanel {
        position:fixed; bottom:130px; right:22px; z-index:1054;
        width:370px; height:560px;
        background:#fff; border-radius:18px;
        box-shadow:0 16px 56px rgba(0,0,0,.22), 0 0 0 1px rgba(0,0,0,.05);
        display:none; flex-direction:column; overflow:hidden;
        font-family:'Nunito',sans-serif;
    }
    #cPanel.show { display:flex; animation:panelIn .22s cubic-bezier(.16,1,.3,1); }
    @keyframes panelIn { from{opacity:0;transform:translateY(18px) scale(.97);} to{opacity:1;transform:none;} }

    /* ── Header ──────────────────────────────── */
    #cHead {
        background:linear-gradient(135deg,var(--cr) 0%,var(--cr2) 100%);
        padding:12px 14px; display:flex; align-items:center; gap:9px; flex-shrink:0;
    }
    #cHeadAv { width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid rgba(255,255,255,.4); flex-shrink:0; background:rgba(255,255,255,.2); }
    #cHeadInfo { flex:1; min-width:0; }
    #cHeadTitle { font-size:.88rem; font-weight:800; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.2; }
    #cHeadSub   { font-size:.6rem; color:rgba(255,255,255,.7); margin-top:1px; display:flex; align-items:center; gap:5px; }
    .ch-live-dot { width:6px; height:6px; border-radius:50%; background:#4ade80; animation:cBlink 1.5s infinite; }
    @keyframes cBlink{0%,100%{opacity:1;}50%{opacity:.15;}}
    .ch-btn { background:rgba(255,255,255,.18); border:none; color:#fff; border-radius:50%; width:28px; height:28px; cursor:pointer; font-size:.72rem; display:flex; align-items:center; justify-content:center; transition:background .2s; flex-shrink:0; }
    .ch-btn:hover { background:rgba(255,255,255,.32); }

    /* ── Search bar ──────────────────────────── */
    #cSearchBar { display:none; padding:7px 10px; gap:7px; align-items:center; background:#fff; border-bottom:1px solid #f1f3f5; flex-shrink:0; position:relative; }
    #cSearchBar.show { display:flex; }
    #cSearchIn { flex:1; border:1.5px solid #e9ecef; border-radius:18px; padding:5px 12px; font-size:.76rem; outline:none; background:#f8f9fa; font-family:inherit; }
    #cSearchIn:focus { border-color:var(--cr2); background:#fff; }
    #cSearchClose { background:none; border:none; color:#adb5bd; cursor:pointer; font-size:.75rem; }
    #cSearchResults {
        position:absolute; top:100%; left:8px; right:8px; z-index:30;
        background:#fff; border-radius:0 0 12px 12px; box-shadow:0 12px 32px rgba(0,0,0,.18);
        max-height:240px; overflow-y:auto; display:none; border:1px solid #f1f3f5;
    }
    #cSearchResults.show { display:block; }
    .c-sr-item { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f8f9fa; }
    .c-sr-item:hover { background:#f8f9fa; }
    .c-sr-who  { font-size:.62rem; font-weight:700; color:var(--cr2); display:flex; justify-content:space-between; }
    .c-sr-txt  { font-size:.72rem; color:#495057; margin-top:1px; }
    .c-sr-txt mark { background:#fef08a; padding:0 1px; border-radius:2px; }
    .c-sr-empty { padding:14px; text-align:center; color:#adb5bd; font-size:.72rem; }

    /* ── Tabs ────────────────────────────────── */
    #cTabs { display:flex; border-bottom:2px solid #f1f3f5; flex-shrink:0; background:#fff; }
    .c-tab { flex:1; padding:9px 4px; text-align:center; font-size:.72rem; font-weight:700; color:#adb5bd; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .18s; position:relative; user-select:none; }
    .c-tab.on { color:var(--cr2); border-bottom-color:var(--cr2); }
    .c-tab-badge { position:absolute; top:5px; right:14px; background:#ef4444; color:#fff; border-radius:10px; min-width:14px; height:14px; padding:0 3px; font-size:.52rem; font-weight:800; line-height:1; display:none; align-items:center; justify-content:center; }

    /* ── Pin bar ─────────────────────────────── */
    #cPinBar {
        display:none; align-items:center; gap:8px; padding:6px 12px;
        background:#fffbeb; border-bottom:1px solid #fde68a; flex-shrink:0; cursor:pointer;
    }
    #cPinBar.show { display:flex; }
    #cPinBar .pin-ico { color:#d97706; font-size:.72rem; flex-shrink:0; }
    #cPinBar .pin-info { flex:1; min-width:0; }
    #cPinBar .pin-who { font-size:.6rem; font-weight:800; color:#92400e; }
    #cPinBar .pin-txt { font-size:.7rem; color:#78350f; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    #cPinBar .pin-x   { background:none; border:none; color:#b45309; cursor:pointer; font-size:.68rem; padding:2px; flex-shrink:0; }

    /* ── Tag bar ─────────────────────────────── */
    #cTagBar { display:flex; gap:5px; padding:6px 10px; background:#f8f9fa; border-bottom:1px solid #f1f3f5; flex-shrink:0; flex-wrap:wrap; }
    .c-tb { padding:2px 9px; border-radius:20px; font-size:.6rem; font-weight:700; border:1.5px solid; cursor:pointer; background:transparent; transition:all .18s; letter-spacing:.3px; }
    .c-tb.info     { border-color:#6c757d;color:#6c757d; } .c-tb.on.info     { background:#6c757d;color:#fff; }
    .c-tb.report   { border-color:#f59e0b;color:#d97706; } .c-tb.on.report   { background:#f59e0b;color:#fff; }
    .c-tb.error    { border-color:#dc2626;color:#dc2626; } .c-tb.on.error    { background:#dc2626;color:#fff; }
    .c-tb.resolved { border-color:#16a34a;color:#16a34a; } .c-tb.on.resolved { background:#16a34a;color:#fff; }

    /* ── Messages ────────────────────────────── */
    .c-msgs { flex:1; overflow-y:auto; padding:10px 10px 4px; display:flex; flex-direction:column; gap:3px; min-height:0; scroll-behavior:smooth; }
    .c-msgs::-webkit-scrollbar { width:3px; }
    .c-msgs::-webkit-scrollbar-thumb { background:#dee2e6; border-radius:2px; }

    .c-row { display:flex; gap:7px; padding:1px 0; position:relative; }
    .c-row.me { flex-direction:row-reverse; }
    .c-row.grouped .c-av-wrap { visibility:hidden; }
    .c-row.flash .c-body { animation:msgFlash 1.6s ease; }
    @keyframes msgFlash { 0%,40%{ box-shadow:0 0 0 3px rgba(245,158,11,.55); } 100%{ box-shadow:none; } }

    .c-row-actions { position:absolute; top:-2px; display:none; align-items:center; gap:3px; z-index:5; }
    .c-row.me .c-row-actions { right:auto; left:6px; }
    .c-row:not(.me) .c-row-actions { right:6px; }
    .c-row:hover .c-row-actions { display:flex; }
    .c-act-btn { width:24px; height:24px; border-radius:50%; border:none; cursor:pointer; background:#fff; box-shadow:0 1px 5px rgba(0,0,0,.18); display:flex; align-items:center; justify-content:center; font-size:.6rem; color:#6c757d; transition:background .15s,color .15s; }
    .c-act-btn:hover { background:var(--cr2); color:#fff; }

    .c-av-wrap { width:30px; flex-shrink:0; display:flex; flex-direction:column; align-items:center; margin-top:2px; }
    .c-av { width:30px; height:30px; border-radius:50%; object-fit:cover; display:block; border:2px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,.12); cursor:pointer; }
    .c-av:hover { opacity:.8; }
    .c-av-txt { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.65rem; font-weight:800; color:#fff; border:2px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,.12); flex-shrink:0; cursor:pointer; }

    .c-bubble { max-width:245px; display:flex; flex-direction:column; }
    .c-row.me .c-bubble { align-items:flex-end; }
    .c-name { font-size:.6rem; font-weight:700; color:#6c757d; margin-bottom:2px; padding-left:2px; }
    .c-body { padding:7px 11px; font-size:.8rem; line-height:1.5; word-break:break-word; position:relative; background:#f1f3f5; border-radius:3px 14px 14px 14px; color:#212529; }
    .c-row.me .c-body { background:linear-gradient(135deg,var(--cr),var(--cr2)); color:#fff; border-radius:14px 3px 14px 14px; }
    .c-row.grouped .c-body { border-radius:14px; }
    .c-body.deleted { background:#f8f9fa !important; color:#adb5bd !important; font-style:italic; border:1px dashed #dee2e6; }
    .c-fwd-label { font-size:.58rem; font-style:italic; opacity:.65; display:flex; align-items:center; gap:4px; margin-bottom:2px; }
    .c-mention { font-weight:800; color:var(--cblue); background:rgba(37,99,235,.1); border-radius:4px; padding:0 3px; }
    .c-row.me .c-mention { color:#bfdbfe; background:rgba(255,255,255,.18); }
    .c-edited { font-size:.55rem; opacity:.6; font-style:italic; margin-left:4px; }

    .c-tag-chip { display:inline-block; font-size:.52rem; font-weight:700; padding:1px 6px; border-radius:4px; margin-bottom:3px; text-transform:uppercase; letter-spacing:.5px; }
    .c-tag-chip.report  { background:#fef3c7;color:#d97706; }
    .c-tag-chip.error   { background:#fef2f2;color:#dc2626; }
    .c-tag-chip.resolved{ background:#f0fdf4;color:#16a34a; }

    .c-time-row { display:flex; align-items:center; gap:4px; margin-top:2px; padding:0 2px; }
    .c-row.me .c-time-row { flex-direction:row-reverse; }
    .c-time { font-size:.58rem; color:#adb5bd; }
    .c-ticks { font-size:.6rem; color:#adb5bd; letter-spacing:-2px; }
    .c-ticks.read { color:#3b82f6; }
    .c-row.sending .c-body { opacity:.6; }
    .c-lock-ico { font-size:.48rem; color:#adb5bd; }

    /* Media */
    .c-media-img { max-width:100%; border-radius:10px; cursor:pointer; display:block; margin-bottom:4px; max-height:220px; object-fit:cover; }
    .c-file-card { display:flex; align-items:center; gap:8px; background:rgba(0,0,0,.07); border-radius:9px; padding:7px 10px; margin-bottom:4px; cursor:pointer; text-decoration:none !important; color:inherit; }
    .c-row.me .c-file-card { background:rgba(255,255,255,.16); color:#fff; }
    .c-file-card i { font-size:1.1rem; flex-shrink:0; }
    .c-file-name { font-size:.72rem; font-weight:700; word-break:break-all; }

    /* Quote */
    .c-quote { border-left:3px solid rgba(0,0,0,.2); border-radius:4px; background:rgba(0,0,0,.07); padding:4px 8px; margin-bottom:5px; font-size:.7rem; line-height:1.4; cursor:pointer; }
    .c-quote:hover { background:rgba(0,0,0,.12); }
    .c-row.me .c-quote { border-left-color:rgba(255,255,255,.45); background:rgba(255,255,255,.15); }
    .c-quote-name { font-weight:800; font-size:.62rem; margin-bottom:1px; opacity:.8; }
    .c-quote-msg  { opacity:.75; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* Reaction chips */
    .c-rx-wrap { display:flex; gap:3px; flex-wrap:wrap; margin-top:2px; padding:0 2px; }
    .c-row.me .c-rx-wrap { justify-content:flex-end; }
    .c-rx-chip { display:inline-flex; align-items:center; gap:3px; background:#fff; border:1px solid #e9ecef; border-radius:12px; padding:1px 7px; font-size:.64rem; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,.08); transition:transform .12s; }
    .c-rx-chip:hover { transform:scale(1.08); }
    .c-rx-chip.mine { border-color:var(--cblue); background:#eff6ff; }
    .c-rx-cnt { font-size:.56rem; font-weight:800; color:#6c757d; }

    /* Reaction quick bar (popover) */
    #cRxBar { position:fixed; z-index:2100; background:#fff; border-radius:22px; box-shadow:0 8px 28px rgba(0,0,0,.25); padding:5px 8px; display:none; gap:2px; }
    #cRxBar.show { display:flex; animation:panelIn .15s ease; }
    #cRxBar span { font-size:1.15rem; cursor:pointer; padding:3px 5px; border-radius:50%; transition:transform .12s; line-height:1; }
    #cRxBar span:hover { transform:scale(1.3); }

    /* Context menu (popover) */
    #cMsgMenu { position:fixed; z-index:2100; background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,.22); padding:5px 0; display:none; min-width:160px; }
    #cMsgMenu.show { display:block; animation:panelIn .15s ease; }
    .c-mi { display:flex; align-items:center; gap:9px; padding:7px 14px; font-size:.74rem; color:#374151; cursor:pointer; font-weight:600; }
    .c-mi:hover { background:#f8f9fa; }
    .c-mi.danger { color:#dc2626; }
    .c-mi i { width:14px; text-align:center; font-size:.7rem; }

    /* Lightbox */
    #cLightbox { position:fixed; inset:0; z-index:2200; background:rgba(0,0,0,.88); display:none; align-items:center; justify-content:center; cursor:zoom-out; }
    #cLightbox.show { display:flex; }
    #cLightbox img { max-width:92vw; max-height:90vh; border-radius:8px; }

    /* Forward modal */
    #cFwdModal { position:fixed; inset:0; z-index:2150; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; }
    #cFwdModal.show { display:flex; animation:fadeIn .18s ease; }
    @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
    #cFwdCard { background:#fff; border-radius:16px; width:270px; max-height:380px; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 64px rgba(0,0,0,.28); font-family:'Nunito',sans-serif; }
    #cFwdCard .fwd-head { padding:12px 16px; font-size:.8rem; font-weight:800; border-bottom:1px solid #f1f3f5; display:flex; justify-content:space-between; align-items:center; }
    #cFwdCard .fwd-head button { background:none; border:none; color:#adb5bd; cursor:pointer; }
    #cFwdList { overflow-y:auto; flex:1; }
    .fwd-item { display:flex; align-items:center; gap:10px; padding:9px 14px; cursor:pointer; border-bottom:1px solid #f8f9fa; font-size:.78rem; font-weight:700; color:#212529; }
    .fwd-item:hover { background:#f8f9fa; }
    .fwd-item img { width:30px; height:30px; border-radius:50%; object-fit:cover; }
    .fwd-item .fwd-pub-ico { width:30px; height:30px; border-radius:50%; background:linear-gradient(135deg,var(--cr),var(--cr2)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:.75rem; }

    /* Mention dropdown */
    #cMentionDrop { position:absolute; bottom:100%; left:8px; right:8px; z-index:25; background:#fff; border-radius:12px 12px 0 0; box-shadow:0 -6px 24px rgba(0,0,0,.14); max-height:160px; overflow-y:auto; display:none; border:1px solid #f1f3f5; }
    #cMentionDrop.show { display:block; }
    .c-mn-item { display:flex; align-items:center; gap:9px; padding:7px 12px; cursor:pointer; font-size:.76rem; }
    .c-mn-item:hover, .c-mn-item.sel { background:#eff6ff; }
    .c-mn-item img { width:26px; height:26px; border-radius:50%; object-fit:cover; }
    .c-mn-name { font-weight:700; color:#212529; }
    .c-mn-user { font-size:.62rem; color:#adb5bd; }

    /* Media pending preview */
    .c-media-pending { display:none; align-items:center; gap:9px; padding:6px 12px; background:#f0fdf4; border-top:1px solid #bbf7d0; flex-shrink:0; border-left:3px solid var(--cgreen); }
    .c-media-pending.show { display:flex; }
    .c-media-pending img { width:38px; height:38px; border-radius:8px; object-fit:cover; }
    .c-media-pending .mp-name { flex:1; font-size:.7rem; font-weight:700; color:#166534; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .c-media-pending button { background:none; border:none; color:#adb5bd; cursor:pointer; font-size:.75rem; }

    /* Edit bar */
    .c-edit-bar { display:none; align-items:center; gap:8px; padding:6px 12px; background:#fff7ed; border-top:1px solid #fed7aa; flex-shrink:0; border-left:3px solid #f59e0b; }
    .c-edit-bar.show { display:flex; }
    .c-edit-bar .eb-info { flex:1; min-width:0; }
    .c-edit-bar .eb-lbl { font-size:.62rem; font-weight:700; color:#d97706; }
    .c-edit-bar .eb-msg { font-size:.7rem; color:#92400e; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .c-edit-bar button { background:none; border:none; color:#adb5bd; cursor:pointer; font-size:.75rem; }

    /* Typing */
    #cTypingPub, #cTypingDm { padding:4px 10px 6px; font-size:.65rem; color:#adb5bd; min-height:22px; display:flex; align-items:center; gap:6px; flex-shrink:0; }
    .c-typing-dots { display:flex; gap:3px; align-items:center; }
    .c-typing-dots span { width:5px; height:5px; border-radius:50%; background:#adb5bd; animation:tDot 1.2s ease-in-out infinite; display:block; }
    .c-typing-dots span:nth-child(2){animation-delay:.2s;} .c-typing-dots span:nth-child(3){animation-delay:.4s;}
    @keyframes tDot{0%,60%,100%{transform:translateY(0);}30%{transform:translateY(-4px);}}

    /* Reply bar */
    .c-reply-bar { display:none; align-items:center; gap:8px; padding:6px 12px; background:#f0f4ff; border-top:1px solid #dbeafe; flex-shrink:0; border-left:3px solid var(--cblue); }
    .c-reply-bar.show { display:flex; }
    .c-reply-bar-info { flex:1; min-width:0; }
    .c-reply-bar-name { font-size:.62rem; font-weight:700; color:var(--cblue); }
    .c-reply-bar-msg  { font-size:.7rem; color:#4b5563; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .c-reply-cancel   { background:none; border:none; color:#adb5bd; cursor:pointer; font-size:.75rem; padding:2px; flex-shrink:0; }

    /* Input bar */
    .c-input-bar { padding:8px 10px; border-top:1px solid #f1f3f5; display:flex; align-items:flex-end; gap:6px; flex-shrink:0; background:#fff; }
    .c-input-bar textarea { flex:1; border:1.5px solid #e9ecef; border-radius:20px; padding:7px 12px; font-size:.82rem; outline:none; resize:none; max-height:80px; overflow-y:auto; line-height:1.45; font-family:inherit; transition:border-color .2s; background:#f8f9fa; }
    .c-input-bar textarea:focus { border-color:var(--cr2); background:#fff; }
    .c-emoji-btn, .c-attach-btn { background:none; border:none; font-size:1.05rem; cursor:pointer; padding:5px 3px; line-height:1; transition:transform .15s; flex-shrink:0; align-self:flex-end; margin-bottom:2px; color:#6c757d; }
    .c-emoji-btn:hover, .c-attach-btn:hover { transform:scale(1.18); }
    .c-send { width:34px; height:34px; border-radius:50%; border:none; cursor:pointer; color:#fff; font-size:.82rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:transform .15s; }
    .c-send:hover { transform:scale(1.1); }
    .c-send.pub  { background:linear-gradient(135deg,var(--cr),var(--cr2)); box-shadow:0 2px 8px rgba(139,26,26,.35); }
    .c-send.priv { background:linear-gradient(135deg,#1d4ed8,#3b82f6); box-shadow:0 2px 8px rgba(37,99,235,.35); }

    /* Emoji picker */
    #cEmojiPicker { position:absolute; bottom:56px; left:8px; right:8px; z-index:10; background:#fff; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,.18); padding:10px; display:none; flex-wrap:wrap; gap:4px; border:1px solid #e9ecef; }
    #cEmojiPicker.show { display:flex; animation:panelIn .18s ease; }
    .c-emoji { font-size:1.15rem; cursor:pointer; padding:3px 4px; border-radius:6px; transition:background .15s; line-height:1; }
    .c-emoji:hover { background:#f1f3f5; }

    /* User list */
    #cUserList { flex:1; overflow-y:auto; min-height:0; }
    #cUserList::-webkit-scrollbar { width:3px; } #cUserList::-webkit-scrollbar-thumb { background:#dee2e6; border-radius:2px; }
    .c-private-hint { padding:7px 12px; font-size:.64rem; color:#adb5bd; background:#f8f9fa; border-bottom:1px solid #f1f3f5; flex-shrink:0; }
    .c-user-item { display:flex; align-items:center; gap:10px; padding:9px 12px; cursor:pointer; transition:background .15s; border-bottom:1px solid #f8f9fa; }
    .c-user-item:hover { background:#f8f9fa; }
    .c-u-av-wrap { position:relative; flex-shrink:0; }
    .c-u-av { width:36px; height:36px; border-radius:50%; object-fit:cover; display:block; }
    .c-u-av-txt { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:800; color:#fff; }
    .c-u-online { position:absolute; bottom:1px; right:1px; width:10px; height:10px; border-radius:50%; border:2px solid #fff; }
    .c-u-info { flex:1; min-width:0; }
    .c-u-name { font-size:.8rem; font-weight:700; color:#212529; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .c-u-sub  { font-size:.62rem; color:#adb5bd; margin-top:1px; }
    .c-u-typing { font-size:.6rem; color:var(--cgreen); font-style:italic; }
    .c-u-unread { min-width:20px; height:20px; border-radius:10px; padding:0 5px; background:#ef4444; color:#fff; font-size:.58rem; font-weight:800; display:none; align-items:center; justify-content:center; flex-shrink:0; border:2px solid #fff; box-shadow:0 1px 6px rgba(239,68,68,.4); }

    /* DM back bar */
    #cDmBar { display:flex; align-items:center; gap:9px; padding:8px 12px; border-bottom:1px solid #f1f3f5; background:#fff; flex-shrink:0; }
    #cDmBack { background:none; border:none; color:var(--cr2); cursor:pointer; font-size:.8rem; padding:3px 6px; border-radius:6px; }
    #cDmBack:hover { background:#fef2f2; }
    #cDmAv { width:30px; height:30px; border-radius:50%; object-fit:cover; border:2px solid #f1f3f5; cursor:pointer; }
    #cDmName { font-size:.82rem; font-weight:700; color:#212529; flex:1; cursor:pointer; }
    #cDmName:hover { color:var(--cr2); }
    #cDmStatus { font-size:.62rem; }

    /* Toast */
    #cToastWrap { position:fixed; top:18px; right:22px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none; }
    .c-toast { background:#fff; border-radius:14px; box-shadow:0 8px 32px rgba(0,0,0,.18); padding:11px 14px 11px 12px; display:flex; align-items:flex-start; gap:10px; min-width:270px; max-width:320px; pointer-events:all; cursor:pointer; animation:toastIn .3s cubic-bezier(.16,1,.3,1); border-left:4px solid var(--cr); }
    .c-toast.priv { border-left-color:var(--cblue); }
    .c-toast.out  { animation:toastOut .28s ease forwards; }
    @keyframes toastIn  { from{opacity:0;transform:translateX(40px);}to{opacity:1;transform:none;} }
    @keyframes toastOut { to{opacity:0;transform:translateX(40px);} }
    .c-toast-av  { width:34px; height:34px; border-radius:50%; object-fit:cover; flex-shrink:0; }
    .c-toast-body { flex:1; min-width:0; }
    .c-toast-head { font-size:.72rem; font-weight:700; color:#212529; display:flex; align-items:center; gap:5px; margin-bottom:2px; }
    .c-toast-type { font-size:.58rem; opacity:.55; }
    .c-toast-msg  { font-size:.72rem; color:#6c757d; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .c-toast-time { font-size:.6rem; color:#adb5bd; margin-top:2px; }
    .c-toast-x    { background:none; border:none; color:#adb5bd; cursor:pointer; font-size:.65rem; align-self:flex-start; padding:0; line-height:1; }

    /* Profile modal */
    #cProfileModal { position:fixed; inset:0; z-index:2000; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; }
    #cProfileModal.show { display:flex; animation:fadeIn .2s ease; }
    #cProfileCard { background:#fff; border-radius:20px; width:280px; box-shadow:0 24px 64px rgba(0,0,0,.28); overflow:hidden; animation:slideUp .22s cubic-bezier(.16,1,.3,1); font-family:'Nunito',sans-serif; }
    @keyframes slideUp { from{transform:translateY(30px);opacity:0;} to{transform:none;opacity:1;} }
    #cProfileCard .cp-head { background:linear-gradient(135deg,#1e40af,#3b82f6); padding:24px 20px 14px; text-align:center; position:relative; }
    #cProfileCard .cp-close { position:absolute; top:10px; right:12px; background:rgba(255,255,255,.2); border:none; color:#fff; border-radius:50%; width:26px; height:26px; cursor:pointer; font-size:.7rem; display:flex; align-items:center; justify-content:center; }
    #cProfileCard .cp-av { width:72px; height:72px; border-radius:50%; object-fit:cover; border:3px solid rgba(255,255,255,.5); margin:0 auto 8px; display:block; }
    #cProfileCard .cp-av-txt { width:72px; height:72px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:800; color:#fff; border:3px solid rgba(255,255,255,.4); margin:0 auto 8px; }
    #cProfileCard .cp-name   { font-size:.95rem; font-weight:800; color:#fff; }
    #cProfileCard .cp-role   { font-size:.62rem; font-weight:700; background:rgba(255,255,255,.22); color:#fff; border-radius:10px; display:inline-block; padding:2px 10px; margin-top:3px; }
    #cProfileCard .cp-online { font-size:.62rem; margin-top:4px; }
    #cProfileCard .cp-body   { padding:14px 20px; }
    #cProfileCard .cp-row    { display:flex; justify-content:space-between; align-items:center; padding:5px 0; border-bottom:1px solid #f1f3f5; font-size:.73rem; }
    #cProfileCard .cp-row:last-child { border-bottom:none; }
    #cProfileCard .cp-lbl   { color:#adb5bd; font-weight:600; }
    #cProfileCard .cp-val   { color:#212529; font-weight:700; }
    #cProfileCard .cp-foot  { padding:0 20px 16px; display:flex; gap:8px; }
    #cProfileCard .cp-btn-dm { flex:1; padding:8px; border-radius:10px; border:none; cursor:pointer; background:linear-gradient(135deg,#1d4ed8,#3b82f6); color:#fff; font-size:.75rem; font-weight:700; font-family:inherit; }
    #cProfileCard .cp-btn-close { padding:8px 14px; border-radius:10px; border:1.5px solid #e9ecef; cursor:pointer; background:#fff; color:#6c757d; font-size:.75rem; font-weight:700; font-family:inherit; }
    #cProfileCard .cp-spinner { text-align:center; padding:30px; color:#adb5bd; font-size:.8rem; }
    </style>

    <div id="cToastWrap"></div>

    <button id="cFab" title="Team Chat">
        <i class="fas fa-comments"></i>
        <div id="cFabBadge"></div>
    </button>

    <!-- Reaction quick bar -->
    <div id="cRxBar"></div>
    <!-- Message context menu -->
    <div id="cMsgMenu"></div>
    <!-- Lightbox -->
    <div id="cLightbox"><img src="" alt=""></div>
    <!-- Hidden file input -->
    <input type="file" id="cFileIn" style="display:none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">

    <!-- Forward modal -->
    <div id="cFwdModal">
        <div id="cFwdCard">
            <div class="fwd-head"><span><i class="fas fa-share" style="margin-right:6px;color:var(--cr2);"></i>Teruskan ke...</span><button id="cFwdClose"><i class="fas fa-times"></i></button></div>
            <div id="cFwdList"></div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="cProfileModal">
        <div id="cProfileCard">
            <div class="cp-head" id="cpHead">
                <button class="cp-close" id="cpClose"><i class="fas fa-times"></i></button>
                <div class="cp-spinner" id="cpSpinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
            <div class="cp-body" id="cpBody" style="display:none;"></div>
            <div class="cp-foot" id="cpFoot" style="display:none;">
                <button class="cp-btn-dm" id="cpBtnDm"><i class="fas fa-comment" style="margin-right:5px;"></i>Send Message</button>
                <button class="cp-btn-close" id="cpBtnClose">Close</button>
            </div>
        </div>
    </div>

    <!-- Panel -->
    <div id="cPanel">

        <div id="cHead">
            <img id="cHeadAv" src="img/undraw_profile.svg" alt="">
            <div id="cHeadInfo">
                <div id="cHeadTitle">Team Chat</div>
                <div id="cHeadSub"><div class="ch-live-dot"></div><span id="cHeadSubTxt">Live</span></div>
            </div>
            <button class="ch-btn" id="cSearchBtn" title="Cari pesan"><i class="fas fa-search"></i></button>
            <button class="ch-btn" id="cMinBtn" title="Minimize"><i class="fas fa-minus"></i></button>
            <button class="ch-btn" id="cCloseBtn" title="Close"><i class="fas fa-times"></i></button>
        </div>

        <!-- Search bar -->
        <div id="cSearchBar">
            <i class="fas fa-search" style="color:#adb5bd;font-size:.7rem;"></i>
            <input id="cSearchIn" placeholder="Cari pesan..." autocomplete="off">
            <button id="cSearchClose"><i class="fas fa-times"></i></button>
            <div id="cSearchResults"></div>
        </div>

        <div id="cTabs">
            <div class="c-tab on" data-mode="public" id="cTabPub">
                <i class="fas fa-globe-asia" style="font-size:.65rem;margin-right:3px;"></i>Public
                <div class="c-tab-badge" id="cBadgePub"></div>
            </div>
            <div class="c-tab" data-mode="private" id="cTabPriv">
                <i class="fas fa-lock" style="font-size:.65rem;margin-right:3px;"></i>Private
                <div class="c-tab-badge" id="cBadgePriv"></div>
            </div>
        </div>

        <!-- PUBLIC VIEW -->
        <div id="cViewPub" style="display:flex;flex-direction:column;flex:1;min-height:0;position:relative;">
            <!-- Pin bar -->
            <div id="cPinBar">
                <i class="fas fa-thumbtack pin-ico"></i>
                <div class="pin-info">
                    <div class="pin-who" id="cPinWho"></div>
                    <div class="pin-txt" id="cPinTxt"></div>
                </div>
                <button class="pin-x" id="cPinUnpin" title="Lepas pin"><i class="fas fa-times"></i></button>
            </div>
            <div id="cTagBar">
                <button class="c-tb info on" data-tag="info">Info</button>
                <button class="c-tb report" data-tag="report">Report</button>
                <button class="c-tb error" data-tag="error">Error</button>
                <button class="c-tb resolved" data-tag="resolved">Resolved</button>
            </div>
            <div class="c-msgs" id="cMsgsPub"></div>
            <div id="cTypingPub"></div>
            <div class="c-edit-bar" id="cEditBarPub">
                <i class="fas fa-pen" style="color:#d97706;font-size:.68rem;flex-shrink:0;"></i>
                <div class="eb-info"><div class="eb-lbl">Edit pesan</div><div class="eb-msg" id="cEditMsgPub"></div></div>
                <button id="cEditCancelPub"><i class="fas fa-times"></i></button>
            </div>
            <div class="c-reply-bar" id="cReplyBarPub">
                <i class="fas fa-reply" style="color:var(--cblue);font-size:.7rem;flex-shrink:0;"></i>
                <div class="c-reply-bar-info">
                    <div class="c-reply-bar-name" id="cReplyNamePub"></div>
                    <div class="c-reply-bar-msg"  id="cReplyMsgPub"></div>
                </div>
                <button class="c-reply-cancel" id="cReplyCancelPub"><i class="fas fa-times"></i></button>
            </div>
            <div class="c-media-pending" id="cMediaPendPub">
                <img id="cMediaPendImgPub" src="" alt="" style="display:none;">
                <i class="fas fa-file" id="cMediaPendIcoPub" style="display:none;color:#16a34a;font-size:1.1rem;"></i>
                <div class="mp-name" id="cMediaPendNamePub"></div>
                <button id="cMediaPendXPub"><i class="fas fa-times"></i></button>
            </div>
            <div id="cMentionDrop"></div>
            <div id="cEmojiPicker"></div>
            <div class="c-input-bar">
                <button class="c-attach-btn" id="cAttachPub" title="Lampirkan file"><i class="fas fa-paperclip"></i></button>
                <button class="c-emoji-btn" id="cEmojiToggle" title="Emoji">😊</button>
                <textarea id="cInPub" rows="1" placeholder="Message team... (Enter)"></textarea>
                <button class="c-send pub" id="cSendPub"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>

        <!-- PRIVATE: user list -->
        <div id="cViewPriv" style="display:none;flex-direction:column;flex:1;min-height:0;">
            <div class="c-private-hint"><i class="fas fa-lock" style="margin-right:4px;color:#adb5bd;"></i>Private messages — only visible to you &amp; the recipient</div>
            <div id="cUserList"></div>
        </div>

        <!-- PRIVATE: DM thread -->
        <div id="cViewDm" style="display:none;flex-direction:column;flex:1;min-height:0;position:relative;">
            <div id="cDmBar">
                <button id="cDmBack"><i class="fas fa-chevron-left"></i></button>
                <img id="cDmAv" src="img/undraw_profile.svg" alt="">
                <div id="cDmName">-</div>
                <div id="cDmStatus"></div>
            </div>
            <div class="c-msgs" id="cMsgsDm"></div>
            <div id="cTypingDm"></div>
            <div class="c-edit-bar" id="cEditBarDm">
                <i class="fas fa-pen" style="color:#d97706;font-size:.68rem;flex-shrink:0;"></i>
                <div class="eb-info"><div class="eb-lbl">Edit pesan</div><div class="eb-msg" id="cEditMsgDm"></div></div>
                <button id="cEditCancelDm"><i class="fas fa-times"></i></button>
            </div>
            <div class="c-reply-bar" id="cReplyBarDm">
                <i class="fas fa-reply" style="color:var(--cblue);font-size:.7rem;flex-shrink:0;"></i>
                <div class="c-reply-bar-info">
                    <div class="c-reply-bar-name" id="cReplyNameDm"></div>
                    <div class="c-reply-bar-msg"  id="cReplyMsgDm"></div>
                </div>
                <button class="c-reply-cancel" id="cReplyCancelDm"><i class="fas fa-times"></i></button>
            </div>
            <div class="c-media-pending" id="cMediaPendDm">
                <img id="cMediaPendImgDm" src="" alt="" style="display:none;">
                <i class="fas fa-file" id="cMediaPendIcoDm" style="display:none;color:#16a34a;font-size:1.1rem;"></i>
                <div class="mp-name" id="cMediaPendNameDm"></div>
                <button id="cMediaPendXDm"><i class="fas fa-times"></i></button>
            </div>
            <div class="c-input-bar">
                <button class="c-attach-btn" id="cAttachDm" title="Lampirkan file"><i class="fas fa-paperclip"></i></button>
                <textarea id="cInDm" rows="1" placeholder="Private message... (Enter)"></textarea>
                <button class="c-send priv" id="cSendDm"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>

    </div><!-- #cPanel -->

    <script>
    (function(){
    var ME_ID   = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
    var ME_NAME = <?= json_encode($_SESSION['username'] ?? '') ?>;
    var API     = 'api/chat.php';
    var SSEURL  = 'api/chat_sse.php';
    var AVURL   = 'api/avatar.php?id=';

    /* ── State ──────────────────────────────────── */
    var lastPubId=0, lastPrivId=0;
    var unreadPub=0, unreadPriv=0;
    var isOpen=false, mode='public', dmUser=null, curTag='info';
    var sseConn=null, typingTimer=null, sseReady=false;
    var dmUnread={}, cachedUsers=null, usersLoading=false;
    var dmHistCache={};
    var replyTo=null;            // {id,msg,full_name,ctx}
    var editTarget=null;         // {id,ctx}
    var pendingMedia=null;       // {url,type,name,ctx}
    var otherReadPos={};         // {uid: last_read_id} — read receipts
    var fwdMsgId=null;
    var QUICK_RX = ['👍','❤️','😂','😮','😢','🙏'];
    var EMOJIS = ['😊','😂','👍','❤️','🔥','✅','⚠️','❌','🛠️','📋','🚨','💡','⏰','📊','🎯','🔧','💬','👀','🙏','😅'];

    function nowHHmm(){ var d=new Date(); return ('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2); }
    var $ = function(id){ return document.getElementById(id); };
    var fab=$('cFab'), fabBadge=$('cFabBadge'), panel=$('cPanel');
    var msgsPub=$('cMsgsPub'), msgsDm=$('cMsgsDm');
    var badgePub=$('cBadgePub'), badgePriv=$('cBadgePriv'), toastWrap=$('cToastWrap');

    /* ── Helpers ────────────────────────────────── */
    function esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
    function scrollBot(el,force){ if(!el) return; if(force||el.scrollHeight-el.scrollTop-el.clientHeight<120) el.scrollTop=el.scrollHeight; }
    function ini(s){ return (s||'?').slice(0,2).toUpperCase(); }
    function roleClr(r){ var m={'admin':'#8B1A1A','supervisor':'#1d4ed8','operator':'#374151'}; return m[r]||'#6c757d'; }
    function boxFor(ctx){ return ctx==='pub'?msgsPub:msgsDm; }
    function mkAv(uid, name, role, cls, clickable){
        var img=document.createElement('img');
        img.src=AVURL+uid; img.alt=ini(name); img.className=cls||'c-av';
        if(clickable!==false && +uid!==ME_ID){ img.addEventListener('click',function(e){ e.stopPropagation(); showProfileModal(+uid); }); }
        img.onerror=function(){
            this.style.display='none';
            var d=document.createElement('div');
            d.className=(cls||'c-av').replace('c-u-av','c-u-av-txt').replace('c-av','c-av-txt');
            d.style.background=roleClr(role); d.textContent=ini(name);
            if(clickable!==false && +uid!==ME_ID){ d.addEventListener('click',function(e){ e.stopPropagation(); showProfileModal(+uid); }); }
            this.parentNode.insertBefore(d,this);
        };
        return img;
    }
    function showBadge(el,n){ el.textContent=n>99?'99+':n; el.style.display=n>0?'flex':'none'; }
    function totalPrivUnread(){ var t=0; for(var k in dmUnread) t+=dmUnread[k]; return t; }
    function updateFabBadge(){ var t=unreadPub+totalPrivUnread(); fabBadge.textContent=t>99?'99+':t; fabBadge.style.display=t>0?'flex':'none'; fab.classList.toggle('pulse',t>0); }
    function updatePrivBadge(){ var t=totalPrivUnread(); unreadPriv=t; showBadge(badgePriv,t); updateFabBadge(); }

    /* Highlight @mentions inside an already-escaped HTML string */
    function mentionize(html){
        return html.replace(/(^|\s)@([\w.\-]+)/g, function(all, pre, name){
            return pre+'<span class="c-mention">@'+name+'</span>';
        });
    }

    /* ── Jump to a message + flash ──────────────── */
    function jumpToMsg(id, box){
        var row=box.querySelector('.c-row[data-msg-id="'+id+'"]');
        if(!row){ return false; }
        row.scrollIntoView({behavior:'smooth',block:'center'});
        row.classList.remove('flash'); void row.offsetWidth; row.classList.add('flash');
        return true;
    }

    /* ── Reaction chips ─────────────────────────── */
    function applyReactions(msgId, list){
        document.querySelectorAll('.c-row[data-msg-id="'+msgId+'"]').forEach(function(row){
            var wrap=row.querySelector('.c-rx-wrap');
            if(!wrap){
                wrap=document.createElement('div'); wrap.className='c-rx-wrap';
                var bub=row.querySelector('.c-bubble'); if(bub) bub.appendChild(wrap);
            }
            wrap.innerHTML='';
            (list||[]).forEach(function(r){
                var chip=document.createElement('span');
                chip.className='c-rx-chip'+(r.mine?' mine':'');
                chip.innerHTML=r.emoji+(r.count>1?'<span class="c-rx-cnt">'+r.count+'</span>':'');
                chip.title=r.count+' reaksi';
                chip.addEventListener('click',function(e){ e.stopPropagation(); sendReaction(msgId, r.emoji); });
                wrap.appendChild(chip);
            });
        });
    }
    function sendReaction(msgId, emoji){
        fetch(API+'?action=react',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({msg_id:msgId,emoji:emoji})})
            .then(function(r){ return r.json(); })
            .then(function(d){ if(d.ok) applyReactions(d.msg_id, d.reactions); })
            .catch(function(){});
    }
    function loadReactionsBulk(box){
        var ids=[].map.call(box.querySelectorAll('.c-row[data-msg-id]'),function(r){ return r.dataset.msgId; });
        if(!ids.length) return;
        fetch(API+'?action=reactions_bulk&ids='+ids.join(','))
            .then(function(r){ return r.json(); })
            .then(function(d){ var rx=d.reactions||{}; for(var mid in rx) applyReactions(mid, rx[mid]); })
            .catch(function(){});
    }

    /* ── Reaction quick bar popover ─────────────── */
    var rxBar=$('cRxBar'), rxBarMsgId=null;
    QUICK_RX.forEach(function(e){
        var s=document.createElement('span'); s.textContent=e;
        s.addEventListener('click',function(ev){ ev.stopPropagation(); if(rxBarMsgId) sendReaction(rxBarMsgId,e); hideRxBar(); });
        rxBar.appendChild(s);
    });
    function showRxBar(msgId, anchorEl){
        rxBarMsgId=msgId;
        rxBar.classList.add('show');
        var r=anchorEl.getBoundingClientRect();
        var w=rxBar.offsetWidth||230;
        var x=Math.max(8, Math.min(window.innerWidth-w-8, r.left-w/2));
        rxBar.style.left=x+'px';
        rxBar.style.top=Math.max(8,(r.top-44))+'px';
    }
    function hideRxBar(){ rxBar.classList.remove('show'); rxBarMsgId=null; }

    /* ── Context menu popover ───────────────────── */
    var msgMenu=$('cMsgMenu');
    function showMsgMenu(m, ctx, anchorEl){
        var me=+m.user_id===ME_ID;
        var canEdit=me && !m.deleted_for_all;
        var items=[];
        items.push({ico:'fa-reply',  lbl:'Reply',     fn:function(){ setReply(m,ctx); }});
        items.push({ico:'fa-copy',   lbl:'Salin teks', fn:function(){
            var tmp=document.createElement('textarea'); tmp.value=stripHtml(m.message); document.body.appendChild(tmp);
            tmp.select(); try{ document.execCommand('copy'); }catch(e){} document.body.removeChild(tmp);
        }});
        items.push({ico:'fa-share',  lbl:'Teruskan',  fn:function(){ openForward(+m.id); }});
        if(ctx==='pub') items.push({ico:'fa-thumbtack', lbl:'Pin pesan', fn:function(){ pinMessage(+m.id); }});
        if(canEdit && !m.media_url) items.push({ico:'fa-pen', lbl:'Edit', fn:function(){ startEdit(m,ctx); }});
        items.push({ico:'fa-trash',  lbl:'Hapus untuk saya', danger:true, fn:function(){ deleteMsg(+m.id,false); }});
        if(me) items.push({ico:'fa-trash-alt', lbl:'Hapus untuk semua', danger:true, fn:function(){ deleteMsg(+m.id,true); }});

        msgMenu.innerHTML='';
        items.forEach(function(it){
            var d=document.createElement('div'); d.className='c-mi'+(it.danger?' danger':'');
            d.innerHTML='<i class="fas '+it.ico+'"></i>'+it.lbl;
            d.addEventListener('click',function(e){ e.stopPropagation(); hideMsgMenu(); it.fn(); });
            msgMenu.appendChild(d);
        });
        msgMenu.classList.add('show');
        var r=anchorEl.getBoundingClientRect();
        var mw=msgMenu.offsetWidth||170, mh=msgMenu.offsetHeight||220;
        var x=Math.max(8, Math.min(window.innerWidth-mw-8, r.left-mw+24));
        var y=r.bottom+4; if(y+mh>window.innerHeight-8) y=r.top-mh-4;
        msgMenu.style.left=x+'px'; msgMenu.style.top=Math.max(8,y)+'px';
    }
    function hideMsgMenu(){ msgMenu.classList.remove('show'); }
    document.addEventListener('click',function(){ hideMsgMenu(); hideRxBar(); });
    function stripHtml(s){ var d=document.createElement('div'); d.innerHTML=s||''; return d.textContent; }

    /* ── Delete ─────────────────────────────────── */
    function deleteMsg(msgId, forAll){
        fetch(API+'?action=delete_msg',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({msg_id:msgId,for_all:forAll})})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if(!d.ok) return;
                if(forAll) markDeletedInDom(msgId);
                else document.querySelectorAll('.c-row[data-msg-id="'+msgId+'"]').forEach(function(r){ r.remove(); });
            }).catch(function(){});
    }
    function markDeletedInDom(msgId){
        document.querySelectorAll('.c-row[data-msg-id="'+msgId+'"]').forEach(function(row){
            var body=row.querySelector('.c-body');
            if(body){ body.className='c-body deleted'; body.innerHTML='<i class="fas fa-ban" style="margin-right:5px;"></i>Pesan ini telah dihapus'; }
            var rx=row.querySelector('.c-rx-wrap'); if(rx) rx.remove();
        });
    }

    /* ── Edit ───────────────────────────────────── */
    function startEdit(m, ctx){
        clearReply(ctx); clearPendingMedia();
        editTarget={id:+m.id, ctx:ctx};
        var raw=stripHtml(m.message);
        (ctx==='pub'?$('cEditMsgPub'):$('cEditMsgDm')).textContent=raw.length>60?raw.slice(0,60)+'…':raw;
        (ctx==='pub'?$('cEditBarPub'):$('cEditBarDm')).classList.add('show');
        var inp=ctx==='pub'?$('cInPub'):$('cInDm');
        inp.value=raw; inp.focus();
    }
    function clearEdit(ctx){
        editTarget=null;
        $('cEditBarPub').classList.remove('show');
        $('cEditBarDm').classList.remove('show');
        var inp=ctx==='pub'?$('cInPub'):$('cInDm');
        if(inp&&ctx) inp.value='';
    }
    $('cEditCancelPub').addEventListener('click',function(){ clearEdit('pub'); });
    $('cEditCancelDm').addEventListener('click',function(){ clearEdit('dm'); });
    function submitEdit(txt){
        var t=editTarget; if(!t) return;
        fetch(API+'?action=edit_msg',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({msg_id:t.id,message:txt})})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if(d.error){ alert(d.error); return; }
                document.querySelectorAll('.c-row[data-msg-id="'+t.id+'"]').forEach(function(row){
                    var body=row.querySelector('.c-body');
                    if(body){
                        var quote=body.querySelector('.c-quote'); var qHtml=quote?quote.outerHTML:'';
                        body.innerHTML=qHtml+mentionize(d.message)+'<span class="c-edited">(diedit)</span>';
                    }
                });
            }).catch(function(){});
        clearEdit(t.ctx);
    }

    /* ── Pin ────────────────────────────────────── */
    var curPin=null;
    function renderPinBar(pin){
        curPin=pin;
        var bar=$('cPinBar');
        if(!pin){ bar.classList.remove('show'); return; }
        $('cPinWho').textContent=pin.full_name||pin.username;
        $('cPinTxt').textContent=stripHtml(pin.message).slice(0,80);
        bar.classList.add('show');
    }
    function pinMessage(msgId){
        fetch(API+'?action=pin_msg',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({msg_id:msgId,channel:'public'})})
            .then(function(r){ return r.json(); })
            .then(function(d){ if(d.ok) renderPinBar(d.pin); }).catch(function(){});
    }
    $('cPinBar').addEventListener('click',function(){ if(curPin) jumpToMsg(curPin.msg_id, msgsPub); });
    $('cPinUnpin').addEventListener('click',function(e){
        e.stopPropagation();
        fetch(API+'?action=pin_msg',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({unpin:true,channel:'public'})})
            .then(function(r){ return r.json(); })
            .then(function(){ renderPinBar(null); }).catch(function(){});
    });
    fetch(API+'?action=get_pins&channel=public').then(function(r){ return r.json(); }).then(function(d){ renderPinBar(d.pin); }).catch(function(){});

    /* ── Forward ────────────────────────────────── */
    function openForward(msgId){
        fwdMsgId=msgId;
        var list=$('cFwdList'); list.innerHTML='';
        var pub=document.createElement('div'); pub.className='fwd-item';
        pub.innerHTML='<div class="fwd-pub-ico"><i class="fas fa-globe-asia"></i></div>Public — Team Chat';
        pub.addEventListener('click',function(){ doForward('public'); });
        list.appendChild(pub);
        (cachedUsers||[]).forEach(function(u){
            var it=document.createElement('div'); it.className='fwd-item';
            var img=document.createElement('img'); img.src=AVURL+u.id; img.onerror=function(){ this.style.visibility='hidden'; };
            it.appendChild(img);
            it.appendChild(document.createTextNode(u.full_name||u.username));
            it.addEventListener('click',function(){ doForward(u.id); });
            list.appendChild(it);
        });
        $('cFwdModal').classList.add('show');
    }
    function doForward(to){
        $('cFwdModal').classList.remove('show');
        if(!fwdMsgId) return;
        fetch(API+'?action=forward_msg',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({msg_id:fwdMsgId,to:to})})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if(!d.ok) return;
                if(to!=='public'){ delete dmHistCache[+to]; }
            }).catch(function(){});
        fwdMsgId=null;
    }
    $('cFwdClose').addEventListener('click',function(){ $('cFwdModal').classList.remove('show'); fwdMsgId=null; });
    $('cFwdModal').addEventListener('click',function(e){ if(e.target===$('cFwdModal')){ $('cFwdModal').classList.remove('show'); fwdMsgId=null; } });

    /* ── Media upload ───────────────────────────── */
    var fileIn=$('cFileIn'), attachCtx='pub';
    $('cAttachPub').addEventListener('click',function(){ attachCtx='pub'; fileIn.value=''; fileIn.click(); });
    $('cAttachDm').addEventListener('click',function(){ attachCtx='dm'; fileIn.value=''; fileIn.click(); });
    fileIn.addEventListener('change',function(){
        var f=this.files[0]; if(!f) return;
        var fd=new FormData(); fd.append('file',f);
        var nameEl=attachCtx==='pub'?$('cMediaPendNamePub'):$('cMediaPendNameDm');
        nameEl.textContent='Mengupload '+f.name+'...';
        (attachCtx==='pub'?$('cMediaPendPub'):$('cMediaPendDm')).classList.add('show');
        fetch(API+'?action=upload',{method:'POST',body:fd})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if(d.error){ alert(d.error); clearPendingMedia(); return; }
                pendingMedia={url:d.url,type:d.media_type,name:d.orig_name,ctx:attachCtx};
                nameEl.textContent=d.orig_name;
                var img=attachCtx==='pub'?$('cMediaPendImgPub'):$('cMediaPendImgDm');
                var ico=attachCtx==='pub'?$('cMediaPendIcoPub'):$('cMediaPendIcoDm');
                if(d.media_type==='image'){ img.src=d.url; img.style.display='block'; ico.style.display='none'; }
                else { img.style.display='none'; ico.style.display='block'; }
                (attachCtx==='pub'?$('cInPub'):$('cInDm')).focus();
            })
            .catch(function(){ clearPendingMedia(); });
    });
    function clearPendingMedia(){
        pendingMedia=null;
        $('cMediaPendPub').classList.remove('show');
        $('cMediaPendDm').classList.remove('show');
    }
    $('cMediaPendXPub').addEventListener('click',clearPendingMedia);
    $('cMediaPendXDm').addEventListener('click',clearPendingMedia);

    /* ── Lightbox ───────────────────────────────── */
    var lightbox=$('cLightbox');
    lightbox.addEventListener('click',function(){ lightbox.classList.remove('show'); });
    function openLightbox(src){ lightbox.querySelector('img').src=src; lightbox.classList.add('show'); }

    /* ── Reply state ────────────────────────────── */
    function setReply(m, ctx){
        clearEdit();
        replyTo={ id:+m.id, msg:stripHtml(m.message), full_name:m.full_name||m.username, ctx:ctx };
        (ctx==='pub'?$('cReplyNamePub'):$('cReplyNameDm')).textContent=replyTo.full_name;
        var t=replyTo.msg.length>60?replyTo.msg.slice(0,60)+'…':replyTo.msg;
        (ctx==='pub'?$('cReplyMsgPub'):$('cReplyMsgDm')).textContent=t||(m.media_type==='image'?'📷 Foto':'📎 File');
        (ctx==='pub'?$('cReplyBarPub'):$('cReplyBarDm')).classList.add('show');
        (ctx==='pub'?$('cInPub'):$('cInDm')).focus();
    }
    function clearReply(ctx){
        replyTo=null;
        $('cReplyBarPub').classList.remove('show');
        $('cReplyBarDm').classList.remove('show');
    }
    $('cReplyCancelPub').addEventListener('click',function(){ clearReply('pub'); });
    $('cReplyCancelDm').addEventListener('click',function(){ clearReply('dm'); });

    /* ── Read receipts (DM ticks) ───────────────── */
    function updateTicks(){
        if(!dmUser) return;
        var readId=otherReadPos[+dmUser.id]||0;
        msgsDm.querySelectorAll('.c-row.me[data-msg-id]').forEach(function(row){
            var t=row.querySelector('.c-ticks'); if(!t) return;
            if(+row.dataset.msgId<=readId){ t.classList.add('read'); t.innerHTML='✓✓'; }
            else { t.classList.remove('read'); t.innerHTML='✓✓'; }
        });
    }

    /* ── Render message ─────────────────────────── */
    function renderMsg(m, box, isPrivate, isOptimistic){
        var me=+m.user_id===ME_ID;
        var grouped=(!isOptimistic)&&box._lastSender===+m.user_id&&!(+m.deleted_for_all);
        box._lastSender=+m.user_id;
        var ctx=isPrivate?'dm':'pub';

        var row=document.createElement('div');
        row.className='c-row'+(me?' me':'')+(grouped?' grouped':'')+(isOptimistic?' sending':'');
        if(m.id) row.dataset.msgId=m.id;

        // Hover actions: react / reply / more
        if(!+m.deleted_for_all){
            var act=document.createElement('div'); act.className='c-row-actions';
            var bRx=document.createElement('button'); bRx.className='c-act-btn'; bRx.title='Reaksi'; bRx.innerHTML='<i class="far fa-smile"></i>';
            bRx.addEventListener('click',function(e){ e.stopPropagation(); hideMsgMenu(); if(m.id) showRxBar(+m.id,bRx); });
            var bRe=document.createElement('button'); bRe.className='c-act-btn'; bRe.title='Reply'; bRe.innerHTML='<i class="fas fa-reply"></i>';
            bRe.addEventListener('click',function(e){ e.stopPropagation(); setReply(m,ctx); });
            var bMo=document.createElement('button'); bMo.className='c-act-btn'; bMo.title='Lainnya'; bMo.innerHTML='<i class="fas fa-ellipsis-v"></i>';
            bMo.addEventListener('click',function(e){ e.stopPropagation(); hideRxBar(); showMsgMenu(m,ctx,bMo); });
            act.appendChild(bRx); act.appendChild(bRe); act.appendChild(bMo);
            row.appendChild(act);
        }

        var avWrap=document.createElement('div'); avWrap.className='c-av-wrap';
        if(!grouped){ avWrap.appendChild(mkAv(m.user_id,m.username,m.role)); }
        else { var ph=document.createElement('div'); ph.style.width='30px'; avWrap.appendChild(ph); }

        var bubble=document.createElement('div'); bubble.className='c-bubble';
        if(!grouped&&!me){ var nm=document.createElement('div'); nm.className='c-name'; nm.textContent=m.full_name||m.username; bubble.appendChild(nm); }

        var body=document.createElement('div'); body.className='c-body';

        if(+m.deleted_for_all){
            body.className='c-body deleted';
            body.innerHTML='<i class="fas fa-ban" style="margin-right:5px;"></i>Pesan ini telah dihapus';
        } else {
            if(+m.is_forwarded){
                var fw=document.createElement('div'); fw.className='c-fwd-label';
                fw.innerHTML='<i class="fas fa-share"></i>Diteruskan';
                body.appendChild(fw);
            }
            if(m.reply_to_id&&m.reply_msg){
                var quote=document.createElement('div'); quote.className='c-quote';
                quote.innerHTML='<div class="c-quote-name">'+esc(stripHtml(m.reply_full_name||m.reply_username||'User'))+'</div>'+
                    '<div class="c-quote-msg">'+esc(stripHtml(m.reply_msg).slice(0,60))+'</div>';
                var rid=+m.reply_to_id;
                quote.addEventListener('click',function(e){ e.stopPropagation(); jumpToMsg(rid, box); });
                body.appendChild(quote);
            }
            if(m.media_url){
                if(m.media_type==='image'){
                    var im=document.createElement('img'); im.className='c-media-img'; im.src=m.media_url;
                    im.addEventListener('click',function(){ openLightbox(m.media_url); });
                    body.appendChild(im);
                } else {
                    var fc=document.createElement('a'); fc.className='c-file-card'; fc.href=m.media_url; fc.target='_blank';
                    fc.innerHTML='<i class="fas fa-file-alt"></i><span class="c-file-name">'+esc(stripHtml(m.message)||'File')+'</span><i class="fas fa-download" style="font-size:.7rem;opacity:.6;"></i>';
                    body.appendChild(fc);
                }
            }
            if(m.tag&&m.tag!=='info'){
                var chip=document.createElement('span'); chip.className='c-tag-chip '+m.tag; chip.textContent=m.tag;
                body.appendChild(chip); body.appendChild(document.createElement('br'));
            }
            // Message text (server data already escaped; optimistic gets esc())
            if(m.message&&!(m.media_url&&m.media_type==='file')){
                var span=document.createElement('span');
                span.innerHTML=mentionize(isOptimistic?esc(m.message):m.message);
                body.appendChild(span);
            }
            if(m.edited_at){
                var ed=document.createElement('span'); ed.className='c-edited'; ed.textContent='(diedit)';
                body.appendChild(ed);
            }
        }
        bubble.appendChild(body);

        var tRow=document.createElement('div'); tRow.className='c-time-row';
        var tEl=document.createElement('span'); tEl.className='c-time'; tEl.textContent=m.time_str||(isOptimistic?nowHHmm():'');
        tRow.appendChild(tEl);
        if(isPrivate){
            if(me){
                var tk=document.createElement('span'); tk.className='c-ticks';
                var readId=dmUser?(otherReadPos[+dmUser.id]||0):0;
                tk.innerHTML=isOptimistic?'✓':'✓✓';
                if(m.id&&+m.id<=readId) tk.classList.add('read');
                tRow.appendChild(tk);
            } else {
                var lk=document.createElement('i'); lk.className='fas fa-lock c-lock-ico'; tRow.appendChild(lk);
            }
        }
        bubble.appendChild(tRow);

        row.appendChild(avWrap); row.appendChild(bubble);
        box.appendChild(row);
        return row;
    }

    /* ── Profile modal ──────────────────────────── */
    var profileTargetUser=null;
    function showProfileModal(uid){
        profileTargetUser=null;
        var modal=$('cProfileModal'), head=$('cpHead'), body=$('cpBody'), foot=$('cpFoot'), spin=$('cpSpinner');
        spin.style.display='block'; spin.innerHTML='<i class="fas fa-spinner fa-spin"></i> Loading...';
        body.style.display='none'; foot.style.display='none';
        var old=head.querySelector('.cp-center'); if(old) old.remove();
        modal.classList.add('show');
        fetch(API+'?action=user_profile&uid='+uid)
            .then(function(r){ return r.json(); })
            .then(function(d){
                if(d.error||!d.user){ spin.textContent='User not found.'; return; }
                var u=d.user; profileTargetUser=u;
                spin.style.display='none';
                var c=document.createElement('div'); c.className='cp-center'; c.style.textAlign='center';
                var av=document.createElement('img'); av.src=AVURL+u.id+'&v='+Date.now(); av.className='cp-av';
                av.onerror=function(){ this.style.display='none'; var t=document.createElement('div'); t.className='cp-av-txt'; t.style.background=roleClr(u.role); t.textContent=ini(u.full_name||u.username); c.insertBefore(t,this); };
                c.appendChild(av);
                c.insertAdjacentHTML('beforeend',
                    '<div class="cp-name">'+esc(u.full_name||u.username)+'</div>'+
                    '<div class="cp-role">'+esc(u.role)+'</div>'+
                    '<div class="cp-online">'+(+u.online?'<span style="color:#4ade80;">● Online</span>':'<span style="color:rgba(255,255,255,.5);">● Offline</span>')+'</div>');
                head.appendChild(c);
                body.innerHTML=
                    '<div class="cp-row"><span class="cp-lbl">Username</span><span class="cp-val">@'+esc(u.username)+'</span></div>'+
                    '<div class="cp-row"><span class="cp-lbl">Role</span><span class="cp-val">'+esc(u.role)+'</span></div>'+
                    (u.last_login_str?'<div class="cp-row"><span class="cp-lbl">Last seen</span><span class="cp-val">'+u.last_login_str+'</span></div>':'')+
                    (u.joined_str?'<div class="cp-row"><span class="cp-lbl">Joined</span><span class="cp-val">'+u.joined_str+'</span></div>':'')+
                    (u.msg_count?'<div class="cp-row"><span class="cp-lbl">Public msgs</span><span class="cp-val">'+u.msg_count+'</span></div>':'');
                body.style.display='block';
                foot.style.display=+u.id!==ME_ID?'flex':'none';
            })
            .catch(function(){ spin.textContent='Failed to load profile.'; });
    }
    function closeProfileModal(){ $('cProfileModal').classList.remove('show'); profileTargetUser=null; }
    $('cpClose').addEventListener('click',closeProfileModal);
    $('cpBtnClose').addEventListener('click',closeProfileModal);
    $('cProfileModal').addEventListener('click',function(e){ if(e.target===$('cProfileModal')) closeProfileModal(); });
    $('cpBtnDm').addEventListener('click',function(){
        if(!profileTargetUser) return;
        var u=profileTargetUser; closeProfileModal(); openPanel(); switchTab('private');
        (function doOpen(){
            if(cachedUsers){ var f=cachedUsers.filter(function(x){ return +x.id===+u.id; })[0]; openDm(f||u); }
            else setTimeout(doOpen,200);
        })();
    });

    /* ── Search ─────────────────────────────────── */
    var searchTimer=null;
    $('cSearchBtn').addEventListener('click',function(){
        $('cSearchBar').classList.toggle('show');
        if($('cSearchBar').classList.contains('show')) $('cSearchIn').focus();
        else { $('cSearchResults').classList.remove('show'); $('cSearchIn').value=''; }
    });
    $('cSearchClose').addEventListener('click',function(){
        $('cSearchBar').classList.remove('show'); $('cSearchResults').classList.remove('show'); $('cSearchIn').value='';
    });
    $('cSearchIn').addEventListener('input',function(){
        var q=this.value.trim();
        clearTimeout(searchTimer);
        if(q.length<2){ $('cSearchResults').classList.remove('show'); return; }
        searchTimer=setTimeout(function(){ doSearch(q); },350);
    });
    function doSearch(q){
        var channel=(mode==='dm'&&dmUser)?String(dmUser.id):'public';
        fetch(API+'?action=search&q='+encodeURIComponent(q)+'&channel='+channel)
            .then(function(r){ return r.json(); })
            .then(function(d){
                var res=$('cSearchResults'); res.innerHTML='';
                var msgs=d.messages||[];
                if(!msgs.length){ res.innerHTML='<div class="c-sr-empty">Tidak ditemukan</div>'; res.classList.add('show'); return; }
                msgs.slice().reverse().forEach(function(m){
                    var it=document.createElement('div'); it.className='c-sr-item';
                    var txt=stripHtml(m.message);
                    var rx=new RegExp('('+q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','ig');
                    var hl=esc(txt.length>90?txt.slice(0,90)+'…':txt).replace(rx,'<mark>$1</mark>');
                    it.innerHTML='<div class="c-sr-who"><span>'+esc(m.full_name||m.username)+'</span><span style="color:#adb5bd;font-weight:400;">'+m.time_str+'</span></div>'+
                        '<div class="c-sr-txt">'+hl+'</div>';
                    it.addEventListener('click',function(){
                        $('cSearchResults').classList.remove('show');
                        var box=(mode==='dm')?msgsDm:msgsPub;
                        if(!jumpToMsg(m.id, box)){
                            // not in DOM (older than history window)
                            alert('Pesan lebih lama dari riwayat yang dimuat.');
                        }
                    });
                    res.appendChild(it);
                });
                res.classList.add('show');
            }).catch(function(){});
    }

    /* ── Mention autocomplete (public input) ────── */
    var mnDrop=$('cMentionDrop'), mnActive=false;
    function checkMention(inp){
        var v=inp.value, pos=inp.selectionStart;
        var upto=v.slice(0,pos);
        var match=upto.match(/(^|\s)@([\w.\-]*)$/);
        if(!match||!cachedUsers){ mnDrop.classList.remove('show'); mnActive=false; return; }
        var q=match[2].toLowerCase();
        var list=cachedUsers.filter(function(u){
            return u.username.toLowerCase().indexOf(q)===0||(u.full_name||'').toLowerCase().indexOf(q)===0;
        }).slice(0,5);
        if(!list.length){ mnDrop.classList.remove('show'); mnActive=false; return; }
        mnDrop.innerHTML='';
        list.forEach(function(u){
            var it=document.createElement('div'); it.className='c-mn-item';
            var img=document.createElement('img'); img.src=AVURL+u.id; img.onerror=function(){ this.style.visibility='hidden'; };
            it.appendChild(img);
            it.insertAdjacentHTML('beforeend','<div><div class="c-mn-name">'+esc(u.full_name||u.username)+'</div><div class="c-mn-user">@'+esc(u.username)+'</div></div>');
            it.addEventListener('click',function(){
                var before=upto.replace(/@[\w.\-]*$/,'@'+u.username+' ');
                inp.value=before+v.slice(pos);
                inp.focus();
                var np=before.length; inp.setSelectionRange(np,np);
                mnDrop.classList.remove('show'); mnActive=false;
            });
            mnDrop.appendChild(it);
        });
        mnDrop.classList.add('show'); mnActive=true;
    }

    /* ── Service Worker ─────────────────────────── */
    var swReg=null;
    if('serviceWorker' in navigator){
        navigator.serviceWorker.register('/oee/sw.js').then(function(reg){ swReg=reg; }).catch(function(){});
        navigator.serviceWorker.addEventListener('message',function(e){
            var d=e.data;
            if(!d||d.type!=='OPEN_CHAT') return;
            openPanel();
            if(d.mode==='priv'&&d.uid&&cachedUsers){
                var u=cachedUsers.filter(function(x){ return +x.id===+d.uid; })[0];
                if(u){ switchTab('private'); openDm(u); }
            } else switchTab('public');
        });
    }

    /* ── Notification permission ────────────────── */
    var notifBanner=null;
    function askNotifPermission(cb){
        if(!('Notification' in window)){ if(cb) cb(false); return; }
        if(Notification.permission==='granted'){ if(cb) cb(true); return; }
        if(Notification.permission==='denied'){ if(cb) cb(false); return; }
        Notification.requestPermission().then(function(p){ hidNotifBanner(); if(cb) cb(p==='granted'); });
    }
    function showNotifBanner(){
        if(notifBanner||Notification.permission!=='default') return;
        notifBanner=document.createElement('div');
        notifBanner.style.cssText='position:fixed;bottom:130px;left:50%;transform:translateX(-50%);z-index:2000;background:linear-gradient(135deg,#8B1A1A,#dc2626);color:#fff;border-radius:12px;padding:10px 16px;display:flex;align-items:center;gap:10px;font-size:.78rem;box-shadow:0 8px 28px rgba(139,26,26,.45);white-space:nowrap;';
        notifBanner.innerHTML='<i class="fas fa-bell" style="font-size:1rem;"></i><span>Aktifkan notifikasi Windows untuk pesan chat</span>'+
            '<button id="notifAllowBtn" style="background:rgba(255,255,255,.22);border:none;color:#fff;border-radius:8px;padding:4px 12px;font-size:.75rem;font-weight:700;cursor:pointer;margin-left:4px;">Izinkan</button>'+
            '<button id="notifDismissBtn" style="background:none;border:none;color:rgba(255,255,255,.6);cursor:pointer;font-size:.8rem;padding:2px 4px;">✕</button>';
        document.body.appendChild(notifBanner);
        document.getElementById('notifAllowBtn').addEventListener('click',function(){ askNotifPermission(function(){}); });
        document.getElementById('notifDismissBtn').addEventListener('click',hidNotifBanner);
    }
    function hidNotifBanner(){ if(notifBanner){ notifBanner.remove(); notifBanner=null; } }
    setTimeout(function(){ if('Notification' in window&&Notification.permission==='default') showNotifBanner(); },3000);

    /* ── Sound ──────────────────────────────────── */
    var actx=null;
    function beep(type){
        try{
            if(!actx) actx=new(window.AudioContext||window.webkitAudioContext)();
            var o=actx.createOscillator(), g=actx.createGain();
            o.connect(g); g.connect(actx.destination); o.type='sine';
            if(type==='priv'){ o.frequency.setValueAtTime(900,actx.currentTime); o.frequency.setValueAtTime(1200,actx.currentTime+.12); }
            else o.frequency.setValueAtTime(700,actx.currentTime);
            g.gain.setValueAtTime(.15,actx.currentTime);
            g.gain.exponentialRampToValueAtTime(.001,actx.currentTime+.3);
            o.start(); o.stop(actx.currentTime+.3);
        }catch(e){}
    }

    /* ── Browser / Windows notification ─────────── */
    function browserNotify(title, body, isPriv, fromUid){
        if(!('Notification' in window)||Notification.permission!=='granted') return;
        var readingThis=isPriv?(isOpen&&mode==='dm'&&dmUser&&+dmUser.id===+fromUid):(isOpen&&mode==='public');
        if(readingThis) return;
        var iconUrl=window.location.origin+'/oee/api/avatar.php?id='+(fromUid||0);
        var tag=isPriv?'oee-priv-'+fromUid:'oee-pub';
        if(swReg&&swReg.active){
            swReg.active.postMessage({type:'CHAT_NOTIFY',title:title,body:body,icon:iconUrl,tag:tag,url:window.location.href,mode:isPriv?'priv':'pub',uid:fromUid||0});
        } else {
            try{
                var n=new Notification(title,{body:body,icon:iconUrl,tag:tag,renotify:true});
                n.onclick=function(){ window.focus(); openPanel(); if(isPriv&&fromUid&&cachedUsers){ var u=cachedUsers.filter(function(x){ return +x.id===+fromUid; })[0]; if(u){ switchTab('private'); openDm(u); } } else switchTab('public'); n.close(); };
                setTimeout(function(){ n.close(); },8000);
            }catch(e){}
        }
    }

    /* ── Toast ──────────────────────────────────── */
    function showToast(m, isPriv){
        if(isOpen){
            if(!isPriv&&mode==='public') return;
            if(isPriv&&mode==='dm'&&dmUser&&+dmUser.id===+m.user_id) return;
        }
        var t=document.createElement('div'); t.className='c-toast'+(isPriv?' priv':'');
        var who=m.full_name||m.username;
        var txtRaw=stripHtml(m.message)||(m.media_type==='image'?'📷 Foto':(m.media_url?'📎 File':''));
        var txt=txtRaw.length>55?txtRaw.slice(0,55)+'…':txtRaw;
        var avImg=document.createElement('img');
        avImg.src=AVURL+m.user_id; avImg.className='c-toast-av';
        avImg.onerror=function(){ this.style.display='none'; };
        t.innerHTML='<div class="c-toast-body"><div class="c-toast-head"><span class="c-toast-type">'+(isPriv?'🔒 Private':'💬 Public')+'</span> '+esc(who)+'</div>'+
            '<div class="c-toast-msg">'+esc(txt)+'</div><div class="c-toast-time">'+m.time_str+'</div></div>'+
            '<button class="c-toast-x"><i class="fas fa-times"></i></button>';
        t.insertBefore(avImg,t.firstChild);
        t.querySelector('.c-toast-x').addEventListener('click',function(e){ e.stopPropagation(); dismissToast(t); });
        t.addEventListener('click',function(){
            openPanel();
            if(isPriv){ switchTab('private'); if(m.from_user_obj) openDm(m.from_user_obj); }
            else switchTab('public');
            dismissToast(t);
        });
        toastWrap.appendChild(t);
        setTimeout(function(){ dismissToast(t); },5500);
    }
    function dismissToast(t){ t.classList.add('out'); setTimeout(function(){ if(t.parentNode) t.parentNode.removeChild(t); },290); }

    /* ── Title blink ────────────────────────────── */
    var origTitle=document.title, blinkT=null;
    function titleBlink(n){ if(blinkT) return; var on=true; blinkT=setInterval(function(){ document.title=on?('💬 '+n+' new'):origTitle; on=!on; },1300); }
    function titleStop(){ clearInterval(blinkT); blinkT=null; document.title=origTitle; }
    document.addEventListener('visibilitychange',function(){ if(!document.hidden) titleStop(); });

    /* ── Mark read ──────────────────────────────── */
    function markRead(uid, lastId){
        if(!uid||!lastId) return;
        fetch(API+'?action=mark_read',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({with_user_id:uid,last_id:lastId})}).catch(function(){});
        dmUnread[uid]=0; updateUserBadge(uid,0); updatePrivBadge();
    }
    function updateUserBadge(uid, cnt){
        var itm=document.querySelector('.c-user-item[data-uid="'+uid+'"]');
        if(!itm) return;
        var badge=itm.querySelector('.c-u-unread');
        if(!badge){ badge=document.createElement('div'); badge.className='c-u-unread'; itm.appendChild(badge); }
        badge.textContent=cnt>99?'99+':cnt;
        badge.style.display=cnt>0?'flex':'none';
    }

    /* ── FAB / Panel ────────────────────────────── */
    function openPanel(){
        isOpen=true; panel.classList.add('show'); fab.classList.remove('pulse');
        if(mode==='public'){ unreadPub=0; showBadge(badgePub,0); updateFabBadge(); scrollBot(msgsPub,true); titleStop(); }
    }
    function closePanel(){ isOpen=false; panel.classList.remove('show'); hideMsgMenu(); hideRxBar(); }
    fab.addEventListener('click',function(){ isOpen?closePanel():openPanel(); });
    $('cCloseBtn').addEventListener('click',closePanel);
    $('cMinBtn').addEventListener('click',closePanel);
    $('cHeadAv').src=AVURL+ME_ID; $('cHeadAv').onerror=function(){ this.src='img/undraw_profile.svg'; };

    /* ── Tabs ───────────────────────────────────── */
    document.querySelectorAll('.c-tab').forEach(function(t){ t.addEventListener('click',function(){ switchTab(this.dataset.mode); }); });
    function switchTab(m){
        document.querySelectorAll('.c-tab').forEach(function(t){ t.classList.remove('on'); });
        document.querySelector('.c-tab[data-mode="'+m+'"]').classList.add('on');
        switchMode(m);
    }
    function switchMode(m){
        mode=m; hideMsgMenu(); hideRxBar();
        $('cViewPub').style.display  = m==='public'  ?'flex':'none';
        $('cViewPriv').style.display = m==='private' ?'flex':'none';
        $('cViewDm').style.display   = 'none';
        $('cHeadTitle').textContent  = m==='public' ? 'Team Chat' : 'Private Messages';
        $('cHeadAv').src             = AVURL+ME_ID;
        $('cHeadSubTxt').textContent = m==='public' ? 'Live' : 'Encrypted';
        if(m==='public'){ unreadPub=0; showBadge(badgePub,0); updateFabBadge(); scrollBot(msgsPub,true); titleStop(); }
        if(m==='private'){ loadUsers(); }
    }

    /* ── Tag selector ───────────────────────────── */
    document.querySelectorAll('.c-tb').forEach(function(b){
        b.addEventListener('click',function(){ document.querySelectorAll('.c-tb').forEach(function(x){x.classList.remove('on');}); this.classList.add('on'); curTag=this.dataset.tag; });
    });

    /* ── Emoji picker ───────────────────────────── */
    var picker=$('cEmojiPicker');
    EMOJIS.forEach(function(e){ var d=document.createElement('span'); d.className='c-emoji'; d.textContent=e; d.addEventListener('click',function(){ var t=$('cInPub'); t.value+=e; t.focus(); picker.classList.remove('show'); }); picker.appendChild(d); });
    $('cEmojiToggle').addEventListener('click',function(e){ e.stopPropagation(); picker.classList.toggle('show'); });
    document.addEventListener('click',function(){ picker.classList.remove('show'); });
    picker.addEventListener('click',function(e){ e.stopPropagation(); });

    /* ── Init ───────────────────────────────────── */
    fetch(API+'?action=init')
        .then(function(r){ return r.json(); })
        .then(function(d){ lastPubId=d.lastPubId||0; lastPrivId=d.lastPrivId||0; sseReady=true; startSSE(); })
        .catch(function(){ sseReady=true; startSSE(); });

    /* ── PUBLIC: history ────────────────────────── */
    msgsPub._lastSender=null;
    fetch(API+'?action=history_public')
        .then(function(r){ return r.json(); })
        .then(function(d){
            (d.messages||[]).forEach(function(m){ renderMsg(m,msgsPub,false); });
            scrollBot(msgsPub,true);
            loadReactionsBulk(msgsPub);
        }).catch(function(){});

    /* ── PUBLIC: send ───────────────────────────── */
    var inPub=$('cInPub');
    function sendPub(){
        var txt=inPub.value.trim();
        if(editTarget&&editTarget.ctx==='pub'){ if(txt) submitEdit(txt); return; }
        var media=(pendingMedia&&pendingMedia.ctx==='pub')?pendingMedia:null;
        if(!txt&&!media) return;
        var rid=replyTo&&replyTo.ctx==='pub'?replyTo.id:null;
        var fakeM={user_id:ME_ID,username:ME_NAME,full_name:ME_NAME,role:'',message:txt||(media?media.name:''),tag:curTag,time_str:nowHHmm(),reply_to_id:rid,
                   media_url:media?media.url:null,media_type:media?media.type:null};
        if(rid&&replyTo){ fakeM.reply_msg=replyTo.msg; fakeM.reply_full_name=replyTo.full_name; }
        inPub.value=''; inPub.style.height='auto'; picker.classList.remove('show'); mnDrop.classList.remove('show');
        clearReply('pub'); clearPendingMedia();
        var row=renderMsg(fakeM,msgsPub,false,true);
        scrollBot(msgsPub,true); clearTyping();
        fetch(API+'?action=send_public',{method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({message:txt||(media?media.name:''),tag:curTag,reply_to_id:rid,media_url:media?media.url:null,media_type:media?media.type:null})})
            .then(function(r){ return r.json(); })
            .then(function(d){ if(d.id&&row){ row.classList.remove('sending'); row.dataset.msgId=d.id; } })
            .catch(function(){ if(row){ row.style.opacity='.4'; row.title='Send failed'; } });
    }
    $('cSendPub').addEventListener('click',sendPub);
    inPub.addEventListener('keydown',function(e){
        if(mnActive&&(e.key==='Escape')){ mnDrop.classList.remove('show'); mnActive=false; return; }
        if(e.key==='Escape'){ clearEdit('pub'); clearReply('pub'); return; }
        if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); sendPub(); }
    });
    inPub.addEventListener('input',function(){
        this.style.height='auto'; this.style.height=Math.min(this.scrollHeight,80)+'px';
        sendTyping('public'); checkMention(this);
    });

    /* ── PRIVATE: user list ─────────────────────── */
    function renderUserList(users){
        var ul=$('cUserList'); ul.innerHTML='';
        if(!users||!users.length){ ul.innerHTML='<div style="padding:20px;text-align:center;color:#adb5bd;font-size:.75rem;">No other users</div>'; return; }
        users.forEach(function(u){
            var itm=document.createElement('div'); itm.className='c-user-item'; itm.dataset.uid=u.id;
            var avWrap=document.createElement('div'); avWrap.className='c-u-av-wrap';
            avWrap.appendChild(mkAv(u.id,u.username,u.role,'c-u-av',false));
            var dot=document.createElement('div'); dot.className='c-u-online'; dot.style.background=+u.online?'#22c55e':'#dee2e6'; avWrap.appendChild(dot);
            var info=document.createElement('div'); info.className='c-u-info';
            var nm=document.createElement('div'); nm.className='c-u-name'; nm.textContent=u.full_name||u.username; info.appendChild(nm);
            var sub=document.createElement('div'); sub.className='c-u-sub'; sub.innerHTML='@'+esc(u.username)+' &middot; '+esc(u.role); info.appendChild(sub);
            var cnt=dmUnread[+u.id]||0;
            var ub=document.createElement('div'); ub.className='c-u-unread'; ub.textContent=cnt>99?'99+':cnt; ub.style.display=cnt>0?'flex':'none';
            itm.appendChild(avWrap); itm.appendChild(info); itm.appendChild(ub);
            itm.addEventListener('click',function(){ openDm(u); });
            ul.appendChild(itm);
        });
    }
    function loadUsers(){
        if(cachedUsers){ renderUserList(cachedUsers); return; }
        if(usersLoading) return;
        usersLoading=true;
        $('cUserList').innerHTML='<div style="padding:14px;text-align:center;color:#adb5bd;font-size:.75rem;"><i class="fas fa-spinner fa-spin"></i></div>';
        fetch(API+'?action=users')
            .then(function(r){ return r.json(); })
            .then(function(d){
                cachedUsers=d.users||[];
                cachedUsers.forEach(function(u){ if(+u.unread>0&&!dmUnread[+u.id]) dmUnread[+u.id]=+u.unread; });
                updatePrivBadge();
                if(mode==='private'||mode==='dm') renderUserList(cachedUsers);
                var delay=200;
                cachedUsers.forEach(function(u){ if(+u.unread>0){ setTimeout(function(){ prefetchDm(u.id); },delay); delay+=300; } });
            })
            .catch(function(){ $('cUserList').innerHTML='<div style="padding:14px;text-align:center;color:#dc2626;font-size:.75rem;">Failed to load users</div>'; })
            .finally(function(){ usersLoading=false; });
    }
    setInterval(function(){
        if(!usersLoading) fetch(API+'?action=users').then(function(r){ return r.json(); }).then(function(d){
            cachedUsers=d.users||[]; if(mode==='private') renderUserList(cachedUsers);
        }).catch(function(){});
    },30000);
    loadUsers();

    function prefetchDm(uid){
        if(dmHistCache[uid]) return;
        fetch(API+'?action=history_private&with='+uid)
            .then(function(r){ return r.json(); })
            .then(function(d){ dmHistCache[uid]=d.messages||[]; })
            .catch(function(){});
    }

    /* ── PRIVATE: open DM ───────────────────────── */
    function openDm(u){
        dmUser=u; mode='dm'; msgsDm._lastSender=null;
        clearReply('dm'); clearEdit(); clearPendingMedia();
        $('cViewPriv').style.display='none'; $('cViewDm').style.display='flex';
        $('cDmName').textContent=u.full_name||u.username;
        $('cHeadTitle').textContent='🔒 '+(u.full_name||u.username);
        $('cHeadSubTxt').textContent='Private';
        $('cDmAv').src=AVURL+u.id; $('cDmAv').onerror=function(){ this.src='img/undraw_profile.svg'; };
        $('cHeadAv').src=AVURL+u.id;
        $('cDmStatus').innerHTML=+u.online?'<span style="color:#22c55e;font-size:.62rem;">● Online</span>':'<span style="color:#adb5bd;font-size:.62rem;">● Offline</span>';
        msgsDm.innerHTML='';

        // Fetch other user's read position for ticks
        fetch(API+'?action=read_pos&with='+u.id)
            .then(function(r){ return r.json(); })
            .then(function(d){ otherReadPos[+u.id]=+d.last_read_id||0; updateTicks(); })
            .catch(function(){});

        function renderHistory(msgs){
            msgs.forEach(function(m){ renderMsg(m,msgsDm,true); });
            scrollBot(msgsDm,true);
            loadReactionsBulk(msgsDm);
            updateTicks();
            var lastId=msgs.length?+msgs[msgs.length-1].id:0;
            if(lastId) markRead(u.id,lastId);
        }
        if(dmHistCache[u.id]) renderHistory(dmHistCache[u.id]);
        else {
            fetch(API+'?action=history_private&with='+u.id)
                .then(function(r){ return r.json(); })
                .then(function(d){ dmHistCache[u.id]=d.messages||[]; renderHistory(dmHistCache[u.id]); })
                .catch(function(){});
        }
    }
    $('cDmAv').addEventListener('click',function(){ if(dmUser&&+dmUser.id!==ME_ID) showProfileModal(+dmUser.id); });
    $('cDmName').addEventListener('click',function(){ if(dmUser&&+dmUser.id!==ME_ID) showProfileModal(+dmUser.id); });
    $('cDmBack').addEventListener('click',function(){
        mode='private'; clearReply('dm'); clearEdit(); clearPendingMedia();
        $('cViewDm').style.display='none'; $('cViewPriv').style.display='flex';
        $('cHeadTitle').textContent='Private Messages'; $('cHeadAv').src=AVURL+ME_ID; dmUser=null;
        renderUserList(cachedUsers||[]);
    });

    /* ── PRIVATE: send DM ───────────────────────── */
    var inDm=$('cInDm');
    function sendDm(){
        if(!dmUser) return;
        var txt=inDm.value.trim();
        if(editTarget&&editTarget.ctx==='dm'){ if(txt) submitEdit(txt); return; }
        var media=(pendingMedia&&pendingMedia.ctx==='dm')?pendingMedia:null;
        if(!txt&&!media) return;
        var rid=replyTo&&replyTo.ctx==='dm'?replyTo.id:null;
        var fakeM={user_id:ME_ID,username:ME_NAME,full_name:ME_NAME,role:'',message:txt||(media?media.name:''),tag:'info',time_str:nowHHmm(),reply_to_id:rid,
                   media_url:media?media.url:null,media_type:media?media.type:null};
        if(rid&&replyTo){ fakeM.reply_msg=replyTo.msg; fakeM.reply_full_name=replyTo.full_name; }
        inDm.value=''; inDm.style.height='auto';
        clearReply('dm'); clearPendingMedia();
        var row=renderMsg(fakeM,msgsDm,true,true);
        scrollBot(msgsDm,true); clearTyping();
        fetch(API+'?action=send_private',{method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({message:txt||(media?media.name:''),to_user_id:dmUser.id,reply_to_id:rid,media_url:media?media.url:null,media_type:media?media.type:null})})
            .then(function(r){ return r.json(); })
            .then(function(d){ if(d.id&&row){ row.classList.remove('sending'); row.dataset.msgId=d.id; var tk=row.querySelector('.c-ticks'); if(tk) tk.innerHTML='✓✓'; markRead(dmUser.id,d.id); } })
            .catch(function(){ if(row) row.style.opacity='.4'; });
    }
    $('cSendDm').addEventListener('click',sendDm);
    inDm.addEventListener('keydown',function(e){
        if(e.key==='Escape'){ clearEdit('dm'); clearReply('dm'); return; }
        if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); sendDm(); }
    });
    inDm.addEventListener('input',function(){ this.style.height='auto'; this.style.height=Math.min(this.scrollHeight,80)+'px'; if(dmUser) sendTyping(String(dmUser.id)); });

    /* ── Typing ─────────────────────────────────── */
    function sendTyping(ctx){ clearTimeout(typingTimer); fetch(API+'?action=typing',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({context:ctx})}).catch(function(){}); typingTimer=setTimeout(clearTyping,3000); }
    function clearTyping(){ clearTimeout(typingTimer); }
    function renderTyping(list, box, myCtx){
        var rel=list.filter(function(t){ return t.context===myCtx&&+t.user_id!==ME_ID; });
        if(!rel.length){ box.innerHTML=''; return; }
        box.innerHTML='<div class="c-typing-dots"><span></span><span></span><span></span></div><span>'+rel.map(function(t){ return esc(t.full_name||t.username); }).join(', ')+' typing...</span>';
    }

    /* ── Handle new public messages ─────────────── */
    function handlePubMsgs(msgs){
        msgs.forEach(function(m){
            if(+m.id<=lastPubId) return;
            lastPubId=Math.max(lastPubId,+m.id);
            var fromMe=+m.user_id===ME_ID;
            var opt=msgsPub.querySelector('.sending');
            if(opt&&fromMe){ opt.classList.remove('sending'); opt.dataset.msgId=m.id; return; }
            if(fromMe) return;
            renderMsg(m,msgsPub,false);
            var reading=isOpen&&mode==='public';
            var bodyTxt=stripHtml(m.message)||(m.media_type==='image'?'📷 Foto':'📎 File');
            // Highlight if I'm mentioned
            var mentioned=new RegExp('(^|\\s)@'+ME_NAME+'(\\b|$)','i').test(stripHtml(m.message));
            beep('pub'); browserNotify((mentioned?'🔔 ':'')+(m.full_name||m.username), bodyTxt, false, m.user_id);
            if(!reading){ unreadPub++; showBadge(badgePub,unreadPub); updateFabBadge(); showToast(m,false); titleBlink(unreadPub); }
            if(reading){ unreadPub=0; showBadge(badgePub,0); updateFabBadge(); titleStop(); scrollBot(msgsPub); }
        });
    }

    /* ── Handle new private messages ────────────── */
    function handlePrivMsgs(msgs){
        msgs.forEach(function(m){
            if(+m.id<=lastPrivId) return;
            lastPrivId=Math.max(lastPrivId,+m.id);
            var fromMe=+m.user_id===ME_ID;
            var otherId=fromMe?+m.to_user_id:+m.user_id;
            var readingThisDm=isOpen&&mode==='dm'&&dmUser&&+dmUser.id===otherId;

            if(readingThisDm){
                var opt=msgsDm.querySelector('.sending');
                if(opt&&fromMe){ opt.classList.remove('sending'); opt.dataset.msgId=m.id; }
                else if(!fromMe){ renderMsg(m,msgsDm,true); scrollBot(msgsDm); }
                if(!dmHistCache[otherId]) dmHistCache[otherId]=[];
                dmHistCache[otherId].push(m);
                if(!fromMe) markRead(m.user_id,+m.id);
                return;
            }

            if(!dmHistCache[otherId]) dmHistCache[otherId]=[];
            dmHistCache[otherId].push(m);

            if(!fromMe){
                dmUnread[otherId]=(dmUnread[otherId]||0)+1;
                updateUserBadge(otherId,dmUnread[otherId]);
                updatePrivBadge();
                if(cachedUsers){ var uo=cachedUsers.filter(function(u){ return +u.id===otherId; })[0]; if(uo) m.from_user_obj=uo; }
                var bodyTxt=stripHtml(m.message)||(m.media_type==='image'?'📷 Foto':'📎 File');
                beep('priv'); browserNotify('🔒 '+(m.full_name||m.username), bodyTxt, true, m.user_id);
                showToast(m,true); titleBlink(totalPrivUnread());
            }
        });
    }

    /* ── SSE ────────────────────────────────────── */
    function startSSE(){
        if(!sseReady) return;
        if(sseConn) try{sseConn.close();}catch(e){}
        sseConn=new EventSource(SSEURL+'?pub='+lastPubId+'&priv='+lastPrivId);
        sseConn.addEventListener('public',  function(e){ handlePubMsgs(JSON.parse(e.data)||[]); });
        sseConn.addEventListener('private', function(e){ handlePrivMsgs(JSON.parse(e.data)||[]); });
        sseConn.addEventListener('typing',  function(e){
            var list=JSON.parse(e.data)||[];
            renderTyping(list,$('cTypingPub'),'public');
            if(dmUser) renderTyping(list,$('cTypingDm'),String(dmUser.id));
            list.forEach(function(t){
                var s=document.querySelector('.c-user-item[data-uid="'+t.user_id+'"] .c-u-sub');
                if(s) s.innerHTML='<span class="c-u-typing">typing...</span>';
            });
        });
        // Edits & deletes from other users
        sseConn.addEventListener('modified', function(e){
            var mods=JSON.parse(e.data)||[];
            mods.forEach(function(m){
                if(+m.deleted_for_all){ markDeletedInDom(m.id); return; }
                document.querySelectorAll('.c-row[data-msg-id="'+m.id+'"]').forEach(function(row){
                    if(row.classList.contains('sending')) return;
                    var body=row.querySelector('.c-body');
                    if(body&&!body.classList.contains('deleted')&&body.querySelector('.c-edited')===null){
                        var quote=body.querySelector('.c-quote'); var qHtml=quote?quote.outerHTML:'';
                        var fwd=body.querySelector('.c-fwd-label'); var fHtml=fwd?fwd.outerHTML:'';
                        var mediaEl=body.querySelector('.c-media-img,.c-file-card'); var mHtml=mediaEl?mediaEl.outerHTML:'';
                        body.innerHTML=fHtml+qHtml+mHtml+mentionize(m.message)+'<span class="c-edited">(diedit)</span>';
                        var im2=body.querySelector('.c-media-img'); if(im2) im2.addEventListener('click',function(){ openLightbox(this.src); });
                    }
                });
            });
        });
        // Reaction updates
        sseConn.addEventListener('reactions', function(e){
            var map=JSON.parse(e.data)||{};
            for(var mid in map) applyReactions(mid, map[mid]);
        });
        // Pin updates
        sseConn.addEventListener('pin', function(e){
            var pin=JSON.parse(e.data);
            renderPinBar(pin||null);
        });
        // Read positions → blue ticks
        sseConn.addEventListener('readpos', function(e){
            var map=JSON.parse(e.data)||{};
            for(var uid in map) otherReadPos[+uid]=+map[uid];
            updateTicks();
        });
        sseConn.addEventListener('unread', function(e){
            var map=JSON.parse(e.data)||{};
            for(var uid in map){
                var cnt=+map[uid];
                if(!(isOpen&&mode==='dm'&&dmUser&&+dmUser.id===+uid)){ dmUnread[+uid]=cnt; updateUserBadge(uid,cnt); }
            }
            for(var k in dmUnread){ if(map[k]===undefined){ dmUnread[k]=0; updateUserBadge(k,0); } }
            updatePrivBadge();
        });
        sseConn.addEventListener('reconnect', function(){ sseConn.close(); setTimeout(startSSE,200); });
        sseConn.addEventListener('ping', function(){});
        sseConn.onerror=function(){ sseConn.close(); setTimeout(startSSE,3000); };
    }

    /* ── Polling fallback ───────────────────────── */
    setInterval(function(){
        fetch(API+'?action=get_public&since='+lastPubId)
            .then(function(r){ return r.json(); })
            .then(function(d){ handlePubMsgs(d.messages||[]); })
            .catch(function(){});
        if(mode==='dm'&&dmUser){
            fetch(API+'?action=get_private&with='+dmUser.id+'&since='+lastPrivId)
                .then(function(r){ return r.json(); })
                .then(function(d){ handlePrivMsgs(d.messages||[]); })
                .catch(function(){});
        }
    },2000);

    })();
    </script>
    <!-- ══════════════ END CHAT WIDGET ══════════════ -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="vendor/chart.js/Chart.min.js"></script>
    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <script>
        function safeDataTable(selector, options) {
            var $tables = typeof selector === 'string' ? $(selector) : $(selector);
            var dtInstance = null;
            $tables.each(function () {
                try {
                    if ($.fn.DataTable && !$.fn.DataTable.isDataTable(this)) {
                        $(this).find('tbody tr').each(function () {
                            if ($(this).find('td[colspan], th[colspan]').length > 0) $(this).remove();
                        });
                        var opts = $.extend({ responsive: true, language: { url: 'vendor/datatables/i18n/id.json' } }, options || {});
                        dtInstance = $(this).DataTable(opts);
                    } else if ($.fn.DataTable && $.fn.DataTable.isDataTable(this)) {
                        dtInstance = $(this).DataTable();
                    }
                } catch (e) { console.warn('DataTable init error on #' + (this.id || '?') + ':', e.message); }
            });
            return dtInstance;
        }
        $(document).ready(function () {
            if ($.fn.DataTable) safeDataTable('.dataTable:not(.no-auto-init)');
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>
