{{-- Chrome renders the header in isolation: every style must be inline, with an explicit font size. --}}
<div style="box-sizing: border-box; width: 100%; padding: 0 18mm; display: flex; justify-content: space-between; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; font-size: 8pt; color: #64748b;">
    <span style="font-weight: 600; color: #0f766e;">{{ $research->name }}</span>
    <span>{{ $label }} report</span>
</div>
