<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Inter, system-ui, sans-serif; background: #f8fafc; color: #0f172a; line-height: 1.5; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 64px 24px; }
        header { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 48px; }
        header h1 { font-size: 28px; font-weight: 700; letter-spacing: -0.01em; }
        header .tag { color: #64748b; font-size: 13px; }
        .hero { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 48px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .hero h2 { font-size: 36px; font-weight: 700; margin-bottom: 16px; letter-spacing: -0.02em; }
        .hero p { color: #475569; font-size: 17px; max-width: 64ch; margin-bottom: 32px; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 12px 20px; border-radius: 8px; font-weight: 500; text-decoration: none; font-size: 14px; transition: background-color 120ms; }
        .btn-primary { background: #0f172a; color: white; }
        .btn-primary:hover { background: #1e293b; }
        .btn-secondary { background: white; color: #0f172a; border: 1px solid #cbd5e1; }
        .btn-secondary:hover { background: #f1f5f9; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 48px; }
        .card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; }
        .card h3 { font-size: 14px; font-weight: 600; color: #334155; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.03em; }
        .card p { color: #64748b; font-size: 14px; }
        .ok { color: #16a34a; font-weight: 500; }
        footer { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 64px; }
        code { background: #f1f5f9; padding: 1px 6px; border-radius: 4px; font-size: 13px; font-family: ui-monospace, SFMono-Regular, monospace; }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <div>
                <h1>GRM Sierra Leone</h1>
                <span class="tag">Grievance Response Management · rewrite build</span>
            </div>
            <span class="ok">● running</span>
        </header>

        <section class="hero">
            <h2>The rewrite is live.</h2>
            <p>
                Laravel {{ app()->version() }} on PHP {{ PHP_VERSION }}. State machine, audit log, notifications,
                role-based access, and reports all wired up. Database migrated with
                <strong>{{ \Spatie\Permission\Models\Permission::count() }}</strong> seeded permissions across
                <strong>{{ \Spatie\Permission\Models\Role::count() }}</strong> roles.
            </p>
            <div class="actions">
                <a class="btn btn-primary" href="/submit-grievance">Submit a grievance</a>
                <a class="btn btn-secondary" href="/login">Admin sign-in</a>
            </div>
        </section>

        <div class="grid">
            <div class="card">
                <h3>Endpoints</h3>
                <p>
                    <code>GET /submit-grievance</code><br>
                    <code>POST /submit-grievance</code><br>
                    <code>GET /grievance/&lt;ref&gt;/status</code><br>
                    <code>POST /webhooks/sms/inbound</code>
                </p>
            </div>
            <div class="card">
                <h3>Admin</h3>
                <p>
                    <code>/admin/grievances</code> · <code>/admin/reports</code> · <code>/admin/users</code> ·
                    <code>/admin/roles</code> · <code>/admin/audit</code>
                </p>
            </div>
            <div class="card">
                <h3>Stack</h3>
                <p>Laravel 11 · Vue 3 · Inertia 2 · Vite · Tailwind · SQLite (demo). Prod target: MySQL + Redis + queue worker.</p>
            </div>
            <div class="card">
                <h3>Demo notes</h3>
                <p>
                    Inertia pages still need scaffold completion (Ziggy wiring, <code>app.ts</code> export).
                    This landing confirms the backend, routing, database, cache, and frontend build all boot cleanly.
                </p>
            </div>
        </div>

        <footer>Built with the rewrite in <code>/home/jmwebaze/grm_sl/rewrite</code>.</footer>
    </div>
</body>
</html>
