@extends('layouts.public')

@section('title', 'Actividades · ' . config('app.name'))

@section('content')
<section style="max-width:1180px;margin:0 auto;padding:56px 40px 88px;">
    <div style="margin-bottom:34px;">
        <div style="font-size:13px;letter-spacing:.04em;text-transform:uppercase;font-weight:600;color:var(--naranjo);margin-bottom:12px;">Calendario</div>
        <h1 style="font-weight:800;font-size:40px;line-height:1.1;margin:0 0 12px;letter-spacing:-.02em;">Actividades solidarias</h1>
        <p style="font-size:16px;color:var(--gris);margin:0;">{{ $actividades->total() }} {{ Str::plural('actividad', $actividades->total()) }} publicada{{ $actividades->total() === 1 ? '' : 's' }}.</p>
    </div>

    <form method="GET" class="card" style="padding:20px 22px;margin-bottom:34px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;align-items:end;">
        <div>
            <label class="helper" for="f-region" style="display:block;margin-bottom:6px;font-weight:600;">Región</label>
            <select class="fld" name="region" id="f-region" onchange="this.form.submit()">
                <option value="">Todas</option>
                @foreach ($regiones as $r)
                    <option value="{{ $r->id }}" @selected(request('region') == $r->id)>{{ $r->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="helper" for="f-comuna" style="display:block;margin-bottom:6px;font-weight:600;">Comuna</label>
            <select class="fld" name="comuna" id="f-comuna" @disabled($comunas->isEmpty())>
                <option value="">{{ $comunas->isEmpty() ? 'Elige una región' : 'Todas' }}</option>
                @foreach ($comunas as $c)
                    <option value="{{ $c->id }}" @selected(request('comuna') == $c->id)>{{ $c->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="helper" for="f-tema" style="display:block;margin-bottom:6px;font-weight:600;">Tema</label>
            <select class="fld" name="tema" id="f-tema">
                <option value="">Todos</option>
                @foreach ($temas as $t)
                    <option value="{{ $t->id }}" @selected(request('tema') == $t->id)>{{ $t->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="helper" for="f-formato" style="display:block;margin-bottom:6px;font-weight:600;">Formato</label>
            <select class="fld" name="formato" id="f-formato">
                <option value="">Todos</option>
                @foreach ($formatos as $f)
                    <option value="{{ $f }}" @selected(request('formato') === $f)>{{ $f }}</option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <a href="{{ route('activities.index') }}" class="btn btn-ghost btn-sm">Limpiar</a>
        </div>
    </form>

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
</section>
@endsection
