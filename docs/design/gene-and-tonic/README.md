# Gene & Tonic — design handoff

This folder holds the approved redesign of this app as **Gene & Tonic** (direction "Graded Research"). [`HANDOFF.md`](HANDOFF.md) is the spec. Everything else is reference material.

```
HANDOFF.md        spec: IA, shell, components, per-screen data mapping, gaps, plan, tests
tokens.css        Tailwind 4 @theme block (colours, radii, Flux accent), replaces app.css @theme
screens/          static HTML of each screen, exact measurements inline (open in a browser)
screenshots/      PNG of each screen at 1440 px (desktop) and 390 px (mobile, @2x)
brand/            mark.svg, mark-small.svg (≤24 px), lockup(-reverse).svg, wordmark.svg,
                  app-icon.svg/-512.png, favicon.svg/.ico, apple-touch-icon.png, preview.png
icons/            24 px stroke icons from the mocks (helix, capsule, group icons, ...)
```

Screens: `01-home`, `02-supplements`, `03-supplement-detail`, `04-genetics`, `05-ai-usage`.

Design canvas (all screens, the mark explorations and earlier directions): https://claude.ai/code/artifact/ea9583cc-98e7-4510-847f-9f512cb03009

## Kick-off prompt for Claude Code

```
Implement the Gene & Tonic redesign described in docs/design/gene-and-tonic/HANDOFF.md.

- Read CLAUDE.md and AGENTS.md first; their module boundary rules and domain
  constraints win over the handoff.
- The static HTML in docs/design/gene-and-tonic/screens/ is the visual source of
  truth: copy its exact sizes, colours and spacing into Tailwind classes using the
  tokens in tokens.css. Screenshots show desktop and mobile.
- Work through HANDOFF.md §10 one step at a time. Start with step 1
  (foundations + x-ui components + the local /_ui page) and stop for review
  before step 2.
- Do not invent data: anything in HANDOFF.md §9 marked Omit stays out; "Build"
  items need their contract/DTO change, fake and tests in the same step.
- Use Livewire 4 single-file components and Flux (free) as the repo already does;
  check Flux and Livewire APIs with Boost's search-docs before using them.
- Keep existing data-* test hooks; run `composer test` before finishing each step.
```

Tip: add one line to CLAUDE.md, such as "UI design source of truth: docs/design/gene-and-tonic/HANDOFF.md", so every later session finds it.
