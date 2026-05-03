<style>
    :root {
        --ink: #112031;
        --muted: #577086;
        --surface: rgba(255, 255, 255, 0.9);
        --line: rgba(17, 32, 49, 0.12);
        --accent: #0f766e;
        --accent-strong: #164e63;
        --danger: #b42318;
        --shadow: 0 24px 64px rgba(17, 32, 49, 0.12);
    }

    * { box-sizing: border-box; }
    body {
        margin: 0;
        min-height: 100vh;
        font-family: "Instrument Sans", sans-serif;
        color: var(--ink);
        background:
            radial-gradient(circle at top left, rgba(32, 201, 151, 0.24), transparent 30%),
            linear-gradient(180deg, #f7fbfc 0%, #eef5f8 52%, #e5edf2 100%);
    }

    .page { width: min(1040px, calc(100% - 32px)); margin: 0 auto; padding: 36px 0 64px; }
    .panel {
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: 28px;
        box-shadow: var(--shadow);
        padding: 28px;
    }
    .header-row { margin-bottom: 24px; }
    h1, h2, p { margin: 0; }
    .header-row p { margin-top: 12px; color: var(--muted); line-height: 1.6; max-width: 64ch; }
    .form-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .field { display: grid; gap: 8px; }
    .field-wide { grid-column: 1 / -1; }
    .field span, .toggle span { font-size: 0.94rem; font-weight: 600; }
    .field input {
        width: 100%;
        border: 1px solid rgba(17, 32, 49, 0.16);
        border-radius: 16px;
        padding: 14px 16px;
        font: inherit;
        background: rgba(255,255,255,0.82);
    }
    .toggle {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding-top: 34px;
    }
    .prerequisite-panel {
        margin-top: 28px;
        padding-top: 24px;
        border-top: 1px solid rgba(17, 32, 49, 0.08);
    }
    .section-title {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-bottom: 16px;
    }
    .section-title span, .muted-note { color: var(--muted); }
    .prerequisite-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .prerequisite-option {
        display: flex;
        gap: 12px;
        align-items: start;
        padding: 14px 16px;
        border: 1px solid rgba(17, 32, 49, 0.1);
        border-radius: 18px;
        background: rgba(255,255,255,0.7);
    }
    .prerequisite-option span {
        display: grid;
        gap: 4px;
    }
    .prerequisite-option small { color: var(--muted); }
    .action-row {
        display: flex;
        gap: 14px;
        align-items: center;
        margin-top: 28px;
    }
    .button, .secondary-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        padding: 12px 18px;
        font-weight: 700;
        text-decoration: none;
    }
    .button {
        border: 0;
        background: linear-gradient(135deg, var(--accent), var(--accent-strong));
        color: #fff;
        cursor: pointer;
        font: inherit;
    }
    .secondary-link { color: var(--accent); }
    .error-box {
        margin-bottom: 20px;
        padding: 16px 18px;
        border-radius: 18px;
        border: 1px solid rgba(180, 35, 24, 0.18);
        background: rgba(180, 35, 24, 0.08);
        color: var(--danger);
    }
    .error-box ul { margin: 10px 0 0; padding-left: 18px; }
    @media (max-width: 760px) {
        .page { width: min(100% - 18px, 1040px); }
        .form-grid, .prerequisite-grid { grid-template-columns: 1fr; }
        .toggle { padding-top: 0; }
        .action-row { flex-direction: column; align-items: start; }
    }
</style>
