{{-- Chrome renders the footer in isolation: every style must be inline, with an explicit font size. --}}
<div style="box-sizing: border-box; width: 100%; padding: 0 16mm; display: flex; justify-content: space-between; font-family: Georgia, 'Times New Roman', Times, serif; font-size: 8pt; color: #4b5563;">
    <span>Gene &amp; Tonic | Generated {{ $generatedAt->format('F j, Y') }} | Not medical advice</span>
    <span>Page @pageNumber of @totalPages</span>
</div>
