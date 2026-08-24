<section class="reveal" style="max-width:1040px;margin:0 auto;padding:30px 40px 76px;text-align:center;">
    <div style="display:flex;flex-direction:column;gap:34px;">
        @foreach ($grupos as $etiqueta => $logos)
            @continue($logos->isEmpty())
            @php $destacado = $loop->first; @endphp

            <div>
                <div style="font-size:12.5px;letter-spacing:.06em;text-transform:uppercase;font-weight:700;color:var(--naranjo);margin-bottom:16px;">{{ $etiqueta }}</div>

                <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:{{ $destacado ? '24px' : '18px' }};">
                    @foreach ($logos as $lg)
                        <div class="logo-chip" style="display:grid;place-items:center;height:{{ $destacado ? '124px' : '76px' }};padding:0 {{ $destacado ? '44px' : '26px' }};background:#fff;border:1px solid #eef0f1;border-radius:{{ $destacado ? '16px' : '14px' }};box-shadow:0 6px 18px -14px rgba(0,0,0,.25);">
                            @if ($lg->logo_path)
                                <img src="{{ $lg->logo_url }}" alt="{{ $lg->nombre }}"
                                     style="max-height:{{ $destacado ? '76px' : '44px' }};max-width:{{ $destacado ? '300px' : '200px' }};width:auto;object-fit:contain;">
                            @else
                                <span style="font-weight:800;font-size:20px;letter-spacing:-.01em;color:var(--gris-700);white-space:nowrap;">{{ $lg->nombre }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>
