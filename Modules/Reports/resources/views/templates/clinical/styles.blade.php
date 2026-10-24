<style>
    :root {
        --ink: #1f2937;
        --muted: #4b5563;
        --rule: #d1d5db;
        --accent: #1e3a5f;
        --panel: #f3f4f6;
        --font: Georgia, 'Times New Roman', Times, serif;
    }

    * { box-sizing: border-box; }

    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    body {
        margin: 0;
        color: var(--ink);
        font-family: var(--font);
        font-size: 10.5pt;
        line-height: 1.5;
    }

    h1, h2, h3 { color: var(--accent); line-height: 1.25; break-after: avoid; }
    h1 { font-size: 24pt; margin: 0 0 8pt; }
    h2 { font-size: 15pt; margin: 0 0 10pt; padding-bottom: 4pt; border-bottom: 1pt solid var(--rule); }
    h3 { font-size: 11.5pt; margin: 12pt 0 4pt; }
    p { margin: 0 0 6pt; }
    a { color: var(--accent); }

    .title-block { padding-bottom: 12pt; border-bottom: 2pt solid var(--accent); }
    .eyebrow { margin: 0 0 4pt; color: var(--muted); font-size: 9pt; letter-spacing: 0.08em; text-transform: uppercase; }
    .meta { display: flex; gap: 24pt; margin: 0; }
    .meta dt { color: var(--muted); font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.06em; }
    .meta dd { margin: 0; }

    .disclaimer { margin: 14pt 0 18pt; padding: 8pt 10pt; background: var(--panel); border-left: 3pt solid var(--accent); font-size: 9.5pt; }

    .report-section { margin-top: 18pt; }
    .report-section + .report-section { break-before: page; }

    .prose { white-space: pre-line; }
    .item { padding: 8pt 0; border-bottom: 0.5pt solid var(--rule); break-inside: avoid; }
    .item h3 { margin-top: 0; }
    .grade { color: var(--muted); font-size: 9pt; font-style: italic; }
    .cite { color: var(--accent); font-size: 7.5pt; }
    .empty { color: var(--muted); font-style: italic; }

    .studies { width: 100%; border-collapse: collapse; font-size: 9pt; }
    .studies th, .studies td { padding: 4pt 5pt; text-align: left; vertical-align: top; border-bottom: 0.5pt solid var(--rule); }
    .studies th { color: var(--muted); font-weight: normal; text-transform: uppercase; font-size: 7.5pt; letter-spacing: 0.06em; }
    .studies tr { break-inside: avoid; }
    .study-title { font-weight: bold; }

    .sources { margin: 0; padding: 0; list-style: none; }
    .sources li { padding: 4pt 0; break-inside: avoid; }
    .source-number { display: inline-block; min-width: 22pt; color: var(--accent); }
    .source-domain { color: var(--muted); font-size: 9pt; }

    @media print {
        a { text-decoration: none; }
    }
</style>
