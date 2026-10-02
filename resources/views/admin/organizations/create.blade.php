@extends('layouts.admin')
@section('title', 'Nueva organización')
@section('miga', 'Nueva')

{{--
    Sumar una organización al listado sin pasar por un CSV (punto 11 del
    30/09). Queda como las importadas: sin cuenta, sin verificar y activa. La
    reclama después quien la represente, desde «Publica tu actividad».
--}}

@section('content')
<a href="{{ route('admin.organizations.index') }}" class="textlink" style="font-size:14px;">← Volver a organizaciones</a>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:18px;align-items:start;">

    <section class="card" style="padding:26px;grid-column:span 2;min-width:0;">
        <form method="POST" action="{{ route('admin.organizations.store') }}"
              style="display:flex;flex-direction:column;gap:18px;" data-crear-organizacion>
            @csrf

            @include('admin.organizations._campos', ['tipoObligatorio' => false])

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary" data-cargando="Guardando…">Crear organización</button>
                <a href="{{ route('admin.organizations.index') }}" class="btn btn-ghost">Cancelar</a>
            </div>
        </form>
    </section>

    <aside class="card" style="padding:22px 24px;">
        <div class="seclabel" style="margin-bottom:12px;">Cómo queda</div>
        <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:8px;font-size:14px;line-height:1.5;color:var(--gris-700);">
            <li><strong>Sin cuenta.</strong> Nadie entra con ella todavía.</li>
            <li><strong>Reclamable.</strong> Sale en el buscador del paso «Tu organización» de «Publica tu actividad»: quien la represente la elige y se pone su contraseña.</li>
            <li><strong>Sin verificar</strong>, como las importadas. La marca la pones tú cuando sepas quiénes son.</li>
            <li>Para que salga en la marquesina del home, añádela después en Páginas → Marquesina de organizaciones.</li>
        </ul>
    </aside>
</div>
@endsection
