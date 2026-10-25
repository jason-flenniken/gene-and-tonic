{{-- Chrome renders the header in isolation: every style must be inline, with an explicit font size. --}}
<div style="box-sizing: border-box; width: 100%; padding: 0 16mm; display: flex; justify-content: space-between; font-family: Georgia, 'Times New Roman', Times, serif; font-size: 8pt; color: #4b5563;">
    <span>{{ $research->name }} | Gene &amp; Tonic</span>
    <span>{{ $label }}</span>
</div>
