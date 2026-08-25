@include('errors._pagina', [
    'codigo' => 403,
    'titulo' => 'No puedes ver esta página',
    // Si quien abortó dejó un motivo, ese explica mejor que el texto genérico.
    'texto' => (($exception ?? null)?->getMessage() ?: null) ?? 'Puede que el enlace haya caducado o que esa página no sea para tu cuenta.',
])
