{{-- Chrome renders the footer in isolation: every style must be inline, with an explicit font size. --}}
<div style="box-sizing: border-box; width: 100%; padding: 0 18mm; display: flex; justify-content: space-between; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 8pt; color: #64748b;">
    <span>Gene &amp; Tonic &middot; {{ $generatedAt->format('M j, Y') }} &middot; Research summary, not medical advice</span>
    <span>Page @pageNumber of @totalPages</span>
</div>
