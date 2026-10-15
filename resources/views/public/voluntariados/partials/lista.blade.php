{{-- El listado. Sale de `index.blade.php`, que trae la cabecera, los filtros y el conmutador. --}}
@if ($voluntariados->isEmpty())
    <div class="card" style="padding:44px;text-align:center;">
        <p style="font-size:16px;color:var(--gris);margin:0 0 18px;">
            {{ $puestos ? 'No encontramos voluntariados con esos filtros.' : 'Todavía no hay voluntariados publicados. Vuelve pronto.' }}
        </p>
        @if ($puestos)
            <a href="{{ route('voluntariados.index') }}" class="btn btn-outline">Ver todos</a>
        @endif
    </div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:26px;">
        @foreach ($voluntariados as $vol)
            @include('public.partials.voluntariado-card', ['vol' => $vol])
        @endforeach
    </div>

    <div style="margin-top:40px;">{{ $voluntariados->links() }}</div>
@endif
