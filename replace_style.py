from pathlib import Path
path = Path('resources/views/dashboard.blade.php')
text = path.read_text()
old = """        .summary-card .card-body {

            display: flex;

            flex-direction: column;

            height: 100%;

        }


        .summary-card .summary-meta {

            margin-top: auto;

        }"""
new = """        .summary-card .card-body {

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            gap: 0.75rem;

            height: 100%;

        }


        .summary-card .summary-meta {

            margin-top: 0;

        }"""
if old not in text:
    raise SystemExit('not found block')
text = text.replace(old, new, 1)
path.write_text(text)
