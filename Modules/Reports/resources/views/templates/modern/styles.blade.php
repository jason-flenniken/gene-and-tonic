<style>
    :root {
        --ink: #0f172a;
        --muted: #64748b;
        --rule: #e2e8f0;
        --accent: #0f766e;
        --accent-soft: #ccfbf1;
        --panel: #f8fafc;
        --font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
    }

    * { box-sizing: border-box; }

    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    body {
        margin: 0;
        color: var(--ink);
        font-family: var(--font);
        font-size: 10pt;
        line-height: 1.55;
    }

    h1, h2, h3 { line-height: 1.25; break-after: avoid; }
    h1 { font-size: 28pt; font-weight: 800; margin: 0 0 8pt; color: #fff; }
    h2 { font-size: 14pt; font-weight: 700; margin: 0 0 10pt; color: var(--accent); }
    h3 { font-size: 11pt; font-weight: 600; margin: 12pt 0 4pt; }
    p { margin: 0 0 6pt; }
    a { color: var(--accent); }

    .hero { padding: 18pt 20pt; border-radius: 10pt; background: var(--accent); color: #fff; }
    .eyebrow { margin: 0 0 6pt; font-size: 8.5pt; letter-spacing: 0.14em; text-transform: uppercase; opacity: 0.85; }
    .hero-meta { margin: 0; font-size: 9pt; opacity: 0.95; }

    .pill { display: inline-block; padding: 1pt 7pt; margin-right: 4pt; border-radius: 10pt; background: var(--rule); color: var(--ink); font-size: 8pt; }
    .hero .pill { background: rgba(255, 255, 255, 0.2); color: #fff; }
    .pill-accent { background: var(--accent-soft); color: var(--accent); font-weight: 600; }

    .disclaimer { margin: 14pt 0 6pt; padding: 8pt 12pt; background: var(--panel); border: 1pt solid var(--rule); border-radius: 6pt; color: var(--muted); font-size: 9pt; }

    .report-section { margin-top: 18pt; }
    .report-section + .report-section { break-before: page; }

    .prose { white-space: pre-line; }
    .item { padding: 8pt 0; border-bottom: 0.5pt solid var(--rule); break-inside: avoid; }
    .item h3 { margin-top: 0; }
    .grade { color: var(--muted); font-size: 9pt; }
    .cite { color: var(--accent); font-size: 7.5pt; }
    .empty { color: var(--muted); }

    .study-cards { display: block; }
    .study-card { margin-bottom: 10pt; padding: 10pt 12pt; border: 1pt solid var(--rule); border-left: 4pt solid var(--accent); border-radius: 6pt; background: var(--panel); break-inside: avoid; }
    .study-card h3 { margin: 0 0 6pt; }
    .study-tags { margin: 0 0 6pt; }
    .study-population { color: var(--muted); font-size: 9pt; }
    .study-link { margin: 0; font-size: 9pt; }

    .sources { margin: 0; padding: 0; list-style: none; }
    .sources li { padding: 4pt 0; break-inside: avoid; }
    .source-number { display: inline-block; min-width: 22pt; color: var(--accent); font-weight: 600; }
    .source-domain { color: var(--muted); font-size: 9pt; }

    @media print {
        a { text-decoration: none; }
    }
</style>
