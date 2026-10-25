<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subsystem ? __('maintenance.subsystem_titles.'.$subsystem) : __('maintenance.page_title', ['app' => config('app.name')]) }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #dddddf;
            --panel: #f4f0ea;
            --border: #cabca8;
            --text: #0f2133;
            --muted: #5c6570;
            --accent: #8f3f1f;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem;
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.72), transparent 32%),
                linear-gradient(180deg, #e7e0d6 0%, var(--bg) 100%);
            color: var(--text);
            font-family: Georgia, "Times New Roman", serif;
        }

        main {
            width: min(100%, 42rem);
            border: 1px solid var(--border);
            border-radius: 18px;
            background: rgba(244, 240, 234, 0.94);
            padding: 2rem;
            box-shadow: 0 16px 42px rgba(21, 16, 11, 0.14);
        }

        .eyebrow {
            margin: 0 0 0.75rem;
            color: var(--accent);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.05;
        }

        p {
            margin: 1rem 0 0;
            font-size: 1.05rem;
            line-height: 1.65;
        }

        .meta {
            margin-top: 1.5rem;
            color: var(--muted);
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
<main>
    <p class="eyebrow">{{ __('maintenance.eyebrow') }}</p>
    <h1>{{ $subsystem ? __('maintenance.subsystem_titles.'.$subsystem) : __('maintenance.title', ['app' => config('app.name')]) }}</h1>
    <p>{{ $message }}</p>
    @if(($startedAtUtc ?? null) !== null)
        <p class="meta">{{ __('maintenance.started_at', ['time' => $startedAtUtc]) }}</p>
    @endif
    @if(($retryAfter ?? 0) > 0)
        <p class="meta">{{ __('maintenance.retry_after', ['duration' => $retryAfterHuman ?? '']) }}</p>
    @endif
</main>
</body>
</html>
