{{-- El listado de siempre. Sale de `index.blade.php`, que trae la cabecera y los filtros. --}}
@if ($actividades->isEmpty())
    <div class="card" style="padding:44px;text-align:center;">
        <p style="font-size:16px;color:var(--gris);margin:0 0 18px;">No encontramos actividades con esos filtros.</p>
        <a href="{{ route('activities.index') }}" class="btn btn-outline">Ver todas</a>
    </div>
@else
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:26px;">
        @foreach ($actividades as $act)
            @include('public.partials.activity-card', ['act' => $act])
        @endforeach
    </div>

    <div style="margin-top:40px;">{{ $actividades->links() }}</div>
@endif
