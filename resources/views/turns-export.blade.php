<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Exportar Turnos</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #dbe2ee;
            --brand: #2563eb;
            --brand-soft: #eff6ff;
            --ok: #047857;
            --danger: #b91c1c;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial; background: var(--bg); color: var(--text); }
        .wrap { max-width: 900px; margin: 0 auto; padding: 24px 16px 40px; }
        .top { display: flex; justify-content: space-between; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; }
        .title { margin: 0; font-size: 24px; }
        .sub { margin: 6px 0 0; color: var(--muted); font-size: 14px; }
        .link { text-decoration: none; border: 1px solid var(--border); background: white; border-radius: 10px; padding: 8px 10px; color: var(--text); font-size: 13px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 16px; box-shadow: 0 8px 24px rgba(15, 23, 42, .05); }
        .label { display: block; margin-bottom: 8px; font-size: 13px; color: var(--muted); }
        textarea { width: 100%; min-height: 220px; border: 1px solid var(--border); border-radius: 12px; padding: 12px; font: inherit; }
        textarea:focus { outline: 2px solid #bfdbfe; border-color: #93c5fd; }
        .row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 12px; }
        .btn { border: 1px solid #1d4ed8; background: var(--brand); color: white; border-radius: 10px; padding: 10px 14px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #1d4ed8; }
        .hint { margin-top: 10px; color: var(--muted); font-size: 12px; }
        .ok { margin-top: 10px; background: #ecfdf5; border: 1px solid #a7f3d0; color: var(--ok); border-radius: 10px; padding: 8px 10px; font-size: 13px; }
        .err { margin-top: 10px; background: #fef2f2; border: 1px solid #fecaca; color: var(--danger); border-radius: 10px; padding: 8px 10px; font-size: 13px; }
        .example { margin-top: 10px; padding: 10px; background: var(--brand-soft); border: 1px solid #bfdbfe; border-radius: 10px; font-size: 13px; color: #1e3a8a; }
        .snippet-wrap { margin-top: 10px; }
        .snippet-title { margin: 0 0 6px; font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .snippet {
            margin: 0;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #0f172a;
            color: #e2e8f0;
            font-size: 12px;
            line-height: 1.5;
            overflow-x: auto;
            white-space: pre;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1 class="title">Exportar horários por texto de turnos</h1>
            <p class="sub">Cola os turnos com dia e hora (ex.: Seg 08:00-10:00 API T1). A app processa e abre o construtor com o horário carregado.</p>
        </div>
        <a class="link" href="{{ route('schedules.index') }}">Voltar ao construtor</a>
    </div>

    <div class="card">
        <form method="post" action="{{ route('turn-selection-export.apply') }}">
            @csrf
            <label class="label" for="selection_text">Turnos em texto</label>
            <textarea id="selection_text" name="selection_text" placeholder="Seg 08:00-10:00 API T1&#10;Ter 11:00-14:00 IA PL2&#10;Qua 16:00-18:00 ES TP1">{{ old('selection_text') }}</textarea>

            <div class="row">
                <button class="btn" type="submit">Processar e abrir construtor</button>
                <span class="ok">Sem upload manual: cola e processa diretamente.</span>
            </div>

            <div class="example">
                Exemplo válido: <code>Seg 08:00-10:00 API T1 | Qua 16:00-18:00 ES TP1 | Qui 09:00-11:00 API PL2</code>
            </div>

            <div class="snippet-wrap">
                <p class="snippet-title">Snippet (cola neste formato)</p>
                <pre class="snippet">Seg 08:00-10:00 API T1
Seg 15:00-18:00 ES PL2
Ter 11:00-14:00 IA PL2
Ter 16:00-18:00 ES TP1
Qua 11:00-14:00 SI PL2
Qui 09:00-11:00 API PL2
Qui 10:00-13:00 SBD PL2</pre>
                <p class="hint">Podes pedir ao ChatGPT (ou outra IA) para converter prints/screenshot dos horários para este formato de texto.</p>
            </div>

            <div class="snippet-wrap">
                <p class="snippet-title">OBR (imutável) - como funciona</p>
                <pre class="snippet">OBR no texto NAO cria aula imutavel.
Imutavel ja vem definido no Excel.

Usa OBR apenas para exigir esse bloco fixo no match:
Sex 17:00-19:00 SBD OBR

Atalho tambem aceite:
SBD OBR</pre>
            </div>

            <p class="hint">Formato recomendado: <strong>Dia HH:MM-HH:MM UC TIPO</strong>. Podes separar por linhas ou vírgulas.</p>
            <p class="hint"><strong>Horários imutáveis (fixos)</strong> já entram automaticamente no resultado. Exemplo comum: <code>IA IAT1</code>, <code>SI TP1</code> e <code>SBD OBR</code>.</p>

            @if($errors->any())
                <div class="err">{{ $errors->first() }}</div>
            @endif
        </form>
    </div>
</div>
</body>
</html>
