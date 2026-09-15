{literal}
<style>
.xtai-client-card{border-radius:12px;border:1px solid #e5e7eb;box-shadow:0 1px 2px rgba(16,24,40,.06);overflow:hidden}
.xtai-client-card__header{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;background:#f8fafc;padding:14px 18px;border-bottom:1px solid #e5e7eb}
.xtai-client-card__header .panel-title{font-size:15px;font-weight:700}
.xtai-client-dl{margin:0}
.xtai-client-row{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 18px;border-bottom:1px solid #f1f5f9}
.xtai-client-row:last-child{border-bottom:none}
.xtai-client-row dt{flex:none;width:150px;font-weight:600;color:#374151;font-size:13px;margin:0}
.xtai-client-row dd{flex:1;min-width:0;margin:0;display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.xtai-client-value{font-weight:600;font-size:15px;word-break:break-all}
.xtai-client-code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:#f1f5f9;border:1px solid #e5e7eb;border-radius:6px;padding:5px 9px;font-size:14px;word-break:break-all}
.xtai-status-badge{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
.xtai-status-badge::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
.xtai-status-badge--success{background:#ecfdf5;color:#166534}
.xtai-status-badge--warning{background:#fffbeb;color:#92400e}
.xtai-status-badge--neutral{background:#f3f4f6;color:#4b5563}
.xtai-copy-btn{margin-left:2px}
.xtai-copy-btn.copied{background:#ecfdf5;border-color:#bbf7d0;color:#166534}
.xtai-conn-table{margin:0}
.xtai-conn-table th{background:#f8fafc;border-bottom:2px solid #e5e7eb;font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:#6b7280;white-space:nowrap}
.xtai-conn-table td{font-size:13.5px;color:#1f2937;vertical-align:middle;word-break:break-all}
.xtai-conn-empty{padding:20px 18px;text-align:center;color:#6b7280;font-size:13.5px}
</style>
{/literal}
<div class="panel panel-default card mb-3 xtai-client-card">
    <div class="panel-heading card-header xtai-client-card__header">
        <h3 class="panel-title card-title m-0">IPTV Line Details</h3>
        {if $status}
            <span class="xtai-status-badge" id="xtai-status-badge" data-status="{$status|escape}">{$status|escape}</span>
        {/if}
    </div>
    <div class="panel-body card-body" style="padding:0;">
        {if $username}
            <dl class="xtai-client-dl">
                <div class="xtai-client-row">
                    <dt>Username</dt>
                    <dd>
                        <span class="xtai-client-value" id="xtai-line-username">{$username|escape}</span>
                        <button type="button" class="btn btn-default btn-sm xtai-copy-btn" data-copy-target="xtai-line-username" data-label="Copy">Copy</button>
                    </dd>
                </div>
                <div class="xtai-client-row">
                    <dt>Password</dt>
                    <dd>
                        <code id="xtai-line-password" class="xtai-client-code">{$password|escape}</code>
                        <button type="button" class="btn btn-default btn-sm" id="xtai-password-toggle">Show</button>
                        <button type="button" class="btn btn-default btn-sm xtai-copy-btn" data-copy-target="xtai-line-password" data-label="Copy">Copy</button>
                    </dd>
                </div>
                {if $account_type == 'reseller'}
                <div class="xtai-client-row">
                    <dt>Credits</dt>
                    <dd>
                        <span class="xtai-client-value" id="xtai-line-credits">{$credits|escape}</span>
                        <button type="button" class="btn btn-default btn-sm xtai-copy-btn" data-copy-target="xtai-line-credits" data-label="Copy">Copy</button>
                    </dd>
                </div>
                {else}
                <div class="xtai-client-row">
                    <dt>Expiry Date</dt>
                    <dd><span class="xtai-client-value">{$expires|escape}</span></dd>
                </div>
                {/if}
                {if $m3u_url}
                <div class="xtai-client-row">
                    <dt>Connection URL</dt>
                    <dd>
                        <code class="xtai-client-code" id="xtai-line-m3u">{$m3u_url|escape}</code>
                        <button type="button" class="btn btn-default btn-sm xtai-copy-btn" data-copy-target="xtai-line-m3u" data-label="Copy">Copy</button>
                    </dd>
                </div>
                {/if}
                {if $epg_url}
                <div class="xtai-client-row">
                    <dt>EPG URL</dt>
                    <dd>
                        <code class="xtai-client-code" id="xtai-line-epg">{$epg_url|escape}</code>
                        <button type="button" class="btn btn-default btn-sm xtai-copy-btn" data-copy-target="xtai-line-epg" data-label="Copy">Copy</button>
                    </dd>
                </div>
                {/if}
            </dl>
            {literal}
            <script>
            (function () {
                var code = document.getElementById('xtai-line-password');
                var toggle = document.getElementById('xtai-password-toggle');
                var original = code ? code.textContent : '';
                var hidden = true;
                if (code) { code.textContent = '••••••••'; }
                if (toggle && code) {
                    toggle.addEventListener('click', function () {
                        if (hidden) {
                            code.textContent = original;
                            toggle.textContent = 'Hide';
                        } else {
                            code.textContent = '••••••••';
                            toggle.textContent = 'Show';
                        }
                        hidden = !hidden;
                    });
                }

                function fallbackCopy(text) {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'absolute';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    var ok = false;
                    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
                    document.body.removeChild(ta);
                    return ok;
                }

                function copyText(text, btn) {
                    function done() {
                        if (!btn) { return; }
                        var label = btn.getAttribute('data-label') || 'Copy';
                        btn.textContent = 'Copied!';
                        btn.classList.add('copied');
                        setTimeout(function () {
                            btn.textContent = label;
                            btn.classList.remove('copied');
                        }, 1500);
                    }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
                    } else {
                        fallbackCopy(text);
                        done();
                    }
                }

                document.querySelectorAll('.xtai-copy-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var target = btn.getAttribute('data-copy-target');
                        var text = '';
                        if (target === 'xtai-line-password') {
                            text = original;
                        } else {
                            var el = document.getElementById(target);
                            text = el ? el.textContent : '';
                        }
                        copyText(text, btn);
                    });
                });

                var badge = document.getElementById('xtai-status-badge');
                if (badge) {
                    var s = (badge.getAttribute('data-status') || '').toLowerCase();
                    var cls = 'xtai-status-badge--neutral';
                    if (s.indexOf('active') !== -1) {
                        cls = 'xtai-status-badge--success';
                    } else if (s.indexOf('suspend') !== -1) {
                        cls = 'xtai-status-badge--warning';
                    }
                    badge.classList.add(cls);
                }
            })();
            </script>
            {/literal}
        {else}
            <div class="alert alert-warning" style="margin-bottom:0;border-radius:0;">Your IPTV line is not ready yet. If you just ordered, wait for provisioning to finish or contact support.</div>
        {/if}
    </div>
</div>

{if $account_type == 'line'}
<div class="panel panel-default card mb-3 xtai-client-card">
    <div class="panel-heading card-header xtai-client-card__header">
        <h3 class="panel-title card-title m-0">Active Connections</h3>
        <span class="xtai-status-badge xtai-status-badge--neutral">{$connections_count|escape}</span>
    </div>
    <div class="panel-body card-body" style="padding:0;">
        {if $connections}
            <div class="table-responsive">
                <table class="table table-hover xtai-conn-table">
                    <thead>
                        <tr>
                            <th>Content</th>
                            <th>IP</th>
                            <th>Country</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $connections as $connection}
                        <tr>
                            <td>{$connection.content|escape}</td>
                            <td>{$connection.ip|escape}</td>
                            <td>{$connection.country|escape}</td>
                            <td>{$connection.duration|escape}</td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {else}
            <div class="xtai-conn-empty">No active connections right now</div>
        {/if}
    </div>
</div>
{/if}
