<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Course Dashboard</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
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
            .page { width: min(1200px, calc(100% - 32px)); margin: 0 auto; padding: 36px 0 64px; }
            .panel {
                background: var(--surface);
                border: 1px solid var(--line);
                border-radius: 28px;
                box-shadow: var(--shadow);
                padding: 28px;
            }
            .hero, .toolbar, table { width: 100%; }
            .hero { display: flex; justify-content: space-between; gap: 16px; align-items: end; margin-bottom: 24px; }
            .hero h1, .hero p, h2 { margin: 0; }
            .hero p { margin-top: 12px; color: var(--muted); max-width: 70ch; line-height: 1.6; }
            .toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 20px; }
            .button, .secondary-link {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 16px;
                padding: 12px 18px;
                font-weight: 700;
                text-decoration: none;
            }
            .button { background: linear-gradient(135deg, var(--accent), var(--accent-strong)); color: #fff; }
            .secondary-link { color: var(--accent); }
            .status-box {
                margin-bottom: 20px;
                padding: 14px 16px;
                border-radius: 16px;
                color: var(--accent-strong);
                background: rgba(15, 118, 110, 0.1);
            }
            table { border-collapse: collapse; }
            th, td { padding: 14px 12px; border-bottom: 1px solid rgba(17, 32, 49, 0.08); text-align: left; vertical-align: top; }
            th { color: var(--muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; }
            .pill {
                display: inline-flex;
                padding: 6px 10px;
                border-radius: 999px;
                font-size: 0.78rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }
            .pill-active { background: rgba(15, 118, 110, 0.12); color: var(--accent); }
            .pill-inactive { background: rgba(180, 35, 24, 0.12); color: var(--danger); }
            .muted { color: var(--muted); }
            .action-list { display: flex; gap: 10px; flex-wrap: wrap; }
            .inline-form { margin: 0; }
            .danger-link {
                border: 0;
                background: transparent;
                color: var(--danger);
                font: inherit;
                font-weight: 700;
                padding: 0;
                cursor: pointer;
            }
            @media (max-width: 820px) {
                .hero, .toolbar { flex-direction: column; align-items: start; }
                .page { width: min(100% - 18px, 1200px); }
            }
        </style>
    </head>
    <body>
        <main class="page">
            <section class="hero panel">
                <div>
                    <h1>Course Dashboard</h1>
                    <p>Define your official course list here and assign prerequisites once. The evaluator can then use these saved rules instead of depending only on whatever text appears in the uploaded sheet.</p>
                </div>
                <a class="button" href="{{ route('courses.create') }}">Add Course</a>
            </section>

            <section class="panel">
                <div class="toolbar">
                    <h2>Curriculum Courses</h2>
                    <a class="secondary-link" href="{{ route('grade-evaluator.index') }}">Open evaluator</a>
                </div>

                @if (session('status'))
                    <div class="status-box">{{ session('status') }}</div>
                @endif

                @if ($courses->isEmpty())
                    <p class="muted">No courses configured yet.</p>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Title</th>
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
                                        <div class="action-list">
                                            <a class="secondary-link" href="{{ route('courses.edit', $course) }}">Edit</a>
                                            <form class="inline-form" method="POST" action="{{ route('courses.destroy', $course) }}">
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
                @endif
            </section>
        </main>
    </body>
</html>
