@php
    $evaluation = $evaluation ?? null;
    $narration = $narration ?? null;
    $uploadedFileName = $uploadedFileName ?? null;
    $useAi = $useAi ?? true;
    $editingCourse = $editingCourse ?? null;
    $hasEvaluation = is_array($evaluation);
    $selectedSemester = (int) ($selectedSemester ?? old('semester', 1));
    $gradeInput = $gradeInput ?? old('grades', []);
    $coursesBySemester = $coursesBySemester ?? collect();
    $semesters = $semesters ?? range(1, \App\Models\Course::MAX_SEMESTER);
    $activeCoursesCount = $courses->where('is_active', true)->count();
    $configuredSemestersCount = $coursesBySemester->filter(fn ($semesterCourses) => $semesterCourses->isNotEmpty())->count();
    $selectedSemesterCourseCount = ($coursesBySemester->get($selectedSemester) ?? collect())->count();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Grade Evaluator</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        <style>
            :root {
                --canvas: #f5f7fa;
                --surface: #ffffff;
                --surface-muted: #f8fafc;
                --ink: #121826;
                --muted: #667085;
                --line: #d9e1ec;
                --line-soft: #eef2f6;
                --accent: #2563eb;
                --accent-ink: #1d4ed8;
                --success: #047857;
                --success-bg: #ecfdf3;
                --danger: #b42318;
                --danger-bg: #fff1f0;
                --warning: #b54708;
                --warning-bg: #fffaeb;
                --shadow: 0 14px 34px rgba(15, 23, 42, 0.07);
            }

            * {
                box-sizing: border-box;
            }

            html {
                scroll-behavior: smooth;
            }

            body {
                margin: 0;
                min-height: 100vh;
                font-family: "Instrument Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                color: var(--ink);
                background: var(--canvas);
            }

            a {
                color: inherit;
            }

            h1,
            h2,
            h3,
            p {
                margin: 0;
            }

            .app-shell {
                min-height: 100vh;
            }

            .topbar {
                position: sticky;
                top: 0;
                z-index: 20;
                border-bottom: 1px solid var(--line);
                background: rgba(255, 255, 255, 0.94);
                backdrop-filter: blur(14px);
            }

            .topbar-inner,
            .page {
                width: min(1220px, calc(100% - 32px));
                margin: 0 auto;
            }

            .topbar-inner {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
                min-height: 68px;
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 0;
            }

            .brand-mark {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                border-radius: 8px;
                background: #111827;
                color: #ffffff;
                font-size: 0.82rem;
                font-weight: 800;
            }

            .brand-title {
                display: grid;
                gap: 2px;
                min-width: 0;
            }

            .brand-title strong {
                font-size: 0.98rem;
                line-height: 1.2;
            }

            .brand-title span {
                color: var(--muted);
                font-size: 0.78rem;
            }

            .topbar-actions {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                justify-content: flex-end;
            }

            .page {
                padding: 28px 0 56px;
            }

            .section-header {
                display: flex;
                align-items: end;
                justify-content: space-between;
                gap: 24px;
                margin-bottom: 16px;
            }

            .section-header h1 {
                max-width: 760px;
                font-size: 2rem;
                line-height: 1.15;
                font-weight: 800;
            }

            .section-header h2 {
                font-size: 1.35rem;
                line-height: 1.25;
            }

            .section-copy {
                margin-top: 10px;
                max-width: 820px;
                color: var(--muted);
                line-height: 1.65;
            }

            .meta-strip {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
                margin-top: 18px;
            }

            .flow-strip {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px;
                margin: 18px 0;
            }

            .flow-step {
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 0;
                padding: 14px;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: var(--surface);
                box-shadow: var(--shadow);
            }

            .flow-step-number {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                flex: 0 0 auto;
                width: 28px;
                height: 28px;
                border-radius: 999px;
                background: #eef4ff;
                color: var(--accent-ink);
                font-size: 0.82rem;
                font-weight: 900;
            }

            .flow-step strong,
            .flow-step span {
                display: block;
            }

            .flow-step strong {
                font-size: 0.9rem;
            }

            .flow-step span:last-child {
                margin-top: 2px;
                color: var(--muted);
                font-size: 0.78rem;
            }

            .workspace-grid,
            .course-workspace {
                display: grid;
                grid-template-columns: minmax(320px, 420px) minmax(0, 1fr);
                gap: 18px;
                align-items: start;
            }

            .module {
                background: var(--surface);
                border: 1px solid var(--line);
                border-radius: 8px;
                box-shadow: var(--shadow);
            }

            .module-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 14px;
                padding: 18px 20px;
                border-bottom: 1px solid var(--line-soft);
            }

            .module-header h2 {
                font-size: 1rem;
                line-height: 1.3;
            }

            .module-header span,
            .module-subtitle {
                color: var(--muted);
                font-size: 0.86rem;
            }

            .module-body {
                padding: 20px;
            }

            .semester-form,
            .upload-form,
            .course-form {
                display: grid;
                gap: 16px;
            }

            .field {
                display: grid;
                gap: 8px;
            }

            .field label,
            .field > span,
            .checkbox label,
            .toggle span {
                color: #344054;
                font-size: 0.88rem;
                font-weight: 700;
            }

            .field input[type="file"],
            .field input[type="text"],
            .field input[type="number"],
            .field select {
                width: 100%;
                min-height: 42px;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                padding: 10px 12px;
                background: #ffffff;
                color: var(--ink);
                font: inherit;
                outline: none;
                appearance: none;
                transition: border-color 0.15s ease, box-shadow 0.15s ease;
            }

            .field select {
                background-image:
                    linear-gradient(45deg, transparent 50%, #64748b 50%),
                    linear-gradient(135deg, #64748b 50%, transparent 50%);
                background-position:
                    calc(100% - 18px) 18px,
                    calc(100% - 13px) 18px;
                background-repeat: no-repeat;
                background-size: 5px 5px, 5px 5px;
                padding-right: 34px;
            }

            .field input[type="file"] {
                padding: 14px;
                border-style: dashed;
                background: var(--surface-muted);
            }

            .field input:focus,
            .field select:focus {
                border-color: var(--accent);
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
            }

            .semester-tabs {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 8px;
            }

            .semester-tab {
                display: grid;
                gap: 3px;
                min-height: 58px;
                border: 1px solid var(--line);
                border-radius: 8px;
                padding: 9px 10px;
                background: #ffffff;
                color: #344054;
                font: inherit;
                text-align: left;
                cursor: pointer;
            }

            .semester-tab strong {
                font-size: 0.88rem;
                line-height: 1.1;
            }

            .semester-tab span {
                color: var(--muted);
                font-size: 0.75rem;
            }

            .semester-tab.is-active {
                border-color: var(--accent);
                background: #eff6ff;
                color: var(--accent-ink);
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            }

            .semester-tab:disabled {
                opacity: 0.52;
                cursor: not-allowed;
            }

            .semester-course-group[hidden] {
                display: none;
            }

            .course-grade-list {
                display: grid;
                gap: 10px;
            }

            .course-grade-row {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 118px;
                gap: 12px;
                align-items: center;
                padding: 12px;
                border: 1px solid var(--line-soft);
                border-radius: 8px;
                background: var(--surface-muted);
            }

            .course-grade-row:hover {
                border-color: #bfdbfe;
                background: #f8fbff;
            }

            .course-grade-row strong,
            .course-grade-row span {
                display: block;
            }

            .course-grade-row span {
                margin-top: 2px;
                color: var(--muted);
                font-size: 0.84rem;
                line-height: 1.35;
            }

            .course-grade-meta {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                margin-top: 7px;
            }

            .legacy-import {
                margin-top: 22px;
            }

            .legacy-import summary {
                cursor: pointer;
                list-style: none;
            }

            .legacy-import summary::-webkit-details-marker {
                display: none;
            }

            .form-grid {
                display: grid;
                gap: 14px;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .field-wide {
                grid-column: 1 / -1;
            }

            .checkbox,
            .toggle {
                display: flex;
                align-items: flex-start;
                gap: 10px;
            }

            .checkbox {
                padding: 12px;
                border: 1px solid var(--line-soft);
                border-radius: 8px;
                background: var(--surface-muted);
            }

            .toggle {
                align-items: center;
                min-height: 42px;
                padding-top: 24px;
            }

            input[type="checkbox"] {
                accent-color: var(--accent);
            }

            .hint,
            .muted,
            .muted-note {
                color: var(--muted);
            }

            .hint {
                font-size: 0.9rem;
                line-height: 1.55;
            }

            .button,
            .secondary-link,
            .danger-link {
                font: inherit;
                font-weight: 800;
            }

            .button {
                appearance: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 42px;
                border: 1px solid var(--accent);
                border-radius: 6px;
                padding: 10px 16px;
                background: var(--accent);
                color: #ffffff;
                text-decoration: none;
                cursor: pointer;
            }

            .button:disabled {
                border-color: #cbd5e1;
                background: #e2e8f0;
                color: #64748b;
                cursor: not-allowed;
            }

            .button:hover {
                background: var(--accent-ink);
                border-color: var(--accent-ink);
            }

            .button:disabled:hover {
                border-color: #cbd5e1;
                background: #e2e8f0;
            }

            .secondary-link {
                display: inline-flex;
                align-items: center;
                min-height: 34px;
                color: var(--accent-ink);
                font-size: 0.9rem;
                text-decoration: none;
            }

            .secondary-link:hover {
                text-decoration: underline;
            }

            .template-links,
            .action-row,
            .course-action-list {
                display: flex;
                align-items: center;
                gap: 12px;
                flex-wrap: wrap;
            }

            .action-row {
                margin-top: 4px;
            }

            .course-action-list form {
                margin: 0;
            }

            .danger-link {
                border: 0;
                padding: 0;
                background: transparent;
                color: var(--danger);
                cursor: pointer;
            }

            .danger-link:hover {
                text-decoration: underline;
            }

            .badge,
            .status-pill,
            .source-pill,
            .pill {
                display: inline-flex;
                align-items: center;
                width: fit-content;
                min-height: 26px;
                border-radius: 999px;
                padding: 4px 10px;
                font-size: 0.75rem;
                font-weight: 800;
                line-height: 1;
                text-transform: uppercase;
                white-space: nowrap;
            }

            .badge {
                border: 1px solid var(--line);
                background: #ffffff;
                color: #344054;
            }

            .badge-rule {
                border-color: #fedf89;
                background: var(--warning-bg);
                color: var(--warning);
            }

            .status-pass,
            .source-ai,
            .pill-active {
                background: var(--success-bg);
                color: var(--success);
            }

            .status-fail,
            .source-fallback,
            .pill-inactive {
                background: var(--danger-bg);
                color: var(--danger);
            }

            .source-rules {
                background: var(--warning-bg);
                color: var(--warning);
            }

            .summary-state {
                display: grid;
                gap: 16px;
            }

            .summary-topline {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding-bottom: 14px;
                border-bottom: 1px solid var(--line-soft);
            }

            .metrics {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                border: 1px solid var(--line-soft);
                border-radius: 8px;
                overflow: hidden;
            }

            .metric {
                min-width: 0;
                padding: 14px;
                border-right: 1px solid var(--line-soft);
                background: var(--surface-muted);
            }

            .metric:last-child {
                border-right: 0;
            }

            .metric .label {
                display: block;
                color: var(--muted);
                font-size: 0.76rem;
                font-weight: 700;
                line-height: 1.25;
                text-transform: uppercase;
            }

            .metric strong {
                display: block;
                margin-top: 6px;
                font-size: 1.35rem;
                line-height: 1.1;
            }

            .empty-state {
                display: grid;
                gap: 14px;
                min-height: 286px;
                align-content: center;
                color: var(--muted);
                line-height: 1.65;
            }

            .empty-state strong {
                color: var(--ink);
            }

            .curriculum-map {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px;
                margin-bottom: 18px;
            }

            .semester-card {
                display: grid;
                gap: 10px;
                min-height: 126px;
                padding: 14px;
                border: 1px solid var(--line);
                border-radius: 8px;
                background: var(--surface);
            }

            .semester-card-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
            }

            .semester-card h3 {
                font-size: 0.95rem;
            }

            .semester-course-chips {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }

            .course-chip {
                display: inline-flex;
                align-items: center;
                min-height: 24px;
                border-radius: 999px;
                padding: 4px 8px;
                background: var(--surface-muted);
                color: #344054;
                font-size: 0.75rem;
                font-weight: 800;
            }

            .results-stack,
            .dashboard-section {
                margin-top: 22px;
            }

            .results-stack {
                display: grid;
                gap: 18px;
            }

            .notice,
            .status-box,
            .error-box {
                border-radius: 8px;
                padding: 12px 14px;
                line-height: 1.5;
            }

            .notice {
                margin-bottom: 12px;
                border: 1px solid #fedf89;
                background: var(--warning-bg);
                color: var(--warning);
                font-size: 0.9rem;
            }

            .status-box {
                margin-bottom: 16px;
                border: 1px solid #abefc6;
                background: var(--success-bg);
                color: var(--success);
                font-weight: 700;
            }

            .error-box {
                margin-bottom: 18px;
                border: 1px solid #fecdca;
                background: var(--danger-bg);
                color: var(--danger);
            }

            .error-box ul {
                margin: 8px 0 0;
                padding-left: 18px;
            }

            .narration-text {
                color: #344054;
                line-height: 1.7;
            }

            .table-wrap {
                overflow-x: auto;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                background: #ffffff;
            }

            th,
            td {
                padding: 12px 14px;
                border-bottom: 1px solid var(--line-soft);
                text-align: left;
                vertical-align: top;
                font-size: 0.92rem;
            }

            th {
                color: #475467;
                background: var(--surface-muted);
                font-size: 0.73rem;
                font-weight: 800;
                line-height: 1.2;
                text-transform: uppercase;
                white-space: nowrap;
            }

            tbody tr:hover {
                background: #fbfdff;
            }

            tbody tr.fail-row {
                background: #fff8f7;
            }

            .subject-cell {
                min-width: 220px;
            }

            .subject-cell strong {
                display: inline-block;
                margin-bottom: 2px;
            }

            .stacked-copy {
                display: grid;
                gap: 4px;
            }

            .prerequisite-panel {
                margin-top: 2px;
                padding-top: 16px;
                border-top: 1px solid var(--line-soft);
            }

            .section-title {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 12px;
            }

            .section-title h2 {
                font-size: 0.96rem;
            }

            .section-title span {
                color: var(--muted);
                font-size: 0.84rem;
            }

            .prerequisite-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .prerequisite-option {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                min-width: 0;
                padding: 10px;
                border: 1px solid var(--line-soft);
                border-radius: 8px;
                background: var(--surface-muted);
            }

            .prerequisite-option span {
                display: grid;
                gap: 2px;
                min-width: 0;
            }

            .prerequisite-option small {
                color: var(--muted);
                line-height: 1.35;
            }

            .course-table td:nth-child(2) {
                min-width: 210px;
            }

            @media (max-width: 1040px) {
                .workspace-grid,
                .course-workspace {
                    grid-template-columns: 1fr;
                }

                .flow-strip,
                .curriculum-map {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .metrics {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }

                .metric:nth-child(3) {
                    border-right: 0;
                }

                .metric:nth-child(n + 4) {
                    border-top: 1px solid var(--line-soft);
                }
            }

            @media (max-width: 760px) {
                .topbar-inner,
                .page {
                    width: min(100% - 20px, 1220px);
                }

                .topbar-inner,
                .section-header,
                .summary-topline {
                    align-items: flex-start;
                    flex-direction: column;
                }

                .section-header h1 {
                    font-size: 1.6rem;
                }

                .topbar-actions {
                    justify-content: flex-start;
                }

                .module-header {
                    align-items: flex-start;
                    flex-direction: column;
                }

                .metrics,
                .form-grid,
                .prerequisite-grid,
                .semester-tabs,
                .flow-strip,
                .curriculum-map,
                .course-grade-row {
                    grid-template-columns: 1fr;
                }

                .metric {
                    border-right: 0;
                    border-top: 1px solid var(--line-soft);
                }

                .metric:first-child {
                    border-top: 0;
                }

                .toggle {
                    padding-top: 0;
                }
            }
        </style>
    </head>
    <body>
        <div class="app-shell">
            <header class="topbar">
                <div class="topbar-inner">
                    <a class="brand" href="{{ route('grade-evaluator.index') }}" aria-label="Grade Evaluator home">
                        <span class="brand-mark">GE</span>
                        <span class="brand-title">
                            <strong>Grade Evaluator</strong>
                            <span>Semester progression and prerequisites</span>
                        </span>
                    </a>

                    <nav class="topbar-actions" aria-label="Primary navigation">
                        <a class="secondary-link" href="#evaluate">Evaluate Semester</a>
                        <a class="secondary-link" href="#courses">Course Dashboard</a>
                        <a class="secondary-link" href="#workbook-import">Workbook Import</a>
                        <span class="badge badge-rule">Blank Grade = FAIL</span>
                    </nav>
                </div>
            </header>

            <main class="page">
                <section id="evaluate" class="section-header">
                    <div>
                        <span class="badge">Semester Evaluation</span>
                        <h1>Evaluate semester progression.</h1>
                        <p class="section-copy">
                            Assign courses to semesters, choose the semester being evaluated, enter the student grades,
                            and the system will decide whether the student can proceed. Failed subjects also block any
                            future subjects that depend on them as prerequisites.
                        </p>
                        <div class="meta-strip">
                            <span class="badge badge-rule">Blank Grade = FAIL</span>
                            <span class="badge">Passing grade: 75</span>
                            <span class="badge">Prerequisites block future subjects</span>
                        </div>
                    </div>

                    <div class="template-links">
                        <a class="secondary-link" href="#courses">Define Courses</a>
                        <a class="secondary-link" href="#workbook-import">Use Workbook Import</a>
                    </div>
                </section>

                <section class="flow-strip" aria-label="Evaluation workflow">
                    <div class="flow-step">
                        <span class="flow-step-number">1</span>
                        <span>
                            <strong>Curriculum</strong>
                            <span>{{ $activeCoursesCount }} active courses</span>
                        </span>
                    </div>
                    <div class="flow-step">
                        <span class="flow-step-number">2</span>
                        <span>
                            <strong>Semester</strong>
                            <span>{{ $configuredSemestersCount }} configured</span>
                        </span>
                    </div>
                    <div class="flow-step">
                        <span class="flow-step-number">3</span>
                        <span>
                            <strong>Grades</strong>
                            <span>{{ $selectedSemesterCourseCount }} fields ready</span>
                        </span>
                    </div>
                    <div class="flow-step">
                        <span class="flow-step-number">4</span>
                        <span>
                            <strong>Result</strong>
                            <span>{{ $hasEvaluation ? ($evaluation['progression_status_label'] ?? 'Complete') : 'Pending' }}</span>
                        </span>
                    </div>
                </section>

                @if ($errors->has('semester') || $errors->has('grades') || $errors->has('grades.*'))
                    <section class="error-box">
                        <strong>Semester evaluation could not run.</strong>
                        <ul>
                            @error('semester')
                                <li>{{ $message }}</li>
                            @enderror
                            @error('grades')
                                <li>{{ $message }}</li>
                            @enderror
                            @error('grades.*')
                                <li>{{ $message }}</li>
                            @enderror
                        </ul>
                    </section>
                @endif

                @if ($errors->has('grade_sheet'))
                    <section class="error-box">
                        <strong>Upload could not be evaluated.</strong>
                        <ul>
                            @error('grade_sheet')
                                <li>{{ $message }}</li>
                            @enderror
                        </ul>
                    </section>
                @endif

                <section class="workspace-grid">
                    <article class="module">
                        <div class="module-header">
                            <div>
                                <h2>Evaluate Semester</h2>
                                <p class="module-subtitle">Semester {{ $selectedSemester }} selected.</p>
                            </div>
                            <span id="selected_semester_count">{{ $selectedSemesterCourseCount }} courses</span>
                        </div>

                        <div class="module-body">
                            <form class="semester-form" method="POST" action="{{ route('semester-evaluator.evaluate') }}">
                                @csrf

                                <div class="field">
                                    <label for="semester_selector">Semester to evaluate</label>
                                    <select id="semester_selector" name="semester" required>
                                        @foreach ($semesters as $semester)
                                            <option value="{{ $semester }}" @selected((int) old('semester', $selectedSemester) === (int) $semester)>
                                                Semester {{ $semester }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="semester-tabs" aria-label="Semester quick select">
                                    @foreach ($semesters as $semester)
                                        @php
                                            $semesterCoursesCount = ($coursesBySemester->get($semester) ?? collect())->count();
                                        @endphp
                                        <button
                                            class="semester-tab @if ((int) old('semester', $selectedSemester) === (int) $semester) is-active @endif"
                                            type="button"
                                            data-semester-tab="{{ $semester }}"
                                            data-course-count="{{ $semesterCoursesCount }}"
                                            @disabled($semesterCoursesCount === 0)
                                        >
                                            <strong>Semester {{ $semester }}</strong>
                                            <span>{{ $semesterCoursesCount }} {{ $semesterCoursesCount === 1 ? 'course' : 'courses' }}</span>
                                        </button>
                                    @endforeach
                                </div>

                                <div class="checkbox">
                                    <input
                                        id="use_ai"
                                        type="checkbox"
                                        name="use_ai"
                                        value="1"
                                        @checked(old('use_ai', $useAi))
                                    >
                                    <div>
                                        <label for="use_ai">Generate an AI explanation when configured</label>
                                        <p class="hint">Rules determine the result. AI only writes the explanation.</p>
                                    </div>
                                </div>

                                @foreach ($semesters as $semester)
                                    @php
                                        $semesterCourses = $coursesBySemester->get($semester) ?? collect();
                                    @endphp
                                    <section
                                        class="semester-course-group"
                                        data-semester-courses="{{ $semester }}"
                                        data-course-count="{{ $semesterCourses->count() }}"
                                        @if ((int) old('semester', $selectedSemester) !== (int) $semester) hidden @endif
                                    >
                                        @if ($semesterCourses->isEmpty())
                                            <p class="hint">
                                                No active courses are assigned to Semester {{ $semester }} yet. Add or
                                                update courses in the Course Dashboard below.
                                            </p>
                                        @else
                                            <div class="course-grade-list">
                                                @foreach ($semesterCourses as $course)
                                                    <label class="course-grade-row" for="grade_{{ $course->id }}">
                                                        <span>
                                                            <strong>{{ $course->code }}</strong>
                                                            <span>
                                                                {{ $course->title }}
                                                            </span>
                                                            <span class="course-grade-meta">
                                                                <span class="badge">{{ number_format((float) $course->credit_units, 2) }} units</span>
                                                                @if ($course->prerequisites->isNotEmpty())
                                                                    <span class="badge">Req: {{ $course->prerequisites->pluck('code')->implode(', ') }}</span>
                                                                @else
                                                                    <span class="badge">No prerequisite</span>
                                                                @endif
                                                            </span>
                                                        </span>
                                                        <span class="field">
                                                            <input
                                                                id="grade_{{ $course->id }}"
                                                                type="number"
                                                                name="grades[{{ $course->id }}]"
                                                                value="{{ old("grades.{$course->id}", $gradeInput[$course->id] ?? '') }}"
                                                                min="0"
                                                                max="100"
                                                                step="0.01"
                                                                placeholder="Grade"
                                                            >
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @endif
                                    </section>
                                @endforeach

                                <button
                                    id="evaluate_semester_button"
                                    class="button"
                                    type="submit"
                                    @disabled($selectedSemesterCourseCount === 0)
                                >
                                    Evaluate Semester
                                </button>
                            </form>
                        </div>
                    </article>

                    <article class="module">
                        <div class="module-header">
                            <div>
                                <h2>Evaluation Summary</h2>
                                <p class="module-subtitle">
                                    @if ($hasEvaluation)
                                        {{ $uploadedFileName }}
                                    @else
                                        No workbook uploaded yet
                                    @endif
                                </p>
                            </div>
                            @if ($hasEvaluation)
                                <span class="status-pill {{ $evaluation['overall_status'] === 'pass' ? 'status-pass' : 'status-fail' }}">
                                    {{ $evaluation['overall_status_label'] }}
                                </span>
                            @else
                                <span class="badge">Ready</span>
                            @endif
                        </div>

                        <div class="module-body">
                            @if ($hasEvaluation)
                                <div class="summary-state">
                                    <div class="summary-topline">
                                        <p class="hint">
                                            {{ ($evaluation['evaluation_type'] ?? 'workbook') === 'semester' ? 'Semester' : 'Worksheet' }}
                                            <strong>{{ $evaluation['semester'] ?? $evaluation['sheet_name'] }}</strong> evaluated with a
                                            passing grade of <strong>{{ number_format($evaluation['passing_grade'], 0) }}</strong>.
                                        </p>
                                        <p class="hint">
                                            {{ $evaluation['progression_status_label'] ?? 'Evaluation complete' }}
                                        </p>
                                    </div>

                                    <div class="metrics">
                                        <div class="metric">
                                            <span class="label">Subjects</span>
                                            <strong>{{ $evaluation['total_subjects'] }}</strong>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Failed</span>
                                            <strong>{{ $evaluation['failed_subjects'] }}</strong>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Blank Grades</span>
                                            <strong>{{ $evaluation['missing_grade_subjects'] }}</strong>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Blocked</span>
                                            <strong>{{ $evaluation['blocked_subjects'] }}</strong>
                                        </div>
                                        <div class="metric">
                                            <span class="label">Failed Units</span>
                                            <strong>{{ number_format($evaluation['failed_credit_units'], 2) }}</strong>
                                        </div>
                                    </div>

                                    <p class="hint">
                                        @if (($evaluation['evaluation_type'] ?? 'workbook') === 'semester')
                                            Failed subjects must be retaken. Any future subjects that require those failed
                                            subjects are listed as blocked.
                                        @else
                                            Subjects with failed prerequisites are marked as blocked for the next term.
                                            Saved course prerequisites override worksheet prerequisite text.
                                        @endif
                                    </p>

                                    @if (! empty($evaluation['blocked_courses']))
                                        <div class="course-grade-meta">
                                            @foreach (array_slice($evaluation['blocked_courses'], 0, 5) as $blockedCourse)
                                                <span class="badge badge-rule">
                                                    {{ $blockedCourse['code'] }} blocked
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="empty-state">
                                    <p>
                                        Select a semester and enter grades for its active courses. The result will show
                                        pass/fail counts, failed units, and future subjects blocked by prerequisite rules.
                                    </p>
                                    <p><strong>Blank grades are not ignored.</strong></p>
                                </div>
                            @endif
                        </div>
                    </article>
                </section>

                <details id="workbook-import" class="module legacy-import">
                    <summary class="module-header">
                        <span>
                            <h2>Workbook Import</h2>
                            <p class="module-subtitle">Secondary path for existing CSV or Excel grade sheets.</p>
                        </span>
                        <span>.xlsx or .csv</span>
                    </summary>

                    <div class="module-body">
                        <form class="upload-form" method="POST" action="{{ route('grade-evaluator.evaluate') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="field">
                                <label for="grade_sheet">Workbook or CSV file</label>
                                <input
                                    id="grade_sheet"
                                    type="file"
                                    name="grade_sheet"
                                    accept=".xlsx,.csv"
                                    required
                                >
                            </div>

                            <p class="hint">
                                Required headers: <strong>Subject Code</strong>, <strong>Credit Units</strong>, and
                                <strong>Grade</strong>. Optional subject details are shown when present.
                            </p>

                            <div class="template-links">
                                <a class="secondary-link" href="{{ route('grade-evaluator.template') }}">Download CSV template</a>
                                <a class="secondary-link" href="{{ route('grade-evaluator.excel-template') }}">Download Excel template</a>
                            </div>

                            <button class="button" type="submit">Evaluate Workbook</button>
                        </form>
                    </div>
                </details>

                @if ($hasEvaluation && $narration)
                    <section class="results-stack">
                        <article class="module">
                            <div class="module-header">
                                <div>
                                    <h2>Explanation Layer</h2>
                                    <p class="module-subtitle">Human-readable summary generated after rule evaluation.</p>
                                </div>
                                <span class="source-pill source-{{ $narration['source'] }}">
                                    {{ strtoupper($narration['source']) }}
                                </span>
                            </div>

                            <div class="module-body">
                                @if ($narration['notice'])
                                    <p class="notice">{{ $narration['notice'] }}</p>
                                @endif

                                <p class="narration-text">{{ $narration['content'] }}</p>
                            </div>
                        </article>

                        @if (! empty($evaluation['blocked_courses']))
                            <article class="module">
                                <div class="module-header">
                                    <div>
                                        <h2>Blocked Future Subjects</h2>
                                        <p class="module-subtitle">These subjects cannot be taken until failed prerequisites are passed.</p>
                                    </div>
                                    <span class="badge">{{ count($evaluation['blocked_courses']) }} blocked</span>
                                </div>

                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Semester</th>
                                                <th>Blocked By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($evaluation['blocked_courses'] as $blockedCourse)
                                                <tr>
                                                    <td>
                                                        <strong>{{ $blockedCourse['code'] }}</strong>
                                                        <br>
                                                        <span class="hint">{{ $blockedCourse['title'] }}</span>
                                                    </td>
                                                    <td>Semester {{ $blockedCourse['semester'] }}</td>
                                                    <td>{{ implode(', ', $blockedCourse['blocking_subject_codes']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        @endif

                        <article class="module">
                            <div class="module-header">
                                <div>
                                    <h2>Subject Evaluation</h2>
                                    <p class="module-subtitle">{{ $evaluation['passed_subjects'] }} passed / {{ $evaluation['failed_subjects'] }} failed</p>
                                </div>
                                <span class="badge">{{ count($evaluation['records']) }} rows</span>
                            </div>

                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Row</th>
                                            <th>Subject</th>
                                            <th>Units</th>
                                            <th>Grade</th>
                                            <th>Status</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($evaluation['records'] as $record)
                                            <tr class="{{ $record['status'] === 'fail' ? 'fail-row' : '' }}">
                                                <td>{{ $record['row_number'] }}</td>
                                                <td class="subject-cell">
                                                    <div class="stacked-copy">
                                                        <strong>{{ $record['subject_code'] }}</strong>
                                                        @if ($record['subject_description'] !== '')
                                                            <span class="hint">{{ $record['subject_description'] }}</span>
                                                        @endif
                                                        @if ($record['prerequisite'] !== '')
                                                            <span class="hint">Prerequisite: {{ $record['prerequisite'] }}</span>
                                                        @endif
                                                        @if ($record['is_blocked_for_next_term'])
                                                            <span class="hint">
                                                                Blocked next term by: {{ implode(', ', $record['blocking_subject_codes']) }}
                                                            </span>
                                                        @endif
                                                        @if (! empty($record['blocks_subject_codes']))
                                                            <span class="hint">
                                                                Blocks future subjects: {{ implode(', ', $record['blocks_subject_codes']) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>{{ number_format($record['credit_units_value'], 2) }}</td>
                                                <td>{{ $record['grade'] !== '' ? $record['grade'] : 'Blank' }}</td>
                                                <td>
                                                    <span class="status-pill {{ $record['status'] === 'pass' ? 'status-pass' : 'status-fail' }}">
                                                        {{ $record['status_label'] }}
                                                    </span>
                                                </td>
                                                <td>{{ $record['reason'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </section>
                @endif

                <section class="dashboard-section" id="courses">
                    <div class="section-header">
                        <div>
                            <span class="badge">Prerequisite Source</span>
                            <h2>Course Dashboard</h2>
                            <p class="section-copy">
                                Manage the official course list, assign each subject to a semester, and link
                                prerequisites. The semester evaluator uses these saved rules to decide which future
                                subjects are blocked.
                            </p>
                        </div>
                        <span class="badge">{{ $courses->count() }} saved courses</span>
                    </div>

                    @if (session('status'))
                        <div class="status-box">{{ session('status') }}</div>
                    @endif

                    <div class="curriculum-map">
                        @foreach ($semesters as $semester)
                            @php
                                $semesterCourses = $coursesBySemester->get($semester) ?? collect();
                            @endphp
                            <article class="semester-card">
                                <div class="semester-card-header">
                                    <h3>Semester {{ $semester }}</h3>
                                    <span class="badge">{{ $semesterCourses->count() }}</span>
                                </div>

                                @if ($semesterCourses->isEmpty())
                                    <p class="hint">No active courses.</p>
                                @else
                                    <div class="semester-course-chips">
                                        @foreach ($semesterCourses->take(8) as $course)
                                            <span class="course-chip">{{ $course->code }}</span>
                                        @endforeach
                                        @if ($semesterCourses->count() > 8)
                                            <span class="course-chip">+{{ $semesterCourses->count() - 8 }}</span>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    <div class="course-workspace">
                        <article class="module">
                            <div class="module-header">
                                <div>
                                    <h2>{{ $editingCourse ? 'Edit Course' : 'Add Course' }}</h2>
                                    <p class="module-subtitle">{{ $editingCourse ? $editingCourse->code : 'New curriculum entry' }}</p>
                                </div>
                            </div>

                            <div class="module-body">
                                <form
                                    class="course-form"
                                    method="POST"
                                    action="{{ $editingCourse ? route('courses.update', $editingCourse) : route('courses.store') }}"
                                >
                                    @csrf
                                    @if ($editingCourse)
                                        @method('PUT')
                                    @endif
                                    @include('courses._form', [
                                        'course' => $courseFormCourse,
                                        'buttonLabel' => $editingCourse ? 'Update Course' : 'Create Course',
                                    ])
                                </form>
                            </div>
                        </article>

                        <article class="module">
                            <div class="module-header">
                                <div>
                                    <h2>Saved Courses</h2>
                                    <p class="module-subtitle">Live semester and prerequisite source.</p>
                                </div>
                            </div>

                            @if ($courses->isEmpty())
                                <div class="module-body">
                                    <p class="muted">No courses configured yet.</p>
                                </div>
                            @else
                                <div class="table-wrap">
                                    <table class="course-table">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Title</th>
                                                <th>Semester</th>
                                                <th>Units</th>
                                                <th>Prerequisites</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($courses as $course)
                                                <tr>
                                                    <td><strong>{{ $course->code }}</strong></td>
                                                    <td>{{ $course->title }}</td>
                                                    <td>Semester {{ $course->semester }}</td>
                                                    <td>{{ number_format((float) $course->credit_units, 2) }}</td>
                                                    <td>
                                                        @if ($course->prerequisites->isEmpty())
                                                            <span class="muted">None</span>
                                                        @else
                                                            {{ $course->prerequisites->pluck('code')->implode(', ') }}
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="pill {{ $course->is_active ? 'pill-active' : 'pill-inactive' }}">
                                                            {{ $course->is_active ? 'Active' : 'Inactive' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="course-action-list">
                                                            <a class="secondary-link" href="{{ route('courses.edit', $course) }}">Edit</a>
                                                            <form method="POST" action="{{ route('courses.destroy', $course) }}">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="danger-link" type="submit">Delete</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </article>
                    </div>
                </section>
            </main>
        </div>
        <script>
            const semesterSelector = document.querySelector('#semester_selector');
            const semesterGroups = document.querySelectorAll('[data-semester-courses]');
            const semesterTabs = document.querySelectorAll('[data-semester-tab]');
            const selectedSemesterCount = document.querySelector('#selected_semester_count');
            const evaluateSemesterButton = document.querySelector('#evaluate_semester_button');

            if (semesterSelector) {
                const showSelectedSemester = () => {
                    let selectedCourseCount = 0;

                    semesterGroups.forEach((group) => {
                        const isSelected = group.dataset.semesterCourses === semesterSelector.value;

                        group.hidden = ! isSelected;

                        if (isSelected) {
                            selectedCourseCount = Number(group.dataset.courseCount || 0);
                        }
                    });

                    semesterTabs.forEach((tab) => {
                        tab.classList.toggle('is-active', tab.dataset.semesterTab === semesterSelector.value);
                    });

                    if (selectedSemesterCount) {
                        selectedSemesterCount.textContent = `${selectedCourseCount} ${selectedCourseCount === 1 ? 'course' : 'courses'}`;
                    }

                    if (evaluateSemesterButton) {
                        evaluateSemesterButton.disabled = selectedCourseCount === 0;
                    }
                };

                semesterSelector.addEventListener('change', showSelectedSemester);

                semesterTabs.forEach((tab) => {
                    tab.addEventListener('click', () => {
                        semesterSelector.value = tab.dataset.semesterTab;
                        showSelectedSemester();
                    });
                });

                showSelectedSemester();
            }
        </script>
    </body>
</html>
